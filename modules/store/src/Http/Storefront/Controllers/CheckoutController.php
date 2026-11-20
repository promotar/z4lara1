<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Store\Application\Customer\CustomerAddressService;
use Modules\Store\Application\Customer\CustomerResourceNotFound;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\OrderPlacementService;
use Modules\Store\Application\Storefront\StorefrontLocale;
use Modules\Store\Contracts\Payments\PaymentMethodRegistry;
use RuntimeException;

final class CheckoutController extends Controller
{
    private const SUBMISSION_TOKEN = 'store.checkout.submission_token';

    public function __construct(
        private readonly CartService $cart,
        private readonly OrderPlacementService $orders,
        private readonly PaymentMethodRegistry $payments,
        private readonly StorefrontLocale $locale,
        private readonly CustomerAddressService $addresses,
        private readonly StoreSettingsService $settings,
        private readonly ShippingRegionCatalog $regions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->locale->apply();
        $settings = $this->settings->current();
        if (! (bool) $settings['store_accepting_orders']) {
            return redirect()->route('store.cart.index')
                ->withErrors(['checkout' => __((string) $settings['store_paused_message'])]);
        }
        $cart = $this->cart->snapshot($request->session(), $request->user()?->id);
        if ($cart['items']->isEmpty()) {
            return redirect()->route('store.cart.index')->withErrors(['cart' => __('store::ui.store_auto_572da7ef14')]);
        }
        $paymentMethod = $this->payments->default();
        if (! $paymentMethod->isAvailable()) {
            return redirect()->route('store.cart.index')
                ->withErrors(['checkout' => __('store::ui.store_auto_880edbad80')]);
        }
        if ($request->user() === null) {
            return view('store::storefront.checkout-auth', [
                'cart' => $cart,
                'cartCount' => $cart['count'],
            ]);
        }

        $userId = $this->userId($request);
        $savedAddresses = $this->addresses->list($userId);
        $defaultAddress = $savedAddresses->firstWhere('is_default', true) ?? $savedAddresses->first();
        if ($defaultAddress !== null) {
            $this->cart->setShippingAddress($request->session(), (array) $defaultAddress);
        }
        $cart = $this->cart->snapshot($request->session(), $userId);

        $submissionToken = $this->submissionToken($request);
        $shippingCountries = [];
        $catalogCountries = $this->regions->countries();
        foreach ((array) $settings['shipping_countries'] as $countryCode) {
            $countryCode = strtoupper(trim((string) $countryCode));
            if (isset($catalogCountries[$countryCode])) {
                $shippingCountries[$countryCode] = $catalogCountries[$countryCode];
            }
        }

        return view('store::storefront.checkout', [
            'cart' => $cart,
            'paymentMethod' => $paymentMethod,
            'cartCount' => $cart['count'],
            'submissionToken' => $submissionToken,
            'savedAddresses' => $savedAddresses,
            'storeSettings' => $settings,
            'shippingCountries' => $shippingCountries,
            'shippingGovernorates' => array_intersect_key(
                $this->regions->governoratesByCountry(),
                $shippingCountries,
            ),
        ]);
    }

