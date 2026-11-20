<?php

namespace Modules\Store\Domain\Catalog;

final readonly class ProductRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $typeCode,
        public ProductStatus $status,
        public string $defaultLocale,
        public int $version,
    ) {}
}
