<?php

namespace Modules\Exhibition\Application\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ArtworkEffectiveAvailabilityService
{
    private const CONFIRMED_ORDER_STATUSES = [
        'confirmed',
        'processing',
        'ready',
        'shipped',
        'delivered',
    ];

    private const COMMITTED_FULFILLMENT_STATES = [
        'sale_confirmed',
        'artist_notification_sent',
        'preparing',
        'ready_for_pickup',
        'collected',
        'quality_inspection',
        'ready_for_shipment',
        'shipped',
        'delayed',
        'delivered',
        'return_requested',
        'damaged',
        'lost',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    public function forArtwork(int $artworkId, ?string $commerceAvailability = null): ?string
    {
        return $this->forArtworks([$artworkId], [$artworkId => $commerceAvailability])[$artworkId] ?? null;
    }

    /**
     * Batch availability for a set of artworks. One query for inventory states
     * and one for confirmed sales, regardless of how many artworks are passed.
     *
     * @param  array<int, int>  $artworkIds
     * @param  array<int, ?string>  $commerceAvailability  artwork id => store availability
     * @return array<int, ?string>  artwork id => available|reserved|sold|unavailable|null
     */
    public function forArtworks(array $artworkIds, array $commerceAvailability = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $artworkIds))));

        if ($ids === []) {
            return [];
        }

        $statesByArtwork = $this->hasTable('exhibition_artwork_inventory_items')
            ? DB::table('exhibition_artwork_inventory_items')
                ->whereIn('artwork_id', $ids)
                ->get(['artwork_id', 'state'])
                ->groupBy(fn (object $row): int => (int) $row->artwork_id)
            : collect();

        $confirmed = $this->confirmedSaleArtworkIds($ids);
        $availability = [];

        foreach ($ids as $id) {
            $states = $statesByArtwork->get($id, collect());
            $commerce = $commerceAvailability[$id] ?? null;
            $allSold = $states->isNotEmpty()
                && $states->every(static fn (object $row): bool => (string) $row->state === 'sold');

            if ($allSold || $commerce === 'sold' || isset($confirmed[$id])) {
                $availability[$id] = 'sold';

                continue;
            }

            if ($states->contains(static fn (object $row): bool => (string) $row->state === 'available')) {
                $availability[$id] = 'available';

                continue;
            }

            if ($states->contains(static fn (object $row): bool => in_array((string) $row->state, ['reserved', 'temporarily_held'], true))) {
                $availability[$id] = 'reserved';

                continue;
            }

            $availability[$id] = $states->isEmpty() ? null : 'unavailable';
        }

        return $availability;
    }

    public function hasConfirmedSale(int $artworkId): bool
    {
        return $this->confirmedSaleArtworkIds([$artworkId]) !== [];
    }

    /**
     * @param  array<int, int>  $artworkIds
     * @return array<int, true>
     */
    private function confirmedSaleArtworkIds(array $artworkIds): array
    {
        if ($artworkIds === [] || ! $this->hasTable('exhibition_fulfillments')) {
            return [];
        }

        $query = DB::table('exhibition_fulfillments as effective_sale')
            ->whereIn('effective_sale.artwork_id', $artworkIds)
            ->select('effective_sale.artwork_id');

        $this->joinAndConstrainConfirmedSale($query);

        $confirmed = [];

        foreach ($query->distinct()->pluck('effective_sale.artwork_id') as $id) {
            $confirmed[(int) $id] = true;
        }

        return $confirmed;
    }

    public function constrainToConfirmedSale(object $query, string $artworkIdColumn): void
    {
        if (! $this->hasTable('exhibition_fulfillments')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($sale) use ($artworkIdColumn): void {
            $sale->selectRaw('1')
                ->from('exhibition_fulfillments as effective_sale')
                ->whereColumn('effective_sale.artwork_id', $artworkIdColumn);
            $this->joinAndConstrainConfirmedSale($sale);
        });
    }

    public function excludeConfirmedSale(object $query, string $artworkIdColumn): void
    {
        if (! $this->hasTable('exhibition_fulfillments')) {
            return;
        }

        $query->whereNotExists(function ($sale) use ($artworkIdColumn): void {
            $sale->selectRaw('1')
                ->from('exhibition_fulfillments as effective_sale')
                ->whereColumn('effective_sale.artwork_id', $artworkIdColumn);
            $this->joinAndConstrainConfirmedSale($sale);
        });
    }

    private function joinAndConstrainConfirmedSale(object $query): void
    {
        if ($this->hasTable('store_orders')) {
            $query->leftJoin('store_orders as effective_sale_order', 'effective_sale_order.public_id', '=', 'effective_sale.store_order_public_id');
        }

        $query->where(function ($sale): void {
            $sale->whereIn('effective_sale.state', self::COMMITTED_FULFILLMENT_STATES);
            if ($this->hasTable('store_orders')) {
                $sale->orWhere(function ($reserved): void {
                    $reserved->where('effective_sale.state', 'store_reserved')
                        ->whereIn('effective_sale_order.status', self::CONFIRMED_ORDER_STATUSES);
                });
            }
        });
    }

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }
}
