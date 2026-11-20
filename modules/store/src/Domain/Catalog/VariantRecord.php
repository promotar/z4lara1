<?php

namespace Modules\Store\Domain\Catalog;

final readonly class VariantRecord
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $publicId,
        public VariantStatus $status,
        public ?string $sku,
        public bool $isDefault,
        public int $version,
    ) {}
}
