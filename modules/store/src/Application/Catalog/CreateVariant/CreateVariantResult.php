<?php

namespace Modules\Store\Application\Catalog\CreateVariant;

final readonly class CreateVariantResult
{
    public function __construct(
        public string $variantPublicId,
        public string $sku,
        public string $combinationKey,
        public bool $isDefault,
    ) {}
}
