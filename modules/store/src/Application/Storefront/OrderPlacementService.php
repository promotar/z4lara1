<?php

namespace Modules\Store\Application\Storefront;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Modules\Store\Application\Coupons\CouponApplicationResult;
use Modules\Store\Application\Coupons\CouponApplicationService;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Orders\OrderEventRecorder;
use Modules\Store\Application\Settings\StoreMoneyFormatter;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Application\Settings\StoreTaxCalculator;
use Modules\Store\Application\Shipping\ShippingQuoteService;
use Modules\Store\Contracts\Payments\PaymentMethodRegistry;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;
use Modules\Store\Events\OrderPlaced;
use Modules\Store\Infrastructure\Integrations\DatabaseExternalSellableLifecycleFeed;
use RuntimeException;

final readonly class OrderPlacementService
{
    public function __construct(
        private ConnectionInterface $database,
        private PublicIdGenerator $ids,
        private TransactionManager $transactions,
        private InventoryService $inventory,
        private PaymentMethodRegistry $payments,
        private OrderEventRecorder $orderEvents,
        private CouponApplicationService $coupons,
        private ShippingQuoteService $shipping,
        private StoreSettingsService $settings,
        private StoreTaxCalculator $taxes,
        private StoreMoneyFormatter $money,
        private DatabaseExternalSellableLifecycleFeed $externalLifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $customer
     * @param  array{items: Collection, subtotal_minor: int, shipping_minor: int, total_minor: int, currency: string, coupon: array<string, mixed>|null}  $cart
     * @return array{public_id: string, order_number: string}
     */
    public function place(array $customer, array $cart, string $idempotencyKey, int $userId): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('You must sign in before completing checkout.');
        }
        if ($cart['items']->isEmpty()) {
            throw new RuntimeException('Your cart is empty.');
        }
        $runtime = $this->settings->current();
        if (! (bool) $runtime['store_accepting_orders']) {
            throw new RuntimeException(__((string) $runtime['store_paused_message']));
        }
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 64) {
            throw new RuntimeException('A valid checkout submission token is required.');
        }

        $payment = $this->payments->default();
        if (! $payment->isAvailable() || $payment->code() !== 'cash_on_delivery') {
            throw new RuntimeException('Cash on Delivery is currently unavailable.');
        }

        try {
            $result = $this->transactions->run(function () use ($customer, $cart, $userId, $payment, $idempotencyKey, $runtime): array {
                $existing = $this->database->table('store_orders')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first(['public_id', 'order_number', 'created_by']);
                if ($existing !== null) {
                    $this->assertOrderOwner($existing, $userId);

                    return ['public_id' => (string) $existing->public_id, 'order_number' => (string) $existing->order_number, 'created' => false];
                }

                $coupon = $this->lockedCoupon($cart, $userId, $customer);
                $discountMinor = $coupon?->discountMinor ?? 0;
                $discountedProducts = max(0, (int) $cart['subtotal_minor'] - $discountMinor);
                if ($discountedProducts < (int) $runtime['minimum_order_minor']) {
                    throw new RuntimeException('Your order has not reached the store minimum.');
                }
                $tax = $this->taxes->calculate($discountedProducts);
                $shipping = $this->shipping->quote(
                    $customer,
                    $discountedProducts,
                );
                if (! $shipping->supported) {
                    throw new RuntimeException($shipping->message ?: 'Delivery is not available for this address.');
                }
                $totalMinor = $discountedProducts + $tax['total_addition_minor'] + $shipping->costMinor;
                $orderPublicId = (string) $this->ids->generate();
                $orderNumber = $runtime['order_number_prefix'].'-'.now()->format('ymd').'-'.substr($orderPublicId, -8);
                $initialStatus = (string) $runtime['order_default_status'];
                $now = now();
                $orderId = (int) $this->database->table('store_orders')->insertGetId([
                    'public_id' => $orderPublicId,
                    'order_number' => $orderNumber,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $initialStatus,
                    'payment_method' => $payment->code(),
                    'payment_status' => (string) $runtime['cod_default_payment_status'],
                    'currency' => $cart['currency'],
                    'subtotal_minor' => $cart['subtotal_minor'],
                    'discount_minor' => $discountMinor,
                    'tax_minor' => $tax['tax_minor'],
                    'tax_name' => $tax['tax_name'],
                    'prices_include_tax' => $tax['prices_include_tax'],
                    'shipping_minor' => $shipping->costMinor,
                    'shipping_method_code' => $shipping->methodCode,
                    'shipping_zone_name' => $shipping->zoneName,
                    'shipping_free_reason' => $shipping->freeReason,
                    'shipping_estimate' => $shipping->estimatedDelivery,
                    'total_minor' => $totalMinor,
                    'coupon_code' => $coupon?->code,
                    'coupon_discount_type' => $coupon?->discountType,
                    'coupon_fixed_amount_minor' => $coupon?->fixedAmountMinor,
                    'coupon_percentage_basis_points' => $coupon?->percentageBasisPoints,
                    'coupon_eligible_items' => $coupon === null ? null : json_encode($coupon->eligibleItems, JSON_THROW_ON_ERROR),
                    'customer_name' => trim((string) $customer['customer_name']),
                    'customer_email' => $this->nullable($customer['customer_email'] ?? null),
                    'customer_phone' => trim((string) $customer['customer_phone']),
                    'country_code' => strtoupper((string) ($customer['country_code'] ?? 'JO')),
                    'governorate' => $this->nullable($customer['governorate'] ?? null),
                    'city' => trim((string) $customer['city']),
                    'area' => $this->nullable($customer['area'] ?? null),
                    'district' => $this->nullable($customer['district'] ?? null),
                    'street' => $this->nullable($customer['street'] ?? null),
                    'building' => $this->nullable($customer['building'] ?? null),
                    'floor' => $this->nullable($customer['floor'] ?? null),
                    'apartment' => $this->nullable($customer['apartment'] ?? null),
                    'landmark' => $this->nullable($customer['landmark'] ?? null),
                    'delivery_phone' => $this->nullable($customer['delivery_phone'] ?? null),
                    'delivery_notes' => $this->nullable($customer['delivery_notes'] ?? null),
                    'address_line_1' => trim((string) $customer['address_line_1']),
                    'address_line_2' => $this->nullable($customer['address_line_2'] ?? null),
                    'postal_code' => $this->nullable($customer['postal_code'] ?? null),
                    'customer_notes' => $this->nullable($customer['customer_notes'] ?? null),
                    'locale' => app()->getLocale(),
                    'created_by' => $userId,
                    'placed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($cart['items'] as $item) {
                    $lineDiscount = $coupon?->lineDiscounts[(string) $item['variant_public_id']] ?? 0;
                    $itemPublicId = (string) $this->ids->generate();
                    $itemId = (int) $this->database->table('store_order_items')->insertGetId([
                        'public_id' => $itemPublicId,
                        'order_id' => $orderId,
                        'product_public_id' => $item['product_public_id'],
                        'variant_public_id' => $item['variant_public_id'],
                        'product_name' => $item['product_name'],
                        'variant_name' => $item['variant_name'],
                        'sku' => $item['sku'],
                        'image_url' => $item['image_url'],
                        'quantity' => $item['quantity'],
                        'unit_price_minor' => $item['price_minor'],
                        'discount_minor' => $lineDiscount,
                        'line_total_minor' => max(0, (int) $item['line_total_minor'] - $lineDiscount),
                        'currency' => $item['currency'],
                        'inventory_reservation_public_id' => null,
                        'external_sellable_snapshot' => $this->externalSnapshot($item),
                        'created_at' => $now,
                    ]);

                    if ($item['tracking_mode'] === 'tracked' && $item['inventory_public_id'] !== null) {
                        $reservation = $this->inventory->reserve(
                            $item['inventory_public_id'],
                            $item['quantity'],
                            'order',
                            $orderPublicId,
                            'order:'.$orderPublicId.':'.$item['variant_public_id'],
                            DateTimeImmutable::createFromInterface($now)->modify('+24 hours'),
                        );
                        $this->database->table('store_order_items')->where('id', $itemId)->update([
                            'inventory_reservation_public_id' => $reservation,
                        ]);
                    }

                    $this->externalLifecycle->recordOrderItem($orderId, $itemId, 'reserved');
                }

                if ($coupon !== null) {
                    $this->coupons->recordUsage(
                        $coupon,
                        $orderId,
                        $userId,
                        $this->nullable($customer['customer_email'] ?? null),
                        $this->nullable($customer['customer_phone'] ?? null),
                    );
                }

                $this->orderEvents->record(
                    $orderId,
                    'created',
                    new ActorContext('user', (string) $userId),
                    'Order received from storefront checkout.',
                    toStatus: $initialStatus,
                    metadata: ['payment_method' => $payment->code()],
                    visibility: 'customer',
                );

                return ['public_id' => $orderPublicId, 'order_number' => $orderNumber, 'created' => true];
            });
        } catch (QueryException $exception) {
            // The unique Store-owned key closes the race between two identical submits.
            $existing = $this->database->table('store_orders')
                ->where('idempotency_key', $idempotencyKey)
                ->first(['public_id', 'order_number', 'created_by']);
            if ($existing === null) {
                throw $exception;
            }
            $this->assertOrderOwner($existing, $userId);
            $result = ['public_id' => (string) $existing->public_id, 'order_number' => (string) $existing->order_number, 'created' => false];
        }

        if ($result['created']) {
            Event::dispatch(new OrderPlaced(
                $result['public_id'],
                $result['order_number'],
                $this->nullable($customer['customer_email'] ?? null),
                trim((string) $customer['customer_phone']),
            ));
        }

        return ['public_id' => $result['public_id'], 'order_number' => $result['order_number']];
    }

    /**
     * @param  array<string, mixed>  $cart
     * @param  array<string, mixed>  $customer
     */
    private function lockedCoupon(array $cart, int $userId, array $customer): ?CouponApplicationResult
    {
        $code = trim((string) ($cart['coupon']['code'] ?? ''));
        if ($code === '') {
            return null;
        }

        return $this->coupons->evaluate(
            $code,
            $cart['items'],
            $userId,
            $this->nullable($customer['customer_email'] ?? null),
            $this->nullable($customer['customer_phone'] ?? null),
            lock: true,
            enforceIdentity: true,
        );
    }

    /** @return array<string, mixed>|null */
    public function confirmation(string $publicId, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $order = $this->database->table('store_orders')
            ->where('public_id', $publicId)
            ->where('created_by', $userId)
            ->first();
        if ($order === null) {
            return null;
        }
        $runtime = $this->settings->current();
        $pricesIncludeTax = (bool) $order->prices_include_tax;
        $taxableBase = max(0, (int) $order->subtotal_minor - (int) $order->discount_minor);
        $gross = static function (int $amount) use ($order, $pricesIncludeTax, $taxableBase): int {
            if (! $pricesIncludeTax || $taxableBase === 0 || (int) $order->tax_minor === 0) {
                return $amount;
            }

            return $amount + intdiv($amount * (int) $order->tax_minor, $taxableBase);
        };

        $items = $this->database->table('store_order_items')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $item): array => [
                'product_name' => (string) $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => (int) $item->quantity,
                'image_url' => $item->image_url,
                'line_total_formatted' => $this->formatMoney($gross((int) $item->line_total_minor), (string) $item->currency),
            ]);

        return [
            'public_id' => (string) $order->public_id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'payment_method' => (string) $order->payment_method,
            'customer_name' => (string) $order->customer_name,
            'customer_phone' => (string) $order->customer_phone,
            'city' => (string) $order->city,
            'address' => implode('، ', array_filter([$order->area, $order->address_line_1, $order->address_line_2])),
            'subtotal_formatted' => $this->formatMoney($gross((int) $order->subtotal_minor), (string) $order->currency),
            'coupon_code' => $order->coupon_code,
            'discount_minor' => (int) $order->discount_minor,
            'discount_formatted' => $this->formatMoney($gross((int) $order->discount_minor), (string) $order->currency),
            'tax_minor' => (int) $order->tax_minor,
            'tax_name' => $order->tax_name,
            'prices_include_tax' => $pricesIncludeTax,
            'tax_formatted' => $this->formatMoney((int) $order->tax_minor, (string) $order->currency),
            'shipping_zone_name' => $order->shipping_zone_name,
            'shipping_estimate' => $order->shipping_estimate,
            'shipping_formatted' => (int) $order->shipping_minor === 0
                ? __('store::ui.store_auto_b24ce0cd39')
                : $this->formatMoney((int) $order->shipping_minor, (string) $order->currency),
            'total_formatted' => $this->formatMoney((int) $order->total_minor, (string) $order->currency),
            'payment_label' => (string) $runtime['cod_label'],
            'payment_confirmation_message' => (string) $runtime['cod_confirmation_message'],
            'items' => $items,
            'placed_at' => $order->placed_at,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertOrderOwner(object $order, int $userId): void
    {
        if ((int) $order->created_by !== $userId) {
            throw new RuntimeException('This checkout submission token belongs to another account.');
        }
    }

    /** @param array<string, mixed> $item */
    private function externalSnapshot(array $item): ?string
    {
        $link = $this->database->table('store_external_sellables as link')
            ->join('store_product_variants as variant', 'variant.id', '=', 'link.variant_id')
            ->where('variant.public_id', (string) $item['variant_public_id'])
            ->first(['link.owner_code', 'link.external_id', 'link.source_revision', 'link.metadata']);
        if ($link === null) {
            return null;
        }

        $metadata = json_decode((string) ($link->metadata ?? '{}'), true, 512, JSON_THROW_ON_ERROR) ?: [];
        $rate = min(10_000, max(0, (int) ($metadata['commission_rate'] ?? 0)));
        $approved = (int) $item['price_minor'];
        $commission = intdiv($approved * $rate, 10_000);
        $required = [
            'source_module', 'artwork_public_id', 'artist_public_id', 'artist_display_name',
            'artwork_title', 'artwork_type', 'edition_type', 'edition_number', 'dimensions',
            'frame_status', 'preparation_lead_time', 'certificate_status', 'main_image_display_path',
        ];
        $snapshot = array_intersect_key($metadata, array_flip($required));
        $snapshot += [
            'source_module' => (string) $link->owner_code,
            'artwork_public_id' => (string) $link->external_id,
            'source_revision' => (int) $link->source_revision,
            'approved_sale_price' => $approved,
            'currency' => (string) $item['currency'],
            'commission_rule' => (string) ($metadata['commission_rule'] ?? 'not_applicable'),
            'commission_rate' => $rate,
            'artist_net_snapshot' => $approved - $commission,
            'platform_commission_snapshot' => $commission,
        ];

        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function formatMoney(int $minor, string $currency): string
    {
        return $this->money->format($minor, $currency);
    }
}
