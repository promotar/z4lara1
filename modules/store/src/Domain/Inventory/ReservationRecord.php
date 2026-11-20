<?php

namespace Modules\Store\Domain\Inventory;

use DateTimeImmutable;

final readonly class ReservationRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $inventoryItemId,
        public int $quantity,
        public ReservationStatus $status,
        public string $idempotencyKey,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
