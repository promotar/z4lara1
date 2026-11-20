<?php

namespace Modules\Store\Domain\Catalog;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface ProductRepository
{
    public function createProduct(
        string $publicId,
        string $typeCode,
        ProductVisibility $visibility,
        string $defaultLocale,
        ?string $internalCode,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int;

    public function addTranslation(
        int $productId,
        string $locale,
        string $name,
        string $slug,
        DateTimeImmutable $now,
    ): void;

    public function createVariant(
        int $productId,
        string $publicId,
        VariantStatus $status,
        ?string $sku,
        bool $isDefault,
        string $combinationKey,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int;

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?ProductRecord;

    public function findVariantByPublicId(string $publicId, bool $forUpdate = false): ?VariantRecord;

    public function findDefaultVariantForProduct(string $productPublicId, bool $forUpdate = false): ?VariantRecord;

    /** @return list<VariantRecord> */
    public function findVariantsForProduct(string $productPublicId, bool $forUpdate = false): array;

    public function variantBelongsToProduct(string $variantPublicId, string $productPublicId): bool;

    public function skuExists(string $sku): bool;

    public function combinationExists(int $productId, string $combinationKey): bool;

    public function hasVariants(int $productId): bool;

    public function clearDefaultVariant(int $productId, DateTimeImmutable $now): void;

    public function updateVariantStatus(
        int $variantId,
        int $expectedVersion,
        VariantStatus $status,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    public function updateProductStatus(
        int $productId,
        int $expectedVersion,
        ProductStatus $status,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    public function updateProductVisibility(
        int $productId,
        int $expectedVersion,
        ProductVisibility $visibility,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    public function updateProductDetails(
        int $productId,
        int $expectedVersion,
        ProductVisibility $visibility,
        ?string $internalCode,
        ?string $artistName,
        bool $featured,
        ?string $brandPublicId,
        array $tags,
        ?DateTimeImmutable $publishedAt,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    public function upsertTranslation(
        int $productId,
        string $locale,
        string $name,
        string $slug,
        ?string $shortDescription,
        ?string $description,
        DateTimeImmutable $now,
    ): void;

    public function updateVariantIdentity(
        int $variantId,
        int $expectedVersion,
        string $sku,
        ?string $barcode,
        int $position,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    /** @param list<int> $categoryIds */
    public function syncCategories(int $productId, array $categoryIds, ?int $primaryCategoryId, DateTimeImmutable $now): void;
}
