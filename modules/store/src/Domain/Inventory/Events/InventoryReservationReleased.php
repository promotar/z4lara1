<?php

namespace Modules\Store\Domain\Inventory\Events;

use DateTimeImmutable;

final readonly class InventoryReservationReleased
{
    public function __construct(public string $reservationPublicId, public DateTimeImmutable $occurredAt) {}
}
