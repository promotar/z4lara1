<?php

namespace Modules\Store\Application\Storefront;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Application\Catalog\CatalogTranslationSelector;
use Modules\Store\Application\Media\StoreMediaUrl;
use Modules\Store\Application\Settings\StoreMoneyFormatter;
use Modules\Store\Application\Settings\StoreSettingsService;

final readonly class StorefrontCatalogService
{
    public function __construct(
        private ConnectionInterface $database,
        private StoreMediaUrl $mediaUrls,
        private StoreMoneyFormatter $money,
        private StoreSettingsService $settings,
        private CatalogTranslationSelector $translations,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{products: LengthAwarePaginator, categories: Collection, brands: Collection, tags: Collection, filterCategories: Collection, filterBrands: Collection}
     */
    public function browse(
        array $filters = [],
        ?string $categorySlug = null,
        ?string $brandSlug = null,
        bool $includeFacets = true,
    ): array
    {
        if (! $this->catalogReady()) {
            $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
            $perPage = max(1, min(48, (int) ($filters['per_page'] ?? config('store.storefront.products_per_page', 12))));

            return [
                'products' => new LengthAwarePaginator([], 0, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]),
                'categories' => collect(),
                'brands' => collect(),
                'tags' => collect(),
                'filterCategories' => collect(),
                'filterBrands' => collect(),
            ];
        }

        $locale = $this->locale();
        $runtime = $this->settings->current();
        $now = now();
        $exhibitionsAvailable = Route::has('exhibitions.artworks.show');
        $perPage = max(1, min(48, (int) ($filters['per_page'] ?? config('store.storefront.products_per_page', 12))));
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));

        $priceRows = $this->database->table('store_prices as price')
            ->selectRaw('price.variant_id')
            ->selectRaw("MIN(CASE WHEN price.type = 'sale' THEN price.amount_minor END) as sale_price_minor")
            ->selectRaw("MIN(CASE WHEN price.type = 'regular' THEN price.amount_minor END) as regular_price_minor")
            ->where('price.status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('price.starts_at')->orWhere('price.starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('price.ends_at')->orWhere('price.ends_at', '>', $now);
            })
            ->groupBy('price.variant_id');

        $query = $this->database->table('store_products as product')
            ->joinSub($this->productTranslationIds($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->join('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->join('store_product_variants as variant', function ($join): void {
                $join->on('variant.product_id', '=', 'product.id')
                    ->where('variant.is_default', '=', true)
                    ->where('variant.status', '=', 'active');
            })
            ->joinSub($priceRows, 'resolved_price', function ($join): void {
                $join->on('resolved_price.variant_id', '=', 'variant.id');
            })
            ->leftJoin('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variant.id')
            ->leftJoin('store_brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('store_external_sellables as external', 'external.product_id', '=', 'product.id')
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->where(function ($ownership) use ($exhibitionsAvailable): void {
                $ownership->where(function ($storeOwned): void {
                    $storeOwned->whereNull('external.id')
                        ->where('product.type_code', '!=', 'artwork');
                });
                if ($exhibitionsAvailable) {
                    $ownership->orWhere('external.owner_code', 'exhibitions');
                }
            })
            ->where(function ($builder): void {
                $builder->whereNull('brand.id')->orWhere('brand.status', 'active');
            })
            ->select([
                'product.id',
                'product.public_id',
                'product.type_code',
                'product.artist_name',
                'product.is_featured',
                'product.published_at',
                'translation.name',
                'translation.slug',
                'translation.short_description',
                'translation.description',
                'product.tags',
                'brand.name as brand_name',
                'brand.slug as brand_slug',
                'variant.public_id as variant_public_id',
                'variant.sku',
                'inventory.public_id as inventory_public_id',
                'inventory.tracking_mode',
                'inventory.on_hand',
                'inventory.reserved',
                'inventory.safety_stock',
                'inventory.allow_backorder',
                'resolved_price.sale_price_minor',
                'resolved_price.regular_price_minor',
                'external.owner_code as source_owner',
                'external.external_id as source_external_id',
                'external.metadata as source_metadata',
            ])
            ->selectRaw('COALESCE(resolved_price.sale_price_minor, resolved_price.regular_price_minor) as effective_price_minor');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search, $locale): void {
                $term = '%'.$search.'%';
                $builder->where('translation.name', 'like', $term)
                    ->orWhere('translation.short_description', 'like', $term)
                    ->orWhere('translation.description', 'like', $term)
                    ->orWhere('product.tags', 'like', $term)
                    ->orWhere('product.artist_name', 'like', $term)
                    ->orWhere('variant.sku', 'like', $term)
                    ->orWhere('brand.name', 'like', $term)
                    ->orWhereExists(function ($category) use ($term, $locale): void {
                        $category->selectRaw('1')
                            ->from('store_product_categories as search_link')
                            ->join('store_categories as search_category', 'search_category.id', '=', 'search_link.category_id')
                            ->joinSub($this->categoryTranslationIds($locale), 'search_translation_choice', fn ($join) => $join->on('search_translation_choice.category_id', '=', 'search_category.id'))
                            ->join('store_category_translations as search_translation', 'search_translation.id', '=', 'search_translation_choice.translation_id')
                            ->whereColumn('search_link.product_id', 'product.id')
                            ->where('search_category.status', 'active')
                            ->where(function ($searchQuery) use ($term): void {
                                $searchQuery->where('search_translation.name', 'like', $term)
                                    ->orWhere('search_translation.slug', 'like', $term);
                            });
                    });
            });
        }

        if (! empty($filters['type_code'])) {
            $query->where('product.type_code', (string) $filters['type_code']);
        }

        if (! empty($filters['exclude_artwork'])) {
            $query->where('product.type_code', '!=', 'artwork')
                ->where(function ($ownership): void {
                    $ownership->whereNull('external.id')
                        ->orWhere('external.owner_code', '!=', 'exhibitions');
                });
        }

        $catalogSection = (string) ($filters['catalog_section'] ?? '');
        if ($catalogSection === 'artworks') {
            $query->where('external.owner_code', 'exhibitions');
        } elseif (in_array($catalogSection, ['products', 'clothing'], true)) {
            $query->whereNull('external.id')->where('product.type_code', '!=', 'artwork');
            $clothingCategoryIds = $this->clothingCategoryIds();
            if ($catalogSection === 'clothing') {
                $clothingCategoryIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereExists(function ($category) use ($clothingCategoryIds): void {
                        $category->selectRaw('1')
                            ->from('store_product_categories as clothing_link')
                            ->whereColumn('clothing_link.product_id', 'product.id')
                            ->whereIn('clothing_link.category_id', $clothingCategoryIds);
                    });
            } elseif ($clothingCategoryIds !== []) {
                $query->whereNotExists(function ($category) use ($clothingCategoryIds): void {
                    $category->selectRaw('1')
                        ->from('store_product_categories as clothing_link')
                        ->whereColumn('clothing_link.product_id', 'product.id')
                        ->whereIn('clothing_link.category_id', $clothingCategoryIds);
                });
            }
        } elseif ($catalogSection === '') {
            // The Store's default catalog belongs to Store. Exhibition
            // artworks remain reachable from their explicit section only.
            $query->whereNull('external.id')->where('product.type_code', '!=', 'artwork');
        }

        if (! empty($filters['artwork_category']) && $this->artworkCatalogReady()) {
            $query->whereExists(function ($details) use ($filters): void {
                $details->selectRaw('1')
                    ->from('store_artwork_details as artwork_filter')
                    ->whereColumn('artwork_filter.product_id', 'product.id')
                    ->where('artwork_filter.category', (string) $filters['artwork_category']);
            });
        }

        if ($categorySlug !== null || ! empty($filters['category'])) {
            $slug = $categorySlug ?? (string) $filters['category'];
            $query->whereExists(function ($category) use ($slug, $locale): void {
                $category->selectRaw('1')
                    ->from('store_product_categories as product_category')
                    ->join('store_categories as category', 'category.id', '=', 'product_category.category_id')
                    ->joinSub($this->categoryTranslationIds($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'category.id'))
                    ->join('store_category_translations as category_translation', 'category_translation.id', '=', 'category_translation_choice.translation_id')
                    ->whereColumn('product_category.product_id', 'product.id')
                    ->where('category.status', 'active')
                    ->where('category_translation.slug', $slug);
            });
        }

        if ($brandSlug !== null || ! empty($filters['brand'])) {
            $query->where('brand.slug', $brandSlug ?? (string) $filters['brand']);
        }

        if (! empty($filters['tag'])) {
            $query->whereJsonContains('product.tags', (string) $filters['tag']);
        }

        if (! empty($filters['featured'])) {
            $query->where('product.is_featured', true);
        }

        if (! empty($filters['on_sale'])) {
            $query->whereNotNull('resolved_price.sale_price_minor');
        }

        if (! empty($filters['in_stock'])) {
            $query->where(function ($builder): void {
                $builder->whereNull('inventory.id')
                    ->orWhere('inventory.tracking_mode', 'untracked')
                    ->orWhere('inventory.allow_backorder', true)
                    ->orWhereRaw(
                        '(CAST(inventory.on_hand AS SIGNED) - CAST(inventory.reserved AS SIGNED) - CAST(inventory.safety_stock AS SIGNED)) > 0',
                    );
            });
        }
        if ($runtime['out_of_stock_visibility'] === 'hidden') {
            $query->where(function ($builder): void {
                $builder->whereNull('inventory.id')
                    ->orWhere('inventory.tracking_mode', 'untracked')
                    ->orWhere('inventory.allow_backorder', true)
                    ->orWhereRaw(
                        '(CAST(inventory.on_hand AS SIGNED) - CAST(inventory.reserved AS SIGNED) - CAST(inventory.safety_stock AS SIGNED)) > 0',
                    );
            });
        }

        if (($minimum = $this->minorAmount($filters['min_price'] ?? null)) !== null) {
            $query->whereRaw('COALESCE(resolved_price.sale_price_minor, resolved_price.regular_price_minor) >= ?', [$minimum]);
        }
        if (($maximum = $this->minorAmount($filters['max_price'] ?? null)) !== null) {
            $query->whereRaw('COALESCE(resolved_price.sale_price_minor, resolved_price.regular_price_minor) <= ?', [$maximum]);
        }

        match ((string) ($filters['sort'] ?? 'newest')) {
            'price_asc' => $query->orderBy('effective_price_minor')->orderBy('product.id'),
            'price_desc' => $query->orderByDesc('effective_price_minor')->orderByDesc('product.id'),
            'name', 'name_asc' => $query->orderBy('translation.name')->orderBy('product.id'),
            'name_desc' => $query->orderByDesc('translation.name')->orderByDesc('product.id'),
            'relevance' => $search === ''
                ? $query->orderByDesc('product.published_at')->orderByDesc('product.id')
                : $query->orderByRaw('CASE WHEN translation.name LIKE ? THEN 0 WHEN variant.sku LIKE ? THEN 1 WHEN brand.name LIKE ? THEN 2 ELSE 3 END', ['%'.$search.'%', '%'.$search.'%', '%'.$search.'%'])
                    ->orderByDesc('product.published_at')->orderByDesc('product.id'),
            'featured', 'newest' => $query->orderByDesc('product.published_at')->orderByDesc('product.id'),
            default => $query->orderByDesc('product.published_at')->orderByDesc('product.id'),
        };

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();
        $items = $this->hydrateCards($rows, (bool) $runtime['show_stock_status']);
        $products = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        $categories = $includeFacets ? $this->categories() : collect();
        $brands = $includeFacets ? $this->brands($catalogSection) : collect();
        $tags = $includeFacets ? $this->tags($catalogSection) : collect();
        if ($catalogSection === 'artworks') {
            // Artwork taxonomy remains owned by Exhibitions and is rendered on
            // the artwork page reached from each Store card.
            $categories = collect();
            $brands = collect();
            $tags = collect();
        } elseif ($catalogSection === 'clothing') {
            $categories = $categories->where('catalog_section', 'clothing')->values();
        } elseif ($catalogSection === 'products') {
            $categories = $categories->where('catalog_section', 'standard')->values();
        }

        return [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'tags' => $tags,
            // Filter controls must not offer an option that cannot return a product.
            'filterCategories' => $categories->filter(fn (array $category): bool => $category['products_count'] > 0)->values(),
            'filterBrands' => $brands->filter(fn (array $brand): bool => $brand['products_count'] > 0)->values(),
        ];
    }

    /** @return array{products: LengthAwarePaginator, categories: Collection} */
    public function artworks(array $filters = []): array
    {
        if (! $this->artworkCatalogReady()) {
            $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
            $perPage = max(1, min(48, (int) ($filters['per_page'] ?? 12)));

            return [
                'products' => new LengthAwarePaginator([], 0, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]),
                'categories' => collect(),
            ];
        }

        $filters['type_code'] = 'artwork';
        $filters['artwork_category'] = $filters['category'] ?? null;
        unset($filters['category'], $filters['brand']);
        $result = $this->browse($filters);
        $categories = $this->database->table('store_artwork_details as artwork')
            ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
            ->where('product.type_code', 'artwork')
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->whereNotNull('artwork.category')
            ->where('artwork.category', '!=', '')
            ->groupBy('artwork.category')
            ->orderBy('artwork.category')
            ->get(['artwork.category', $this->database->raw('COUNT(*) as products_count')])
            ->map(fn (object $category): array => [
                'name' => (string) $category->category,
                'slug' => (string) $category->category,
                'products_count' => (int) $category->products_count,
            ]);

        return ['products' => $result['products'], 'categories' => $categories];
    }

    /** @return array<string, mixed>|null */
    public function product(string $slug): ?array
    {
        if (! $this->catalogReady()) {
            return null;
        }

        $locale = $this->locale();
        $exhibitionsAvailable = Route::has('exhibitions.artworks.show');
        $product = $this->database->table('store_products as product')
            ->joinSub($this->productTranslationIds($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->join('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->leftJoin('store_brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('store_external_sellables as external', 'external.product_id', '=', 'product.id')
            ->whereExists(function ($translations) use ($slug): void {
                $translations->selectRaw('1')
                    ->from('store_product_translations as route_translation')
                    ->whereColumn('route_translation.product_id', 'product.id')
                    ->where('route_translation.slug', $slug);
            })
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->where(function ($ownership) use ($exhibitionsAvailable): void {
                $ownership->where(function ($storeOwned): void {
                    $storeOwned->whereNull('external.id')
                        ->where('product.type_code', '!=', 'artwork');
                });
                if ($exhibitionsAvailable) {
                    $ownership->orWhere('external.owner_code', 'exhibitions');
                }
            })
            ->first([
                'product.id',
                'product.public_id',
                'product.type_code',
                'product.is_featured',
                'product.tags',
                'product.artist_name',
                'product.shipping_policy',
                'product.return_policy',
                'translation.name',
                'translation.slug',
                'translation.short_description',
                'translation.description',
                'translation.meta_title',
                'translation.meta_description',
                'brand.name as brand_name',
                'brand.slug as brand_slug',
                'external.owner_code as source_owner',
                'external.external_id as source_external_id',
                'external.metadata as source_metadata',
            ]);

        if ($product === null) {
            return null;
        }

        $variants = $this->variantRows(
            $this->database->table('store_product_variants')
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('position')
                ->get(['id', 'public_id', 'sku', 'is_default']),
        );

        if ($variants->isEmpty()) {
            return null;
        }
        $runtime = $this->settings->current();
        $storePolicies = (array) ($runtime['policies'] ?? []);
        $shippingPolicy = trim((string) ($product->shipping_policy ?? ''));
        if ($shippingPolicy === '') {
            $shippingPolicy = trim((string) ($storePolicies['delivery'] ?? ''));
        }
        $returnPolicy = trim((string) ($product->return_policy ?? ''));
        if ($returnPolicy === '') {
            $returnPolicy = trim((string) ($storePolicies['return'] ?? ''));
        }
        if ($runtime['out_of_stock_visibility'] === 'hidden'
            && ! $variants->contains(fn (array $variant): bool => (bool) $variant['available'])) {
            return null;
        }

        $categories = $this->productCategories([(int) $product->id])->get((int) $product->id, collect());
        $media = $this->productMedia([(int) $product->id])->get((int) $product->id, collect());
        if ($media->isEmpty()) {
            return null;
        }

        $attributes = $this->database->table('store_product_attribute_assignments as assignment')
            ->join('store_attributes as attribute', 'attribute.id', '=', 'assignment.attribute_id')
            ->joinSub($this->translations->attributes($locale), 'attribute_translation_choice', fn ($join) => $join->on('attribute_translation_choice.attribute_id', '=', 'attribute.id'))
            ->join('store_attribute_translations as translation', 'translation.id', '=', 'attribute_translation_choice.translation_id')
            ->leftJoin('store_product_attribute_values as value', function ($join): void {
                $join->on('value.product_id', '=', 'assignment.product_id')
                    ->on('value.attribute_id', '=', 'assignment.attribute_id');
            })
            ->leftJoinSub($this->translations->attributeOptions($locale), 'option_translation_choice', fn ($join) => $join->on('option_translation_choice.attribute_option_id', '=', 'value.option_id'))
            ->leftJoin('store_attribute_option_translations as option_translation', 'option_translation.id', '=', 'option_translation_choice.translation_id')
            ->where('assignment.product_id', $product->id)
            ->where('assignment.is_visible', true)
            ->orderBy('assignment.position')
            ->get([
                'translation.name',
                'attribute.data_type',
                'value.value_text',
                'value.value_integer',
                'value.value_decimal',
                'value.value_boolean',
                'option_translation.name as option_name',
            ])
            ->map(fn (object $attribute): array => [
                'name' => (string) $attribute->name,
                'value' => $this->attributeValue($attribute),
            ])
            ->filter(fn (array $attribute): bool => $attribute['value'] !== null && $attribute['value'] !== '')
            ->values();

        $defaultVariant = $variants->firstWhere('is_default', true) ?? $variants->first();
        $related = $this->relatedProducts(
            (int) $product->id,
            $categories->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            (int) config('store.storefront.related_limit', 4),
            (string) $product->type_code,
        );

        $artwork = (string) $product->type_code === 'artwork'
            ? $this->artworkDetails((int) $product->id)
            : null;

        return [
            'id' => (int) $product->id,
            'public_id' => (string) $product->public_id,
            'type_code' => (string) $product->type_code,
            'name' => (string) $product->name,
            'slug' => (string) $product->slug,
            'artist_name' => $product->artist_name,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'brand_name' => $product->brand_name,
            'brand_slug' => $product->brand_slug,
            'is_featured' => (bool) $product->is_featured,
            'tags' => array_values(array_filter(json_decode((string) ($product->tags ?? '[]'), true) ?: [], 'is_string')),
            'variants' => $variants,
            'default_variant' => $defaultVariant,
            'price' => [
                'minor' => $defaultVariant['price_minor'],
                'regular_minor' => $defaultVariant['regular_price_minor'],
                'formatted' => $this->formatCatalogMoney($defaultVariant['price_minor']),
                'regular_formatted' => $defaultVariant['regular_price_minor'] === null
                    ? null
                    : $this->formatCatalogMoney($defaultVariant['regular_price_minor']),
                'currency' => $defaultVariant['currency'],
                'on_sale' => $defaultVariant['on_sale'],
                'sale_starts_at' => $defaultVariant['sale_starts_at'],
                'sale_ends_at' => $defaultVariant['sale_ends_at'],
            ],
            'available' => (bool) $defaultVariant['available'],
            'prices_include_tax' => $this->money->pricesIncludeTax(),
            'show_stock_status' => (bool) $runtime['show_stock_status'],
            'shipping_policy' => $shippingPolicy,
            'return_policy' => $returnPolicy,
            'categories' => $categories,
            'media' => $media,
            'attributes' => $attributes,
            'related' => $related,
            'artwork' => $artwork,
            'source_owner' => $product->source_owner,
            'source_external_id' => $product->source_external_id,
            'detail_url' => $this->detailUrl($product->source_owner, $product->source_external_id, (string) $product->slug),
        ];
    }

    /**
     * @param  array<string, int>  $quantities
     * @return Collection<int, array<string, mixed>>
     */
    public function cartItems(array $quantities): Collection
    {
        if (! $this->catalogReady()) {
            return collect();
        }

        $variantIds = array_keys(array_filter($quantities, fn (int $quantity): bool => $quantity > 0));
        if ($variantIds === []) {
            return collect();
        }

        $locale = $this->locale();
        $rows = $this->database->table('store_product_variants as variant')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->joinSub($this->productTranslationIds($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->join('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->leftJoin('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variant.id')
            ->leftJoin('store_external_sellables as external', 'external.variant_id', '=', 'variant.id')
            ->whereIn('variant.public_id', $variantIds)
            ->where('variant.status', 'active')
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->get([
                'variant.id',
                'variant.public_id',
                'variant.sku',
                'variant.is_default',
                'product.id as product_id',
                'product.public_id as product_public_id',
                'translation.name',
                'translation.slug',
                'inventory.public_id as inventory_public_id',
                'inventory.tracking_mode',
                'inventory.on_hand',
                'inventory.reserved',
                'inventory.safety_stock',
                'inventory.allow_backorder',
                'external.metadata as external_metadata',
            ]);

        $variants = $this->variantRows($rows);
        $media = $this->productMedia($rows->pluck('product_id')->map(fn ($id): int => (int) $id)->all());

        return $variants->map(function (array $variant) use ($quantities, $rows, $media): array {
            $row = $rows->firstWhere('public_id', $variant['public_id']);
            $quantity = max(1, min(99, (int) ($quantities[$variant['public_id']] ?? 1)));
            $maximum = $variant['available_quantity'] === null || $variant['allow_backorder']
                ? 99
                : max(0, min(99, $variant['available_quantity']));
            $quantity = min($quantity, max(1, $maximum));
            $image = $media->get((int) $row->product_id, collect())->first();
            $externalMetadata = $row->external_metadata === null
                ? []
                : (json_decode((string) $row->external_metadata, true) ?: []);

            return [
                'product_public_id' => (string) $row->product_public_id,
                'product_name' => (string) $row->name,
                'product_slug' => (string) $row->slug,
                'variant_public_id' => $variant['public_id'],
                'variant_name' => $variant['option_label'],
                'sku' => $variant['sku'],
                'image_url' => $image['url'] ?? null,
                'image_alt' => $image['alt'] ?? (string) $row->name,
                'quantity' => $quantity,
                'maximum_quantity' => $maximum,
                'price_minor' => $variant['price_minor'],
                'price_formatted' => $this->formatCatalogMoney($variant['price_minor']),
                'line_total_minor' => $variant['price_minor'] * $quantity,
                'line_total_formatted' => $this->formatCatalogMoney($variant['price_minor'] * $quantity),
                'currency' => $variant['currency'],
                'available' => $variant['available'],
                'tracking_mode' => $variant['tracking_mode'],
                'inventory_public_id' => $variant['inventory_public_id'],
                'artist_display_name' => $externalMetadata['artist_display_name'] ?? null,
                'edition_type' => $externalMetadata['edition_type'] ?? null,
                'frame_cost_included' => (bool) ($externalMetadata['frame_cost_included'] ?? false),
                'packaging_cost_included' => (bool) ($externalMetadata['packaging_cost_included'] ?? false),
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function featuredProducts(int $limit = 8): Collection
    {
        return $this->browse(
            ['featured' => true, 'per_page' => $limit, 'page' => 1],
            includeFacets: false,
        )['products']->getCollection();
    }

    public function formatMoney(int $amountMinor, ?string $currency = null): string
    {
        return $this->money->format($amountMinor, $currency);
    }

    public function formatCatalogMoney(int $amountMinor, ?string $currency = null): string
    {
        return $this->money->formatCatalog($amountMinor, $currency);
    }

    /** @param Collection<int, object> $rows @return Collection<int, array<string, mixed>> */
    private function hydrateCards(Collection $rows, bool $showStockStatus): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $productIds = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $media = $this->productMedia($productIds);
        $categories = $this->productCategories($productIds);
        $artworkDetails = $this->artworkCatalogReady()
            ? $this->database->table('store_artwork_details')->whereIn('product_id', $productIds)->get()->keyBy('product_id')
            : collect();

        return $rows->map(function (object $row) use ($media, $categories, $artworkDetails, $showStockStatus): array {
            $price = (int) ($row->sale_price_minor ?? $row->regular_price_minor);
            $availableQuantity = $this->availableQuantity($row);
            $available = ($row->tracking_mode ?? 'untracked') !== 'tracked'
                || (bool) ($row->allow_backorder ?? false)
                || ($availableQuantity ?? 0) > 0;
            $rawSourceMetadata = $row->source_metadata ?? null;
            $sourceMetadata = $rawSourceMetadata === null
                ? []
                : (json_decode((string) $rawSourceMetadata, true) ?: []);
            $sourceOwner = ($row->source_owner ?? null) === null ? null : (string) $row->source_owner;
            $isExhibitionArtwork = $sourceOwner === 'exhibitions';

            return [
                'public_id' => (string) $row->public_id,
                'type_code' => $isExhibitionArtwork ? 'artwork' : (string) ($row->type_code ?? 'simple'),
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'short_description' => $row->short_description,
                'description' => $row->description,
                'tags' => collect(json_decode((string) ($row->tags ?? '[]'), true))
                    ->filter(fn (mixed $tag): bool => is_string($tag) && trim($tag) !== '')
                    ->values()
                    ->all(),
                'brand_name' => $row->brand_name,
                'brand_slug' => $row->brand_slug,
                'variant_public_id' => (string) $row->variant_public_id,
                'sku' => $row->sku,
                'is_featured' => (bool) $row->is_featured,
                'price_minor' => $price,
                'price_formatted' => $this->formatCatalogMoney($price),
                'regular_price_minor' => $row->regular_price_minor === null ? null : (int) $row->regular_price_minor,
                'regular_price_formatted' => $row->regular_price_minor === null ? null : $this->formatCatalogMoney((int) $row->regular_price_minor),
                'on_sale' => $row->sale_price_minor !== null,
                'available' => $available,
                'show_stock_status' => $showStockStatus,
                'prices_include_tax' => $this->money->pricesIncludeTax(),
                'available_quantity' => $availableQuantity,
                'currency' => (string) config('store.default_currency', 'JOD'),
                'published_at' => (string) ($row->published_at ?? ''),
                'image' => $media->get((int) $row->id, collect())->first(),
                'categories' => $categories->get((int) $row->id, collect()),
                'artwork' => (($detail = $artworkDetails->get((int) $row->id)) === null) ? null : [
                    'completion_year' => $detail->completion_year,
                    'work_type' => $detail->work_type,
                    'edition_type' => $detail->edition_type,
                    'category' => $detail->category,
                    'artistic_style' => $detail->artistic_style,
                    'technique' => $detail->technique,
                ],
                'source_owner' => $sourceOwner,
                'source_external_id' => $row->source_external_id ?? null,
                'artist_name' => $sourceMetadata['artist_display_name'] ?? $row->artist_name ?? null,
                'detail_url' => $this->detailUrl($sourceOwner, $row->source_external_id ?? null, (string) $row->slug),
            ];
        });
    }

    /** @return list<int> */
    private function clothingCategoryIds(): array
    {
        $rows = $this->database->table('store_categories')
            ->where('status', 'active')
            ->get(['id', 'parent_id', 'catalog_section']);
        $ids = $rows
            ->filter(fn (object $category): bool => $category->catalog_section === 'clothing')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        do {
            $before = count($ids);
            foreach ($rows as $category) {
                if ($category->parent_id !== null
                    && in_array((int) $category->parent_id, $ids, true)
                    && ! in_array((int) $category->id, $ids, true)) {
                    $ids[] = (int) $category->id;
                }
            }
        } while (count($ids) !== $before);

        return $ids;
    }

    private function categoryTranslationIds(string $locale): \Illuminate\Database\Query\Builder
    {
        return $this->translations->categories($locale);
    }

    private function productTranslationIds(string $locale): \Illuminate\Database\Query\Builder
    {
        return $this->translations->products($locale);
    }

    private function detailUrl(?string $owner, string|int|null $externalId, string $storeSlug): string
    {
        if ($owner === 'exhibitions' && $externalId !== null && Route::has('exhibitions.artworks.show')) {
            return route('exhibitions.artworks.show', ['artwork' => (string) $externalId]);
        }

        return route('store.products.show', ['slug' => $storeSlug]);
    }

    /** @param Collection<int, object> $rows @return Collection<int, array<string, mixed>> */
    private function variantRows(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $variantIds = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $now = now();
        $prices = $this->database->table('store_prices')
            ->whereIn('variant_id', $variantIds)
            ->where('status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get(['variant_id', 'type', 'amount_minor', 'currency', 'starts_at', 'ends_at'])
            ->groupBy('variant_id');

        $inventory = $this->database->table('store_inventory_items')
            ->whereIn('variant_id', $variantIds)
            ->get(['variant_id', 'public_id', 'tracking_mode', 'on_hand', 'reserved', 'safety_stock', 'allow_backorder'])
            ->keyBy('variant_id');

        $locale = $this->locale();
        $options = $this->database->table('store_variant_option_values as value')
            ->join('store_attributes as attribute', 'attribute.id', '=', 'value.attribute_id')
            ->joinSub($this->translations->attributes($locale), 'attribute_translation_choice', fn ($join) => $join->on('attribute_translation_choice.attribute_id', '=', 'value.attribute_id'))
            ->join('store_attribute_translations as attribute_translation', 'attribute_translation.id', '=', 'attribute_translation_choice.translation_id')
            ->join('store_attribute_options as option', 'option.id', '=', 'value.attribute_option_id')
            ->joinSub($this->translations->attributeOptions($locale), 'option_translation_choice', fn ($join) => $join->on('option_translation_choice.attribute_option_id', '=', 'value.attribute_option_id'))
            ->join('store_attribute_option_translations as option_translation', 'option_translation.id', '=', 'option_translation_choice.translation_id')
            ->whereIn('value.variant_id', $variantIds)
            ->orderBy('attribute_translation.name')
            ->get([
                'value.variant_id',
                'attribute.code as attribute_code',
                'attribute_translation.name as attribute_name',
                'option.code as option_code',
                'option_translation.name as option_name',
            ])
            ->groupBy('variant_id');

        $variantMedia = $this->database->table('store_product_media as association')
            ->join('store_media_assets as asset', 'asset.id', '=', 'association.media_asset_id')
            ->leftJoinSub($this->translations->productMedia($locale), 'media_translation_choice', fn ($join) => $join->on('media_translation_choice.product_media_id', '=', 'association.id'))
            ->leftJoin('store_product_media_translations as translation', 'translation.id', '=', 'media_translation_choice.translation_id')
            ->whereIn('association.variant_id', $variantIds)
            ->where('association.visibility', 'public')
            ->where('asset.status', 'ready')
            ->orderByDesc('association.is_primary')
            ->orderBy('association.position')
            ->get(['association.variant_id', 'asset.disk', 'asset.path', 'translation.alt_text'])
            ->map(fn (object $media): array => [
                'variant_id' => (int) $media->variant_id,
                'url' => $this->fileUrl((string) $media->disk, (string) $media->path),
                'alt' => $media->alt_text,
            ])
            ->filter(fn (array $media): bool => $media['url'] !== null)
            ->groupBy('variant_id');

        return $rows->map(function (object $row) use ($prices, $inventory, $options, $variantMedia): ?array {
            $priceRows = $prices->get((int) $row->id, collect());
            $sale = $priceRows->firstWhere('type', 'sale');
            $regular = $priceRows->firstWhere('type', 'regular');
            $effective = $sale ?? $regular;
            if ($effective === null) {
                return null;
            }

            $stock = $inventory->get((int) $row->id);
            $availableQuantity = $this->availableQuantity($stock);
            $allowBackorder = (bool) ($stock->allow_backorder ?? false);
            $available = ($stock->tracking_mode ?? 'untracked') !== 'tracked'
                || $allowBackorder
                || ($availableQuantity ?? 0) > 0;
            $optionValues = $options->get((int) $row->id, collect())
                ->map(fn (object $option): array => [
                    'attribute_code' => (string) $option->attribute_code,
                    'attribute_name' => (string) $option->attribute_name,
                    'option_code' => (string) $option->option_code,
                    'option_name' => (string) $option->option_name,
                ])
                ->values();
            $labels = $optionValues
                ->map(fn (array $option): string => $option['attribute_name'].': '.$option['option_name'])
                ->values()
                ->all();
            $image = $variantMedia->get((int) $row->id, collect())->first();

            return [
                'public_id' => (string) $row->public_id,
                'sku' => $row->sku,
                'is_default' => (bool) ($row->is_default ?? false),
                'options' => $labels,
                'option_values' => $optionValues->all(),
                'option_label' => $labels === [] ? null : implode(' · ', $labels),
                'image_url' => $image['url'] ?? null,
                'image_alt' => $image['alt'] ?? null,
                'price_minor' => (int) $effective->amount_minor,
                'price_formatted' => $this->formatCatalogMoney((int) $effective->amount_minor, (string) $effective->currency),
                'regular_price_minor' => $regular === null ? null : (int) $regular->amount_minor,
                'regular_price_formatted' => $regular === null ? null : $this->formatCatalogMoney((int) $regular->amount_minor, (string) $regular->currency),
                'currency' => (string) $effective->currency,
                'on_sale' => $sale !== null,
                'sale_starts_at' => $sale?->starts_at,
                'sale_ends_at' => $sale?->ends_at,
                'available' => $available,
                'available_quantity' => $availableQuantity,
                'allow_backorder' => $allowBackorder,
                'tracking_mode' => (string) ($stock->tracking_mode ?? 'untracked'),
                'inventory_public_id' => $stock?->public_id,
            ];
        })->filter()->values();
    }

    /** @param list<int> $productIds @return Collection<int, Collection<int, array<string, mixed>>> */
    private function productMedia(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $locale = $this->locale();

        return $this->database->table('store_product_media as association')
            ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'association.media_asset_id')
            ->leftJoin('store_artwork_media as artwork_media', 'artwork_media.product_media_public_id', '=', 'association.public_id')
            ->leftJoinSub($this->translations->productMedia($locale), 'media_translation_choice', fn ($join) => $join->on('media_translation_choice.product_media_id', '=', 'association.id'))
            ->leftJoin('store_product_media_translations as translation', 'translation.id', '=', 'media_translation_choice.translation_id')
            ->whereIn('association.product_id', $productIds)
            ->where('association.visibility', 'public')
            ->where(function ($query): void {
                $query->where('asset.status', 'ready')
                    ->orWhere(function ($external): void {
                        $external->whereNotNull('association.external_source')
                            ->where('association.external_key', 'like', '/%');
                    });
            })
            ->whereIn('association.role', ['cover', 'gallery', 'thumbnail'])
            ->orderByDesc('association.is_primary')
            ->orderBy('association.position')
            ->get(['association.product_id', 'association.public_id', 'association.external_source', 'association.external_key', 'association.role', 'artwork_media.role as artwork_role', 'asset.disk', 'asset.path', 'asset.width', 'asset.height', 'translation.alt_text'])
            ->map(fn (object $media): array => [
                'id' => (string) $media->public_id,
                'product_id' => (int) $media->product_id,
                'url' => $media->external_source !== null ? (string) $media->external_key : $this->fileUrl((string) $media->disk, (string) $media->path),
                'alt' => $media->alt_text,
                'role' => $media->artwork_role ?: $media->role,
                'width' => $media->width === null ? null : (int) $media->width,
                'height' => $media->height === null ? null : (int) $media->height,
            ])
            ->filter(fn (array $media): bool => $media['url'] !== null)
            ->groupBy('product_id');
    }

    /** @param list<int> $productIds @return Collection<int, Collection<int, array<string, mixed>>> */
    private function productCategories(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $locale = $this->locale();

        return $this->database->table('store_product_categories as link')
            ->join('store_categories as category', 'category.id', '=', 'link.category_id')
            ->joinSub($this->categoryTranslationIds($locale), 'selected_translation', fn ($join) => $join->on('selected_translation.category_id', '=', 'category.id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'selected_translation.translation_id')
            ->whereIn('link.product_id', $productIds)
            ->where('category.status', 'active')
            ->orderByDesc('link.is_primary')
            ->orderBy('link.position')
            ->get(['link.product_id', 'category.id', 'category.public_id', 'translation.name', 'translation.slug'])
            ->map(fn (object $category): array => [
                'product_id' => (int) $category->product_id,
                'id' => (int) $category->id,
                'public_id' => (string) $category->public_id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ])
            ->groupBy('product_id');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function categories(): Collection
    {
        $locale = $this->locale();
        $clothingCategoryIds = $this->clothingCategoryIds();

        return $this->database->table('store_categories as category')
            ->joinSub($this->categoryTranslationIds($locale), 'selected_translation', fn ($join) => $join->on('selected_translation.category_id', '=', 'category.id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'selected_translation.translation_id')
            ->leftJoin('store_product_categories as link', 'link.category_id', '=', 'category.id')
            ->leftJoin('store_products as product', function ($join): void {
                $join->on('product.id', '=', 'link.product_id')
                    ->where('product.status', '=', 'published')
                    ->where('product.visibility', '=', 'public');
            })
            ->leftJoin('store_external_sellables as external', 'external.product_id', '=', 'product.id')
            ->where('category.status', 'active')
            ->groupBy('category.id', 'category.public_id', 'category.parent_id', 'category.image_path', 'category.is_featured', 'category.position', 'translation.name', 'translation.slug', 'translation.description')
            ->orderByDesc('category.is_featured')
            ->orderBy('category.position')
            ->orderBy('translation.name')
            ->get([
                'category.id',
                'category.public_id',
                'category.parent_id',
                'category.image_path',
                'category.is_featured',
                'translation.name',
                'translation.slug',
                'translation.description',
                $this->database->raw("COUNT(DISTINCT CASE WHEN external.id IS NULL AND product.type_code <> 'artwork' THEN product.id END) as products_count"),
            ])
            ->map(fn (object $category): array => [
                'id' => (int) $category->id,
                'public_id' => (string) $category->public_id,
                'parent_id' => $category->parent_id === null ? null : (int) $category->parent_id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_path ? $this->fileUrl('public', (string) $category->image_path) : null,
                'is_featured' => (bool) $category->is_featured,
                'products_count' => (int) $category->products_count,
                'catalog_section' => in_array((int) $category->id, $clothingCategoryIds, true) ? 'clothing' : 'standard',
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function brands(string $catalogSection = ''): Collection
    {
        $query = $this->database->table('store_brands as brand')
            ->leftJoin('store_products as product', function ($join): void {
                $join->on('product.brand_id', '=', 'brand.id')
                    ->where('product.status', '=', 'published')
                    ->where('product.visibility', '=', 'public');
            })
            ->leftJoin('store_external_sellables as external', 'external.product_id', '=', 'product.id')
            ->where('brand.status', 'active')
            ->whereNull('external.id')
            ->where('product.type_code', '!=', 'artwork');
        $clothingCategoryIds = $this->clothingCategoryIds();
        if ($catalogSection === 'clothing') {
            if ($clothingCategoryIds === []) {
                return collect();
            }
            $query->whereExists(function ($category) use ($clothingCategoryIds): void {
                $category->selectRaw('1')->from('store_product_categories as clothing_link')
                    ->whereColumn('clothing_link.product_id', 'product.id')
                    ->whereIn('clothing_link.category_id', $clothingCategoryIds);
            });
        } elseif ($catalogSection === 'products' && $clothingCategoryIds !== []) {
            $query->whereNotExists(function ($category) use ($clothingCategoryIds): void {
                $category->selectRaw('1')->from('store_product_categories as clothing_link')
                    ->whereColumn('clothing_link.product_id', 'product.id')
                    ->whereIn('clothing_link.category_id', $clothingCategoryIds);
            });
        }

        return $query
            ->groupBy('brand.id', 'brand.public_id', 'brand.name', 'brand.slug', 'brand.description', 'brand.logo_path', 'brand.position')
            ->orderBy('brand.position')
            ->orderBy('brand.name')
            ->get([
                'brand.public_id',
                'brand.name',
                'brand.slug',
                'brand.description',
                'brand.logo_path',
                $this->database->raw("COUNT(DISTINCT CASE WHEN external.id IS NULL AND product.type_code <> 'artwork' THEN product.id END) as products_count"),
            ])
            ->map(fn (object $brand): array => [
                'public_id' => (string) $brand->public_id,
                'name' => (string) $brand->name,
                'slug' => (string) $brand->slug,
                'description' => $brand->description,
                'logo_url' => $brand->logo_path ? $this->fileUrl('public', (string) $brand->logo_path) : null,
                'products_count' => (int) $brand->products_count,
            ]);
    }

    /** @return Collection<int, array{name:string, products_count:int}> */
    private function tags(string $catalogSection = ''): Collection
    {
        $counts = [];
        $query = $this->database->table('store_products as product')
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->where('product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')
                    ->from('store_external_sellables')
                    ->whereColumn('store_external_sellables.product_id', 'product.id');
            })
            ->whereNotNull('product.tags');
        $clothingCategoryIds = $this->clothingCategoryIds();
        if ($catalogSection === 'clothing') {
            if ($clothingCategoryIds === []) {
                return collect();
            }
            $query->whereExists(function ($category) use ($clothingCategoryIds): void {
                $category->selectRaw('1')->from('store_product_categories as clothing_link')
                    ->whereColumn('clothing_link.product_id', 'product.id')
                    ->whereIn('clothing_link.category_id', $clothingCategoryIds);
            });
        } elseif ($catalogSection === 'products' && $clothingCategoryIds !== []) {
            $query->whereNotExists(function ($category) use ($clothingCategoryIds): void {
                $category->selectRaw('1')->from('store_product_categories as clothing_link')
                    ->whereColumn('clothing_link.product_id', 'product.id')
                    ->whereIn('clothing_link.category_id', $clothingCategoryIds);
            });
        }

        foreach ($query
            ->orderBy('id')
            ->cursor(['product.tags']) as $row) {
            $productTags = json_decode((string) $row->tags, true);
            if (! is_array($productTags)) {
                continue;
            }

            $seen = [];
            foreach ($productTags as $tag) {
                if (! is_string($tag) || ($tag = trim($tag)) === '') {
                    continue;
                }

                $key = mb_strtolower($tag);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $counts[$key] ??= ['name' => $tag, 'products_count' => 0];
                $counts[$key]['products_count']++;
            }
        }

        return collect(array_values($counts))
            ->sortBy([
                ['products_count', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /** @param list<int> $categoryIds @return Collection<int, array<string, mixed>> */
    private function relatedProducts(int $productId, array $categoryIds, int $limit, ?string $typeCode = null): Collection
    {
        $limit = max(1, min(12, $limit));
        $locale = $this->locale();
        $slugs = collect();

        if ($categoryIds !== []) {
            $slugs = $this->database->table('store_products as product')
                ->join('store_product_categories as link', 'link.product_id', '=', 'product.id')
                ->joinSub($this->productTranslationIds($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
                ->join('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
                ->whereIn('link.category_id', $categoryIds)
                ->where('product.id', '!=', $productId)
                ->where('product.status', 'published')
                ->where('product.visibility', 'public')
                ->when($typeCode !== null, fn ($query) => $query->where('product.type_code', $typeCode))
                ->orderByDesc('product.published_at')
                ->distinct()
                ->limit($limit)
                ->pluck('translation.slug');
        }

        if ($slugs->count() < $limit) {
            $fallback = $this->database->table('store_products as product')
                ->joinSub($this->productTranslationIds($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
                ->join('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
                ->where('product.id', '!=', $productId)
                ->where('product.status', 'published')
                ->where('product.visibility', 'public')
                ->when($typeCode !== null, fn ($query) => $query->where('product.type_code', $typeCode))
                ->when($slugs->isNotEmpty(), fn ($query) => $query->whereNotIn('translation.slug', $slugs->all()))
                ->orderByDesc('product.published_at')
                ->limit($limit - $slugs->count())
                ->pluck('translation.slug');

            $slugs = $slugs->concat($fallback)->values();
        }

        if ($slugs->isEmpty()) {
            return collect();
        }

        $items = collect();
        $page = $this->browse(['q' => '', 'per_page' => 48, 'type_code' => $typeCode]);
        foreach ($slugs as $slug) {
            $match = $page['products']->getCollection()->firstWhere('slug', $slug);
            if ($match !== null) {
                $items->push($match);
            }
        }

        return $items->take($limit)->values();
    }

    private function availableQuantity(?object $inventory): ?int
    {
        if ($inventory === null || ($inventory->tracking_mode ?? 'untracked') !== 'tracked') {
            return null;
        }

        return max(0, (int) $inventory->on_hand - (int) $inventory->reserved - (int) ($inventory->safety_stock ?? 0));
    }

    private function attributeValue(object $attribute): string|int|float|null
    {
        return match ((string) $attribute->data_type) {
            'select' => $attribute->option_name,
            'integer' => $attribute->value_integer === null ? null : (int) $attribute->value_integer,
            'decimal' => $attribute->value_decimal === null ? null : (float) $attribute->value_decimal,
            'boolean' => $attribute->value_boolean === null ? null : ((bool) $attribute->value_boolean ? __('store::ui.store_auto_93cba07454') : __('store::ui.store_auto_bafd7322c6')),
            default => $attribute->value_text,
        };
    }

    private function locale(): string
    {
        return $this->translations->current();
    }

    private function minorAmount(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        $currency = strtoupper((string) config('store.default_currency', 'JOD'));
        $digits = $currency === 'JOD' ? 3 : 2;

        return (int) round((float) $value * (10 ** $digits));
    }

    private function fileUrl(string $disk, string $path): ?string
    {
        return $this->mediaUrls->forDisk($disk, $path);
    }

    private function catalogReady(): bool
    {
        foreach ([
            'store_products',
            'store_product_translations',
            'store_product_variants',
            'store_prices',
            'store_inventory_items',
            'store_brands',
            'store_categories',
            'store_category_translations',
            'store_product_categories',
            'store_media_assets',
            'store_product_media',
            'store_product_media_translations',
            'store_variant_option_values',
            'store_product_attribute_assignments',
            'store_product_attribute_values',
            'store_attributes',
            'store_attribute_translations',
            'store_attribute_option_translations',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function artworkDetails(int $productId): ?array
    {
        if (! $this->artworkCatalogReady()) {
            return null;
        }

        $detail = $this->database->table('store_artwork_details as artwork')
            ->leftJoin('store_artwork_authenticity as authenticity', 'authenticity.artwork_detail_id', '=', 'artwork.id')
            ->where('artwork.product_id', $productId)
            ->first([
                'artwork.*',
                'authenticity.state as authenticity_state',
            ]);
        if ($detail === null) {
            return null;
        }

        return [
            'completion_year' => $detail->completion_year,
            'work_type' => $detail->work_type,
            'collection_name' => $detail->collection_name,
            'edition_type' => $detail->edition_type,
            'edition_number' => $detail->edition_number,
            'edition_total' => $detail->edition_total,
            'category' => $detail->category,
            'artistic_style' => $detail->artistic_style,
            'subject' => $detail->subject,
            'technique' => $detail->technique,
            'material' => $detail->material,
            'surface' => $detail->surface,
            'primary_colors' => json_decode((string) ($detail->primary_colors ?? '[]'), true) ?: [],
            'orientation' => $detail->orientation,
            'width' => $detail->width,
            'height' => $detail->height,
            'depth' => $detail->depth,
            'dimension_unit' => $detail->dimension_unit,
            'weight' => $detail->weight,
            'weight_unit' => $detail->weight_unit,
            'is_framed' => (bool) $detail->is_framed,
            'frame_material' => $detail->frame_material,
            'condition' => $detail->condition,
            'ready_to_hang' => (bool) $detail->ready_to_hang,
            'story' => $detail->artwork_story,
            'inspiration' => $detail->inspiration_source,
            'care_instructions' => $detail->care_instructions,
            'availability' => $detail->availability,
            'preparation_days' => $detail->preparation_days,
            'shipping_notes' => $detail->shipping_notes,
            'authenticity_state' => $detail->authenticity_state,
        ];
    }

    private function artworkCatalogReady(): bool
    {
        return Schema::hasTable('store_artwork_details') && Schema::hasTable('store_artwork_authenticity');
    }
}
