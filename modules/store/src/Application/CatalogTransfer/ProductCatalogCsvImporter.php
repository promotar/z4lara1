<?php

namespace Modules\Store\Application\CatalogTransfer;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Store\Application\Artwork\ArtworkMediaService;
use Modules\Store\Application\Media\UploadProductImageService;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Throwable;

final class ProductCatalogCsvImporter
{
    /** @var array<string, string> */
    private array $pendingCategoryParents = [];

    /** @var array<string, string> */
    private array $importedCategoryIds = [];

    public function __construct(
        private readonly PublicIdGenerator $ids,
        private readonly RemoteProductImageDownloader $images,
        private readonly UploadProductImageService $productImages,
        private readonly ArtworkMediaService $artworkImages,
    ) {}

    public function import(
        string $path,
        string $existingBehavior,
        bool $downloadImages,
        bool $preserveStatus,
        ActorContext $actor,
    ): ProductCatalogImportResult {
        if (! in_array($existingBehavior, ['skip', 'update'], true)) {
            throw new InvalidArgumentException('Choose whether existing products should be skipped or updated.');
        }
        if ($actor->type !== 'user' || (int) $actor->id <= 0) {
            throw new InvalidArgumentException('A signed-in administrator is required for catalog import.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException('The uploaded CSV file could not be opened.');
        }

        $result = new ProductCatalogImportResult();
        $this->pendingCategoryParents = [];
        $this->importedCategoryIds = [];
        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if (! is_array($headers)) {
                throw new InvalidArgumentException('The CSV file does not contain a header row.');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headers[0] ?? '')) ?? '';
            $this->assertHeaders($headers);
            $maximumRows = max(1, (int) config('store.catalog_transfer.maximum_rows', 10_000));

            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($this->blankRow($values)) {
                    continue;
                }
                $result->rows++;
                if ($result->rows > $maximumRows) {
                    throw new InvalidArgumentException("The CSV file exceeds the {$maximumRows}-product import limit.");
                }
                $rowNumber = $result->rows + 1;
                if (count($values) !== count($headers)) {
                    $result->failed++;
                    $result->errors[] = "Row {$rowNumber}: the number of values does not match the header.";
                    continue;
                }
                $row = array_combine($headers, $values);
                if (! is_array($row)) {
                    $result->failed++;
                    $result->errors[] = "Row {$rowNumber}: the CSV columns could not be read.";
                    continue;
                }

                try {
                    $payload = $this->validateRow($row);
                    $outcome = DB::transaction(
                        fn (): array => $this->persistProduct($payload, $existingBehavior, $preserveStatus, $actor),
                        3,
                    );
                    if ($outcome['status'] === 'skipped') {
                        $result->skipped++;
                        continue;
                    }
                    $outcome['status'] === 'created' ? $result->created++ : $result->updated++;
                    if ($downloadImages) {
                        $this->importImages($outcome['product_public_id'], $outcome['type_code'], $payload['media'], $actor, $result, $rowNumber);
                    }
                } catch (Throwable $exception) {
                    $result->failed++;
                    $result->errors[] = "Row {$rowNumber}: ".$this->safeMessage($exception);
                }
            }

