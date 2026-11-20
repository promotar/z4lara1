<?php

namespace Modules\Store\Events;

/**
 * Store-owned event. Optional notification or analytics plugins may subscribe
 * without Store taking a dependency on them or on Core.
 */
final readonly class OrderPlaced
{
    public function __construct(
        public string $orderPublicId,
        public string $orderNumber,
        public ?string $customerEmail,
        public string $customerPhone,
    ) {}
}
