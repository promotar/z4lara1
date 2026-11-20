<?php

namespace Modules\Store\Application\Inventory;

use Illuminate\Database\ConnectionInterface;
use Modules\Store\Application\Orders\StoreAdminNotificationService;
use Modules\Store\Application\Settings\StoreSettingsService;

final readonly class LowStockNotificationService
{
    public function __construct(
        private ConnectionInterface $database,
        private StoreSettingsService $settings,
        private StoreAdminNotificationService $notifications,
    ) {}

    public function forInventoryItem(string $publicId): void
    {
        $this->notify($this->database->table('store_inventory_items as inventory')
            ->join('store_product_variants as variant', 'variant.id', '=', 'inventory.variant_id')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->leftJoin('store_product_translations as translation', function ($join): void {
                $join->on('translation.product_id', '=', 'product.id')
                    ->whereColumn('translation.locale', 'product.default_locale');
            })
            ->where('inventory.public_id', $publicId)
            ->first([
                'inventory.on_hand',
                'inventory.reserved',
                'inventory.safety_stock',
                'variant.sku',
                'translation.name',
            ]));
    }

    public function forReservation(string $publicId): void
    {
        $item = $this->database->table('store_inventory_reservations')
            ->where('public_id', $publicId)
            ->value('inventory_item_id');
        if ($item === null) {
            return;
        }
        $inventoryPublicId = $this->database->table('store_inventory_items')
            ->where('id', $item)
            ->value('public_id');
        if ($inventoryPublicId !== null) {
            $this->forInventoryItem((string) $inventoryPublicId);
        }
    }

    private function notify(?object $item): void
    {
        if ($item === null || ! (bool) $this->settings->current()['admin_low_stock_notification']) {
            return;
        }
        $available = (int) $item->on_hand - (int) $item->reserved;
        if ($available > (int) $item->safety_stock) {
            return;
        }
        $name = trim((string) ($item->name ?? '')) ?: 'Store product';
        $sku = trim((string) ($item->sku ?? '')) ?: 'no SKU';
        $this->notifications->send(
            'low_stock',
            "Low stock: {$name}",
            "{$name} ({$sku}) has {$available} unit(s) available; its threshold is {$item->safety_stock}.",
        );
    }
}
