<?php

namespace Modules\Store\Application\PageBuilder;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Modules\Store\Application\Templates\ProductTemplateRenderer;
use Modules\Store\Models\StoreProductTemplate;

final readonly class PageBuilderExtension
{
    private const PRODUCTS_PATTERN = '/<div\b(?=[^>]*\bdata-store-products\s*=)[^>]*>[\s\S]*?<\/div>/i';
    private const CATEGORIES_PATTERN = '/<div\b(?=[^>]*\bdata-store-categories\s*=)[^>]*>[\s\S]*?<\/div>/i';
    private const BRANDS_PATTERN = '/<div\b(?=[^>]*\bdata-store-brands\s*=)[^>]*>[\s\S]*?<\/div>/i';
    private const CATALOG_PATTERN = '/<div\b(?=[^>]*\bdata-store-catalog\s*=)[^>]*>[\s\S]*?<\/div>/i';

    public function __construct(
        private StorefrontCatalogService $catalog,
        private ProductTemplateRenderer $renderer,
        private CartService $cart,
    ) {}

    /** @param array<int, array<string, mixed>> $extensions */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $catalog = $this->catalog->browse(['per_page' => 24, 'page' => 1, 'sort' => 'newest', 'exclude_artwork' => true]);
        $products = $catalog['products']->getCollection();
        $categories = $catalog['categories']->filter(fn (array $item): bool => $item['products_count'] > 0)->values();
        $brands = $catalog['brands']->filter(fn (array $item): bool => $item['products_count'] > 0)->values();
        $templates = $this->templatesForEditor($products);
        $extensions[] = [
            'id' => 'store.catalog-elements',
            'config' => [
                'productsPreviewHtml' => $this->productCardsHtml($products),
                'categoriesPreviewHtml' => $this->categoryCardsHtml($categories),
                'brandsPreviewHtml' => $this->brandCardsHtml($brands),
                'templates' => $templates,
                'defaultTemplateSlug' => collect($templates)->firstWhere('isDefault', true)['slug'] ?? ($templates[0]['slug'] ?? ''),
                'hasProducts' => $products->isNotEmpty(),
                'hasCategories' => $categories->isNotEmpty(),
                'hasBrands' => $brands->isNotEmpty(),
                'categories' => $categories->map(fn (array $item): array => ['slug' => $item['slug'], 'name' => $item['name']])->all(),
                'brands' => $brands->map(fn (array $item): array => ['slug' => $item['slug'], 'name' => $item['name']])->all(),
            ],
            'scripts' => [url('/platform/plugins/store/js/vvveb-store-elements.js?v=12.10.2')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady()) {
            return $html;
        }

        if (str_contains($html, 'data-store-catalog')) {
            $html = preg_replace_callback(self::CATALOG_PATTERN, fn (array $match): string => $this->renderCatalog($match[0]), $html) ?? $html;
        }
        if (str_contains($html, 'data-store-products')) {
            $html = preg_replace_callback(self::PRODUCTS_PATTERN, fn (array $match): string => $this->renderProducts($match[0]), $html) ?? $html;
        }
        if (str_contains($html, 'data-store-categories')) {
            $html = preg_replace_callback(self::CATEGORIES_PATTERN, fn (array $match): string => $this->renderCategories($match[0]), $html) ?? $html;
        }
        if (str_contains($html, 'data-store-brands')) {
            $html = preg_replace_callback(self::BRANDS_PATTERN, fn (array $match): string => $this->renderBrands($match[0]), $html) ?? $html;
        }

        return $html;
    }

    private function renderCatalog(string $slot): string
    {
        $attributes = $this->attributes($slot, 'data-store-catalog');
        $query = request()->query();
        $columns = $this->allowedInteger($query['columns'] ?? null, [3, 4, 5])
            ?? $this->number($attributes, 'data-store-catalog-columns', 4, 3, 5);
        $perPage = $this->allowedInteger($query['per_page'] ?? null, [12, 24, 48])
            ?? $this->allowedInteger($attributes['data-store-catalog-per-page'] ?? null, [12, 24, 48])
            ?? 24;
        $sort = $this->allowedString($query['sort'] ?? null, [
            'featured', 'newest', 'price_asc', 'price_desc', 'name', 'name_asc', 'name_desc', 'relevance',
        ]) ?? 'newest';
        $filters = [
            'q' => $this->limitedString($query['q'] ?? null, 120),
            'category' => $this->limitedString($query['category'] ?? null, 255),
            'brand' => $this->limitedString($query['brand'] ?? null, 255),
            'tag' => $this->limitedString($query['tag'] ?? null, 120),
            'min_price' => $this->decimal($query['min_price'] ?? null),
            'max_price' => $this->decimal($query['max_price'] ?? null),
            'in_stock' => filter_var($query['in_stock'] ?? false, FILTER_VALIDATE_BOOL),
            'on_sale' => filter_var($query['on_sale'] ?? false, FILTER_VALIDATE_BOOL),
            'featured' => filter_var($query['featured'] ?? false, FILTER_VALIDATE_BOOL),
            'sort' => $sort,
            'columns' => $columns,
            'per_page' => $perPage,
            'page' => max(1, min(100000, (int) ($query['page'] ?? 1))),
            'exclude_artwork' => true,
        ];
        $result = $this->catalog->browse($filters);
        $variantPublicIds = $result['products']->getCollection()
            ->pluck('variant_public_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $request = request();
        $session = $request->hasSession()
            ? $request->session()
            : app('session')->driver();

        return view('store::storefront.page-builder-catalog', [
            ...$result,
            'filters' => $filters,
            'columns' => $columns,
            'perPage' => $perPage,
            'showFilters' => ($attributes['data-store-catalog-filters'] ?? '1') !== '0',
            'showSort' => ($attributes['data-store-catalog-sort'] ?? '1') !== '0',
            'cartCount' => $this->cart->count($session),
            'cartCapacities' => $this->cart->variantCapacities($session, $variantPublicIds),
        ])->render();
    }

    private function renderProducts(string $slot): string
    {
        $attributes = $this->attributes($slot, 'data-store-products');
        $limit = max(1, min(24, (int) ($attributes['data-store-products-limit'] ?? 8)));
        $source = (string) ($attributes['data-store-products-source'] ?? 'all');
        $order = (string) ($attributes['data-store-products-order'] ?? 'newest');
        $templateSlug = trim((string) ($attributes['data-store-products-template'] ?? ''));
        $template = $templateSlug === '' || ! $this->templatesReady()
            ? null
            : StoreProductTemplate::query()->active()->where('slug', $templateSlug)->first();
        $instance = $this->instance($attributes, 'data-store-products-instance', 'store-products');
        $paginationEnabled = ($attributes['data-store-products-pagination'] ?? '0') === '1' && $order !== 'random';
        $pageName = 'store_page_'.str_replace('-', '_', $instance);
        $page = $paginationEnabled ? max(1, (int) request()->query($pageName, 1)) : 1;
        $filters = ['per_page' => $order === 'random' ? 48 : $limit, 'page' => $page, 'sort' => $order === 'random' ? 'newest' : $order, 'exclude_artwork' => true];
        if ($source === 'featured') $filters['featured'] = true;
        if ($source === 'in-stock') $filters['in_stock'] = true;
        if ($source === 'on-sale') $filters['on_sale'] = true;
        if ($source === 'standard') $filters['exclude_artwork'] = true;
        $category = $source === 'category' ? trim((string) ($attributes['data-store-products-category'] ?? '')) : null;
        $brand = $source === 'brand' ? trim((string) ($attributes['data-store-products-brand'] ?? '')) : null;
        $paginator = $this->catalog->browse($filters, $category ?: null, $brand ?: null, false)['products'];
        $paginator->setPageName($pageName)->withQueryString();
        $products = $paginator->getCollection();
        if ($order === 'random') $products = $products->shuffle()->take($limit)->values();
        if ($products->isEmpty()) return '';

        if (! $template) {
            return '<section class="store-products" data-store-products-rendered="'.e($instance).'">'
                .$this->productStyles($instance, $attributes)
                .'<div class="store-products-items">'.$this->productCardsHtml($products).'</div></section>';
        }

        $layout = $this->productLayout($attributes);
        $title = match ($source) {
            'featured' => 'Featured products',
            'in-stock' => 'Available products',
            'on-sale' => 'Products on sale',
            'category' => $category ?: 'Category products',
            'brand' => $brand ?: 'Brand products',
            default => 'Products',
        };
        $pagination = $paginationEnabled ? $this->paginationHtml($paginator, $instance) : '';

        return '<section class="store-products" data-store-products-rendered="'.e($instance).'" data-store-template-layout="'.e($layout['mode']).'">'
            .$this->templateLayoutStyles($instance, $layout, $attributes)
            .$this->renderer->render($template, $products, ['catalog_title' => $title], ['wrap_products' => $layout['mode'] !== 'template'])
            .$pagination
            .'</section>';
    }

    private function renderCategories(string $slot): string
    {
        $attributes = $this->attributes($slot, 'data-store-categories');
        $limit = max(1, min(24, (int) ($attributes['data-store-categories-limit'] ?? 6)));
        $order = (string) ($attributes['data-store-categories-order'] ?? 'featured');
        $source = (string) ($attributes['data-store-categories-source'] ?? 'with-products');
        $categories = $this->catalogData()['categories'];
        if ($source === 'with-products') $categories = $categories->filter(fn (array $item): bool => $item['products_count'] > 0);
        if ($source === 'root') $categories = $categories->filter(fn (array $item): bool => $item['parent_id'] === null);
        $categories = match ($order) {
            'popular' => $categories->sortByDesc('products_count'),
            'name' => $categories->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'random' => $categories->shuffle(),
            default => $categories->sort(function (array $left, array $right): int {
                $featured = ((int) $right['is_featured']) <=> ((int) $left['is_featured']);

                return $featured !== 0 ? $featured : strnatcasecmp((string) $left['name'], (string) $right['name']);
            }),
        };
        $categories = $categories->take($limit)->values();
        if ($categories->isEmpty()) return '';
        $instance = $this->instance($attributes, 'data-store-categories-instance', 'store-categories');

        return '<section class="store-categories" data-store-categories-rendered="'.e($instance).'">'
            .$this->taxonomyStyles($instance, $attributes, 'category')
            .'<div class="store-taxonomy-items">'.$this->categoryCardsHtml($categories).'</div></section>';
    }

    private function renderBrands(string $slot): string
    {
        $attributes = $this->attributes($slot, 'data-store-brands');
        $limit = max(1, min(24, (int) ($attributes['data-store-brands-limit'] ?? 6)));
        $order = (string) ($attributes['data-store-brands-order'] ?? 'popular');
        $brands = $this->catalogData()['brands']->filter(fn (array $item): bool => $item['products_count'] > 0);
        $brands = match ($order) {
            'name' => $brands->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'random' => $brands->shuffle(),
            default => $brands->sortByDesc('products_count'),
        };
        $brands = $brands->take($limit)->values();
        if ($brands->isEmpty()) return '';
        $instance = $this->instance($attributes, 'data-store-brands-instance', 'store-brands');

        return '<section class="store-brands" data-store-brands-rendered="'.e($instance).'">'
            .$this->taxonomyStyles($instance, $attributes, 'brand')
            .'<div class="store-taxonomy-items">'.$this->brandCardsHtml($brands).'</div></section>';
    }

    /** @return array{categories:Collection<int,array<string,mixed>>,brands:Collection<int,array<string,mixed>>} */
    private function catalogData(): array
    {
        $result = $this->catalog->browse(['per_page' => 1, 'page' => 1, 'sort' => 'newest', 'exclude_artwork' => true]);

        return ['categories' => $result['categories'], 'brands' => $result['brands']];
    }

    /** @param Collection<int,array<string,mixed>> $products @return array<int,array<string,mixed>> */
    private function templatesForEditor(Collection $products): array
    {
        if (! $this->templatesReady()) {
            return [];
        }

        return StoreProductTemplate::query()
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (StoreProductTemplate $template): array => [
                'name' => $template->name,
                'slug' => $template->slug,
                'category' => $template->category,
                'isDefault' => $template->system_key === 'products',
                'previewHtml' => $this->renderer->render(
                    $template,
                    $products->take(8),
                    ['catalog_title' => 'Template preview'],
                    ['include_script' => false, 'wrap_products' => true],
                ),
            ])
            ->values()
            ->all();
    }

    /** @return array{mode:string,desktop:int,tablet:int,mobile:int,gap:int} */
    private function productLayout(array $attributes): array
    {
        $mode = (string) ($attributes['data-store-products-layout'] ?? 'grid');

        return [
            'mode' => in_array($mode, ['template', 'grid', 'cards', 'slider'], true) ? $mode : 'grid',
            'desktop' => $this->number($attributes, 'data-store-products-columns-desktop', 4, 1, 6),
            'tablet' => $this->number($attributes, 'data-store-products-columns-tablet', 2, 1, 4),
            'mobile' => $this->number($attributes, 'data-store-products-columns-mobile', 1, 1, 2),
            'gap' => $this->number($attributes, 'data-store-products-gap', 24, 0, 120),
        ];
    }

    /** @param array{mode:string,desktop:int,tablet:int,mobile:int,gap:int} $layout */
    private function templateLayoutStyles(string $instance, array $layout, array $attributes): string
    {
        $selector = '[data-store-products-rendered="'.e($instance).'"]';
        $hidden = [];
        if (($attributes['data-store-products-show-meta'] ?? '1') === '0') $hidden[] = '.store-product-meta';
        if (($attributes['data-store-products-show-price'] ?? '1') === '0') $hidden[] = '.store-product-price';
        if (($attributes['data-store-products-show-stock'] ?? '1') === '0') $hidden[] = '.store-product-stock';
        if (($attributes['data-store-products-show-badge'] ?? '1') === '0') $hidden[] = '.store-product-badge';
        $hide = $hidden === [] ? '' : $selector.' '.implode(','.$selector.' ', $hidden).'{display:none!important}';
        $fit = ($attributes['data-store-products-image-fit'] ?? 'contain') === 'cover' ? 'cover' : 'contain';
        $image = $selector.' img{object-fit:'.$fit.'}';

        if ($layout['mode'] === 'template') {
            return '<style data-store-template-layout-styles>'.$image.$hide.'</style>';
        }

        $gap = $layout['gap'];
        if ($layout['mode'] === 'slider') {
            $desktop = 'grid-auto-columns:calc((100% - '.(($layout['desktop'] - 1) * $gap).'px)/'.$layout['desktop'].');grid-template-columns:none;grid-auto-flow:column;overflow-x:auto;scroll-snap-type:x mandatory';
            $tablet = 'grid-auto-columns:calc((100% - '.(($layout['tablet'] - 1) * $gap).'px)/'.$layout['tablet'].')';
            $mobile = 'grid-auto-columns:calc((100% - '.(($layout['mobile'] - 1) * $gap).'px)/'.$layout['mobile'].')';
        } else {
            $desktop = 'grid-template-columns:repeat('.$layout['desktop'].',minmax(0,1fr))';
            $tablet = 'grid-template-columns:repeat('.$layout['tablet'].',minmax(0,1fr))';
            $mobile = 'grid-template-columns:repeat('.$layout['mobile'].',minmax(0,1fr))';
        }

        $cards = $layout['mode'] === 'cards'
            ? $selector.' [data-store-template-items]>*{overflow:hidden;border:1px solid #eadada;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(50,20,20,.08)}'
            : '';

        return '<style data-store-template-layout-styles>'
            .$selector.' [data-store-template-items]{display:grid;'.$desktop.';gap:'.$gap.'px;width:100%;grid-column:1/-1;flex:0 0 100%}'
            .$selector.' [data-store-template-items]>*{min-width:0;scroll-snap-align:start}'
            .$cards.$image.$hide
            .'@media(max-width:991px){'.$selector.' [data-store-template-items]{'.$tablet.'}}'
            .'@media(max-width:575px){'.$selector.' [data-store-template-items]{'.$mobile.'}}'
            .'</style>';
    }

    private function paginationHtml(LengthAwarePaginator $paginator, string $instance): string
    {
        if ($paginator->lastPage() <= 1) {
            return '';
        }

        $links = $paginator->onFirstPage()
            ? '<span class="is-disabled" aria-disabled="true">‹</span>'
            : '<a href="'.e((string) $paginator->previousPageUrl()).'" aria-label="Previous page">‹</a>';
        $first = max(1, $paginator->currentPage() - 2);
        $last = min($paginator->lastPage(), $paginator->currentPage() + 2);
        if ($first > 1) {
            $links .= '<a href="'.e($paginator->url(1)).'">1</a>'.($first > 2 ? '<span class="is-ellipsis">…</span>' : '');
        }
        foreach ($paginator->getUrlRange($first, $last) as $page => $url) {
            $links .= $page === $paginator->currentPage()
                ? '<span class="is-current" aria-current="page">'.e((string) $page).'</span>'
                : '<a href="'.e($url).'">'.e((string) $page).'</a>';
        }
        if ($last < $paginator->lastPage()) {
            $links .= $last < $paginator->lastPage() - 1 ? '<span class="is-ellipsis">…</span>' : '';
            $links .= '<a href="'.e($paginator->url($paginator->lastPage())).'">'.e((string) $paginator->lastPage()).'</a>';
        }
        $links .= $paginator->hasMorePages()
            ? '<a href="'.e((string) $paginator->nextPageUrl()).'" aria-label="Next page">›</a>'
            : '<span class="is-disabled" aria-disabled="true">›</span>';

        return '<nav class="store-template-pagination" data-store-template-pagination="'.e($instance).'" aria-label="Store products pagination">'
            .'<style>.store-template-pagination{display:flex;align-items:center;justify-content:center;gap:.4rem;margin:1.5rem 0;flex-wrap:wrap}.store-template-pagination a,.store-template-pagination>span{display:inline-flex;min-width:2.25rem;height:2.25rem;align-items:center;justify-content:center;padding:0 .65rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;color:#374151;font:600 .875rem/1 system-ui,sans-serif;text-decoration:none}.store-template-pagination a:hover{border-color:#991b1b;color:#991b1b}.store-template-pagination .is-current{border-color:#991b1b;background:#991b1b;color:#fff}.store-template-pagination .is-disabled{opacity:.42}.store-template-pagination .is-ellipsis{border-color:transparent;background:transparent}</style>'
            .$links.'</nav>';
    }

    /** @param Collection<int, array<string,mixed>> $products */
    private function productCardsHtml(Collection $products): string
    {
        return $products->map(function (array $product): string {
            $url = route('store.products.show', ['slug' => $product['slug']], false);
            $image = $product['image']
                ? '<img src="'.e((string) $product['image']['url']).'" alt="'.e((string) ($product['image']['alt'] ?: $product['name'])).'" loading="lazy">'
                : '<span class="store-product-placeholder">PRODUCT</span>';
            $meta = $product['brand_name'] ?: ($product['categories']->first()['name'] ?? 'Store');
            $badge = $product['on_sale'] ? 'Sale' : ($product['is_featured'] ? 'Featured' : '');

            return '<article class="store-product-card" data-product-id="'.e($product['public_id']).'">'
                .'<a class="store-product-media" href="'.e($url).'">'.$image.($badge !== '' ? '<span class="store-product-badge">'.e($badge).'</span>' : '').'</a>'
                .'<div class="store-product-body"><p class="store-product-meta">'.e((string) $meta).'<span class="store-product-stock '.($product['available'] ? 'is-available' : 'is-unavailable').'">'.($product['available'] ? 'In stock' : 'Out of stock').'</span></p>'
                .'<h3><a href="'.e($url).'">'.e($product['name']).'</a></h3><div class="store-product-footer"><strong class="store-product-price">'.e($product['price_formatted']).'</strong><a href="'.e($url).'">View product</a></div></div>'
                .'</article>';
        })->implode('');
    }

    /** @param Collection<int, array<string,mixed>> $categories */
    private function categoryCardsHtml(Collection $categories): string
    {
        return $categories->map(function (array $category): string {
            $url = route('store.categories.show', ['slug' => $category['slug']], false);
            $media = $category['image_url'] ? '<img src="'.e($category['image_url']).'" alt="'.e($category['name']).'" loading="lazy">' : '<span>C</span>';

            return '<article class="store-taxonomy-card"><a class="store-taxonomy-media" href="'.e($url).'">'.$media.'</a><div><h3><a href="'.e($url).'">'.e($category['name']).'</a></h3><p class="store-taxonomy-description">'.e((string) ($category['description'] ?: 'Browse this store category.')).'</p><a class="store-taxonomy-count" href="'.e($url).'">'.e((string) $category['products_count']).' products</a></div></article>';
        })->implode('');
    }

    /** @param Collection<int, array<string,mixed>> $brands */
    private function brandCardsHtml(Collection $brands): string
    {
        return $brands->map(function (array $brand): string {
            $url = route('store.brands.show', ['slug' => $brand['slug']], false);
            $media = $brand['logo_url'] ? '<img src="'.e($brand['logo_url']).'" alt="'.e($brand['name']).'" loading="lazy">' : '<span>B</span>';

            return '<article class="store-taxonomy-card"><a class="store-taxonomy-media" href="'.e($url).'">'.$media.'</a><div><h3><a href="'.e($url).'">'.e($brand['name']).'</a></h3><p class="store-taxonomy-description">'.e((string) ($brand['description'] ?: 'Browse products from this brand.')).'</p><a class="store-taxonomy-count" href="'.e($url).'">'.e((string) $brand['products_count']).' products</a></div></article>';
        })->implode('');
    }

    private function productStyles(string $instance, array $attributes): string
    {
        $s = '[data-store-products-rendered="'.e($instance).'"]'; $d=$this->number($attributes,'data-store-products-columns-desktop',4,1,6); $t=$this->number($attributes,'data-store-products-columns-tablet',2,1,4); $m=$this->number($attributes,'data-store-products-columns-mobile',1,1,2); $g=$this->number($attributes,'data-store-products-gap',24,0,120); $fit=($attributes['data-store-products-image-fit']??'contain')==='cover'?'cover':'contain';
        $hidden=[]; if(($attributes['data-store-products-show-meta']??'1')==='0')$hidden[]='.store-product-meta'; if(($attributes['data-store-products-show-price']??'1')==='0')$hidden[]='.store-product-price'; if(($attributes['data-store-products-show-stock']??'1')==='0')$hidden[]='.store-product-stock'; if(($attributes['data-store-products-show-badge']??'1')==='0')$hidden[]='.store-product-badge'; $hide=$hidden===[]?'':$s.' '.implode(','.$s.' ',$hidden).'{display:none}';
        return '<style data-store-products-styles>'.$s.' .store-products-items{display:grid;grid-template-columns:repeat('.$d.',minmax(0,1fr));gap:'.$g.'px}'.$s.' .store-product-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a}'.$s.' .store-product-media{position:relative;display:flex;aspect-ratio:4/3;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;text-decoration:none}'.$s.' .store-product-media img{width:100%;height:100%;object-fit:'.$fit.'}'.$s.' .store-product-placeholder{color:#94a3b8;font:800 1.2rem/1 system-ui,sans-serif}'.$s.' .store-product-badge{position:absolute;inset-block-start:10px;inset-inline-end:10px;border-radius:999px;background:#991b1b;padding:5px 9px;color:#fff;font:700 11px/1 system-ui,sans-serif}'.$s.' .store-product-body{display:grid;gap:10px;padding:16px}'.$s.' .store-product-meta{display:flex;justify-content:space-between;margin:0;color:#64748b;font:600 12px/1.4 system-ui,sans-serif}'.$s.' .store-product-stock.is-available{color:#166534}'.$s.' .store-product-stock.is-unavailable{color:#991b1b}'.$s.' h3{margin:0;font:700 1rem/1.4 system-ui,sans-serif}'.$s.' h3 a{color:inherit;text-decoration:none}'.$s.' .store-product-footer{display:flex;align-items:center;justify-content:space-between;gap:12px}'.$s.' .store-product-footer a{color:#991b1b;font:700 12px/1.4 system-ui,sans-serif}'.$hide.'@media(max-width:991px){'.$s.' .store-products-items{grid-template-columns:repeat('.$t.',minmax(0,1fr))}}@media(max-width:575px){'.$s.' .store-products-items{grid-template-columns:repeat('.$m.',minmax(0,1fr))}}</style>';
    }

    private function taxonomyStyles(string $instance, array $attributes, string $type): string
    {
        $rendered=$type==='category'?'data-store-categories-rendered':'data-store-brands-rendered'; $s='['.$rendered.'="'.e($instance).'"]'; $key=$type==='category'?'data-store-categories-':'data-store-brands-'; $d=$this->number($attributes,$key.'columns-desktop',3,1,6);$t=$this->number($attributes,$key.'columns-tablet',2,1,4);$m=$this->number($attributes,$key.'columns-mobile',1,1,2);$g=$this->number($attributes,$key.'gap',20,0,120);$fit=($attributes[$key.'image-fit']??'contain')==='cover'?'cover':'contain';$showDescription=($attributes[$key.'show-description']??'1')!=='0';$showCount=($attributes[$key.'show-count']??'1')!=='0';
        return '<style data-store-taxonomy-styles>'.$s.' .store-taxonomy-items{display:grid;grid-template-columns:repeat('.$d.',minmax(0,1fr));gap:'.$g.'px}'.$s.' .store-taxonomy-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a}'.$s.' .store-taxonomy-media{display:flex;aspect-ratio:16/9;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;color:#94a3b8;font:800 2rem/1 system-ui,sans-serif;text-decoration:none}'.$s.' .store-taxonomy-media img{width:100%;height:100%;object-fit:'.$fit.'}'.$s.' .store-taxonomy-card>div{display:grid;gap:8px;padding:16px}'.$s.' h3,'.$s.' p{margin:0}'.$s.' h3{font:700 1rem/1.4 system-ui,sans-serif}'.$s.' a{color:inherit;text-decoration:none}'.$s.' .store-taxonomy-description{color:#64748b;font:400 13px/1.5 system-ui,sans-serif}'.($showDescription?'':$s.' .store-taxonomy-description{display:none}').$s.' .store-taxonomy-count{color:#991b1b;font:700 12px/1.4 system-ui,sans-serif}'.($showCount?'':$s.' .store-taxonomy-count{display:none}').'@media(max-width:991px){'.$s.' .store-taxonomy-items{grid-template-columns:repeat('.$t.',minmax(0,1fr))}}@media(max-width:575px){'.$s.' .store-taxonomy-items{grid-template-columns:repeat('.$m.',minmax(0,1fr))}}</style>';
    }

    /** @return array<string,string> */
    private function attributes(string $slot, string $prefix): array
    {
        preg_match('/^<div\b([^>]*)>/i',$slot,$opening); preg_match_all('/\b('.preg_quote($prefix,'/').'[a-z0-9-]*)\s*=\s*(["\'])(.*?)\2/is',(string)($opening[1]??''),$matches,PREG_SET_ORDER);
        return collect($matches)->mapWithKeys(fn(array $match):array=>[strtolower($match[1])=>html_entity_decode($match[3],ENT_QUOTES|ENT_HTML5,'UTF-8')])->all();
    }
    private function instance(array $attributes,string $key,string $prefix):string{$value=strtolower((string)($attributes[$key]??''));$value=trim((string)preg_replace('/[^a-z0-9_-]+/','-',$value),'-');return $value!==''?substr($value,0,80):$prefix.'-'.substr(sha1((string)json_encode($attributes)),0,12);}
    private function number(array $attributes,string $key,int $fallback,int $min,int $max):int{return max($min,min($max,(int)($attributes[$key]??$fallback)));}
    private function allowedInteger(mixed $value,array $allowed):?int{$value=filter_var($value,FILTER_VALIDATE_INT);return $value!==false&&in_array($value,$allowed,true)?$value:null;}
    private function allowedString(mixed $value,array $allowed):?string{$value=is_scalar($value)?trim((string)$value):'';return in_array($value,$allowed,true)?$value:null;}
    private function limitedString(mixed $value,int $length):?string{$value=is_scalar($value)?trim((string)$value):'';return $value===''?null:mb_substr($value,0,$length);}
    private function decimal(mixed $value):?float{if(!is_scalar($value)||trim((string)$value)===''||!is_numeric($value))return null;return max(0,min(9999999,(float)$value));}
    private function templatesReady():bool{return Schema::hasTable('store_product_templates')&&Route::has('store.template-script');}
    private function isReady():bool{return Route::has('store.products.show')&&Route::has('store.products.quick-view')&&Route::has('store.categories.show')&&Route::has('store.brands.show')&&collect(['store_products','store_product_translations','store_product_variants','store_prices'])->every(fn(string $table):bool=>Schema::hasTable($table));}
}
