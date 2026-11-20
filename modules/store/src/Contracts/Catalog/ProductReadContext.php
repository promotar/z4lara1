<?php

namespace Modules\Store\Contracts\Catalog;

use DateTimeImmutable;

final readonly class ProductReadContext
{
    public function __construct(
        public string $locale,
        public string $fallbackLocale,
        public string $currency,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
