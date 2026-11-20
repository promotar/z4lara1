<?php

namespace Modules\Exhibition\Application\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Application\Commission\CommissionResolver;
use Modules\Exhibition\Application\Verification\ActivationEligibility;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;

final readonly class ArtistDashboardService
{
    public function __construct(
        private ArtistDashboardAccess $access,
        private ActivationEligibility $eligibility,
        private ArtworkEffectiveAvailabilityService $availability,
        private ExhibitionSettingsService $settings,
        private CommissionResolver $commissions,
    ) {}

    /** @return array<string, mixed> */
    public function overview(int $coreUserId): array
    {
        $context = $this->access->context($coreUserId);
        $notificationQuery = DB::table('exhibition_application_notifications')->where('application_id', $context->id);
        $openSupport = DB::table('exhibition_artist_support_tickets')->where('artist_identity_id', $context->artist_identity_id)->where('state', '!=', 'closed')->count();
        $artworks = DB::table('exhibition_artworks')->where('artist_identity_id', $context->artist_identity_id);
        $storeAvailable = $this->storeAvailable();
        $tasks = [];
        $sellerSetup = $this->eligibility->progress((int) $context->id);
        if ($storeAvailable && ! $sellerSetup['ready']) {
            $tasks[] = [
                'title' => __('exhibitions::ui.artist_task_payment_title'),
                'detail' => __('exhibitions::ui.artist_task_payment_detail', ['percentage' => $sellerSetup['percentage']]),
                'url' => route('exhibitions.verification.show'),
            ];
        }
        if ($context->account_state === ArtistAccountState::Restricted->value) {
            $tasks[] = ['title' => __('exhibitions::ui.artist_task_restriction_title'), 'detail' => __('exhibitions::ui.artist_task_restriction_detail'), 'url' => route('exhibitions.verification.show')];
        }
        $case = DB::table('exhibition_verification_cases')->where('application_id', $context->id)->first();
        if ($case?->requested_instructions) {
            $tasks[] = ['title' => __('exhibitions::ui.artist_task_platform_title'), 'detail' => $case->requested_instructions, 'url' => route('exhibitions.verification.show')];
        }

        $hasFulfillments = Schema::hasTable('exhibition_fulfillments');
        $hasSettlements = Schema::hasTable('exhibition_settlements');
        $hasInventory = Schema::hasTable('exhibition_artwork_inventory_items');
        $inventory = $hasInventory
            ? DB::table('exhibition_artwork_inventory_items as inventory')
                ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'inventory.artwork_id')
                ->where('artwork.artist_identity_id', $context->artist_identity_id)
            : null;
        $settlements = $hasSettlements
            ? DB::table('exhibition_settlements')->where('artist_user_id', $coreUserId)->where('currency', 'JOD')
            : null;

        return [
            'context' => $context,
            'metrics' => [
                'artworks_total' => (clone $artworks)->count(),
                'under_review' => (clone $artworks)->where('state', 'submitted')->count(),
                'published' => (clone $artworks)->where('state', 'published')->count(),
                'changes_required' => (clone $artworks)->where('state', 'changes_required')->count(),
                'reserved' => $inventory === null
                    ? (clone $artworks)->where('state', 'reserved')->count()
                    : $this->inventoryArtworkQuery(clone $artworks, 'reserved')->count(),
                'sold' => $inventory === null
                    ? (clone $artworks)->where('state', 'sold')->count()
                    : $this->inventoryArtworkQuery(clone $artworks, 'sold')->count(),
                'pending_amount' => $settlements === null
                    ? 0.0
                    : (float) ((clone $settlements)->whereNotIn('state', ['paid', 'reversed'])->sum('artist_net_minor') / 1000),
                'eligible_amount' => $settlements === null
                    ? 0.0
                    : (float) ((clone $settlements)->whereIn('state', ['approved', 'eligible_for_settlement', 'payout_requested', 'scheduled_for_payment', 'payment_sent'])->sum('artist_net_minor') / 1000),
            ],
            'sourceAvailability' => ['artworks' => $storeAvailable, 'sales' => $storeAvailable && $hasSettlements, 'earnings' => $storeAvailable && $hasSettlements],
            'storeAvailable' => $storeAvailable,
            'notifications' => (clone $notificationQuery)->orderByDesc('created_at')->limit(5)->get(),
            'notificationCount' => (clone $notificationQuery)->count(),
            'openSupportCount' => $openSupport,
            'tasks' => $tasks,
            'sellerSetup' => $sellerSetup,
        ];
    }

    /** @return array<string, mixed> */
    public function artworks(int $coreUserId, string $filter): array
    {
        $context = $this->access->context($coreUserId);
        $base = DB::table('exhibition_artworks')->where('artist_identity_id', $context->artist_identity_id);
        $counts = ['all' => (clone $base)->count()];
        foreach (array_slice($this->artworkFilters(), 1) as $state) {
            $counts[$state] = in_array($state, ['reserved', 'sold'], true) && Schema::hasTable('exhibition_artwork_inventory_items')
                ? $this->inventoryArtworkQuery(clone $base, $state)->count()
                : (clone $base)->where('state', $state)->count();
        }
        $records = match (true) {
            $filter === 'all' => $base,
            in_array($filter, ['reserved', 'sold'], true) && Schema::hasTable('exhibition_artwork_inventory_items') => $this->inventoryArtworkQuery($base, $filter),
            default => $base->where('state', $filter),
        };
        $artworks = $records->orderByDesc('updated_at')->paginate(30)->withQueryString();
        $artworks->getCollection()->each(function (object $artwork): void {
            $state = $this->availability->forArtwork((int) $artwork->id);
            $artwork->effective_state = in_array($state, ['reserved', 'sold'], true) ? $state : $artwork->state;
        });

        return [
            'context' => $context,
            'filter' => $filter,
            'artworks' => $artworks,
            'available' => true,
            'counts' => $counts,
        ];
    }

    /** @return list<string> */
    public function artworkFilters(): array
    {
        return ['all', 'draft', 'submitted', 'changes_required', 'approved', 'published', 'reserved', 'sold', 'rejected', 'archived'];
    }

    private function inventoryArtworkQuery(object $query, string $state): object
    {
        if ($state === 'sold') {
            $query->where(function ($sold): void {
                $sold->where(function ($inventorySold): void {
                    $inventorySold->whereExists(function ($inventory): void {
                        $inventory->selectRaw('1')
                            ->from('exhibition_artwork_inventory_items as inventory_sold')
                            ->whereColumn('inventory_sold.artwork_id', 'exhibition_artworks.id')
                            ->where('inventory_sold.state', 'sold');
                    })->whereNotExists(function ($inventory): void {
                        $inventory->selectRaw('1')
                            ->from('exhibition_artwork_inventory_items as inventory_not_sold')
                            ->whereColumn('inventory_not_sold.artwork_id', 'exhibition_artworks.id')
                            ->where('inventory_not_sold.state', '!=', 'sold');
                    });
                })->orWhere(function ($confirmedSale): void {
                    $this->availability->constrainToConfirmedSale($confirmedSale, 'exhibition_artworks.id');
                });
            });

            return $query;
        }

        $query->whereExists(function ($inventory): void {
            $inventory->selectRaw('1')
                ->from('exhibition_artwork_inventory_items as inventory_filter')
                ->whereColumn('inventory_filter.artwork_id', 'exhibition_artworks.id')
                ->whereIn('inventory_filter.state', ['reserved', 'temporarily_held']);
        })->whereNotExists(function ($inventory): void {
            $inventory->selectRaw('1')
                ->from('exhibition_artwork_inventory_items as inventory_available')
                ->whereColumn('inventory_available.artwork_id', 'exhibition_artworks.id')
                ->where('inventory_available.state', 'available');
        });
        $this->availability->excludeConfirmedSale($query, 'exhibition_artworks.id');

        return $query;
    }

    /** @return array<string, mixed> */
    public function profile(int $coreUserId): array
    {
        $context = $this->access->context($coreUserId);
        $case = DB::table('exhibition_verification_cases')->where('application_id', $context->id)->first();

        return [
            'context' => $context, 'case' => $case,
            'documents' => $case ? DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->orderBy('type')->orderByDesc('version')->get() : collect(),
            'agreement' => $case ? DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first() : null,
            'commission' => $this->commissions->forArtistIdentity((int) $context->artist_identity_id),
            'payout' => $case ? DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first() : null,
        ];
    }

    /** @return array<string, mixed> */
    public function emptyOperationalSection(int $coreUserId, string $source): array
    {
        return ['context' => $this->access->context($coreUserId), 'available' => false, 'source' => $source, 'records' => collect(), 'totals' => ['gross' => 0.0, 'commission' => 0.0, 'deductions' => 0.0, 'pending' => 0.0, 'eligible' => 0.0, 'settled' => 0.0]];
    }

    /** @return array<string, int|bool> */
    public function navigationCounts(int $coreUserId): array
    {
        $context = $this->access->context($coreUserId);

        return [
            'store_available' => $this->storeAvailable(),
            'artworks' => DB::table('exhibition_artworks')->where('artist_identity_id', $context->artist_identity_id)->count(),
            'sales' => Schema::hasTable('exhibition_settlements')
                ? DB::table('exhibition_settlements')->where('artist_user_id', $coreUserId)->count()
                : 0,
            'support' => DB::table('exhibition_artist_support_tickets')->where('artist_identity_id', $context->artist_identity_id)->where('state', '!=', 'closed')->count(),
        ];
    }

    private function storeAvailable(): bool
    {
        if (! (bool) $this->settings->value('exhibitions.store.enabled', true)) {
            return false;
        }

        $contract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway';

        return interface_exists($contract) && app()->bound($contract);
    }
}