    public function shippingQuote(Request $request): JsonResponse
    {
        $this->locale->apply();
        $userId = $this->userId($request);
        $data = $request->validate([
            'saved_address_public_id' => ['nullable', 'string', 'size:26'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'governorate' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'string', 'max:128'],
            'area' => ['nullable', 'string', 'max:128'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ]);
        if (! empty($data['saved_address_public_id'])) {
            try {
                $address = $this->addresses->find($userId, (string) $data['saved_address_public_id']);
            } catch (CustomerResourceNotFound) {
                abort(404);
            }
            $data = (array) $address;
        }
        $this->cart->setShippingAddress($request->session(), $data);
        $cart = $this->cart->snapshot($request->session(), $userId);

        return response()->json([
            'supported' => $cart['shipping_supported'],
            'zone_name' => $cart['shipping_zone_name'] ?: null,
            'shipping_formatted' => $cart['shipping_formatted'],
            'total_formatted' => $cart['total_formatted'],
            'estimated_delivery' => $cart['shipping_estimate'],
            'message' => $cart['shipping_message'] ? __($cart['shipping_message']) : null,
            'free_reason' => $cart['shipping_free_reason'],
            'free_remaining_formatted' => $cart['shipping_free_remaining_formatted'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->locale->apply();
        $userId = $this->userId($request);
        $data = $request->validate([
            'saved_address_public_id' => ['nullable', 'string', 'size:26'],
            'customer_name' => ['nullable', 'required_without:saved_address_public_id', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'required_without:saved_address_public_id', 'string', 'max:64'],
            'country_code' => [
                'nullable',
                'required_without:saved_address_public_id',
                'string',
                'size:2',
            ],
            'governorate' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'required_without:saved_address_public_id', 'string', 'max:128'],
            'area' => ['nullable', 'string', 'max:128'],
            'address_line_1' => ['nullable', 'required_without:saved_address_public_id', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'terms' => ['accepted'],
            'submission_token' => ['nullable', 'string', 'max:64'],
        ], [
            'terms.accepted' => __('store::ui.store_auto_7962ac5657'),
        ]);

        $data['customer_email'] = (string) $request->user()->email;

        if (! empty($data['saved_address_public_id'])) {
            try {
                $address = $this->addresses->find($userId, (string) $data['saved_address_public_id']);
            } catch (CustomerResourceNotFound) {
                abort(404);
            }
            $data = array_merge($data, [
                'customer_name' => (string) $address->recipient_name,
                'customer_phone' => (string) $address->phone,
                'country_code' => (string) $address->country_code,
                'governorate' => $address->governorate,
                'city' => (string) $address->city,
                'area' => $address->area,
                'address_line_1' => (string) $address->street,
                'postal_code' => $address->postal_code,
            ]);
        }

        $submissionToken = $this->submissionToken($request);
        if (isset($data['submission_token']) && ! hash_equals($submissionToken, (string) $data['submission_token'])) {
            return back()->withErrors(['checkout' => __('store::ui.store_auto_e0e8f7376d')])->withInput();
        }

        if (is_string($data['coupon_code'] ?? null) && trim($data['coupon_code']) !== '') {
            try {
                $this->cart->applyCoupon(
                    $request->session(),
                    (string) $data['coupon_code'],
                    $userId,
                );
            } catch (RuntimeException $exception) {
                return back()->withErrors([
                    'checkout' => __($exception->getMessage()).' '.__('store::ui.store_auto_e9c9e1c286'),
                ])->withInput();
            }
        }

        $this->cart->setShippingAddress($request->session(), $data);
        $cart = $this->cart->snapshot($request->session(), $userId);
        if (is_string($cart['coupon_error']) && trim($cart['coupon_error']) !== '') {
            return back()->withErrors([
                'checkout' => __($cart['coupon_error']).' '.__('store::ui.store_auto_e9c9e1c286'),
            ])->withInput();
        }
        if ($cart['shipping_supported'] !== true) {
            return back()->withErrors([
                'checkout' => __($cart['shipping_message'] ?: 'Delivery is not available for this address.'),
            ])->withInput();
        }
        try {
            $order = $this->orders->place($data, $cart, $submissionToken, $userId);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['checkout' => __($exception->getMessage())])->withInput();
        }

        $this->cart->clear($request->session());
        $request->session()->forget(self::SUBMISSION_TOKEN);
        $request->session()->put('store.last_order', $order['public_id']);

        return redirect()->route('store.orders.confirmation', $order['public_id']);
    }

    private function submissionToken(Request $request): string
    {
        $token = (string) $request->session()->get(self::SUBMISSION_TOKEN, '');
        if ($token === '') {
            $token = Str::random(48);
            $request->session()->put(self::SUBMISSION_TOKEN, $token);
        }

        return $token;
    }

    private function userId(Request $request): int
    {
        $userId = (int) $request->user()->id;
        abort_if($userId <= 0, 401);

        return $userId;
    }
}
