<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Storefront\CartQuantityLimitExceeded;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontLocale;
use RuntimeException;

final class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
    ) {}

    public function index(Request $request): View
    {
        $this->locale->apply();

        return view('store::storefront.cart', [
            'cart' => $this->cart->snapshot($request->session(), $request->user()?->id),
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json([
            'cart_count' => $this->cart->count($request->session()),
            'quantities' => $this->cart->quantities($request->session()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->locale->apply();
        $data = $request->validate([
            'variant_public_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $this->cart->add($request->session(), $data['variant_public_id'], (int) $data['quantity']);
        } catch (CartQuantityLimitExceeded $exception) {
            $message = __('store::ui.store_auto_cd49f1c0ff', [
                'cart' => $exception->cartQuantity,
                'maximum' => $exception->maximumQuantity,
            ]);
            if ($request->expectsJson()) {
                return $this->jsonError($request, $data['variant_public_id'], $message);
            }

            return redirect()->route('store.cart.index')->withErrors([
                'cart' => $message,
            ]);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonError($request, $data['variant_public_id'], __($exception->getMessage()));
            }

            return back()->withErrors(['cart' => __($exception->getMessage())])->withInput();
        }

        if ($request->expectsJson()) {
            return $this->jsonSuccess($request, $data['variant_public_id'], __('store::ui.store_auto_ec029179d2'));
        }

        return redirect()->route('store.cart.index')->with('store_success', __('store::ui.store_auto_ec029179d2'));
    }

    public function update(Request $request, string $variant): RedirectResponse|JsonResponse
    {
        $this->locale->apply();
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        try {
            $this->cart->update($request->session(), $variant, (int) $data['quantity']);
        } catch (CartQuantityLimitExceeded $exception) {
            $message = __('store::ui.store_auto_cd49f1c0ff', [
                'cart' => $exception->cartQuantity,
                'maximum' => $exception->maximumQuantity,
            ]);
            if ($request->expectsJson()) {
                return $this->jsonError($request, $variant, $message);
            }

            return back()->withErrors([
                'cart' => $message,
            ]);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonError($request, $variant, __($exception->getMessage()));
            }

            return back()->withErrors(['cart' => __($exception->getMessage())]);
        }

        if ($request->expectsJson()) {
            return $this->jsonSuccess($request, $variant, __('store::ui.store_auto_cbf21b8d78'));
        }

        return back()->with('store_success', __('store::ui.store_auto_cbf21b8d78'));
    }

    public function destroy(Request $request, string $variant): RedirectResponse|JsonResponse
    {
        $this->locale->apply();
        $this->cart->remove($request->session(), $variant);

        if ($request->expectsJson()) {
            return $this->jsonSuccess($request, $variant, __('store::ui.store_auto_d36eb9937b'));
        }

        return back()->with('store_success', __('store::ui.store_auto_d36eb9937b'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->locale->apply();
        $this->cart->clear($request->session());

        return back()->with('store_success', __('store::ui.store_auto_4315dec844'));
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $this->locale->apply();
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:64']]);

        try {
            $this->cart->applyCoupon($request->session(), (string) $data['coupon_code'], $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['coupon' => __($exception->getMessage())])->withInput();
        }

        return back()->with('store_success', __('store::ui.store_auto_5b0f41bf43'));
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $this->locale->apply();
        $this->cart->removeCoupon($request->session());

        return back()->with('store_success', __('store::ui.store_auto_99fc6f8acd'));
    }

    private function jsonSuccess(Request $request, string $variant, string $message): JsonResponse
    {
        return response()->json($this->jsonPayload($request, $variant, $message));
    }

    private function jsonError(Request $request, string $variant, string $message): JsonResponse
    {
        return response()->json($this->jsonPayload($request, $variant, $message), 422);
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Request $request, string $variant, string $message): array
    {
        $capacity = $this->cart->variantCapacities($request->session(), [$variant])[$variant] ?? [
            'cart_quantity' => 0,
            'maximum_quantity' => 0,
            'remaining_quantity' => 0,
            'at_limit' => false,
            'can_add' => false,
        ];

        return [
            'message' => $message,
            'cart_count' => $this->cart->count($request->session()),
            'item' => ['variant_public_id' => $variant, ...$capacity],
        ];
    }
}
