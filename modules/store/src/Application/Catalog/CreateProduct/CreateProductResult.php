<?php

namespace Modules\Store\Application\Catalog\CreateProduct;

final readonly class CreateProductResult
{
    public function __construct(
        public string $productPublicId,
        public ?string $defaultVariantPublicId,
        public int $version,
    ) {}
}
