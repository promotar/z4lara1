<?php

namespace Modules\Store\Domain\Catalog;

final readonly class ProductPublicationPolicy
{
    public function __construct(private bool $requirePrimaryMedia = false) {}

    /** @return list<string> */
    public function violations(
        ProductRecord $product,
        ProductTypeDefinition $type,
        PublicationFacts $facts,
    ): array {
        $violations = [];

        if (! $facts->hasDefaultTranslation) {
            $violations[] = "missing translation for default locale [{$product->defaultLocale}]";
        }
        if ($facts->activeVariantCount === 0) {
            $violations[] = 'at least one active sellable variant is required';
        }
        if ($facts->activeDefaultVariantCount !== 1) {
            $violations[] = 'exactly one active default variant is required';
        }
        if (! $type->supportsVariants && $facts->totalVariantCount !== 1) {
            $violations[] = 'a simple product must own exactly one default variant';
        }
        if ($facts->activeVariantsMissingSku > 0) {
            $violations[] = 'every active variant must have a SKU';
        }
        if ($facts->activeVariantsMissingRegularPrice > 0) {
            $violations[] = 'every active variant must have a current regular price in the operating currency';
        }
        if ($type->supportsInventory && $facts->activeVariantsMissingInventory > 0) {
            $violations[] = 'every active variant must have an inventory policy';
        }
        if ($facts->missingRequiredAttributeValues > 0) {
            $violations[] = 'all required product attributes must have typed values';
        }
        if ($facts->invalidVariantAxisCount > 0) {
            $violations[] = 'every active variant must have a complete variant-axis combination';
        }
        if ($this->requirePrimaryMedia && ! $facts->hasPrimaryMedia) {
            $violations[] = 'a ready primary product image is required';
        }

        return $violations;
    }
}
