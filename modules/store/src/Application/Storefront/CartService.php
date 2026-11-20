<?php

namespace Modules\Store\Application\Storefront;

use Illuminate\Contracts\Session\Session;
use Modules\Store\Application\Coupons\CouponApplicationService;
use Modules\Store\Application\Coupons\CouponRejected;
use Modules\Store\Application\Settings\StoreTaxCalculator;
use Modules\Store\Application\Shipping\ShippingQuoteService;
use RuntimeException;

final readonly class CartService
{
    private const SESSION_KEY = 'store.cart.items';

    private const COUPON_KEY = 'store.cart.coupon_code';

    private const SHIPPING_ADDRESS_KEY = 'store.checkout.shipping_address';

    public function __construct(
        private StorefrontCatalogService $catalog,
        private CouponApplicationService $coupons,
        private ShippingQuoteService $shipping,
        private StoreTaxCalculator $taxes,
    ) {}

    public function add(Session $session, string $variantPublicId, int $quantity): void
    {
        $items = $this->raw($session);
        $cartQuantity = $items[$variantPublicId] ?? 0;
        $targetQuantity = min(99, max(1, $cartQuantity + $quantity));
        $this->assertAvailable($variantPublicId, $targetQuantity, $cartQuantity);
        $items[$variantPublicId] = $targetQuantity;
        $this->persist($session, $items);
    }

    public function update(Session $session, string $variantPublicId, int $quantity): void
    {
        $items = $this->raw($session);
        if (! array_key_exists($variantPublicId, $items)) {
            throw new RuntimeException('Cart item not found.');
        }
        $targetQuantity = min(99, max(1, $quantity));
        $this->assertAvailable($variantPublicId, $targetQuantity, $items[$variantPublicId]);
        $items[$variantPublicId] = $targetQuantity;
        $this->persist($session, $items);
    }

    public function remove(Session $session, string $variantPublicId): void
    {
        $items = $this->raw($session);
        unset($items[$variantPublicId]);
        $this->persist($session, $items);
    }

    public function clear(Session $session): void
    {
        $session->forget([self::SESSION_KEY, self::COUPON_KEY, self::SHIPPING_ADDRESS_KEY]);
    }

    public function applyCoupon(Session $session, string $code, ?int $userId = null): void
    {
        $items = $this->catalog->cartItems($this->raw($session));
        if ($items->isEmpty()) {
            throw new CouponRejected('empty_cart', 'Add products to your cart before applying a coupon.');
        }

        $coupon = $this->coupons->evaluate($code, $items, $userId);
        $session->put(self::COUPON_KEY, $coupon->code);
    }

    public function removeCoupon(Session $session): void
    {
        $session->forget(self::COUPON_KEY);
    }

    /** @param array<string, mixed> $address */
    public function setShippingAddress(Session $session, array $address): void
    {
        $session->put(self::SHIPPING_ADDRESS_KEY, array_intersect_key($address, array_flip([
            'country_code', 'governorate', 'city', 'area', 'postal_code',
        ])));
    }

    /** @return array<string, mixed> */
    public function snapshot(Session $session, ?int $userId = null): array
    {
        $raw = $this->raw($session);
        $items = $this->catalog->cartItems($raw);
        $valid = $items->mapWithKeys(fn (array $item): array => [$item['variant_public_id'] => $item['quantity']])->all();
        if ($valid !== $raw) {
            $this->persist($session, $valid);
        }

        $subtotal = (int) $items->sum('line_total_minor');
        $coupon = null;
        $couponError = null;
        $discount = 0;
        $code = trim((string) $session->get(self::COUPON_KEY, ''));
        if ($code !== '' && $items->isNotEmpty()) {
            try {
                $result = $this->coupons->evaluate($code, $items, $userId);
                $coupon = $result->toArray();
                $discount = $result->discountMinor;
            } catch (CouponRejected $exception) {
                $session->forget(self::COUPON_KEY);
                $couponError = in_array($exception->reason, ['ineligible', 'minimum_not_met'], true)
                    ? 'Your cart changed and the coupon is no longer valid. '.$exception->getMessage()
                    : $exception->getMessage();
            }
        }
        $discountedProducts = max(0, $subtotal - $discount);
        $tax = $this->taxes->calculate($discountedProducts);
        $address = $session->get(self::SHIPPING_ADDRESS_KEY);
        $quote = is_array($address)
            ? $this->shipping->quote($address, $discountedProducts)
            : null;
        $shipping = $quote?->supported ? $quote->costMinor : 0;
        $total = $discountedProducts + $tax['total_addition_minor'] + $shipping;
        $currency = strtoupper((string) config('store.default_currency', 'JOD'));

        return [
            'items' => $items,
            'count' => (int) $items->sum('quantity'),
            'subtotal_minor' => $subtotal,
            'subtotal_formatted' => $tax['prices_include_tax']
                ? $this->catalog->formatCatalogMoney($subtotal, $currency)
                : $this->catalog->formatMoney($subtotal, $currency),
            'discount_minor' => $discount,
            'discount_formatted' => $tax['prices_include_tax']
                ? $this->catalog->formatCatalogMoney($discount, $currency)
                : $this->catalog->formatMoney($discount, $currency),
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'shipping_minor' => $shipping,
            'shipping_formatted' => $quote === null
                ? __('store::ui.store_auto_09dd8b9849')
                : ($quote->supported
                    ? ($shipping === 0 ? __('store::ui.store_auto_b24ce0cd39') : $this->catalog->formatMoney($shipping, $currency))
                    : __('store::ui.store_auto_453e6aa38d')),
            'shipping_resolved' => $quote !== null,
            'shipping_supported' => $quote?->supported,
            'shipping_zone_name' => $quote?->zoneName,
            'shipping_method_code' => $quote?->methodCode ?? 'delivery',
            'shipping_free_reason' => $quote?->freeReason,
            'shipping_free_remaining_minor' => $quote?->freeShippingRemainingMinor,
            'shipping_free_remaining_formatted' => $quote?->freeShippingRemainingMinor === null
                ? null
                : $this->catalog->formatMoney($quote->freeShippingRemainingMinor, $currency),
            'shipping_estimate' => $quote?->estimatedDelivery,
            'shipping_message' => $quote?->message,
            'tax_minor' => $tax['tax_minor'],
            'tax_formatted' => $this->catalog->formatMoney($tax['tax_minor'], $currency),
            'tax_name' => $tax['tax_name'],
            'prices_include_tax' => $tax['prices_include_tax'],
            'total_minor' => $total,
            'total_formatted' => $this->catalog->formatMoney($total, $currency),
            'currency' => $currency,
        ];
    }

    public function count(Session $session): int
    {
        return array_sum($this->raw($session));
    }

    /** @return array<string, int> */
    public function quantities(Session $session): array
    {
        return $this->raw($session);
    }

    /**
     * @param  list<string>  $variantPublicIds
     * @return array<string, array{cart_quantity: int, maximum_quantity: int, remaining_quantity: int, at_limit: bool, can_add: bool}>
     */
    public function variantCapacities(Session $session, array $variantPublicIds): array
    {
        $variantPublicIds = array_values(array_unique(array_filter(
            $variantPublicIds,
            static fn (mixed $variant): bool => is_string($variant) && strlen($variant) === 26,
        )));
        if ($variantPublicIds === []) {
            return [];
        }

        $cartQuantities = $this->raw($session);
        $catalogItems = $this->catalog
            ->cartItems(array_fill_keys($variantPublicIds, 1))
            ->keyBy('variant_public_id');
        $capacities = [];

        foreach ($variantPublicIds as $variantPublicId) {
            $item = $catalogItems->get($variantPublicId);
            $cartQuantity = $cartQuantities[$variantPublicId] ?? 0;
            $maximumQuantity = $item === null ? 0 : max(0, (int) $item['maximum_quantity']);
            $remainingQuantity = max(0, $maximumQuantity - $cartQuantity);

            $capacities[$variantPublicId] = [
                'cart_quantity' => $cartQuantity,
                'maximum_quantity' => $maximumQuantity,
                'remaining_quantity' => $remainingQuantity,
                'at_limit' => $cartQuantity > 0 && $remainingQuantity === 0,
                'can_add' => $item !== null && (bool) $item['available'] && $remainingQuantity > 0,
            ];
        }

        return $capacities;
    }

    /** @return array<string, int> */
    private function raw(Session $session): array
    {
        $items = $session->get(self::SESSION_KEY, []);
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $variant => $quantity) {
            if (is_string($variant) && strlen($variant) === 26 && is_numeric($quantity) && (int) $quantity > 0) {
                $normalized[$variant] = min(99, (int) $quantity);
            }
        }

        return $normalized;
    }

    /** @param array<string, int> $items */
    private function persist(Session $session, array $items): void
    {
        $session->put(self::SESSION_KEY, $items);
    }

    private function assertAvailable(string $variantPublicId, int $quantity, int $cartQuantity): void
    {
        $item = $this->catalog->cartItems([$variantPublicId => $quantity])->firstWhere('variant_public_id', $variantPublicId);
        if ($item === null || ! $item['available']) {
            throw new RuntimeException('This product is currently unavailable.');
        }

        if ($quantity > (int) $item['maximum_quantity']) {
            throw new CartQuantityLimitExceeded(
                $cartQuantity,
                $quantity,
                (int) $item['maximum_quantity'],
            );
        }
    }
}
