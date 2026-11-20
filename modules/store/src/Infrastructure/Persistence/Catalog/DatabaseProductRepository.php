<?php

namespace Modules\Store\Infrastructure\Persistence\Catalog;

use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Store\Domain\Catalog\ProductRecord;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductStatus;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Catalog\VariantRecord;
use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Exceptions\DuplicateSku;
use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;

final class DatabaseProductRepository implements ProductRepository
{
    public function createProduct(
        string $publicId,
        string $typeCode,
        ProductVisibility $visibility,
        string $defaultLocale,
        ?string $internalCode,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int {
        return (int) DB::table('store_products')->insertGetId([
            'public_id' => $publicId,
            'type_code' => $typeCode,
            'status' => ProductStatus::Draft->value,
            'visibility' => $visibility->value,
            'internal_code' => $internalCode === null ? null : trim($internalCode),
            'default_locale' => $defaultLocale,
            'is_featured' => false,
            'version' => 1,
            'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addTranslation(
        int $productId,
        string $locale,
        string $name,
        string $slug,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_product_translations')->insert([
            'product_id' => $productId,
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function createVariant(
        int $productId,
        string $publicId,
        VariantStatus $status,
        ?string $sku,
        bool $isDefault,
        string $combinationKey,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int {
        try {
            return (int) DB::table('store_product_variants')->insertGetId([
                'public_id' => $publicId,
                'product_id' => $productId,
                'status' => $status->value,
                'sku' => $sku,
                'is_default' => $isDefault,
                'combination_key' => $combinationKey,
                'position' => 0,
                'version' => 1,
                'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if ($sku !== null && $this->skuExists($sku)) {
                throw new DuplicateSku("SKU [{$sku}] already exists.", previous: $exception);
            }

            throw new InvalidVariantCombination('Variant uniqueness constraint was violated.', previous: $exception);
        }
    }

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?ProductRecord
    {
        $query = DB::table('store_products')->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first(['id', 'public_id', 'type_code', 'status', 'default_locale', 'version']);

        return $row === null ? null : new ProductRecord(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            typeCode: (string) $row->type_code,
            status: ProductStatus::from((string) $row->status),
            defaultLocale: (string) $row->default_locale,
            version: (int) $row->version,
        );
    }

    public function skuExists(string $sku): bool
    {
        return DB::table('store_product_variants')
            ->whereRaw('LOWER(sku) = ?', [strtolower($sku)])
            ->exists();
    }

    public function findVariantByPublicId(string $publicId, bool $forUpdate = false): ?VariantRecord
    {
        $query = DB::table('store_product_variants')->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first(['id', 'product_id', 'public_id', 'status', 'sku', 'is_default', 'version']);

        return $row === null ? null : new VariantRecord(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            publicId: (string) $row->public_id,
            status: VariantStatus::from((string) $row->status),
            sku: $row->sku === null ? null : (string) $row->sku,
            isDefault: (bool) $row->is_default,
            version: (int) $row->version,
        );
    }

    public function findDefaultVariantForProduct(string $productPublicId, bool $forUpdate = false): ?VariantRecord
    {
        $query = DB::table('store_product_variants as variant')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->where('product.public_id', $productPublicId)
            ->where('variant.is_default', true);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first(['variant.*']);

        return $row === null ? null : new VariantRecord(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            publicId: (string) $row->public_id,
            status: VariantStatus::from((string) $row->status),
            sku: $row->sku === null ? null : (string) $row->sku,
            isDefault: (bool) $row->is_default,
            version: (int) $row->version,
        );
    }

    public function findVariantsForProduct(string $productPublicId, bool $forUpdate = false): array
    {
        $query = DB::table('store_product_variants as variant')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->where('product.public_id', $productPublicId)
            ->orderByDesc('variant.is_default')
            ->orderBy('variant.position')
            ->orderBy('variant.id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->get([
            'variant.id',
            'variant.product_id',
            'variant.public_id',
            'variant.status',
            'variant.sku',
            'variant.is_default',
            'variant.version',
        ])->map(static fn (object $row): VariantRecord => new VariantRecord(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            publicId: (string) $row->public_id,
            status: VariantStatus::from((string) $row->status),
            sku: $row->sku === null ? null : (string) $row->sku,
            isDefault: (bool) $row->is_default,
            version: (int) $row->version,
        ))->all();
    }

    public function variantBelongsToProduct(string $variantPublicId, string $productPublicId): bool
    {
        return DB::table('store_product_variants as variants')
            ->join('store_products as products', 'products.id', '=', 'variants.product_id')
            ->where('variants.public_id', $variantPublicId)
            ->where('products.public_id', $productPublicId)
            ->exists();
    }

    public function combinationExists(int $productId, string $combinationKey): bool
    {
        return DB::table('store_product_variants')
            ->where('product_id', $productId)
            ->where('combination_key', $combinationKey)
            ->exists();
    }

    public function hasVariants(int $productId): bool
    {
        return DB::table('store_product_variants')->where('product_id', $productId)->exists();
    }

    public function clearDefaultVariant(int $productId, DateTimeImmutable $now): void
    {
        DB::table('store_product_variants')
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => $now]);
    }

    public function updateVariantStatus(
        int $variantId,
        int $expectedVersion,
        VariantStatus $status,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool {
        return DB::table('store_product_variants')
            ->where('id', $variantId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status->value,
                'archived_at' => $status === VariantStatus::Archived ? $now : null,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }

    public function updateProductStatus(
        int $productId,
        int $expectedVersion,
        ProductStatus $status,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool {
        return DB::table('store_products')
            ->where('id', $productId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status->value,
                'published_at' => $status === ProductStatus::Published ? $now : null,
                'archived_at' => $status === ProductStatus::Archived ? $now : null,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }

    public function updateProductVisibility(
        int $productId,
        int $expectedVersion,
        ProductVisibility $visibility,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool {
        return DB::table('store_products')
            ->where('id', $productId)
            ->where('version', $expectedVersion)
            ->update([
                'visibility' => $visibility->value,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }

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
    ): bool {
        $brandId = $brandPublicId === null || trim($brandPublicId) === ''
            ? null
            : DB::table('store_brands')
                ->where('public_id', $brandPublicId)
                ->where('status', '!=', 'archived')
                ->value('id');
        if ($brandPublicId !== null && trim($brandPublicId) !== '' && $brandId === null) {
            throw new InvalidArgumentException('The selected brand does not exist or is archived.');
        }

        $attributes = [
            'visibility' => $visibility->value,
            'internal_code' => $internalCode === null || trim($internalCode) === '' ? null : trim($internalCode),
            'artist_name' => $artistName === null || trim($artistName) === '' ? null : trim($artistName),
            'brand_id' => $brandId,
            'is_featured' => $featured,
            'tags' => $tags === [] ? null : json_encode(array_values($tags), JSON_THROW_ON_ERROR),
            'version' => DB::raw('version + 1'),
            'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'updated_at' => $now,
        ];
        if ($publishedAt !== null) {
            $attributes['published_at'] = $publishedAt;
        }

        return DB::table('store_products')
            ->where('id', $productId)
            ->where('version', $expectedVersion)
            ->update($attributes) === 1;
    }

    public function upsertTranslation(
        int $productId,
        string $locale,
        string $name,
        string $slug,
        ?string $shortDescription,
        ?string $description,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_product_translations')->upsert([[
            'product_id' => $productId,
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug,
            'short_description' => $shortDescription,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['product_id', 'locale'], ['name', 'slug', 'short_description', 'description', 'updated_at']);
    }

    public function updateVariantIdentity(
        int $variantId,
        int $expectedVersion,
        string $sku,
        ?string $barcode,
        int $position,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool {
        try {
            return DB::table('store_product_variants')
                ->where('id', $variantId)
                ->where('version', $expectedVersion)
                ->update([
                    'sku' => $sku,
                    'barcode' => $barcode === null || trim($barcode) === '' ? null : trim($barcode),
                    'position' => max(0, $position),
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                    'updated_at' => $now,
                ]) === 1;
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateSku("SKU [{$sku}] or barcode already exists.", previous: $exception);
        }
    }

    public function syncCategories(int $productId, array $categoryIds, ?int $primaryCategoryId, DateTimeImmutable $now): void
    {
        DB::table('store_product_categories')->where('product_id', $productId)->delete();
        if ($categoryIds === []) {
            return;
        }

        DB::table('store_product_categories')->insert(array_map(
            static fn (int $categoryId, int $position): array => [
                'product_id' => $productId,
                'category_id' => $categoryId,
                'is_primary' => $categoryId === $primaryCategoryId,
                'position' => $position,
                'created_at' => $now,
            ],
            $categoryIds,
            array_keys($categoryIds),
        ));
    }
}
