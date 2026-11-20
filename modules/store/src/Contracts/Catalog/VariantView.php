<?php

namespace Modules\Store\Contracts\Catalog;

use DateTimeImmutable;

final readonly class VariantView
{
    public function __construct(
        public string $publicId,
        public ?string $sku,
        public string $status,
        public bool $isDefault,
        public ?int $effectivePriceMinor,
        public string $currency,
        public ?int $availableQuantity,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
