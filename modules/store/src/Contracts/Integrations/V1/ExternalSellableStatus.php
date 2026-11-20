<?php

namespace Modules\Store\Contracts\Integrations\V1;

final readonly class ExternalSellableStatus
{
    public function __construct(
        public bool $available,
        public bool $purchasable,
        public ?int $priceMinor = null,
        public ?string $currency = null,
        public int $availableQuantity = 0,
        public int $maximumQuantity = 0,
        public ?string $productStatus = null,
        public ?string $productVisibility = null,
        public ?string $variantStatus = null,
        public ?string $sellableState = null,
        public ?string $message = null,
    ) {}
}
