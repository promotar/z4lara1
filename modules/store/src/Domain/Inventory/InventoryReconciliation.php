<?php

namespace Modules\Store\Domain\Inventory;

final readonly class InventoryReconciliation
{
    public function __construct(
        public string $inventoryItemPublicId,
        public int $cachedOnHand,
        public int $ledgerOnHand,
        public int $cachedReserved,
        public int $activeReservationTotal,
    ) {}

    public function matches(): bool
    {
        return $this->cachedOnHand === $this->ledgerOnHand
            && $this->cachedReserved === $this->activeReservationTotal;
    }
}
