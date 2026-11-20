<?php

namespace Modules\Store\Application\Shipping;

final readonly class ShippingQuote
{
    public function __construct(
        public bool $supported,
        public ?int $zoneId,
        public ?string $zonePublicId,
        public ?string $zoneName,
        public string $methodCode,
        public int $costMinor,
        public ?string $freeReason,
        public ?int $freeShippingRemainingMinor,
        public ?string $estimatedDelivery,
        public ?string $message,
    ) {}
}
