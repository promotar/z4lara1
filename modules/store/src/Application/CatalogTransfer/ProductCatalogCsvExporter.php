<?php

namespace Modules\Store\Application\CatalogTransfer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Store\Application\Media\StoreMediaUrl;

final readonly class ProductCatalogCsvExporter
{
    public const SCHEMA_VERSION = 'z4-store-products-v1';

    public const HEADERS = [
        'schema_version',
        'source_site',
        'product_public_id',
        'type_code',
        'status',
        'visibility',
        'internal_code',
        'default_locale',
        'is_featured',
        'catalog_position',
        'published_at',
        'archived_at',
        'artist_name',
        'shipping_policy',
        'return_policy',
        'name',
        'slug',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
        'default_sku',
        'default_barcode',
        'regular_price_minor',
        'sale_price_minor',
        'currency',
        'stock_tracking',
        'stock_on_hand',
        'allow_backorder',
        'image_urls',
        'image_extensions',
        'tags_json',
        'brand_json',
        'translations_json',
        'categories_json',
        'attributes_json',
        'variants_json',
        'media_json',
        'artwork_json',
    ];

    public function __construct(private StoreMediaUrl $mediaUrls) {}

    /** @param resource $stream */
    public function write($stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS);

        DB::table('store_products')
            ->where('type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')
                    ->from('store_external_sellables as product_owner')
                    ->whereColumn('product_owner.product_id', 'store_products.id');
            })
            ->orderBy('id')
            ->chunkById(max(1, (int) config('store.catalog_transfer.export_chunk_size', 100)), function (Collection $products) use ($stream): void {
                $context = $this->contextFor($products);

                foreach ($products as $product) {
                    fputcsv($stream, $this->row($product, $context));
                }
            });
    }

    /** @return array<string, mixed> */
    private function contextFor(Collection $products): array
    {
        $productIds = $products->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $brandIds = $products->pluck('brand_id')->filter()->unique()->map(static fn ($id): int => (int) $id)->all();
        $translations = DB::table('store_product_translations')->whereIn('product_id', $productIds)->orderBy('locale')->get()->groupBy('product_id');
        $variants = DB::table('store_product_variants')->whereIn('product_id', $productIds)->orderByDesc('is_default')->orderBy('position')->orderBy('id')->get();
        $variantIds = $variants->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $prices = $variantIds === [] ? collect() : DB::table('store_prices')->whereIn('variant_id', $variantIds)->orderBy('priority')->orderBy('id')->get()->groupBy('variant_id');
        $inventory = $variantIds === [] ? collect() : DB::table('store_inventory_items')->whereIn('variant_id', $variantIds)->get()->keyBy('variant_id');
        $variantOptions = $variantIds === [] ? collect() : DB::table('store_variant_option_values as value')
            ->join('store_attributes as attribute', 'attribute.id', '=', 'value.attribute_id')
            ->join('store_attribute_options as option', 'option.id', '=', 'value.attribute_option_id')
            ->whereIn('value.variant_id', $variantIds)
            ->orderBy('attribute.position')
            ->get(['value.variant_id', 'attribute.code as attribute_code', 'option.code as option_code'])
            ->groupBy('variant_id');
        $brands = $brandIds === [] ? collect() : DB::table('store_brands')->whereIn('id', $brandIds)->get()->keyBy('id');

        $categoryLinks = DB::table('store_product_categories as link')
            ->join('store_categories as category', 'category.id', '=', 'link.category_id')
            ->whereIn('link.product_id', $productIds)
            ->orderByDesc('link.is_primary')
            ->orderBy('link.position')
            ->get([
                'link.product_id', 'link.is_primary', 'link.position as assignment_position',
                'category.id', 'category.public_id', 'category.parent_id', 'category.status', 'category.code', 'category.catalog_section',
                'category.position', 'category.depth', 'category.is_featured',
            ]);
        $categoryIds = $categoryLinks->pluck('id')->map(static fn ($id): int => (int) $id)->unique()->all();
        $categoryTranslations = $categoryIds === [] ? collect() : DB::table('store_category_translations')
            ->whereIn('category_id', $categoryIds)->orderBy('locale')->get()->groupBy('category_id');
        $categoryPublicIds = $categoryIds === [] ? collect() : DB::table('store_categories')->whereIn('id', $categoryIds)->pluck('public_id', 'id');

        $assignments = DB::table('store_product_attribute_assignments as assignment')
            ->join('store_attributes as attribute', 'attribute.id', '=', 'assignment.attribute_id')
            ->leftJoin('store_product_attribute_values as value', function ($join): void {
                $join->on('value.product_id', '=', 'assignment.product_id')->on('value.attribute_id', '=', 'assignment.attribute_id');
            })
            ->leftJoin('store_attribute_options as selected_option', 'selected_option.id', '=', 'value.option_id')
            ->whereIn('assignment.product_id', $productIds)
            ->orderBy('assignment.position')
            ->get([
                'assignment.product_id', 'assignment.attribute_id', 'assignment.is_required', 'assignment.is_variant_axis',
                'assignment.is_filterable as assignment_is_filterable', 'assignment.is_visible as assignment_is_visible',
                'assignment.position as assignment_position', 'attribute.public_id', 'attribute.code', 'attribute.data_type',
                'attribute.status', 'attribute.is_filterable', 'attribute.is_visible', 'attribute.supports_variant_axis',
                'attribute.position', 'attribute.validation_schema', 'value.value_text', 'value.value_integer',
                'value.value_decimal', 'value.value_boolean', 'selected_option.code as selected_option_code',
            ]);
        $attributeIds = $assignments->pluck('attribute_id')->map(static fn ($id): int => (int) $id)->unique()->all();
        $attributeTranslations = $attributeIds === [] ? collect() : DB::table('store_attribute_translations')
            ->whereIn('attribute_id', $attributeIds)->orderBy('locale')->get()->groupBy('attribute_id');
        $attributeOptions = $attributeIds === [] ? collect() : DB::table('store_attribute_options')
            ->whereIn('attribute_id', $attributeIds)->orderBy('position')->orderBy('id')->get();
        $optionIds = $attributeOptions->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $optionTranslations = $optionIds === [] ? collect() : DB::table('store_attribute_option_translations')
            ->whereIn('attribute_option_id', $optionIds)->orderBy('locale')->get()->groupBy('attribute_option_id');

        $media = DB::table('store_product_media as media')
            ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'media.media_asset_id')
            ->leftJoin('store_product_variants as variant', 'variant.id', '=', 'media.variant_id')
            ->leftJoin('store_artwork_media as artwork_media', 'artwork_media.product_media_public_id', '=', 'media.public_id')
            ->whereIn('media.product_id', $productIds)
            ->orderByDesc('media.is_primary')->orderBy('media.position')->orderBy('media.id')
            ->get([
                'media.id', 'media.product_id', 'media.public_id', 'media.role', 'media.is_primary', 'media.position',
                'media.visibility', 'media.external_source', 'media.external_key', 'variant.public_id as variant_public_id',
                'asset.disk', 'asset.path', 'asset.original_filename', 'asset.mime_type', 'asset.size_bytes',
                'asset.checksum', 'asset.width', 'asset.height', 'artwork_media.role as artwork_role',
            ]);
        $mediaIds = $media->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $mediaTranslations = $mediaIds === [] ? collect() : DB::table('store_product_media_translations')
            ->whereIn('product_media_id', $mediaIds)->orderBy('locale')->get()->groupBy('product_media_id');

        $artworks = DB::table('store_artwork_details')->whereIn('product_id', $productIds)->get()->keyBy('product_id');
        $artworkIds = $artworks->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $ownerIds = $artworks->pluck('owner_user_id')->filter()->unique()->map(static fn ($id): int => (int) $id)->all();
        $ownerEmails = $ownerIds === [] ? collect() : DB::table('users')->whereIn('id', $ownerIds)->pluck('email', 'id');
        $authenticity = $artworkIds === [] ? collect() : DB::table('store_artwork_authenticity')->whereIn('artwork_detail_id', $artworkIds)->get()->keyBy('artwork_detail_id');
        $provenance = $artworkIds === [] ? collect() : DB::table('store_artwork_provenance')->whereIn('artwork_detail_id', $artworkIds)->orderBy('event_date')->orderBy('id')->get()->groupBy('artwork_detail_id');

        return compact(
            'translations', 'variants', 'prices', 'inventory', 'variantOptions', 'brands', 'categoryLinks',
            'categoryTranslations', 'categoryPublicIds', 'assignments', 'attributeTranslations', 'attributeOptions',
            'optionTranslations', 'media', 'mediaTranslations', 'artworks', 'ownerEmails', 'authenticity', 'provenance',
        );
    }

    /** @param array<string, mixed> $context @return list<string> */
    private function row(object $product, array $context): array
    {
        $translations = $context['translations']->get($product->id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'product_id', 'created_at', 'updated_at']))->values();
        $default = $translations->firstWhere('locale', $product->default_locale) ?? $translations->first() ?? [];
        $brand = $product->brand_id ? $context['brands']->get($product->brand_id) : null;
        $categories = $context['categoryLinks']->where('product_id', $product->id)->map(function (object $category) use ($context): array {
            return [
                'public_id' => $category->public_id,
                'parent_public_id' => $category->parent_id ? $context['categoryPublicIds']->get($category->parent_id) : null,
                'status' => $category->status,
                'code' => $category->code,
                'catalog_section' => $category->catalog_section,
                'position' => (int) $category->position,
                'depth' => (int) $category->depth,
                'is_featured' => (bool) $category->is_featured,
                'is_primary' => (bool) $category->is_primary,
                'assignment_position' => (int) $category->assignment_position,
                'translations' => $context['categoryTranslations']->get($category->id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'category_id', 'created_at', 'updated_at']))->values()->all(),
            ];
        })->values();
        $attributes = $context['assignments']->where('product_id', $product->id)->map(function (object $assignment) use ($context): array {
            $options = $context['attributeOptions']->where('attribute_id', $assignment->attribute_id)->map(function (object $option) use ($context): array {
                return [
                    'public_id' => $option->public_id,
                    'code' => $option->code,
                    'status' => $option->status,
                    'position' => (int) $option->position,
                    'swatch_type' => $option->swatch_type,
                    'swatch_value' => $option->swatch_value,
                    'translations' => $context['optionTranslations']->get($option->id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'attribute_option_id', 'created_at', 'updated_at']))->values()->all(),
                ];
            })->values()->all();

            return [
                'definition' => [
                    'public_id' => $assignment->public_id,
                    'code' => $assignment->code,
                    'data_type' => $assignment->data_type,
                    'status' => $assignment->status,
                    'is_filterable' => (bool) $assignment->is_filterable,
                    'is_visible' => (bool) $assignment->is_visible,
                    'supports_variant_axis' => (bool) $assignment->supports_variant_axis,
                    'position' => (int) $assignment->position,
                    'validation_schema' => $this->decoded($assignment->validation_schema),
                    'translations' => $context['attributeTranslations']->get($assignment->attribute_id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'attribute_id', 'created_at', 'updated_at']))->values()->all(),
                    'options' => $options,
                ],
                'assignment' => [
                    'is_required' => (bool) $assignment->is_required,
                    'is_variant_axis' => (bool) $assignment->is_variant_axis,
                    'is_filterable' => (bool) $assignment->assignment_is_filterable,
                    'is_visible' => (bool) $assignment->assignment_is_visible,
                    'position' => (int) $assignment->assignment_position,
                ],
                'value' => [
                    'option_code' => $assignment->selected_option_code,
                    'text' => $assignment->value_text,
                    'integer' => $assignment->value_integer === null ? null : (int) $assignment->value_integer,
                    'decimal' => $assignment->value_decimal,
                    'boolean' => $assignment->value_boolean === null ? null : (bool) $assignment->value_boolean,
                ],
            ];
        })->values();
        $variants = $context['variants']->where('product_id', $product->id)->map(function (object $variant) use ($context): array {
            $inventory = $context['inventory']->get($variant->id);

            return [
                'public_id' => $variant->public_id,
                'status' => $variant->status,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'is_default' => (bool) $variant->is_default,
                'combination_key' => $variant->combination_key,
                'position' => (int) $variant->position,
                'weight_minor' => $variant->weight_minor === null ? null : (int) $variant->weight_minor,
                'weight_unit' => $variant->weight_unit,
                'archived_at' => $this->date($variant->archived_at),
                'prices' => $context['prices']->get($variant->id, collect())->map(fn (object $row): array => [
                    'public_id' => $row->public_id,
                    'type' => $row->type,
                    'amount_minor' => (int) $row->amount_minor,
                    'currency' => $row->currency,
                    'starts_at' => $this->date($row->starts_at),
                    'ends_at' => $this->date($row->ends_at),
                    'priority' => (int) $row->priority,
                    'status' => $row->status,
                    'archived_at' => $this->date($row->archived_at),
                ])->values()->all(),
                'inventory' => $inventory ? [
                    'public_id' => $inventory->public_id,
                    'tracking_mode' => $inventory->tracking_mode,
                    'on_hand' => (int) $inventory->on_hand,
                    'allow_backorder' => (bool) $inventory->allow_backorder,
                    'reorder_point' => $inventory->reorder_point === null ? null : (int) $inventory->reorder_point,
                    'safety_stock' => (int) $inventory->safety_stock,
                ] : null,
                'option_values' => $context['variantOptions']->get($variant->id, collect())->map(fn (object $row): array => [
                    'attribute_code' => $row->attribute_code,
                    'option_code' => $row->option_code,
                ])->values()->all(),
            ];
        })->values();
        $media = $context['media']->where('product_id', $product->id)->map(function (object $item) use ($context): array {
            $url = null;
            if ($item->disk === 'public' && filled($item->path)) {
                $path = $this->mediaUrls->publicPath($item->path);
                $url = $path === null ? null : url($path);
            } elseif (is_string($item->external_key) && filter_var($item->external_key, FILTER_VALIDATE_URL)) {
                $url = $item->external_key;
            }

            return [
                'public_id' => $item->public_id,
                'variant_public_id' => $item->variant_public_id,
                'role' => $item->role,
                'artwork_role' => $item->artwork_role,
                'is_primary' => (bool) $item->is_primary,
                'position' => (int) $item->position,
                'visibility' => $item->visibility,
                'image_url' => $url,
                'image_extension' => strtolower(pathinfo((string) ($item->path ?: $item->original_filename), PATHINFO_EXTENSION)),
                'original_filename' => $item->original_filename,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes === null ? null : (int) $item->size_bytes,
                'checksum_sha256' => $item->checksum,
                'width' => $item->width === null ? null : (int) $item->width,
                'height' => $item->height === null ? null : (int) $item->height,
                'translations' => $context['mediaTranslations']->get($item->id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'product_media_id', 'created_at', 'updated_at']))->values()->all(),
            ];
        })->values();
        $defaultVariant = $variants->firstWhere('is_default', true) ?? $variants->first() ?? [];
        $regularPrice = collect($defaultVariant['prices'] ?? [])->firstWhere('type', 'regular');
        $salePrice = collect($defaultVariant['prices'] ?? [])->firstWhere('type', 'sale');
        $defaultInventory = is_array($defaultVariant['inventory'] ?? null) ? $defaultVariant['inventory'] : [];

        return [
            self::SCHEMA_VERSION,
            url('/'),
            $product->public_id,
            $product->type_code,
            $product->status,
            $product->visibility,
            $this->excelSafe($product->internal_code),
            $product->default_locale,
            (string) (int) $product->is_featured,
            (string) (int) $product->catalog_position,
            $this->date($product->published_at),
            $this->date($product->archived_at),
            $this->excelSafe($product->artist_name),
            $this->excelSafe($product->shipping_policy),
            $this->excelSafe($product->return_policy),
            $this->excelSafe($default['name'] ?? null),
            $this->excelSafe($default['slug'] ?? null),
            $this->excelSafe($default['short_description'] ?? null),
            $this->excelSafe($default['description'] ?? null),
            $this->excelSafe($default['meta_title'] ?? null),
            $this->excelSafe($default['meta_description'] ?? null),
            $this->excelSafe($defaultVariant['sku'] ?? null),
            $this->excelSafe($defaultVariant['barcode'] ?? null),
            isset($regularPrice['amount_minor']) ? (string) $regularPrice['amount_minor'] : '',
            isset($salePrice['amount_minor']) ? (string) $salePrice['amount_minor'] : '',
            $regularPrice['currency'] ?? $salePrice['currency'] ?? '',
            $defaultInventory['tracking_mode'] ?? '',
            isset($defaultInventory['on_hand']) ? (string) $defaultInventory['on_hand'] : '',
            isset($defaultInventory['allow_backorder']) ? (string) (int) $defaultInventory['allow_backorder'] : '',
            $this->excelSafe($media->pluck('image_url')->filter()->implode(';')),
            $this->excelSafe($media->pluck('image_extension')->filter()->implode(';')),
            $this->json($this->decoded($product->tags) ?? []),
            $this->json($brand ? $this->without($brand, ['id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'archived_at', 'logo_path', 'version']) : null),
            $this->json($translations->all()),
            $this->json($categories->all()),
            $this->json($attributes->all()),
            $this->json($variants->all()),
            $this->json($media->all()),
            $this->json($this->artwork($product, $context)),
        ];
    }

    /** @param array<string, mixed> $context */
    private function artwork(object $product, array $context): ?array
    {
        $artwork = $context['artworks']->get($product->id);
        if ($artwork === null) {
            return null;
        }
        $details = $this->without($artwork, ['id', 'product_id', 'owner_user_id', 'approved_by', 'draft_key', 'lock_version', 'created_at', 'updated_at']);
        $details['owner_email'] = $artwork->owner_user_id ? $context['ownerEmails']->get($artwork->owner_user_id) : null;
        $details['primary_colors'] = $this->decoded($details['primary_colors'] ?? null);
        $authenticity = $context['authenticity']->get($artwork->id);
        $details['authenticity'] = $authenticity ? $this->without($authenticity, ['id', 'artwork_detail_id', 'public_id', 'lock_version', 'created_at', 'updated_at']) : null;
        $details['provenance'] = $context['provenance']->get($artwork->id, collect())->map(fn (object $row): array => $this->without($row, ['id', 'artwork_detail_id', 'public_id', 'entered_by', 'created_at', 'updated_at']))->values()->all();

        return $details;
    }

    /** @param list<string> $keys @return array<string, mixed> */
    private function without(object|array $value, array $keys): array
    {
        $array = (array) $value;
        foreach ($keys as $key) {
            unset($array[$key]);
        }

        return $array;
    }

    private function decoded(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function date(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function excelSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
