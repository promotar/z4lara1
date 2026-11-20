<?php

namespace Modules\Store\Infrastructure\ReadModel;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Contracts\Inventory\AvailabilityView;
use Modules\Store\Contracts\Inventory\InventoryReadService;

final class DatabaseInventoryReadService implements InventoryReadService
{
    public function forVariant(string $variantPublicId, DateTimeImmutable $at): ?AvailabilityView
    {
        $row = DB::table('store_product_variants as variants')
            ->join('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variants.id')
            ->where('variants.public_id', $variantPublicId)
            ->first([
                'variants.public_id',
                'inventory.tracking_mode',
                'inventory.on_hand',
                'inventory.reserved',
                'inventory.safety_stock',
                'inventory.allow_backorder',
            ]);
        if ($row === null) {
            return null;
        }

        $tracked = (string) $row->tracking_mode === 'tracked';
        $available = $tracked
            ? (int) $row->on_hand - (int) $row->reserved - (int) $row->safety_stock
            : null;

        return new AvailabilityView(
            variantPublicId: (string) $row->public_id,
            trackingMode: (string) $row->tracking_mode,
            availableQuantity: $available,
            allowBackorder: (bool) $row->allow_backorder,
            isAvailable: ! $tracked || (bool) $row->allow_backorder || $available > 0,
            resolvedAt: $at,
        );
    }
}
