<?php

namespace Modules\Exhibition\Application\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;

final readonly class ExhibitionAdminService
{
    public function __construct(private ExhibitionSettingsService $settingsService) {}

    /** @return array<string,array<string,mixed>> */
    public function metrics(object $user): array
    {
        // The foundation route remains available during staged install/upgrade
        // before later phase tables exist; readiness must never become a 500.
        if (! Schema::hasTable('exhibition_artist_applications')) {
            return [];
        }
        $metrics = [];
        if ($user->can('exhibitions.review')) {
            $metrics += [
                'new_artists' => $this->metric(__('exhibitions::ui.auto_2b90e23e35'), DB::table('exhibition_artist_applications')->whereIn('state', ['submitted', 'under_review', 'documents_requested', 'documents_resubmitted'])->count(), 'admin.plugins.exhibitions.applications.index'),
                'review_queue' => $this->metric(__('exhibitions::ui.auto_74181c6e1c'), DB::table('exhibition_artworks')->whereIn('state', ['submitted', 'under_review', 'changes_required'])->count(), 'admin.plugins.exhibitions.artworks.review.index'),
            ];
        }
        if ($user->can('exhibitions.manage')) {
            $metrics['published'] = $this->metric(__('exhibitions::ui.auto_0fe5025af5'), DB::table('exhibition_artwork_publications')->where('state', 'published')->count(), 'admin.plugins.exhibitions.workspace', ['area' => 'artworks', 'tab' => 'published']);
        }
        if ($user->can('exhibitions.support')) {
            $metrics['disputes'] = $this->metric(__('exhibitions::ui.auto_61f53b2e22'), DB::table('exhibition_dispute_cases')->whereNotIn('state', ['resolved', 'closed'])->count(), 'admin.plugins.exhibitions.disputes.index');
        }
        if ($user->can('exhibitions.finance')) {
            $metrics['sales'] = $this->metric(__('exhibitions::ui.auto_086e032128'), DB::table('exhibition_settlements')->count(), 'admin.plugins.exhibitions.sales.index');
            $metrics['settlements'] = $this->metric(__('exhibitions::ui.auto_62b921314b'), DB::table('exhibition_settlements')->whereIn('state', ['payout_requested', 'approved', 'scheduled_for_payment', 'payment_sent', 'held', 'failed'])->count(), 'admin.plugins.exhibitions.settlements.index');
        }

        return $metrics;
    }

    /** @return array<string,mixed> */
    public function workspace(string $area, array $filters): array
    {
        $definition = $this->definition($area);
        $tab = array_key_exists((string) ($filters['tab'] ?? ''), $definition['tabs']) ? (string) $filters['tab'] : array_key_first($definition['tabs']);
        $query = $this->baseQuery($area);
        $this->applyTab($query, $area, $tab);
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search, $area): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('public_id', 'like', '%'.$search.'%')->orWhere('state', 'like', '%'.$search.'%');
                if ($area === 'artworks') {
                    $query->orWhere('owner_name', 'like', '%'.$search.'%');
                }
            });
        }
        $sort = in_array(($filters['sort'] ?? ''), ['title', 'state', 'updated_at'], true) ? (string) $filters['sort'] : 'updated_at';
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $rows = $query->orderBy($sort, $direction)->orderBy('public_id')->paginate($perPage)->withQueryString();
        $counts = [];
        foreach ($definition['tabs'] as $key => $label) {
            $countQuery = $this->baseQuery($area);
            $this->applyTab($countQuery, $area, $key);
            $counts[$key] = $countQuery->count();
        }

        return compact('definition', 'tab', 'rows', 'counts', 'search', 'sort', 'direction', 'perPage') + ['area' => $area];
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        return $this->settingsService->catalogue()['definitions'];
    }

    public function updateSetting(int $actorUserId, string $key, mixed $value, string $reason): object
    {
        $this->settingsService->save($actorUserId, [$key => $value], $reason);

        return DB::table('exhibition_settings')->where('key', $key)->first();
    }

    /** @return array<string,mixed> */
    public function definition(string $area): array
    {
        $definitions = [
            'artists' => ['label' => __('exhibitions::ui.auto_b7b190b165'), 'permission' => 'exhibitions.review', 'tabs' => [
                'all' => __('exhibitions::ui.auto_cff65cba30'),
                'applications' => __('exhibitions::ui.auto_1ddf333c66'),
                'verification' => __('exhibitions::ui.auto_52b8ffce11'),
                'contract_pending' => __('exhibitions::ui.auto_9ac660c7b0'),
                'active' => __('exhibitions::ui.auto_546d7ca84e'),
                'restricted' => __('exhibitions::ui.auto_ecdee5de15'),
                'suspended' => __('exhibitions::ui.auto_8bf906833c'),
                'terminated' => __('exhibitions::ui.auto_fba9c4daa2'),
                'rejected' => __('exhibitions::ui.auto_d37b1f6c05'),
            ]],
            'artworks' => ['label' => __('exhibitions::ui.auto_44eac6ddda'), 'permission' => 'exhibitions.review', 'tabs' => [
                'all' => __('exhibitions::ui.auto_c65caceee9'),
                'drafts' => __('exhibitions::ui.auto_db3af42ba6'),
                'submitted' => __('exhibitions::ui.auto_5c835c2242'),
                'review_queue' => __('exhibitions::ui.auto_cdc7695950'),
                'changes_required' => __('exhibitions::ui.auto_8a5932d1d8'),
                'approved' => __('exhibitions::ui.auto_6f8063417a'),
                'published' => __('exhibitions::ui.auto_d50805ca2a'),
                'reserved' => __('exhibitions::ui.auto_942d4e37dd'),
                'sold' => __('exhibitions::ui.auto_d85a8a381c'),
                'rejected' => __('exhibitions::ui.auto_d37b1f6c05'),
                'archived' => __('exhibitions::ui.auto_3896016d9c'),
            ]],
            'authenticity' => ['label' => __('exhibitions::ui.auto_79c25134f9'), 'permission' => 'exhibitions.review', 'tabs' => ['all' => __('exhibitions::ui.auto_7670347325'), 'evidence_required' => __('exhibitions::ui.auto_938fed380c'), 'ownership_disputes' => __('exhibitions::ui.auto_082e150747'), 'certificates' => __('exhibitions::ui.auto_5a6c0d2579'), 'investigations' => __('exhibitions::ui.auto_8a0275bad9')]],
            'pricing' => ['label' => __('exhibitions::ui.auto_e22ac25b06'), 'permission' => 'exhibitions.review', 'tabs' => ['queue' => __('exhibitions::ui.auto_dda10f9ed2'), 'evidence_required' => __('exhibitions::ui.auto_938fed380c'), 'adjustment_suggested' => __('exhibitions::ui.auto_a09fb2f701'), 'approved' => __('exhibitions::ui.auto_b49cb291be'), 'history' => __('exhibitions::ui.auto_9ecbf2ccdd')]],
            'fulfillment' => ['label' => __('exhibitions::ui.auto_4ea3ff88b0'), 'permission' => 'exhibitions.operations', 'tabs' => ['all' => __('exhibitions::ui.auto_b1c94ca2fb'), 'preparing' => __('exhibitions::ui.auto_ad866f19a1'), 'pickup_required' => __('exhibitions::ui.auto_4d4f576a5e'), 'inspection' => __('exhibitions::ui.auto_b4faa7ed95'), 'ready_to_ship' => __('exhibitions::ui.auto_acc997397c'), 'shipped' => __('exhibitions::ui.auto_747cf5c858'), 'delivered' => __('exhibitions::ui.auto_67edd3b992'), 'returns' => __('exhibitions::ui.auto_4762fdc5fd'), 'damaged' => __('exhibitions::ui.auto_f7bd791aac'), 'lost' => __('exhibitions::ui.auto_b578b733cb'), 'delayed' => __('exhibitions::ui.auto_6e04a0730c')]],
            'finance' => ['label' => __('exhibitions::ui.auto_c482980d38'), 'permission' => 'exhibitions.finance', 'tabs' => ['balances' => __('exhibitions::ui.auto_96e1af3cad'), 'queue' => __('exhibitions::ui.auto_f95a2a02d3'), 'approved' => __('exhibitions::ui.auto_cd99a18516'), 'payouts' => __('exhibitions::ui.auto_137253b0cb'), 'failed' => __('exhibitions::ui.auto_9f87a74863'), 'adjustments' => __('exhibitions::ui.auto_9fa8b13926'), 'rules' => __('exhibitions::ui.auto_41bfe8534b')]],
            'support' => ['label' => __('exhibitions::ui.auto_d151d42dab'), 'permission' => 'exhibitions.support', 'tabs' => ['open' => __('exhibitions::ui.auto_e649d32856'), 'artist' => __('exhibitions::ui.auto_70af033cb3'), 'customer' => __('exhibitions::ui.auto_1171fc0bd7'), 'ownership' => __('exhibitions::ui.auto_082e150747'), 'shipping' => __('exhibitions::ui.auto_7163950043'), 'closed' => __('exhibitions::ui.auto_e8e4439536')]],
        ];
        abort_unless(isset($definitions[$area]), 404);

        return $definitions[$area];
    }

    private function baseQuery(string $area): Builder
    {
        $source = match ($area) {
            'artists' => DB::table('exhibition_artist_applications as a')
                ->leftJoin('exhibition_artist_identities as i', 'i.core_user_id', '=', 'a.core_user_id')
                ->selectRaw("a.public_id, i.public_id artist_public_id, i.auto_approve_artworks, coalesce(nullif(a.artistic_name,''),nullif(a.legal_name,''),a.contact_email,'Untitled artist') title, case when i.state = 'candidate' then a.state else coalesce(i.state,a.state) end state, a.state application_state, a.contact_email secondary, a.updated_at"),
            'artworks' => DB::table('exhibition_artworks as a')
                ->leftJoin('exhibition_artist_identities as i', 'i.id', '=', 'a.artist_identity_id')
                ->leftJoin('exhibition_artist_applications as p', function ($join): void {
                    $join->on('p.core_user_id', '=', 'i.core_user_id')
                        ->whereRaw('p.id = (select max(p2.id) from exhibition_artist_applications p2 where p2.core_user_id = i.core_user_id)');
                })
                ->selectRaw("a.public_id, ".(Schema::hasColumn('exhibition_artworks', 'slug') ? 'a.slug' : 'null as slug').", coalesce(a.title,'Untitled artwork') title, a.state, coalesce(a.category,a.material,'Artwork') secondary, coalesce(nullif(p.artistic_name,''),nullif(p.legal_name,''),p.contact_email,'Unknown artist') owner_name, a.updated_at, exists(select 1 from exhibition_artwork_authenticity authenticity where authenticity.artwork_id = a.id) has_authenticity, exists(select 1 from exhibition_artwork_pricing pricing where pricing.artwork_id = a.id) has_pricing, exists(select 1 from exhibition_artwork_review_rounds review_round where review_round.artwork_id = a.id) has_review"),
            'authenticity' => DB::table('exhibition_artwork_authenticity as x')->join('exhibition_artworks as a', 'a.id', '=', 'x.artwork_id')->selectRaw("a.public_id, coalesce(a.title,'Untitled artwork') title, x.state, 'exhibitions::ui.authenticity_review' secondary, x.updated_at"),
            'pricing' => DB::table('exhibition_artwork_pricing as x')->join('exhibition_artworks as a', 'a.id', '=', 'x.artwork_id')->selectRaw("a.public_id, coalesce(a.title,'Untitled artwork') title, x.state, x.currency secondary, x.updated_at"),
            'fulfillment' => DB::table('exhibition_fulfillments as x')->join('exhibition_artworks as a', 'a.id', '=', 'x.artwork_id')->selectRaw("x.public_id, coalesce(a.title,'Untitled artwork') title, case when x.state = 'artist_notification_sent' and not exists(select 1 from exhibition_settlements s where s.fulfillment_id = x.id) then 'store_reserved' when x.state = 'artist_notification_sent' then 'sale_confirmed' else x.state end state, x.store_order_reference secondary, x.preparation_deadline, x.updated_at"),
            'finance' => DB::table('exhibition_settlements as x')->join('exhibition_fulfillments as f', 'f.id', '=', 'x.fulfillment_id')->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')->selectRaw("x.public_id, coalesce(a.title,'Untitled artwork') title, x.state, x.currency secondary, x.updated_at"),
            'support' => DB::table('exhibition_dispute_cases as x')->selectRaw('x.public_id, x.subject title, x.state, x.type secondary, x.source, x.type, x.updated_at'),
            default => abort(404),
        };

        return DB::query()->fromSub($source, 'workspace');
    }

    private function applyTab(Builder $query, string $area, string $tab): void
    {
        $states = match ($area) {
            'artists' => [
                'applications' => ['draft', 'submitted', 'under_review', 'documents_required', 'documents_requested', 'documents_resubmitted'],
                'verification' => ['approved'],
                'contract_pending' => ['contract_pending'],
                'active' => ['active'],
                'restricted' => ['restricted'],
                'suspended' => ['suspended'],
                'terminated' => ['terminated'],
                'rejected' => ['rejected'],
            ],
            'artworks' => ['drafts' => ['draft'], 'submitted' => ['submitted'], 'review_queue' => ['submitted', 'under_review'], 'changes_required' => ['changes_required'], 'approved' => ['approved'], 'published' => ['published'], 'reserved' => ['reserved'], 'sold' => ['sold'], 'rejected' => ['rejected'], 'archived' => ['archived']],
            'authenticity' => ['evidence_required' => ['evidence_required', 'changes_requested'], 'investigations' => ['under_review', 'manual_investigation'], 'certificates' => ['approved', 'documentation_accepted']],
            'pricing' => ['queue' => ['requested', 'under_review'], 'evidence_required' => ['evidence_required'], 'adjustment_suggested' => ['adjustment_suggested'], 'approved' => ['approved']],
            'fulfillment' => ['preparing' => ['preparing'], 'pickup_required' => ['ready_for_pickup', 'pickup_scheduled'], 'inspection' => ['collected', 'quality_inspection'], 'ready_to_ship' => ['ready_for_shipment'], 'shipped' => ['shipped'], 'delivered' => ['delivered'], 'returns' => ['return_requested', 'returned'], 'damaged' => ['damaged'], 'lost' => ['lost']],
            'finance' => ['queue' => ['pending_sale', 'awaiting_delivery', 'return_window', 'eligible_for_settlement', 'payout_requested', 'under_review', 'held'], 'approved' => ['approved', 'scheduled_for_payment', 'payment_sent'], 'payouts' => ['paid'], 'failed' => ['failed']],
            'support' => ['open' => ['triage', 'under_review', 'awaiting_customer', 'awaiting_artist'], 'closed' => ['resolved', 'closed']],
            default => [],
        };
        if (isset($states[$tab])) {
            $query->whereIn('state', $states[$tab]);
        }
        if ($area === 'fulfillment' && $tab === 'delayed') {
            $query->where('preparation_deadline', '<', now())->whereNotIn('state', ['delivered', 'returned', 'cancelled']);
        }
        if ($area === 'support' && $tab === 'artist') {
            $query->where('source', 'artist');
        }
        if ($area === 'support' && $tab === 'customer') {
            $query->where('source', 'customer');
        }
        if ($area === 'support' && $tab === 'ownership') {
            $query->where('type', 'ownership_authenticity_dispute');
        }
        if ($area === 'support' && $tab === 'shipping') {
            $query->whereIn('type', ['shipping_delay', 'shipping_damage', 'loss', 'refusal_of_delivery']);
        }
    }

    private function metric(string $label, int $value, string $route, array $params = []): array
    {
        return compact('label', 'value', 'route', 'params');
    }
}
