<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Modules\Store\Application\Storefront\StorefrontLocale;

final class StorefrontController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalogService $catalog,
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
    ) {}

    public function index(Request $request): View
    {
        return $this->render($request, 'shop');
    }

    public function search(Request $request): View
    {
        return $this->render($request, 'search');
    }

    public function category(Request $request, string $slug): View
    {
        return $this->render($request, 'category', $slug);
    }

    public function brand(Request $request, string $slug): View
    {
        return $this->render($request, 'brand', null, $slug);
    }

    private function render(
        Request $request,
        string $mode,
        ?string $categorySlug = null,
        ?string $brandSlug = null,
    ): View {
        $this->locale->apply();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'section' => ['nullable', 'in:products,clothing,artworks'],
            'sort' => ['nullable', 'in:featured,newest,price_asc,price_desc,name,name_asc,name_desc,relevance'],
            'columns' => ['nullable', 'integer', 'in:3,4,5'],
            'per_page' => ['nullable', 'integer', 'in:12,24,48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($mode === 'search' && trim((string) ($filters['q'] ?? '')) === '') {
            $filters['q'] = trim((string) $request->query('q', ''));
        }
        $filters['catalog_section'] = $filters['section'] ?? null;

        $result = $this->catalog->browse($filters, $categorySlug, $brandSlug);
        $currentCategory = $categorySlug === null
            ? null
            : $result['categories']->firstWhere('slug', $categorySlug);
        $currentBrand = $brandSlug === null
            ? null
            : $result['brands']->firstWhere('slug', $brandSlug);

        abort_if($categorySlug !== null && $currentCategory === null, 404);
        abort_if($brandSlug !== null && $currentBrand === null, 404);

        $subcategories = $currentCategory === null
            ? collect()
            : $result['categories']
                ->filter(fn (array $category): bool => $category['parent_id'] === $currentCategory['id'] && $category['products_count'] > 0)
                ->values();

        $filters['columns'] = (int) ($filters['columns'] ?? 4);
        $filters['per_page'] = (int) ($filters['per_page'] ?? config('store.storefront.products_per_page', 24));
        $featuredProducts = $mode === 'shop'
            ? $this->catalog->browse([
                'featured' => true,
                'per_page' => (int) config('store.storefront.featured_limit', 8),
                'page' => 1,
                'catalog_section' => $filters['catalog_section'],
            ], includeFacets: false)['products']->getCollection()
            : collect();
        $variantPublicIds = $result['products']->getCollection()
            ->concat($featuredProducts)
            ->pluck('variant_public_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('store::storefront.index', [
            ...$result,
            'filters' => $filters,
            'mode' => $mode,
            'currentCategory' => $currentCategory,
            'currentBrand' => $currentBrand,
            'subcategories' => $subcategories,
            'featuredProducts' => $featuredProducts,
            'cartCount' => $this->cart->count($request->session()),
            'cartCapacities' => $this->cart->variantCapacities($request->session(), $variantPublicIds),
        ]);
    }
}
