<?php

namespace Modules\Exhibition\Contracts\Store\V1;

final readonly class ArtworkCommerceStatus
{
    public function __construct(
        public bool $storeAvailable,
        public bool $purchasable,
        public ?int $priceMinor = null,
        public ?string $currency = null,
        public int $availableQuantity = 0,
        public int $maximumQuantity = 0,
        public ?string $productStatus = null,
        public ?string $productVisibility = null,
        public ?string $variantStatus = null,
        public ?string $availability = null,
        public ?string $message = null,
    ) {}
}
