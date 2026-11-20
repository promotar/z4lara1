<?php

namespace Modules\Store\Application\Catalog\CreateProduct;

use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class CreateProductCommand
{
    public function __construct(
        public string $typeCode,
        public string $defaultLocale,
        public string $name,
        public string $slug,
        public ProductVisibility $visibility,
        public ActorContext $actor,
        public ?string $internalCode = null,
        public ?string $defaultVariantSku = null,
    ) {}
}
