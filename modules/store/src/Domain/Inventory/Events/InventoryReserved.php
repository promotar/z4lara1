<?php

namespace Modules\Store\Domain\Inventory\Events;

use DateTimeImmutable;

final readonly class InventoryReserved
{
    public function __construct(public string $inventoryItemPublicId, public string $reservationPublicId, public int $quantity, public DateTimeImmutable $occurredAt) {}
}
