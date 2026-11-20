<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\OrderPlacementService;
use Modules\Store\Application\Storefront\StorefrontLocale;

final class OrderConfirmationController extends Controller
{
    public function __construct(
        private readonly OrderPlacementService $orders,
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
    ) {}

    public function __invoke(Request $request, string $order): View
    {
        $this->locale->apply();
        abort_unless(hash_equals((string) $request->session()->get('store.last_order', ''), $order), 404);
        $confirmation = $this->orders->confirmation($order, (int) $request->user()->id);
        abort_if($confirmation === null, 404);

        return view('store::storefront.confirmation', [
            'order' => $confirmation,
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }
}
