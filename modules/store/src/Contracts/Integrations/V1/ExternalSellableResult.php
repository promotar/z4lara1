<?php

namespace Modules\Store\Contracts\Integrations\V1;

final readonly class ExternalSellableResult
{
    /** @param list<string> $drift */
    public function __construct(
        public bool $succeeded,
        public string $state,
        public ?string $productPublicId = null,
        public ?string $variantPublicId = null,
        public ?string $inventoryPublicId = null,
        public ?string $failureCode = null,
        public ?string $message = null,
        public array $drift = [],
    ) {}
}
