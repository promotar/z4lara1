<?php

namespace Modules\Store\Domain\Inventory;

final readonly class InventoryItemRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $variantId,
        public InventoryTrackingMode $trackingMode,
        public int $onHand,
        public int $reserved,
        public bool $allowBackorder,
        public int $safetyStock,
        public int $version,
        public ?int $reorderPoint = null,
    ) {}

    public function available(): int
    {
        return $this->onHand - $this->reserved - $this->safetyStock;
    }
}
