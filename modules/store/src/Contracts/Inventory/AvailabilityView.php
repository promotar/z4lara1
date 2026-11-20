<?php

namespace Modules\Store\Contracts\Inventory;

use DateTimeImmutable;

final readonly class AvailabilityView
{
    public function __construct(
        public string $variantPublicId,
        public string $trackingMode,
        public ?int $availableQuantity,
        public bool $allowBackorder,
        public bool $isAvailable,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
