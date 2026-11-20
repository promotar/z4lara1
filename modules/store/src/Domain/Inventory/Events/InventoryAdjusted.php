<?php

namespace Modules\Store\Domain\Inventory\Events;

use DateTimeImmutable;

final readonly class InventoryAdjusted
{
    public function __construct(public string $inventoryItemPublicId, public int $quantityDelta, public DateTimeImmutable $occurredAt) {}
}
