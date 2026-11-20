<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Catalog\CatalogTranslationSelector;
use Modules\Store\Application\Reports\StoreReportingService;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Contracts\Readiness\StoreReadiness;

final class StoreAdminController extends Controller
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly StoreSettingsService $settings,
        private readonly CatalogTranslationSelector $translations,
    ) {}

    public function overview(Request $request, StoreReadiness $readiness, StoreReportingService $reports): View
    {
        $request->merge(['period' => $request->input('period', 'last30')]);
        $filters = $request->validate([
            'period' => ['required', 'in:today,last7,last30,this_month,previous_month,custom'],
            'date_from' => ['nullable', 'required_if:period,custom', 'date'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:date_from'],
        ]);

        return view('store::admin.overview', [
            'store' => $readiness->status(),
            'report' => $reports->report($filters, $request->user()->can('store.orders.customer.view')),
            'filters' => $filters,
        ]);
    }

    public function products(Request $request): View
    {
        $locale = (string) app()->getLocale();
        $now = now();
        $priceSummary = $this->database->table('store_prices')
            ->selectRaw(
                "variant_id,
                MIN(CASE WHEN type = 'regular' AND status = 'active'
                    AND (starts_at IS NULL OR starts_at <= ?)
                    AND (ends_at IS NULL OR ends_at > ?) THEN amount_minor END) AS regular_price_minor,
                MIN(CASE WHEN type = 'sale' AND status = 'active'
                    AND (starts_at IS NULL OR starts_at <= ?)
                    AND (ends_at IS NULL OR ends_at > ?) THEN amount_minor END) AS sale_price_minor",
                [$now, $now, $now, $now],
            )
            ->groupBy('variant_id');
        $categorySummary = $this->database->table('store_product_categories')
            ->selectRaw('product_id, COALESCE(MAX(CASE WHEN is_primary = 1 THEN category_id END), MIN(category_id)) as category_id')
            ->groupBy('product_id');
        $query = $this->database->table('store_products as product')
            ->leftJoinSub($this->translations->products($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->leftJoin('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->leftJoin('store_artwork_details as artwork_detail', 'artwork_detail.product_id', '=', 'product.id')
            ->leftJoin('store_product_variants as variant', function ($join): void {
                $join->on('variant.product_id', '=', 'product.id')
                    ->where('variant.is_default', '=', true)
                    ->where('variant.status', '!=', 'archived');
            })
            ->leftJoinSub($categorySummary, 'product_category', fn ($join) => $join->on('product_category.product_id', '=', 'product.id'))
            ->leftJoinSub($this->translations->categories($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'product_category.category_id'))
            ->leftJoin('store_category_translations as category_translation', 'category_translation.id', '=', 'category_translation_choice.translation_id')
            ->leftJoin('store_brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variant.id')
            ->leftJoinSub($priceSummary, 'price', fn ($join) => $join->on('price.variant_id', '=', 'variant.id'))
            ->leftJoin('store_product_media as cover', function ($join): void {
                $join->on('cover.product_id', '=', 'product.id')
                    ->whereNull('cover.variant_id')
                    ->where('cover.role', '=', 'cover')
                    ->where('cover.is_primary', '=', true);
            })
            ->leftJoin('store_media_assets as cover_asset', 'cover_asset.id', '=', 'cover.media_asset_id')
            ->select([
                'product.public_id', 'product.type_code', 'product.status', 'product.visibility',
                'product.is_featured', 'product.published_at', 'product.updated_at', 'product.version',
                'translation.name', 'translation.slug', 'variant.sku',
                'category_translation.name as category_name', 'brand.public_id as brand_public_id',
                'brand.name as brand_name', 'inventory.tracking_mode', 'inventory.on_hand',
                'inventory.reserved', 'inventory.allow_backorder',
                'price.regular_price_minor', 'price.sale_price_minor',
                'cover_asset.path as cover_path',
                'artwork_detail.workflow_state as artwork_workflow_state',
            ])
            ->where('product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')
                    ->from('store_external_sellables as product_owner')
                    ->whereColumn('product_owner.product_id', 'product.id');
            });

        if (($search = trim((string) $request->query('q'))) !== '') {
            $query->where(fn ($where) => $where
                ->where('translation.name', 'like', "%{$search}%")
                ->orWhere('variant.sku', 'like', "%{$search}%"));
        }
        if (($category = (string) $request->query('category')) !== '') {
            $query->whereExists(function ($exists) use ($category): void {
                $exists->selectRaw('1')
                    ->from('store_product_categories as filter_product_category')
                    ->join('store_categories as filter_category', 'filter_category.id', '=', 'filter_product_category.category_id')
                    ->whereColumn('filter_product_category.product_id', 'product.id')
                    ->where('filter_category.public_id', $category);
            });
        }
        if (($brand = (string) $request->query('brand')) !== '') {
            $query->where('brand.public_id', $brand);
        }
        if (($status = (string) $request->query('status')) !== '') {
            match ($status) {
                'hidden' => $query->where('product.status', 'published')->where('product.visibility', 'hidden'),
                'published' => $query->where('product.status', 'published')->where('product.visibility', 'public'),
                default => $query->where('product.status', $status),
            };
        } else {
            // Archived records are retained safely, but excluded from the everyday product workspace.
            $query->where('product.status', '!=', 'archived');
        }
        if (($stock = (string) $request->query('stock')) !== '') {
            match ($stock) {
                'in_stock' => $query->where(fn ($where) => $where
                    ->where('inventory.tracking_mode', 'untracked')
                    ->orWhereRaw('(inventory.on_hand - inventory.reserved) > 0')),
                'out_of_stock' => $query->where('inventory.tracking_mode', 'tracked')
                    ->whereRaw('(inventory.on_hand - inventory.reserved) <= 0'),
                'not_configured' => $query->whereNull('inventory.id'),
                default => null,
            };
        }

        match ((string) $request->query('sort', 'published_desc')) {
            'published_asc' => $query->orderByRaw('product.published_at IS NULL')->orderBy('product.published_at')->orderBy('product.id'),
            'updated_desc' => $query->orderByDesc('product.updated_at')->orderByDesc('product.id'),
            'name' => $query->orderBy('translation.name'),
            'price_asc' => $query->orderByRaw('COALESCE(price.sale_price_minor, price.regular_price_minor) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(price.sale_price_minor, price.regular_price_minor) DESC'),
            default => $query->orderByDesc('product.published_at')->orderByDesc('product.id'),
        };

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50], true)
            ? (int) $request->query('per_page')
            : 25;

        return view('store::admin.products.index', [
            'products' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only(['q', 'category', 'brand', 'status', 'stock', 'sort', 'per_page']),
            'categories' => $this->database->table('store_categories as category')
                ->joinSub($this->translations->categories($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'category.id'))
                ->join('store_category_translations as translation', 'translation.id', '=', 'category_translation_choice.translation_id')
                ->where('category.status', '!=', 'archived')->orderBy('translation.name')
                ->get(['category.public_id', 'translation.name']),
            'brands' => $this->database->table('store_brands')
                ->where('status', '!=', 'archived')->orderBy('name')->get(['public_id', 'name']),
            'metrics' => [
                'all' => $this->storeOwnedProducts()->where('product.status', '!=', 'archived')->count(),
                'published' => $this->storeOwnedProducts()->where('product.status', 'published')->where('product.visibility', 'public')->count(),
                'drafts' => $this->storeOwnedProducts()->where('product.status', 'draft')->count(),
                'outOfStock' => $this->database->table('store_inventory_items as metric_inventory')
                    ->join('store_product_variants as metric_variant', 'metric_variant.id', '=', 'metric_inventory.variant_id')
                    ->join('store_products as metric_product', 'metric_product.id', '=', 'metric_variant.product_id')
                    ->where('metric_product.type_code', '!=', 'artwork')
                    ->whereNotExists(function ($external): void {
                        $external->selectRaw('1')->from('store_external_sellables as metric_owner')
                            ->whereColumn('metric_owner.product_id', 'metric_product.id');
                    })
                    ->where('metric_inventory.tracking_mode', 'tracked')
                    ->whereRaw('(metric_inventory.on_hand - metric_inventory.reserved) <= 0')
                    ->count(),
            ],
        ]);
    }

    public function archivedProducts(Request $request): View
    {
        $request->query->set('status', 'archived');

        return $this->products($request);
    }

    public function createProduct(): View
    {
        $uiLocale = (string) app()->getLocale();
        $productLocales = app(\App\Platform\Core\Localization\PlatformLocale::class)->languages();
        $defaultLocale = array_key_exists($uiLocale, $productLocales)
            ? $uiLocale
            : \App\Platform\Core\Localization\PlatformLocale::DEFAULT;
        $variantAttributes = $this->database->table('store_attributes as attribute')
            ->leftJoinSub($this->translations->attributes($uiLocale), 'attribute_translation_choice', fn ($join) => $join->on('attribute_translation_choice.attribute_id', '=', 'attribute.id'))
            ->leftJoin('store_attribute_translations as translation', 'translation.id', '=', 'attribute_translation_choice.translation_id')
            ->where('attribute.status', 'active')
            ->where('attribute.data_type', 'select')
            ->where('attribute.supports_variant_axis', true)
            ->orderBy('attribute.position')
            ->get(['attribute.id', 'attribute.public_id', 'attribute.code', 'translation.name']);
        $variantOptions = $this->database->table('store_attribute_options as option')
            ->leftJoinSub($this->translations->attributeOptions($uiLocale), 'option_translation_choice', fn ($join) => $join->on('option_translation_choice.attribute_option_id', '=', 'option.id'))
            ->leftJoin('store_attribute_option_translations as translation', 'translation.id', '=', 'option_translation_choice.translation_id')
            ->whereIn('option.attribute_id', $variantAttributes->pluck('id'))
            ->where('option.status', 'active')
            ->orderBy('option.position')
            ->get(['option.attribute_id', 'option.public_id', 'option.code', 'translation.name'])
            ->groupBy('attribute_id');
        foreach ($variantAttributes as $attribute) {
            $attribute->options = $variantOptions->get($attribute->id, collect());
        }

        return view('store::admin.products.create', [
            'defaultLocale' => $defaultLocale,
            'productLocales' => $productLocales,
            'translatedLocales' => [],
            'variantAttributes' => $variantAttributes,
            'categories' => $this->database->table('store_categories as category')
                ->leftJoinSub($this->translations->categories($uiLocale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'category.id'))
                ->leftJoin('store_category_translations as translation', 'translation.id', '=', 'category_translation_choice.translation_id')
                ->where('category.status', '!=', 'archived')
                ->orderBy('category.depth')
                ->orderBy('category.position')
                ->get(['category.public_id', 'category.depth', 'translation.name']),
            'brands' => $this->database->table('store_brands')
                ->where('status', '!=', 'archived')
                ->orderBy('name')
                ->get(['public_id', 'name']),
            'mediaAssets' => $this->database->table('store_media_assets')
                ->where('status', 'ready')
                ->where('mime_type', 'like', 'image/%')
                ->orderByDesc('created_at')
                ->limit(48)
                ->get(['public_id', 'path', 'original_filename', 'created_at']),
            'tagSuggestions' => $this->productTagSuggestions(),
            'storePolicies' => (array) ($this->settings->current()['policies'] ?? []),
        ]);
    }

    public function editProduct(Request $request, string $product): View|RedirectResponse
    {
        $record = $this->database->table('store_products')->where('public_id', $product)->first();

        abort_if($record === null, 404);
        abort_if($this->isExternallyOwnedProduct((int) $record->id), 404);
        if ($record->type_code === 'artwork') {
            $workflowState = (string) $this->database->table('store_artwork_details')
                ->where('product_id', $record->id)
                ->value('workflow_state');

            if (in_array($workflowState, ['draft', 'changes_required', 'rejected'], true)) {
                return redirect()->route('admin.plugins.store.artworks.edit', $record->public_id);
            }
            if (in_array($workflowState, ['submitted', 'under_review'], true)) {
                return redirect()->route('admin.plugins.store.artworks.review.show', $record->public_id);
            }

            return redirect()->route('admin.plugins.store.products.view', $record->public_id)
                ->with('status', 'Published artwork must be withdrawn from sale before editing.');
        }

        $defaultLocale = (string) $record->default_locale;
        $productLocales = app(\App\Platform\Core\Localization\PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $activeLocale = array_key_exists($settingsLocale, $productLocales) ? $settingsLocale : $defaultLocale;
        $productLocale = (string) $request->query('locale', $activeLocale);

        if (! array_key_exists($productLocale, $productLocales)) {
            $productLocale = $defaultLocale;
        }


        $translation = $this->database->table('store_product_translations')
            ->where('product_id', $record->id)->where('locale', $productLocale)->first();
        $defaultTranslation = $productLocale === $defaultLocale
            ? $translation
            : $this->database->table('store_product_translations')
                ->where('product_id', $record->id)->where('locale', $defaultLocale)->first();
        $record->name = $translation?->name ?? $defaultTranslation?->name ?? '';
        $record->slug = $translation?->slug ?? $defaultTranslation?->slug ?? '';
        $record->short_description = $translation?->short_description ?? $defaultTranslation?->short_description ?? '';
        $record->description = $translation?->description ?? $defaultTranslation?->description ?? '';
        $record->tags_list = implode(', ', json_decode((string) ($record->tags ?? '[]'), true) ?: []);

        $variants = $this->database->table('store_product_variants')->where('product_id', $record->id)->orderBy('position')->get();
        $variantIds = $variants->pluck('id');
        $pricesByVariant = $this->database->table('store_prices')
            ->whereIn('variant_id', $variantIds)->orderByDesc('created_at')->get()->groupBy('variant_id');
        $inventoryByVariant = $this->database->table('store_inventory_items')
            ->whereIn('variant_id', $variantIds)->get()->keyBy('variant_id');
        $optionsByVariant = $this->database->table('store_variant_option_values as value')
            ->join('store_attributes as attribute', 'attribute.id', '=', 'value.attribute_id')
            ->join('store_attribute_options as option', 'option.id', '=', 'value.attribute_option_id')
            ->whereIn('value.variant_id', $variantIds)
            ->get(['value.variant_id', 'attribute.code as attribute_code', 'option.code as option_code'])
            ->groupBy('variant_id');
        foreach ($variants as $variant) {
            $variant->prices = $pricesByVariant->get($variant->id, collect());
            $variant->inventory = $inventoryByVariant->get($variant->id);
            $variant->options = $optionsByVariant->get($variant->id, collect())
                ->pluck('option_code', 'attribute_code')->all();
        }

        $locale = $productLocale;
        $categories = $this->database->table('store_categories as category')
            ->leftJoinSub($this->translations->categories($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'category.id'))
            ->leftJoin('store_category_translations as translation', 'translation.id', '=', 'category_translation_choice.translation_id')
            ->where('category.status', '!=', 'archived')->orderBy('category.depth')->orderBy('category.position')
            ->get(['category.id', 'category.public_id', 'category.depth', 'translation.name']);
        $assignedCategories = $this->database->table('store_product_categories')->where('product_id', $record->id)->get()->keyBy('category_id');

        $attributes = $this->database->table('store_attributes as attribute')
            ->leftJoinSub($this->translations->attributes($locale), 'attribute_translation_choice', fn ($join) => $join->on('attribute_translation_choice.attribute_id', '=', 'attribute.id'))
            ->leftJoin('store_attribute_translations as translation', 'translation.id', '=', 'attribute_translation_choice.translation_id')
            ->where('attribute.status', 'active')->orderBy('attribute.position')
            ->get(['attribute.id', 'attribute.public_id', 'attribute.code', 'attribute.data_type', 'attribute.supports_variant_axis', 'translation.name']);
        $attributeIds = $attributes->pluck('id');
        $optionsByAttribute = $this->database->table('store_attribute_options as option')
            ->leftJoinSub($this->translations->attributeOptions($locale), 'option_translation_choice', fn ($join) => $join->on('option_translation_choice.attribute_option_id', '=', 'option.id'))
            ->leftJoin('store_attribute_option_translations as translation', 'translation.id', '=', 'option_translation_choice.translation_id')
            ->whereIn('option.attribute_id', $attributeIds)->where('option.status', 'active')->orderBy('option.position')
            ->get(['option.attribute_id', 'option.id', 'option.public_id', 'option.code', 'translation.name'])
            ->groupBy('attribute_id');
        $assignmentsByAttribute = $this->database->table('store_product_attribute_assignments')
            ->where('product_id', $record->id)->whereIn('attribute_id', $attributeIds)->get()->keyBy('attribute_id');
        $valuesByAttribute = $this->database->table('store_product_attribute_values')
            ->where('product_id', $record->id)->whereIn('attribute_id', $attributeIds)->get()->keyBy('attribute_id');
        foreach ($attributes as $attribute) {
            $attribute->options = $optionsByAttribute->get($attribute->id, collect());
            $attribute->assignment = $assignmentsByAttribute->get($attribute->id);
            $attribute->value = $valuesByAttribute->get($attribute->id);
        }

        $media = $this->database->table('store_product_media as association')
            ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'association.media_asset_id')
            ->where('association.product_id', $record->id)->orderBy('association.position')
            ->get(['association.public_id', 'association.variant_id', 'association.role', 'association.is_primary', 'association.position', 'asset.public_id as asset_public_id', 'asset.path', 'asset.original_filename']);

        $mediaAssets = $this->database->table('store_media_assets')
            ->where('status', 'ready')
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit(48)
            ->get(['public_id', 'path', 'original_filename', 'created_at']);

        $brands = $this->database->table('store_brands')
            ->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'public_id', 'name']);

        $tagSuggestions = $this->productTagSuggestions();
        $storePolicies = (array) ($this->settings->current()['policies'] ?? []);
        $translatedLocales = $this->database->table('store_product_translations')
            ->where('product_id', $record->id)
            ->pluck('locale')
            ->all();

        return view('store::admin.products.edit', compact('record', 'variants', 'categories', 'assignedCategories', 'attributes', 'media', 'mediaAssets', 'brands', 'tagSuggestions', 'storePolicies') + [
            'product' => $record,
            'productLocale' => $productLocale,
            'productLocales' => $productLocales,
            'defaultLocale' => $defaultLocale,
            'productTranslation' => $translation,
            'translatedLocales' => $translatedLocales,
        ]);
    }

    public function viewProduct(string $product): View
    {
        $locale = (string) app()->getLocale();
        $record = $this->database->table('store_products as product')
            ->leftJoinSub($this->translations->products($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->leftJoin('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->leftJoin('store_artwork_details as artwork_detail', 'artwork_detail.product_id', '=', 'product.id')
            ->leftJoin('store_brands as brand', 'brand.id', '=', 'product.brand_id')
            ->where('product.public_id', $product)
            ->first([
                'product.*', 'translation.name', 'translation.slug', 'translation.short_description',
                'translation.description', 'brand.name as brand_name',
                'artwork_detail.workflow_state as artwork_workflow_state',
            ]);
        abort_if($record === null, 404);
        abort_if($this->isExternallyOwnedProduct((int) $record->id), 404);
        $variants = $this->database->table('store_product_variants')
            ->where('product_id', $record->id)->where('status', '!=', 'archived')->orderBy('position')->get();
        $variantIds = $variants->pluck('id');
        $pricesByVariant = $this->database->table('store_prices')
            ->whereIn('variant_id', $variantIds)->where('status', 'active')
            ->orderByRaw("CASE WHEN type = 'sale' THEN 0 ELSE 1 END")->orderByDesc('created_at')
            ->get()->groupBy('variant_id');
        $inventoryByVariant = $this->database->table('store_inventory_items')
            ->whereIn('variant_id', $variantIds)->get()->keyBy('variant_id');
        foreach ($variants as $variant) {
            $variant->price = $pricesByVariant->get($variant->id, collect())->first();
            $variant->inventory = $inventoryByVariant->get($variant->id);
        }
        $categories = $this->database->table('store_product_categories as link')
            ->joinSub($this->translations->categories($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'link.category_id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'category_translation_choice.translation_id')
            ->where('link.product_id', $record->id)->orderByDesc('link.is_primary')->pluck('translation.name');
        $media = $this->database->table('store_product_media as association')
            ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'association.media_asset_id')
            ->where('association.product_id', $record->id)->orderBy('association.position')
            ->get(['association.role', 'association.is_primary', 'asset.path', 'asset.original_filename']);

        return view('store::admin.products.view', compact('record', 'variants', 'categories', 'media'));
    }

    public function categories(): View
    {
        $locale = (string) app()->getLocale();
        $translationIds = $this->categoryTranslationIds($locale);
        $categories = $this->database->table('store_categories as category')
            ->leftJoin('store_categories as parent', 'parent.id', '=', 'category.parent_id')
            ->joinSub($translationIds, 'selected_translation', fn ($join) => $join->on('selected_translation.category_id', '=', 'category.id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'selected_translation.translation_id')
            ->leftJoin('store_product_categories as product_category', 'product_category.category_id', '=', 'category.id')
            ->leftJoin('store_products as category_product', 'category_product.id', '=', 'product_category.product_id')
            ->leftJoin('store_external_sellables as category_owner', 'category_owner.product_id', '=', 'category_product.id')
            ->where('category.status', '!=', 'archived')
            ->groupBy('category.id', 'category.public_id', 'parent.public_id', 'category.depth', 'category.position', 'category.status', 'category.is_featured', 'category.version', 'category.image_path', 'category.catalog_section', 'translation.name', 'translation.slug', 'translation.description')
            ->orderBy('category.depth')->orderBy('category.position')
            ->select(['category.id', 'category.public_id', 'parent.public_id as parent_public_id', 'category.depth', 'category.position', 'category.status', 'category.is_featured', 'category.version', 'category.image_path', 'category.catalog_section', 'translation.name', 'translation.slug', 'translation.description'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN category_owner.id IS NULL AND category_product.type_code <> 'artwork' THEN category_product.id END) as products_count")
            ->paginate(25)
            ->withQueryString();
        $clothingCategoryIds = $this->clothingCategoryIds();
        $categories->getCollection()->each(function (object $category) use ($clothingCategoryIds): void {
            $category->catalog_section = in_array((int) $category->id, $clothingCategoryIds, true)
                ? 'clothing'
                : 'standard';
        });

        return view('store::admin.categories.index', compact('categories'));
    }

    public function categoryEdit(string $category): View
    {
        $locale = (string) app()->getLocale();
        $translationIds = $this->categoryTranslationIds($locale);
        $record = $this->database->table('store_categories as category')
            ->leftJoin('store_categories as parent', 'parent.id', '=', 'category.parent_id')
            ->joinSub($translationIds, 'selected_translation', fn ($join) => $join->on('selected_translation.category_id', '=', 'category.id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'selected_translation.translation_id')
            ->leftJoin('store_product_categories as product_category', 'product_category.category_id', '=', 'category.id')
            ->leftJoin('store_products as category_product', 'category_product.id', '=', 'product_category.product_id')
            ->leftJoin('store_external_sellables as category_owner', 'category_owner.product_id', '=', 'category_product.id')
            ->where('category.public_id', $category)
            ->groupBy('category.id', 'category.public_id', 'category.parent_id', 'parent.public_id', 'category.depth', 'category.position', 'category.status', 'category.is_featured', 'category.version', 'category.image_path', 'category.catalog_section', 'translation.name', 'translation.slug', 'translation.description')
            ->first([
                'category.id', 'category.public_id', 'category.parent_id', 'parent.public_id as parent_public_id', 'category.depth',
                'category.position', 'category.status', 'category.is_featured', 'category.version', 'category.image_path', 'category.catalog_section',
                'translation.name', 'translation.slug', 'translation.description',
                $this->database->raw("COUNT(DISTINCT CASE WHEN category_owner.id IS NULL AND category_product.type_code <> 'artwork' THEN category_product.id END) as products_count"),
            ]);

        abort_if($record === null, 404);
        $record->catalog_section = in_array((int) $record->id, $this->clothingCategoryIds(), true)
            ? 'clothing'
            : 'standard';

        $siblingIds = $this->database->table('store_categories')
            ->when(
                $record->parent_id === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $record->parent_id),
            )
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');
        $currentIndex = $siblingIds->search(fn ($id): bool => (int) $id === (int) $record->id);
        $record->display_position = $currentIndex === false ? 1 : $currentIndex + 1;
        $record->sibling_count = $siblingIds->count();

        $categories = $this->database->table('store_categories as category')
            ->joinSub($this->categoryTranslationIds($locale), 'selected_translation', fn ($join) => $join->on('selected_translation.category_id', '=', 'category.id'))
            ->join('store_category_translations as translation', 'translation.id', '=', 'selected_translation.translation_id')
            ->where('category.public_id', '!=', $category)
            ->where('category.status', '!=', 'archived')
            ->orderBy('category.depth')
            ->orderBy('category.position')
            ->get(['category.public_id', 'category.depth', 'translation.name']);

        return view('store::admin.categories.edit', compact('record', 'categories'));
    }

    public function brands(): View
    {
        $page = max(1, (int) request()->query('page', 1));
        $total = $this->database->table('store_brands')->where('status', '!=', 'archived')->count();
        $items = $this->database->table('store_brands as brand')
            ->leftJoin('store_products as product', 'product.brand_id', '=', 'brand.id')
            ->where('brand.status', '!=', 'archived')
            ->groupBy('brand.id', 'brand.public_id', 'brand.name', 'brand.slug', 'brand.description', 'brand.logo_path', 'brand.status', 'brand.position', 'brand.version')
            ->orderBy('brand.position')->orderBy('brand.name')
            ->forPage($page, 25)
            ->get([
                'brand.public_id', 'brand.name', 'brand.slug', 'brand.description', 'brand.logo_path',
                'brand.status', 'brand.position', 'brand.version',
                $this->database->raw('COUNT(product.id) as products_count'),
            ]);

        $brands = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, 25, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('store::admin.brands.index', compact('brands'));
    }

    public function brandEdit(string $brand): View
    {
        $record = $this->database->table('store_brands as brand')
            ->leftJoin('store_products as product', 'product.brand_id', '=', 'brand.id')
            ->where('brand.public_id', $brand)
            ->groupBy('brand.id', 'brand.public_id', 'brand.name', 'brand.slug', 'brand.description', 'brand.logo_path', 'brand.status', 'brand.position', 'brand.version')
            ->first([
                'brand.id', 'brand.public_id', 'brand.name', 'brand.slug', 'brand.description', 'brand.logo_path',
                'brand.status', 'brand.position', 'brand.version',
                $this->database->raw('COUNT(product.id) as products_count'),
            ]);

        abort_if($record === null, 404);

        $orderedIds = $this->database->table('store_brands')
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');
        $currentIndex = $orderedIds->search(fn ($id): bool => (int) $id === (int) $record->id);
        $record->display_position = $currentIndex === false ? 1 : $currentIndex + 1;
        $record->brands_count = $orderedIds->count();

        return view('store::admin.brands.edit', compact('record'));
    }

    public function attributes(Request $request): View
    {
        $locale = (string) app()->getLocale();
        $page = max(1, (int) $request->query('page', 1));
        $total = $this->database->table('store_attributes')->count();
        $items = $this->database->table('store_attributes as attribute')
            ->leftJoinSub($this->translations->attributes($locale), 'attribute_translation_choice', fn ($join) => $join->on('attribute_translation_choice.attribute_id', '=', 'attribute.id'))
            ->leftJoin('store_attribute_translations as translation', 'translation.id', '=', 'attribute_translation_choice.translation_id')
            ->leftJoin('store_attribute_options as option', 'option.attribute_id', '=', 'attribute.id')
            ->groupBy('attribute.id', 'attribute.public_id', 'attribute.code', 'attribute.data_type', 'attribute.status', 'attribute.supports_variant_axis', 'attribute.is_filterable', 'attribute.is_visible', 'translation.name')
            ->orderBy('attribute.code')
            ->forPage($page, 25)
            ->get(['attribute.id', 'attribute.public_id', 'attribute.code', 'attribute.data_type', 'attribute.status', 'attribute.supports_variant_axis', 'attribute.is_filterable', 'attribute.is_visible', 'translation.name', $this->database->raw('COUNT(option.id) as options_count')]);
        $attributes = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $optionsByAttribute = $this->database->table('store_attribute_options as option')
            ->leftJoinSub($this->translations->attributeOptions($locale), 'option_translation_choice', fn ($join) => $join->on('option_translation_choice.attribute_option_id', '=', 'option.id'))
            ->leftJoin('store_attribute_option_translations as translation', 'translation.id', '=', 'option_translation_choice.translation_id')
            ->whereIn('option.attribute_id', $attributes->getCollection()->pluck('id'))->orderBy('option.position')
            ->get(['option.attribute_id', 'option.public_id', 'option.code', 'option.status', 'option.position', 'translation.name'])
            ->groupBy('attribute_id');
        foreach ($attributes as $attribute) {
            $attribute->options = $optionsByAttribute->get($attribute->id, collect());
        }

        return view('store::admin.attributes.index', [
            'attributes' => $attributes,
        ]);
    }

    public function promotions(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $this->database->table('store_promotions')->count();
        $items = $this->database->table('store_promotions as promotion')
            ->leftJoin('store_promotion_targets as target', 'target.promotion_id', '=', 'promotion.id')
            ->leftJoin('store_promotion_actions as action', 'action.promotion_id', '=', 'promotion.id')
            ->groupBy('promotion.id', 'promotion.public_id', 'promotion.name', 'promotion.code', 'promotion.mode', 'promotion.status', 'promotion.priority', 'promotion.stacking_policy', 'promotion.version', 'promotion.starts_at', 'promotion.ends_at', 'action.action_type', 'action.amount_minor', 'action.percentage_basis_points')
            ->orderByDesc('promotion.priority')->orderByDesc('promotion.created_at')
            ->forPage($page, 25)
            ->get(['promotion.public_id', 'promotion.name', 'promotion.code', 'promotion.mode', 'promotion.status', 'promotion.priority', 'promotion.stacking_policy', 'promotion.version', 'promotion.starts_at', 'promotion.ends_at', 'action.action_type', 'action.amount_minor', 'action.percentage_basis_points', $this->database->raw('COUNT(target.id) as targets_count')]);

        $promotions = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('store::admin.promotions.index', ['promotions' => $promotions]);
    }

    public function inventory(Request $request): View
    {
        $locale = (string) app()->getLocale();
        $inventoryQuery = $this->database->table('store_inventory_items as item')
            ->join('store_product_variants as variant', 'variant.id', '=', 'item.variant_id')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->leftJoinSub($this->translations->products($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->leftJoin('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->where('product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')->from('store_external_sellables as inventory_owner')
                    ->whereColumn('inventory_owner.product_id', 'product.id');
            })
            ->orderBy('translation.name')
            ->orderBy('variant.position');
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $inventoryQuery->where(fn ($where) => $where
                ->where('translation.name', 'like', "%{$search}%")
                ->orWhere('variant.sku', 'like', "%{$search}%"));
        }
        $stock = (string) $request->query('stock');
        match ($stock) {
            'in_stock' => $inventoryQuery->where('item.tracking_mode', 'tracked')
                ->whereRaw('(item.on_hand - item.reserved) > 0'),
            'low_stock' => $inventoryQuery->where('item.tracking_mode', 'tracked')
                ->whereRaw('(item.on_hand - item.reserved) > 0')
                ->whereRaw('(item.on_hand - item.reserved) <= item.safety_stock'),
            'out_of_stock' => $inventoryQuery->where('item.tracking_mode', 'tracked')
                ->whereRaw('(item.on_hand - item.reserved) <= 0'),
            'untracked' => $inventoryQuery->where('item.tracking_mode', 'untracked'),
            default => null,
        };
        $columns = ['item.id', 'item.public_id', 'item.tracking_mode', 'item.on_hand', 'item.reserved', 'item.safety_stock', 'item.reorder_point', 'item.allow_backorder', 'item.version', 'variant.public_id as variant_public_id', 'variant.sku', 'translation.name as product_name'];
        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }
        $items = (clone $inventoryQuery)->paginate($perPage, $columns)->withQueryString();
        $selectedPublicId = (string) $request->query('item');
        $selectedItem = $selectedPublicId === ''
            ? $items->first()
            : (clone $inventoryQuery)->where('item.public_id', $selectedPublicId)->first($columns);
        $metrics = $this->database->table('store_inventory_items as metric_inventory')
            ->join('store_product_variants as metric_variant', 'metric_variant.id', '=', 'metric_inventory.variant_id')
            ->join('store_products as metric_product', 'metric_product.id', '=', 'metric_variant.product_id')
            ->where('metric_product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')->from('store_external_sellables as metric_owner')
                    ->whereColumn('metric_owner.product_id', 'metric_product.id');
            })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN metric_inventory.tracking_mode = 'untracked' OR (metric_inventory.on_hand - metric_inventory.reserved) > 0 THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN metric_inventory.tracking_mode = 'tracked' AND (metric_inventory.on_hand - metric_inventory.reserved) <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->selectRaw("SUM(CASE WHEN metric_inventory.tracking_mode = 'tracked' AND (metric_inventory.on_hand - metric_inventory.reserved) > 0 AND (metric_inventory.on_hand - metric_inventory.reserved) <= metric_inventory.safety_stock THEN 1 ELSE 0 END) as low_stock")
            ->first();

        return view('store::admin.inventory.index', [
            'items' => $items,
            'selectedItem' => $selectedItem,
            'filters' => $request->only(['q', 'stock', 'per_page']),
            'inventoryMetrics' => [
                'total' => (int) ($metrics->total ?? 0),
                'available' => (int) ($metrics->available ?? 0),
                'out_of_stock' => (int) ($metrics->out_of_stock ?? 0),
                'low_stock' => (int) ($metrics->low_stock ?? 0),
            ],
            'movements' => $selectedItem === null
                ? collect()
                : $this->database->table('store_inventory_movements')->where('inventory_item_id', $selectedItem->id)->orderByDesc('occurred_at')->limit(100)->get(),
        ]);
    }

    private function storeOwnedProducts(): \Illuminate\Database\Query\Builder
    {
        return $this->database->table('store_products as product')
            ->where('product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($external): void {
                $external->selectRaw('1')->from('store_external_sellables as product_owner')
                    ->whereColumn('product_owner.product_id', 'product.id');
            });
    }

    private function isExternallyOwnedProduct(int $productId): bool
    {
        return $this->database->table('store_external_sellables')
            ->where('product_id', $productId)
            ->exists();
    }

    private function categoryTranslationIds(string $locale): \Illuminate\Database\Query\Builder
    {
        return $this->translations->categories($locale);
    }

    /** @return list<int> */
    private function clothingCategoryIds(): array
    {
        $categories = $this->database->table('store_categories')
            ->where('status', '!=', 'archived')
            ->get(['id', 'parent_id', 'catalog_section']);
        $ids = $categories
            ->filter(fn (object $category): bool => $category->catalog_section === 'clothing')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        do {
            $before = count($ids);
            foreach ($categories as $category) {
                if ($category->parent_id !== null
                    && in_array((int) $category->parent_id, $ids, true)
                    && ! in_array((int) $category->id, $ids, true)) {
                    $ids[] = (int) $category->id;
                }
            }
        } while (count($ids) !== $before);

        return $ids;
    }

    /** @return list<string> */
    private function productTagSuggestions(): array
    {
        return $this->database->table('store_products')
            ->where('status', '!=', 'deleted')
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(static fn (mixed $tags): array => json_decode((string) $tags, true) ?: [])
            ->map(static fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique(static fn (string $tag): string => mb_strtolower($tag, 'UTF-8'))
            ->sort(static fn (string $left, string $right): int => strnatcasecmp($left, $right))
            ->take(100)
            ->values()
            ->all();
    }
}
