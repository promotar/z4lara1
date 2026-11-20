<?php

namespace Modules\Store\Domain\Catalog;

final readonly class PublicationFacts
{
    public function __construct(
        public bool $hasDefaultTranslation,
        public int $totalVariantCount,
        public int $activeVariantCount,
        public int $activeDefaultVariantCount,
        public int $activeVariantsMissingSku,
        public int $activeVariantsMissingRegularPrice,
        public int $activeVariantsMissingInventory,
        public int $missingRequiredAttributeValues,
        public int $invalidVariantAxisCount,
        public bool $hasPrimaryMedia,
    ) {}
}
