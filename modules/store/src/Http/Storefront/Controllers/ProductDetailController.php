<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Reviews\ProductReviewService;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Modules\Store\Application\Storefront\StorefrontLocale;

final class ProductDetailController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalogService $catalog,
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
        private readonly ProductReviewService $reviews,
    ) {}

    public function __invoke(Request $request, string $slug): View|Response|RedirectResponse
    {
        $this->locale->apply();
        $product = $this->catalog->product($slug);
        abort_if($product === null, 404);
        if (($product['source_owner'] ?? null) === 'exhibitions') {
            return redirect()->to((string) $product['detail_url']);
        }
        $variantPublicIds = collect($product['variants'])
            ->pluck('public_id')
            ->push($product['default_variant']['public_id'])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $view = $product['type_code'] === 'artwork'
            ? 'store::storefront.artwork'
            : 'store::storefront.product';

        return view($view, [
            'product' => $product,
            'reviews' => $this->reviews->forProduct((int) $product['id']),
            'cartCount' => $this->cart->count($request->session()),
            'cartCapacities' => $this->cart->variantCapacities($request->session(), $variantPublicIds),
        ]);
    }
}