            $this->resolveCategoryParents();
        } finally {
            fclose($stream);
        }

        return $result;
    }

    /** @param list<string|null> $headers */
    private function assertHeaders(array $headers): void
    {
        $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
        if (count($headers) !== count(array_unique($headers))) {
            throw new InvalidArgumentException('The CSV header contains duplicate columns.');
        }
        $missing = array_values(array_diff(ProductCatalogCsvExporter::HEADERS, $headers));
        if ($missing !== []) {
            throw new InvalidArgumentException('The CSV file is missing required columns: '.implode(', ', $missing).'.');
        }
    }

    /** @param list<string|null> $values */
    private function blankRow(array $values): bool
    {
        return count(array_filter($values, static fn ($value): bool => trim((string) $value) !== '')) === 0;
    }

    /** @param array<string, string|null> $row @return array<string, mixed> */
    private function validateRow(array $row): array
    {
        if (($row['schema_version'] ?? '') !== ProductCatalogCsvExporter::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Store catalog CSV schema version.');
        }
        $type = trim((string) ($row['type_code'] ?? ''));
        if (! in_array($type, ['simple', 'variant'], true)) {
            throw new InvalidArgumentException('Store imports accept standard products only. Import artworks from the Exhibitions plugin.');
        }
        $locale = trim((string) ($row['default_locale'] ?? ''));
        if (! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale)) {
            throw new InvalidArgumentException('The product default locale is invalid.');
        }
        $name = $this->restoreExcelValue($row['name'] ?? '');
        $slug = trim($this->restoreExcelValue($row['slug'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255 || $slug === '' || mb_strlen($slug) > 255) {
            throw new InvalidArgumentException('Every product requires a valid name and slug.');
        }

        $translations = $this->jsonList($row, 'translations_json');
        if ($translations === []) {
            $translations[] = [
                'locale' => $locale,
                'name' => $name,
                'slug' => $slug,
                'short_description' => $this->restoreExcelValue($row['short_description'] ?? ''),
                'description' => $this->restoreExcelValue($row['description'] ?? ''),
                'meta_title' => $this->restoreExcelValue($row['meta_title'] ?? ''),
                'meta_description' => $this->restoreExcelValue($row['meta_description'] ?? ''),
            ];
        }

        return [
            'public_id' => strtoupper(trim((string) ($row['product_public_id'] ?? ''))),
            'type_code' => $type,
            'status' => in_array($row['status'] ?? '', ['draft', 'published', 'archived'], true) ? $row['status'] : 'draft',
            'visibility' => in_array($row['visibility'] ?? '', ['public', 'hidden'], true) ? $row['visibility'] : 'hidden',
            'internal_code' => $this->nullable($this->restoreExcelValue($row['internal_code'] ?? '')),
            'default_locale' => $locale,
            'is_featured' => $this->boolean($row['is_featured'] ?? null),
            'catalog_position' => max(0, (int) ($row['catalog_position'] ?? 0)),
            'published_at' => $this->date($row['published_at'] ?? null),
            'archived_at' => $this->date($row['archived_at'] ?? null),
            'artist_name' => $this->nullable($this->restoreExcelValue($row['artist_name'] ?? '')),
            'shipping_policy' => $this->nullable($this->restoreExcelValue($row['shipping_policy'] ?? '')),
            'return_policy' => $this->nullable($this->restoreExcelValue($row['return_policy'] ?? '')),
            'tags' => $this->jsonList($row, 'tags_json'),
            'brand' => $this->jsonObject($row, 'brand_json'),
            'translations' => $translations,
            'categories' => $this->jsonList($row, 'categories_json'),
            'attributes' => $this->jsonList($row, 'attributes_json'),
            'variants' => $this->jsonList($row, 'variants_json'),
            'media' => $this->jsonList($row, 'media_json'),
            'artwork' => $this->jsonObject($row, 'artwork_json'),
        ];
    }

    /** @param array<string, mixed> $payload @return array{status:string,product_public_id:string,type_code:string} */
    private function persistProduct(array $payload, string $existingBehavior, bool $preserveStatus, ActorContext $actor): array
    {
        $requestedPublicId = $this->validPublicId($payload['public_id']) ? $payload['public_id'] : null;
        $existing = $requestedPublicId === null ? null : DB::table('store_products')->where('public_id', $requestedPublicId)->lockForUpdate()->first();
        if ($existing !== null && $existingBehavior === 'skip') {
            return ['status' => 'skipped', 'product_public_id' => $existing->public_id, 'type_code' => $existing->type_code];
        }
        if ($existing !== null && $existing->type_code !== $payload['type_code']) {
            throw new InvalidArgumentException('The existing product type does not match the imported product type.');
        }

        $publicId = $existing?->public_id ?? $requestedPublicId ?? (string) $this->ids->generate();
        $this->assertUniqueProductValues($payload, $existing?->id);
        $brandId = $this->persistBrand($payload['brand'], $actor);
        $status = $preserveStatus ? $payload['status'] : 'draft';
        $visibility = $preserveStatus ? $payload['visibility'] : 'hidden';
        $publishedAt = $status === 'published' ? ($payload['published_at'] ?? now()) : null;
        $archivedAt = $status === 'archived' ? ($payload['archived_at'] ?? now()) : null;
        $values = [
            'type_code' => $payload['type_code'],
            'status' => $status,
            'visibility' => $visibility,
            'internal_code' => $payload['internal_code'],
            'artist_name' => $payload['artist_name'],
            'shipping_policy' => $payload['shipping_policy'],
            'return_policy' => $payload['return_policy'],
            'brand_id' => $brandId,
            'default_locale' => $payload['default_locale'],
            'is_featured' => $payload['is_featured'],
            'tags' => $payload['tags'] === [] ? null : $this->json($payload['tags']),
            'catalog_position' => $payload['catalog_position'],
            'published_at' => $publishedAt,
            'archived_at' => $archivedAt,
            'updated_by' => (int) $actor->id,
            'updated_at' => now(),
        ];
        if ($existing === null) {
            $productId = (int) DB::table('store_products')->insertGetId($values + [
                'public_id' => $publicId,
                'version' => 1,
                'created_by' => (int) $actor->id,
                'created_at' => now(),
            ]);
            $outcome = 'created';
        } else {
            DB::table('store_products')->where('id', $existing->id)->update($values + ['version' => ((int) $existing->version) + 1]);
            $productId = (int) $existing->id;
            $outcome = 'updated';
        }

        $this->persistTranslations($productId, $payload['translations']);
        $this->persistCategories($productId, $payload['categories'], $actor);
        $attributeMap = $this->persistAttributes($productId, $payload['attributes']);
        $this->persistVariants($productId, $payload['variants'], $attributeMap, $actor);
        if ($payload['type_code'] === 'artwork') {
            $this->persistArtwork($productId, $payload['artwork'], $preserveStatus, $actor);
        }

        return ['status' => $outcome, 'product_public_id' => $publicId, 'type_code' => $payload['type_code']];
    }

    /** @param array<string, mixed>|null $brand */
    private function persistBrand(?array $brand, ActorContext $actor): ?int
    {
        if ($brand === null || blank($brand['name'] ?? null) || blank($brand['slug'] ?? null)) {
            return null;
        }
        $existing = null;
        if ($this->validPublicId($brand['public_id'] ?? null)) {
            $existing = DB::table('store_brands')->where('public_id', strtoupper($brand['public_id']))->first();
        }
        $existing ??= DB::table('store_brands')->where('slug', trim((string) $brand['slug']))->first();
        $values = [
            'name' => trim((string) $brand['name']),
            'slug' => trim((string) $brand['slug']),
            'description' => $this->nullable($brand['description'] ?? null),
            'status' => in_array($brand['status'] ?? '', ['active', 'hidden', 'archived'], true) ? $brand['status'] : 'active',
            'position' => max(0, (int) ($brand['position'] ?? 0)),
            'updated_by' => (int) $actor->id,
            'updated_at' => now(),
        ];
        if ($existing !== null) {
            DB::table('store_brands')->where('id', $existing->id)->update($values + ['version' => ((int) $existing->version) + 1]);

            return (int) $existing->id;
        }

        return (int) DB::table('store_brands')->insertGetId($values + [
            'public_id' => $this->importPublicId($brand['public_id'] ?? null),
            'version' => 1,
            'created_by' => (int) $actor->id,
            'created_at' => now(),
        ]);
    }

    /** @param list<array<string, mixed>> $translations */
    private function persistTranslations(int $productId, array $translations): void
    {
        foreach ($translations as $translation) {
            $locale = trim((string) ($translation['locale'] ?? ''));
            $name = trim((string) ($translation['name'] ?? ''));
            $slug = trim((string) ($translation['slug'] ?? ''));
            if (! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale) || $name === '' || $slug === '') {
                throw new InvalidArgumentException('An imported product translation is incomplete.');
            }
            $conflict = DB::table('store_product_translations')->where('locale', $locale)->where('slug', $slug)->where('product_id', '!=', $productId)->exists();
            if ($conflict) {
                throw new InvalidArgumentException("Product slug [{$slug}] is already used for locale [{$locale}].");
            }
            DB::table('store_product_translations')->updateOrInsert(
                ['product_id' => $productId, 'locale' => $locale],
                [
                    'name' => Str::limit($name, 255, ''),
                    'slug' => Str::limit($slug, 255, ''),
                    'short_description' => $this->nullable($translation['short_description'] ?? null),
                    'description' => $this->nullable($translation['description'] ?? null),
                    'meta_title' => $this->nullable($translation['meta_title'] ?? null),
                    'meta_description' => $this->nullable($translation['meta_description'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /** @param list<array<string, mixed>> $categories */
    private function persistCategories(int $productId, array $categories, ActorContext $actor): void
    {
        $categoryIds = [];
        foreach ($categories as $category) {
            $translations = is_array($category['translations'] ?? null) ? $category['translations'] : [];
            $first = $translations[0] ?? null;
            if (! is_array($first) || blank($first['name'] ?? null) || blank($first['slug'] ?? null)) {
                continue;
            }
            $sourcePublicId = $this->validPublicId($category['public_id'] ?? null)
                ? strtoupper((string) $category['public_id'])
                : null;
            $firstLocale = trim((string) ($first['locale'] ?? 'en'));
            $firstSlug = $this->canonicalCategorySlug((string) $first['slug']);
            $existing = null;
            if ($sourcePublicId !== null) {
                $existing = DB::table('store_categories')->where('public_id', $sourcePublicId)->first();
            }
            if ($existing === null && filled($category['code'] ?? null)) {
                $existing = DB::table('store_categories')->where('code', trim((string) $category['code']))->first();
            }
            if ($existing === null) {
                $existingId = DB::table('store_category_translations')
                    ->where('locale', $firstLocale)
                    ->where('slug', $firstSlug)
                    ->value('category_id');
                if ($existingId === null) {
                    $legacyTranslation = DB::table('store_category_translations')
                        ->where('locale', $firstLocale)
                        ->where('slug', 'like', $firstSlug.'-%')
                        ->get(['category_id', 'slug'])
                        ->first(fn (object $row): bool => $this->canonicalCategorySlug((string) $row->slug) === $firstSlug);
                    $existingId = $legacyTranslation?->category_id;
                }
                $existing = $existingId ? DB::table('store_categories')->where('id', $existingId)->first() : null;
            }
            $values = [
                'status' => in_array($category['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $category['status'] : 'active',
                'code' => $this->nullable($category['code'] ?? null),
                'catalog_section' => $this->categorySection($category, $translations, $existing?->catalog_section ?? null),
                'position' => max(0, (int) ($category['position'] ?? 0)),
                'is_featured' => $this->boolean($category['is_featured'] ?? false),
                'updated_by' => (int) $actor->id,
                'updated_at' => now(),
            ];
            if ($existing === null) {
                $categoryPublicId = $this->importPublicId($category['public_id'] ?? null);
                $categoryId = (int) DB::table('store_categories')->insertGetId($values + [
                    'public_id' => $categoryPublicId,
                    'parent_id' => null,
                    'depth' => 0,
                    'version' => 1,
                    'created_by' => (int) $actor->id,
                    'created_at' => now(),
                ]);
            } else {
                $categoryId = (int) $existing->id;
                $categoryPublicId = (string) $existing->public_id;
                DB::table('store_categories')->where('id', $categoryId)->update($values + ['version' => ((int) $existing->version) + 1]);
            }
            if ($sourcePublicId !== null) {
                $this->importedCategoryIds[$sourcePublicId] = $categoryPublicId;
            }
            if ($this->validPublicId($category['parent_public_id'] ?? null)) {
                $this->pendingCategoryParents[$categoryPublicId] = strtoupper((string) $category['parent_public_id']);
            }
            foreach ($translations as $translation) {
                if (! is_array($translation) || blank($translation['locale'] ?? null) || blank($translation['name'] ?? null) || blank($translation['slug'] ?? null)) {
                    continue;
                }
                DB::table('store_category_translations')->updateOrInsert(
                    ['category_id' => $categoryId, 'locale' => trim((string) $translation['locale'])],
                    [
                        'name' => trim((string) $translation['name']),
                        'slug' => $this->canonicalCategorySlug((string) $translation['slug']),
                        'description' => $this->nullable($translation['description'] ?? null),
                        'meta_title' => $this->nullable($translation['meta_title'] ?? null),
                        'meta_description' => $this->nullable($translation['meta_description'] ?? null),
                        'created_at' => now(), 'updated_at' => now(),
                    ],
                );
            }
            $categoryIds[$categoryId] = [
                'is_primary' => $this->boolean($category['is_primary'] ?? false),
                'position' => max(0, (int) ($category['assignment_position'] ?? 0)),
            ];
        }

        DB::table('store_product_categories')->where('product_id', $productId)->delete();
        foreach ($categoryIds as $categoryId => $assignment) {
            DB::table('store_product_categories')->insert([
                'product_id' => $productId,
                'category_id' => $categoryId,
                'is_primary' => $assignment['is_primary'],
                'position' => $assignment['position'],
                'created_at' => now(),
            ]);
        }
    }

    /** @param list<array<string, mixed>> $attributes @return array<string, array{id:int,options:array<string,int>}> */
    private function persistAttributes(int $productId, array $attributes): array
    {
        DB::table('store_product_attribute_values')->where('product_id', $productId)->delete();
        DB::table('store_product_attribute_assignments')->where('product_id', $productId)->delete();
        $map = [];
        foreach ($attributes as $item) {
            $definition = is_array($item['definition'] ?? null) ? $item['definition'] : [];
            $assignment = is_array($item['assignment'] ?? null) ? $item['assignment'] : [];
            $value = is_array($item['value'] ?? null) ? $item['value'] : [];
            $code = trim((string) ($definition['code'] ?? ''));
            $dataType = (string) ($definition['data_type'] ?? '');
            if ($code === '' || ! in_array($dataType, ['text', 'integer', 'decimal', 'boolean', 'select'], true)) {
                throw new InvalidArgumentException('An imported product attribute is invalid.');
            }
            $attribute = DB::table('store_attributes')->where('code', $code)->first();
            $definitionValues = [
                'data_type' => $dataType,
                'status' => in_array($definition['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $definition['status'] : 'active',
                'is_filterable' => $this->boolean($definition['is_filterable'] ?? false),
                'is_visible' => $this->boolean($definition['is_visible'] ?? true),
                'supports_variant_axis' => $this->boolean($definition['supports_variant_axis'] ?? false),
                'position' => max(0, (int) ($definition['position'] ?? 0)),
                'validation_schema' => isset($definition['validation_schema']) ? $this->json($definition['validation_schema']) : null,
                'updated_at' => now(),
            ];
            if ($attribute === null) {
                $attributeId = (int) DB::table('store_attributes')->insertGetId($definitionValues + [
                    'public_id' => $this->importPublicId($definition['public_id'] ?? null),
                    'code' => $code,
                    'created_at' => now(),
                ]);
            } else {
                if ($attribute->data_type !== $dataType) {
                    throw new InvalidArgumentException("Attribute [{$code}] has a different data type on the target site.");
                }
                $attributeId = (int) $attribute->id;
                DB::table('store_attributes')->where('id', $attributeId)->update($definitionValues);
            }
            foreach (is_array($definition['translations'] ?? null) ? $definition['translations'] : [] as $translation) {
                if (! is_array($translation) || blank($translation['locale'] ?? null) || blank($translation['name'] ?? null)) {
                    continue;
                }
                DB::table('store_attribute_translations')->updateOrInsert(
                    ['attribute_id' => $attributeId, 'locale' => trim((string) $translation['locale'])],
                    ['name' => trim((string) $translation['name']), 'help_text' => $this->nullable($translation['help_text'] ?? null), 'created_at' => now(), 'updated_at' => now()],
                );
            }
            $options = [];
            foreach (is_array($definition['options'] ?? null) ? $definition['options'] : [] as $option) {
                if (! is_array($option) || blank($option['code'] ?? null)) {
                    continue;
                }
                $optionCode = trim((string) $option['code']);
                $existingOption = DB::table('store_attribute_options')->where('attribute_id', $attributeId)->where('code', $optionCode)->first();
                $optionValues = [
                    'status' => in_array($option['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $option['status'] : 'active',
                    'position' => max(0, (int) ($option['position'] ?? 0)),
                    'swatch_type' => $this->nullable($option['swatch_type'] ?? null),
                    'swatch_value' => $this->nullable($option['swatch_value'] ?? null),
                    'updated_at' => now(),
                ];
                if ($existingOption === null) {
                    $optionId = (int) DB::table('store_attribute_options')->insertGetId($optionValues + [
                        'public_id' => $this->importPublicId($option['public_id'] ?? null),
                        'attribute_id' => $attributeId,
                        'code' => $optionCode,
                        'created_at' => now(),
                    ]);
                } else {
                    $optionId = (int) $existingOption->id;
                    DB::table('store_attribute_options')->where('id', $optionId)->update($optionValues);
                }
                foreach (is_array($option['translations'] ?? null) ? $option['translations'] : [] as $translation) {
                    if (! is_array($translation) || blank($translation['locale'] ?? null) || blank($translation['name'] ?? null)) {
                        continue;
                    }
                    DB::table('store_attribute_option_translations')->updateOrInsert(
                        ['attribute_option_id' => $optionId, 'locale' => trim((string) $translation['locale'])],
                        ['name' => trim((string) $translation['name']), 'created_at' => now(), 'updated_at' => now()],
                    );
                }
                $options[$optionCode] = $optionId;
            }

            DB::table('store_product_attribute_assignments')->insert([
                'product_id' => $productId,
                'attribute_id' => $attributeId,
                'is_required' => $this->boolean($assignment['is_required'] ?? false),
                'is_variant_axis' => $this->boolean($assignment['is_variant_axis'] ?? false),
                'is_filterable' => $this->boolean($assignment['is_filterable'] ?? false),
                'is_visible' => $this->boolean($assignment['is_visible'] ?? true),
                'position' => max(0, (int) ($assignment['position'] ?? 0)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $hasValue = collect(['option_code', 'text', 'integer', 'decimal', 'boolean'])->contains(fn (string $key): bool => array_key_exists($key, $value) && $value[$key] !== null && $value[$key] !== '');
            if ($hasValue) {
                DB::table('store_product_attribute_values')->insert([
                    'product_id' => $productId,
                    'attribute_id' => $attributeId,
                    'option_id' => filled($value['option_code'] ?? null) ? ($options[(string) $value['option_code']] ?? null) : null,
                    'value_text' => $this->nullable($value['text'] ?? null),
                    'value_integer' => $value['integer'] ?? null,
                    'value_decimal' => $value['decimal'] ?? null,
                    'value_boolean' => array_key_exists('boolean', $value) && $value['boolean'] !== null ? $this->boolean($value['boolean']) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $map[$code] = ['id' => $attributeId, 'options' => $options];
        }

        return $map;
    }

    /** @param list<array<string, mixed>> $variants @param array<string, array{id:int,options:array<string,int>}> $attributeMap */
    private function persistVariants(int $productId, array $variants, array $attributeMap, ActorContext $actor): void
    {
        if ($variants === []) {
            throw new InvalidArgumentException('Every imported product requires at least one variant.');
        }
        if (count(array_filter($variants, fn (array $variant): bool => $this->boolean($variant['is_default'] ?? false))) > 1) {
            throw new InvalidArgumentException('An imported product cannot contain more than one default variant.');
        }
        DB::table('store_product_variants')->where('product_id', $productId)->update(['is_default' => false, 'updated_at' => now()]);

        foreach ($variants as $index => $variant) {
            $sku = $this->nullable($variant['sku'] ?? null);
            $barcode = $this->nullable($variant['barcode'] ?? null);
            $existing = null;
            if ($this->validPublicId($variant['public_id'] ?? null)) {
                $existing = DB::table('store_product_variants')->where('public_id', strtoupper($variant['public_id']))->first();
                if ($existing !== null && (int) $existing->product_id !== $productId) {
                    throw new InvalidArgumentException('An imported variant public ID belongs to another product.');
                }
            }
            if ($existing === null && $sku !== null) {
                $skuMatch = DB::table('store_product_variants')->where('sku', $sku)->first();
                if ($skuMatch !== null && (int) $skuMatch->product_id !== $productId) {
                    throw new InvalidArgumentException("Variant SKU [{$sku}] is already used by another product.");
                }
                $existing = $skuMatch;
            }
            if ($barcode !== null && DB::table('store_product_variants')->where('barcode', $barcode)->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))->exists()) {
                throw new InvalidArgumentException("Variant barcode [{$barcode}] is already used.");
            }
            $values = [
                'status' => in_array($variant['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $variant['status'] : 'inactive',
                'sku' => $sku,
                'barcode' => $barcode,
                'is_default' => $this->boolean($variant['is_default'] ?? ($index === 0)),
                'combination_key' => (string) ($variant['combination_key'] ?? ''),
                'position' => max(0, (int) ($variant['position'] ?? $index)),
                'weight_minor' => isset($variant['weight_minor']) ? max(0, (int) $variant['weight_minor']) : null,
                'weight_unit' => $this->nullable($variant['weight_unit'] ?? null),
                'archived_at' => $this->date($variant['archived_at'] ?? null),
                'updated_by' => (int) $actor->id,
                'updated_at' => now(),
            ];
            if ($existing === null) {
                $variantId = (int) DB::table('store_product_variants')->insertGetId($values + [
                    'public_id' => $this->importPublicId($variant['public_id'] ?? null),
                    'product_id' => $productId,
                    'version' => 1,
                    'created_by' => (int) $actor->id,
                    'created_at' => now(),
                ]);
            } else {
                $variantId = (int) $existing->id;
                DB::table('store_product_variants')->where('id', $variantId)->update($values + ['version' => ((int) $existing->version) + 1]);
            }
            $this->persistPrices($variantId, is_array($variant['prices'] ?? null) ? $variant['prices'] : [], $actor);
            $this->persistInventory($variantId, is_array($variant['inventory'] ?? null) ? $variant['inventory'] : null);
            DB::table('store_variant_option_values')->where('variant_id', $variantId)->delete();
            foreach (is_array($variant['option_values'] ?? null) ? $variant['option_values'] : [] as $optionValue) {
                $attribute = $attributeMap[(string) ($optionValue['attribute_code'] ?? '')] ?? null;
                $optionId = $attribute['options'][(string) ($optionValue['option_code'] ?? '')] ?? null;
                if ($attribute === null || $optionId === null) {
                    throw new InvalidArgumentException('A variant option does not match an imported product attribute.');
                }
                DB::table('store_variant_option_values')->insert([
                    'variant_id' => $variantId,
                    'attribute_id' => $attribute['id'],
                    'attribute_option_id' => $optionId,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /** @param list<array<string, mixed>> $prices */
    private function persistPrices(int $variantId, array $prices, ActorContext $actor): void
    {
        foreach ($prices as $price) {
            if (! in_array($price['type'] ?? '', ['regular', 'sale'], true) || ! in_array($price['status'] ?? '', ['draft', 'active', 'archived'], true)) {
                throw new InvalidArgumentException('An imported variant price is invalid.');
            }
            $publicId = $this->importPublicId($price['public_id'] ?? null);
            $existing = DB::table('store_prices')->where('public_id', $publicId)->first();
            if ($existing !== null && (int) $existing->variant_id !== $variantId) {
                throw new InvalidArgumentException('An imported price public ID belongs to another variant.');
            }
            $values = [
                'variant_id' => $variantId,
                'type' => $price['type'],
                'amount_minor' => max(0, (int) ($price['amount_minor'] ?? 0)),
                'currency' => strtoupper(substr((string) ($price['currency'] ?? config('store.default_currency', 'JOD')), 0, 3)),
                'starts_at' => $this->date($price['starts_at'] ?? null),
                'ends_at' => $this->date($price['ends_at'] ?? null),
                'priority' => (int) ($price['priority'] ?? 0),
                'status' => $price['status'],
                'archived_at' => $this->date($price['archived_at'] ?? null),
                'created_by' => (int) $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ];
            $existing === null
                ? DB::table('store_prices')->insert($values + ['public_id' => $publicId])
                : DB::table('store_prices')->where('id', $existing->id)->update($values);
        }
    }

    /** @param array<string, mixed>|null $inventory */
    private function persistInventory(int $variantId, ?array $inventory): void
    {
        if ($inventory === null) {
            return;
        }
        $existing = DB::table('store_inventory_items')->where('variant_id', $variantId)->first();
        $reserved = $existing ? (int) $existing->reserved : 0;
        $onHand = max((int) ($inventory['on_hand'] ?? 0), $reserved);
        $values = [
            'tracking_mode' => in_array($inventory['tracking_mode'] ?? '', ['tracked', 'untracked'], true) ? $inventory['tracking_mode'] : 'tracked',
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'allow_backorder' => $this->boolean($inventory['allow_backorder'] ?? false),
            'reorder_point' => isset($inventory['reorder_point']) ? (int) $inventory['reorder_point'] : null,
            'safety_stock' => max(0, (int) ($inventory['safety_stock'] ?? 0)),
            'updated_at' => now(),
        ];
        if ($existing === null) {
            DB::table('store_inventory_items')->insert($values + [
                'public_id' => $this->importPublicId($inventory['public_id'] ?? null),
                'variant_id' => $variantId,
                'version' => 1,
                'created_at' => now(),
            ]);
        } else {
            DB::table('store_inventory_items')->where('id', $existing->id)->update($values + ['version' => ((int) $existing->version) + 1]);
        }
    }

    /** @param array<string, mixed>|null $artwork */
    private function persistArtwork(int $productId, ?array $artwork, bool $preserveStatus, ActorContext $actor): void
    {
        if ($artwork === null) {
            throw new InvalidArgumentException('Artwork products require artwork details.');
        }
        $ownershipType = in_array($artwork['ownership_type'] ?? '', ['artist_account', 'platform'], true) ? $artwork['ownership_type'] : 'platform';
        $ownerUserId = null;
        if ($ownershipType === 'artist_account') {
            $ownerEmail = strtolower(trim((string) ($artwork['owner_email'] ?? '')));
            $owners = $ownerEmail === '' ? collect() : DB::table('users')->whereRaw('LOWER(email) = ?', [$ownerEmail])->pluck('id');
            if ($owners->count() !== 1) {
                throw new InvalidArgumentException('The artwork owner email does not match exactly one target-site user.');
            }
            $ownerUserId = (int) $owners->first();
        }
        $workflow = $preserveStatus && in_array($artwork['workflow_state'] ?? '', ['draft', 'submitted', 'under_review', 'changes_required', 'approved', 'rejected'], true)
            ? $artwork['workflow_state']
            : 'draft';
        $allowed = [
            'completion_year', 'work_type', 'collection_name', 'edition_type', 'edition_number', 'edition_total',
            'category', 'artistic_style', 'subject', 'technique', 'material', 'surface', 'orientation', 'width',
            'height', 'depth', 'dimension_unit', 'weight', 'weight_unit', 'is_framed', 'frame_material', 'condition',
            'ready_to_hang', 'artwork_story', 'inspiration_source', 'care_instructions', 'requested_price_minor',
            'frame_cost_minor', 'packaging_cost_minor', 'currency', 'availability', 'preparation_days', 'shipping_notes',
            'ownership_confirmed', 'sale_rights_confirmed', 'authenticity_notes',
        ];
        $values = [
            'owner_user_id' => $ownerUserId,
            'ownership_type' => $ownershipType,
            'workflow_state' => $workflow,
            'primary_colors' => isset($artwork['primary_colors']) ? $this->json($artwork['primary_colors']) : null,
            'submitted_at' => $preserveStatus ? $this->date($artwork['submitted_at'] ?? null) : null,
            'approved_at' => $preserveStatus ? $this->date($artwork['approved_at'] ?? null) : null,
            'approved_by' => $workflow === 'approved' ? (int) $actor->id : null,
            'rejected_at' => $preserveStatus ? $this->date($artwork['rejected_at'] ?? null) : null,
            'updated_at' => now(),
        ];
        foreach ($allowed as $key) {
            $values[$key] = $artwork[$key] ?? null;
        }
        foreach (['is_framed', 'ready_to_hang', 'ownership_confirmed', 'sale_rights_confirmed'] as $key) {
            $values[$key] = $this->boolean($values[$key]);
        }
        $existing = DB::table('store_artwork_details')->where('product_id', $productId)->first();
        if ($existing === null) {
            $artworkId = (int) DB::table('store_artwork_details')->insertGetId($values + [
                'product_id' => $productId,
                'draft_key' => (string) Str::uuid(),
                'lock_version' => 1,
                'created_at' => now(),
            ]);
        } else {
            $artworkId = (int) $existing->id;
            DB::table('store_artwork_details')->where('id', $artworkId)->update($values + ['lock_version' => ((int) $existing->lock_version) + 1]);
        }

        $authenticity = is_array($artwork['authenticity'] ?? null) ? $artwork['authenticity'] : null;
        if ($authenticity !== null) {
            $authValues = [
                'ownership_basis' => (string) ($authenticity['ownership_basis'] ?? 'owned'),
                'may_display_and_sell' => $this->boolean($authenticity['may_display_and_sell'] ?? false),
                'inspiration_disclosure' => (string) ($authenticity['inspiration_disclosure'] ?? ''),
                'digital_tools_disclosure' => (string) ($authenticity['digital_tools_disclosure'] ?? ''),
                'current_physical_location' => (string) ($authenticity['current_physical_location'] ?? ''),
                'state' => (string) ($authenticity['state'] ?? 'submitted'),
                'declared_at' => $this->date($authenticity['declared_at'] ?? null) ?? now(),
                'updated_at' => now(),
            ];
            $existingAuth = DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artworkId)->first();
            $existingAuth === null
                ? DB::table('store_artwork_authenticity')->insert($authValues + ['public_id' => (string) $this->ids->generate(), 'artwork_detail_id' => $artworkId, 'lock_version' => 1, 'created_at' => now()])
                : DB::table('store_artwork_authenticity')->where('id', $existingAuth->id)->update($authValues + ['lock_version' => ((int) $existingAuth->lock_version) + 1]);
        }
        foreach (is_array($artwork['provenance'] ?? null) ? $artwork['provenance'] : [] as $event) {
            if (! is_array($event) || blank($event['event_date'] ?? null) || blank($event['event_type'] ?? null) || blank($event['description'] ?? null)) {
                continue;
            }
            $exists = DB::table('store_artwork_provenance')->where('artwork_detail_id', $artworkId)
                ->where('event_date', $event['event_date'])->where('event_type', $event['event_type'])->where('description', $event['description'])->exists();
            if (! $exists) {
                DB::table('store_artwork_provenance')->insert([
                    'public_id' => (string) $this->ids->generate(),
                    'artwork_detail_id' => $artworkId,
                    'event_date' => $event['event_date'],
                    'event_type' => $event['event_type'],
                    'owner_name' => $this->nullable($event['owner_name'] ?? null),
                    'location' => $this->nullable($event['location'] ?? null),
                    'description' => $event['description'],
                    'document_reference' => $this->nullable($event['document_reference'] ?? null),
                    'entered_by' => (int) $actor->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** @param list<array<string, mixed>> $media */
    private function importImages(string $productPublicId, string $typeCode, array $media, ActorContext $actor, ProductCatalogImportResult $result, int $rowNumber): void
    {
        foreach ($media as $item) {
            $url = trim((string) ($item['image_url'] ?? ''));
            if ($url === '') {
                $result->imagesSkipped++;
                continue;
            }
            $checksum = strtolower(trim((string) ($item['checksum_sha256'] ?? '')));
            if ($checksum !== '' && $this->imageAlreadyImported($productPublicId, $typeCode, $checksum, $item)) {
                $result->imagesSkipped++;
                continue;
            }

            $download = null;
            try {
                $download = $this->images->download($url, preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : null);
                if ($this->imageAlreadyImported($productPublicId, $typeCode, $download->checksum, $item)) {
                    $result->imagesSkipped++;
                    continue;
                }
                if ($typeCode === 'artwork') {
                    $upload = new UploadedFile(
                        $download->temporaryPath,
                        $download->candidate->originalFilename,
                        $download->candidate->detectedMimeType,
                        null,
                        true,
                    );
                    $role = in_array($item['artwork_role'] ?? '', $this->artworkImages->roles(), true)
                        ? $item['artwork_role']
                        : ($this->boolean($item['is_primary'] ?? false) ? 'front' : 'detail');
                    $created = $this->artworkImages->importItem((int) $actor->id, (string) ($actor->name ?? 'Catalog import'), $productPublicId, $upload, $role);
                    if ($this->boolean($item['is_primary'] ?? false) && $role === 'front') {
                        $this->artworkImages->makePrimary((int) $actor->id, $productPublicId, (string) $created->public_id, true);
                    }
                } else {
                    $role = in_array($item['role'] ?? '', array_column(MediaRole::cases(), 'value'), true) ? MediaRole::from($item['role']) : MediaRole::Gallery;
                    $variantPublicId = $this->validPublicId($item['variant_public_id'] ?? null) ? strtoupper((string) $item['variant_public_id']) : null;
                    if ($variantPublicId !== null && ! DB::table('store_product_variants')->where('public_id', $variantPublicId)->whereIn('product_id', DB::table('store_products')->select('id')->where('public_id', $productPublicId))->exists()) {
                        $variantPublicId = null;
                    }
                    $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
                    $firstTranslation = is_array($translations[0] ?? null) ? $translations[0] : [];
                    $association = $this->productImages->upload(
                        $productPublicId,
                        $variantPublicId,
                        $download->temporaryPath,
                        $download->candidate,
                        $role,
                        $this->boolean($item['is_primary'] ?? false),
                        max(0, (int) ($item['position'] ?? 0)),
                        $actor,
                        $firstTranslation['locale'] ?? null,
                        $firstTranslation['alt_text'] ?? null,
                    );
                    $this->persistMediaTranslations($association, $translations);
                    DB::table('store_product_media')->where('public_id', $association)->update([
                        'visibility' => in_array($item['visibility'] ?? '', ['public', 'hidden'], true) ? $item['visibility'] : 'public',
                        'updated_at' => now(),
                    ]);
                }
                $result->imagesImported++;
            } catch (Throwable $exception) {
                $result->imagesFailed++;
                $result->errors[] = "Row {$rowNumber} image: ".$this->safeMessage($exception);
            } finally {
                $download?->cleanup();
            }
        }
    }

    /** @param list<array<string, mixed>> $translations */
    private function persistMediaTranslations(string $associationPublicId, array $translations): void
    {
        $associationId = DB::table('store_product_media')->where('public_id', $associationPublicId)->value('id');
        if ($associationId === null) {
            return;
        }
        foreach ($translations as $translation) {
            if (! is_array($translation) || blank($translation['locale'] ?? null)) {
                continue;
            }
            DB::table('store_product_media_translations')->updateOrInsert(
                ['product_media_id' => $associationId, 'locale' => trim((string) $translation['locale'])],
                [
                    'alt_text' => $this->nullable($translation['alt_text'] ?? null),
                    'title' => $this->nullable($translation['title'] ?? null),
                    'caption' => $this->nullable($translation['caption'] ?? null),
                    'created_at' => now(), 'updated_at' => now(),
                ],
            );
        }
    }

    /** @param array<string, mixed> $item */
    private function imageAlreadyImported(string $productPublicId, string $typeCode, string $checksum, array $item): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            return false;
        }
        if ($typeCode === 'artwork') {
            return DB::table('store_artwork_media as artwork_media')
                ->join('store_artwork_details as artwork', 'artwork.id', '=', 'artwork_media.artwork_detail_id')
                ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
                ->where('product.public_id', $productPublicId)
                ->where('artwork_media.checksum_sha256', $checksum)
                ->where('artwork_media.role', $item['artwork_role'] ?? ($this->boolean($item['is_primary'] ?? false) ? 'front' : 'detail'))
                ->where('artwork_media.state', 'ready')
                ->exists();
        }

        return DB::table('store_product_media as media')
            ->join('store_products as product', 'product.id', '=', 'media.product_id')
            ->join('store_media_assets as asset', 'asset.id', '=', 'media.media_asset_id')
            ->where('product.public_id', $productPublicId)
            ->where('asset.checksum', $checksum)
            ->where('media.role', $item['role'] ?? 'gallery')
            ->exists();
    }

    /** @param array<string, mixed> $payload */
    private function assertUniqueProductValues(array $payload, ?int $productId): void
    {
        if ($payload['internal_code'] !== null && DB::table('store_products')->where('internal_code', $payload['internal_code'])->when($productId, fn ($query) => $query->where('id', '!=', $productId))->exists()) {
            throw new InvalidArgumentException("Internal product code [{$payload['internal_code']}] is already used.");
        }
    }

    private function resolveCategoryParents(): void
    {
        foreach ($this->pendingCategoryParents as $childPublicId => $sourceParentPublicId) {
            $parentPublicId = $this->importedCategoryIds[$sourceParentPublicId] ?? $sourceParentPublicId;
            $child = DB::table('store_categories')->where('public_id', $childPublicId)->first();
            $parent = DB::table('store_categories')->where('public_id', $parentPublicId)->first();
            if ($child === null || $parent === null || (int) $child->id === (int) $parent->id) {
                continue;
            }
            DB::table('store_categories')->where('id', $child->id)->update([
                'parent_id' => $parent->id,
                'depth' => min(8, ((int) $parent->depth) + 1),
                'updated_at' => now(),
            ]);
        }
    }

    private function canonicalCategorySlug(string $slug): string
    {
        $slug = trim($slug);

        return preg_replace('/-[a-f0-9]{8}$/i', '', $slug) ?: $slug;
    }

    /** @param array<string, mixed> $category @param list<array<string, mixed>> $translations */
    private function categorySection(array $category, array $translations, mixed $existingSection): string
    {
        $requested = $category['catalog_section'] ?? null;
        if (in_array($requested, ['standard', 'clothing'], true)) {
            return (string) $requested;
        }
        if (in_array($existingSection, ['standard', 'clothing'], true)) {
            return (string) $existingSection;
        }

        $clothingNames = [
            'apparel', 'clothes', 'clothing', 'fashion', 'textiles',
            'ملابس', 'منسوجات', 'أزياء', 'ازياء',
        ];
        foreach ($translations as $translation) {
            $name = mb_strtolower(trim((string) ($translation['name'] ?? '')), 'UTF-8');
            if (in_array($name, $clothingNames, true)) {
                return 'clothing';
            }
        }

        return 'standard';
    }

    /** @param array<string, string|null> $row @return list<array<string, mixed>> */
    private function jsonList(array $row, string $key): array
    {
        $value = $this->decodeJson($row[$key] ?? '', $key);

        return is_array($value) && array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @param array<string, string|null> $row @return array<string, mixed>|null */
    private function jsonObject(array $row, string $key): ?array
    {
        $value = $this->decodeJson($row[$key] ?? '', $key);

        return is_array($value) && ! array_is_list($value) ? $value : null;
    }

    private function decodeJson(?string $json, string $column): mixed
    {
        if (trim((string) $json) === '') {
            return null;
        }
        $decoded = json_decode((string) $json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Column [{$column}] does not contain valid JSON.");
        }

        return $decoded;
    }

    private function importPublicId(mixed $value): string
    {
        return $this->validPublicId($value) ? strtoupper(trim((string) $value)) : (string) $this->ids->generate();
    }

    private function validPublicId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', trim($value)) === 1;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function date(mixed $value): mixed
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function restoreExcelValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match("/^'[=+\\-@]/u", $value) === 1 ? substr($value, 1) : $value;
    }

    private function safeMessage(Throwable $exception): string
    {
        return $exception instanceof InvalidArgumentException || $exception instanceof \DomainException
            ? $exception->getMessage()
            : 'The product could not be imported safely.';
    }
}
