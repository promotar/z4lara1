<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Modules\Store\Application\Storefront\StorefrontLocale;

final class ProductQuickViewController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalogService $catalog,
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $this->locale->apply();
        $product = $this->catalog->product($slug);
        abort_if($product === null, 404);
        abort_if(($product['source_owner'] ?? null) === 'exhibitions', 404);

        $variantIds = collect($product['variants'])->pluck('public_id')->filter()->values()->all();

        return view('store::storefront.partials.quick-view', [
            'product' => $product,
            'cartCapacities' => $this->cart->variantCapacities($request->session(), $variantIds),
        ]);
    }
}
