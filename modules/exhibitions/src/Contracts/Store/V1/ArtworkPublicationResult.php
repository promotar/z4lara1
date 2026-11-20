<?php

namespace Modules\Exhibition\Contracts\Store\V1;

final readonly class ArtworkPublicationResult
{
    public function __construct(
        public bool $succeeded,
        public string $state,
        public ?string $storeProductPublicId = null,
        public ?string $failureCode = null,
        public ?string $message = null,
        public ?string $storeVariantPublicId = null,
        public ?string $storeInventoryPublicId = null,
        public array $drift = [],
    ) {}
}
