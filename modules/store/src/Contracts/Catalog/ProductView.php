<?php

namespace Modules\Store\Contracts\Catalog;

use DateTimeImmutable;

final readonly class ProductView
{
    /** @param list<VariantView> $variants */
    public function __construct(
        public string $publicId,
        public string $typeCode,
        public string $status,
        public string $visibility,
        public string $locale,
        public bool $usedFallback,
        public string $name,
        public string $slug,
        public array $variants,
        public ?int $minimumPriceMinor,
        public ?int $maximumPriceMinor,
        public string $currency,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
