<?php

namespace Modules\Store\Application\Settings;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;
use Modules\Store\Domain\Settings\StoreSettingsRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class StoreSettingsService
{
    public const ORDER_STATUSES = ['new', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'];

    private const DEFAULTS = [
        'store_name' => 'Z4 Store',
        'store_email' => '',
        'store_phone' => '',
        'store_address' => '',
        'store_country' => 'JO',
        'timezone' => 'Asia/Amman',
        'default_language' => 'en',
        'store_accepting_orders' => true,
        'store_paused_message' => 'store::ui.store_paused_message',
        'shipping_countries' => ['JO'],
        'currency' => 'JOD',
        'currency_symbol' => 'د.أ',
        'currency_symbol_position' => 'after',
        'currency_decimal_places' => 3,
        'currency_thousands_separator' => ',',
        'currency_decimal_separator' => '.',
        'usd_per_jod_basis_points' => 14_100,
        'price_display' => 'code',
        'tax_enabled' => false,
        'tax_basis_points' => 0,
        'prices_include_tax' => false,
        'tax_name' => 'Tax',
        'inventory_enabled' => true,
        'default_safety_stock' => 0,
        'prevent_overselling' => true,
        'cancelled_orders_restore_stock' => true,
        'returned_products_restore_stock' => false,
        'admin_low_stock_notification' => true,
        'out_of_stock_visibility' => 'visible',
        'show_stock_status' => true,
        'default_product_visibility' => 'hidden',
        'maximum_upload_mb' => 15,
        'order_number_prefix' => 'Z4',
        'order_default_status' => 'new',
        'self_cancel_hours' => 24,
        'self_cancel_statuses' => ['new'],
        'coupon_release_on_cancel' => true,
        'minimum_order_minor' => 0,
        'order_instructions' => '',
        'cod_enabled' => true,
        'cod_label' => 'Cash on Delivery',
        'cod_description' => 'Pay the order amount when your shipment is delivered.',
        'cod_instructions' => '',
        'cod_confirmation_message' => 'Your order was received and payment is due on delivery.',
        'cod_default_payment_status' => 'cod_pending',
        'cod_allow_collection' => true,
        'customer_notifications' => [
            'new' => ['enabled' => true, 'subject' => 'Order {{order_number}} received', 'body' => 'We received order {{order_number}} for {{customer_name}}.'],
            'confirmed' => ['enabled' => true, 'subject' => 'Order {{order_number}} confirmed', 'body' => 'Your order {{order_number}} is confirmed.'],
            'processing' => ['enabled' => true, 'subject' => 'Preparing order {{order_number}}', 'body' => 'We are preparing order {{order_number}}.'],
            'ready' => ['enabled' => true, 'subject' => 'Order {{order_number}} is ready', 'body' => 'Order {{order_number}} is ready for delivery.'],
            'shipped' => ['enabled' => true, 'subject' => 'Order {{order_number}} is on the way', 'body' => 'Order {{order_number}} is out for delivery.'],
            'delivered' => ['enabled' => true, 'subject' => 'Order {{order_number}} delivered', 'body' => 'Order {{order_number}} was delivered.'],
            'cancelled' => ['enabled' => true, 'subject' => 'Order {{order_number}} cancelled', 'body' => 'Order {{order_number}} was cancelled.'],
            'returned' => ['enabled' => true, 'subject' => 'Order {{order_number}} returned', 'body' => 'Order {{order_number}} was recorded as returned.'],
        ],
        'admin_notifications' => [
            'new_order' => true,
            'cancellation_request' => true,
            'low_stock' => true,
            'customer_email_failed' => true,
            'recipients' => [],
        ],
        'policies' => [
            'order' => '',
            'cancellation' => '',
            'delivery' => '',
            'return' => '',
            'cod' => '',
            'privacy' => '',
        ],
        'store_pages' => [
            'shop' => '',
            'cart' => '',
            'checkout' => '',
            'my_orders' => '',
            'confirmation' => '',
            'policies' => '',
        ],
    ];

    public function __construct(
        private StoreSettingsRepository $settings,
        private Clock $clock,
        private TransactionManager $transactions,
        private ConnectionInterface $database,
        private ShippingRegionCatalog $shippingRegions,
    ) {}

    /** @return array<string, mixed> */
    public function current(): array
    {
        return array_replace_recursive(self::DEFAULTS, $this->settings->all());
    }

    /** Legacy Phase 2 update path retained for compatibility. @param array<string, mixed> $settings */
    public function update(array $settings, ActorContext $actor): void
    {
        $normalized = [
            'currency' => strtoupper((string) ($settings['currency'] ?? 'JOD')),
            'price_display' => (string) ($settings['price_display'] ?? 'code'),
            'default_product_visibility' => (string) ($settings['default_product_visibility'] ?? 'hidden'),
            'default_safety_stock' => (int) ($settings['default_safety_stock'] ?? 0),
            'maximum_upload_mb' => (int) ($settings['maximum_upload_mb'] ?? 15),
            'cod_enabled' => (bool) ($settings['cod_enabled'] ?? false),
        ];
        $this->validateCurrencyAndCatalog($normalized);
        $this->persist($normalized, $actor);
    }

    /** @param array<string, mixed> $values */
    public function updateSection(string $section, array $values, ActorContext $actor): void
    {
        $normalized = match ($section) {
            'general' => $this->general($values),
            'shipping' => $this->shipping($values),
            'currency' => $this->currency($values),
            'tax' => $this->tax($values),
            'inventory' => $this->inventory($values),
            'orders' => $this->orders($values),
            'cod' => $this->cod($values),
            'customer_notifications' => ['customer_notifications' => $this->customerNotifications($values)],
            'admin_notifications' => ['admin_notifications' => $this->adminNotifications($values)],
            'policies' => ['policies' => $this->stringMap($values, ['order', 'cancellation', 'delivery', 'return', 'cod', 'privacy'], 20_000)],
            'pages' => ['store_pages' => $this->pages($values)],
            default => throw new InvalidArgumentException('Store settings section is invalid.'),
        };
        $this->persist($normalized, $actor);
    }

    /** @param array<string, mixed> $values @return array<string, list<string>> */
    private function shipping(array $values): array
    {
        $requested = array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            (array) ($values['shipping_countries'] ?? []),
        );
        $countries = array_values(array_intersect(array_keys($this->shippingRegions->countries()), $requested));

        if ($countries === []) {
            throw new InvalidArgumentException('Select at least one country that the store can ship to.');
        }

        return ['shipping_countries' => $countries];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function general(array $values): array
    {
        $country = strtoupper(trim((string) ($values['store_country'] ?? 'JO')));
        if (! array_key_exists($country, $this->shippingRegions->countries())) {
            throw new InvalidArgumentException('Store country is invalid.');
        }

        $pausedMessage = $this->required($values['store_paused_message'] ?? null, 'Paused store message');
        if ($pausedMessage === 'The store is temporarily not accepting new orders.'
            || $pausedMessage === __('store::ui.store_paused_message')) {
            $pausedMessage = 'store::ui.store_paused_message';
        }

        return [
            'store_name' => $this->required($values['store_name'] ?? null, 'Store name'),
            'store_email' => trim((string) ($values['store_email'] ?? '')),
            'store_phone' => trim((string) ($values['store_phone'] ?? '')),
            'store_address' => trim((string) ($values['store_address'] ?? '')),
            'store_country' => $country,
            'timezone' => $this->shippingRegions->timezone($country),
            'default_language' => in_array(($values['default_language'] ?? ''), ['en', 'ar'], true) ? $values['default_language'] : 'en',
            'store_accepting_orders' => (bool) ($values['store_accepting_orders'] ?? false),
            'store_paused_message' => $pausedMessage,
        ];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function currency(array $values): array
    {
        $usdPerJod = $values['usd_per_jod'] ?? 1.41;
        if (! is_numeric($usdPerJod)) {
            throw new InvalidArgumentException('USD exchange rate must be numeric.');
        }

        $normalized = [
            'currency' => strtoupper((string) ($values['currency'] ?? 'JOD')),
            'currency_symbol' => trim((string) ($values['currency_symbol'] ?? 'د.أ')),
            'currency_symbol_position' => (string) ($values['currency_symbol_position'] ?? 'after'),
            'currency_decimal_places' => (int) ($values['currency_decimal_places'] ?? 3),
            'currency_thousands_separator' => (string) ($values['currency_thousands_separator'] ?? ','),
            'currency_decimal_separator' => (string) ($values['currency_decimal_separator'] ?? '.'),
            'usd_per_jod_basis_points' => (int) round((float) $usdPerJod * 10_000),
            'price_display' => (string) ($values['price_display'] ?? 'code'),
        ];
        $this->validateCurrencyAndCatalog($normalized);
        if (! in_array($normalized['currency_symbol_position'], ['before', 'after'], true)
            || $normalized['currency_decimal_places'] < 0
            || $normalized['currency_decimal_places'] > 4
            || strlen($normalized['currency_thousands_separator']) > 1
            || strlen($normalized['currency_decimal_separator']) !== 1
            || $normalized['currency_decimal_separator'] === $normalized['currency_thousands_separator']
            || $normalized['usd_per_jod_basis_points'] < 1
            || $normalized['usd_per_jod_basis_points'] > 10_000_000) {
            throw new InvalidArgumentException('Currency formatting is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function tax(array $values): array
    {
        $rate = (int) ($values['tax_basis_points'] ?? 0);
        if ($rate < 0 || $rate > 100_000) {
            throw new InvalidArgumentException('Tax rate is invalid.');
        }

        return [
            'tax_enabled' => $rate > 0,
            'tax_basis_points' => $rate,
            'prices_include_tax' => (bool) ($values['prices_include_tax'] ?? false),
            'tax_name' => $this->required($values['tax_name'] ?? null, 'Tax name'),
        ];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function inventory(array $values): array
    {
        $threshold = (int) ($values['default_safety_stock'] ?? 0);
        if ($threshold < 0 || $threshold > 1_000_000) {
            throw new InvalidArgumentException('Low-stock threshold is invalid.');
        }

        return [
            'inventory_enabled' => (bool) ($values['inventory_enabled'] ?? false),
            'default_safety_stock' => $threshold,
            'prevent_overselling' => true,
            'cancelled_orders_restore_stock' => (bool) ($values['cancelled_orders_restore_stock'] ?? false),
            'returned_products_restore_stock' => (bool) ($values['returned_products_restore_stock'] ?? false),
            'admin_low_stock_notification' => (bool) ($values['admin_low_stock_notification'] ?? false),
            'out_of_stock_visibility' => in_array(($values['out_of_stock_visibility'] ?? ''), ['visible', 'hidden'], true) ? $values['out_of_stock_visibility'] : 'visible',
            'show_stock_status' => (bool) ($values['show_stock_status'] ?? false),
        ];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function orders(array $values): array
    {
        $default = (string) ($values['order_default_status'] ?? 'new');
        if (! in_array($default, ['new', 'confirmed'], true)) {
            throw new InvalidArgumentException('Default order status is invalid.');
        }

        return [
            'order_number_prefix' => preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string) ($values['order_number_prefix'] ?? 'Z4'))) ?: 'Z4',
            'order_default_status' => $default,
            // Kept in persisted settings for backward compatibility. The active policy is fixed:
            // Pending and Confirmed cancel directly; later fulfilment stages require review.
            'self_cancel_hours' => 0,
            'self_cancel_statuses' => ['new', 'confirmed'],
            'coupon_release_on_cancel' => (bool) ($values['coupon_release_on_cancel'] ?? false),
            'minimum_order_minor' => max(0, (int) ($values['minimum_order_minor'] ?? 0)),
            'order_instructions' => trim((string) ($values['order_instructions'] ?? '')),
            'store_accepting_orders' => (bool) ($values['store_accepting_orders'] ?? false),
        ];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function cod(array $values): array
    {
        return [
            'cod_enabled' => (bool) ($values['cod_enabled'] ?? false),
            'cod_label' => $this->required($values['cod_label'] ?? null, 'COD label'),
            'cod_description' => trim((string) ($values['cod_description'] ?? '')),
            'cod_instructions' => trim((string) ($values['cod_instructions'] ?? '')),
            'cod_confirmation_message' => trim((string) ($values['cod_confirmation_message'] ?? '')),
            'cod_default_payment_status' => in_array(($values['cod_default_payment_status'] ?? ''), ['cod_pending', 'pending'], true) ? $values['cod_default_payment_status'] : 'cod_pending',
            'cod_allow_collection' => (bool) ($values['cod_allow_collection'] ?? false),
        ];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function customerNotifications(array $values): array
    {
        $allowed = ['customer_name', 'order_number', 'order_status', 'store_name'];
        $result = [];
        foreach (self::ORDER_STATUSES as $status) {
            $template = (array) ($values[$status] ?? []);
            $subject = trim((string) ($template['subject'] ?? ''));
            $body = trim((string) ($template['body'] ?? ''));
            foreach ([$subject, $body] as $text) {
                preg_match_all('/\{\{([a-z_]+)\}\}/', $text, $matches);
                if (array_diff($matches[1] ?? [], $allowed) !== []) {
                    throw new InvalidArgumentException("Notification template [{$status}] contains an invalid variable.");
                }
            }
            $result[$status] = [
                'enabled' => (bool) ($template['enabled'] ?? false),
                'subject' => $subject,
                'body' => $body,
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function adminNotifications(array $values): array
    {
        $recipients = array_values(array_unique(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            preg_split('/[\s,;]+/', (string) ($values['recipients'] ?? '')) ?: [],
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));

        return [
            'new_order' => (bool) ($values['new_order'] ?? false),
            'cancellation_request' => (bool) ($values['cancellation_request'] ?? false),
            'low_stock' => (bool) ($values['low_stock'] ?? false),
            'customer_email_failed' => (bool) ($values['customer_email_failed'] ?? false),
            'recipients' => $recipients,
        ];
    }

    /** @param array<string, mixed> $values @return array<string, string> */
    private function pages(array $values): array
    {
        $pages = $this->stringMap($values, ['shop', 'cart', 'checkout', 'my_orders', 'confirmation', 'policies'], 255);
        foreach ($pages as $slug) {
            if ($slug !== '' && ! $this->database->table('platform_pages')->where('slug', $slug)->exists()) {
                throw new InvalidArgumentException("Store page [{$slug}] does not exist.");
            }
        }

        return $pages;
    }

    /** @param array<string, mixed> $values @param list<string> $keys @return array<string, string> */
    private function stringMap(array $values, array $keys, int $limit): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = mb_substr(trim((string) ($values[$key] ?? '')), 0, $limit);
        }

        return $result;
    }

    /** @param array<string, mixed> $settings */
    private function validateCurrencyAndCatalog(array $settings): void
    {
        if (! in_array(($settings['currency'] ?? 'JOD'), ['JOD', 'USD'], true)
            || ! in_array(($settings['price_display'] ?? 'code'), ['code', 'symbol'], true)
            || (isset($settings['default_product_visibility']) && ! in_array($settings['default_product_visibility'], ['public', 'hidden'], true))
            || (isset($settings['default_safety_stock']) && ($settings['default_safety_stock'] < 0 || $settings['default_safety_stock'] > 1_000_000))
            || (isset($settings['maximum_upload_mb']) && ($settings['maximum_upload_mb'] < 1 || $settings['maximum_upload_mb'] > 15))) {
            throw new InvalidArgumentException('One or more Store settings are invalid.');
        }
    }

    private function required(mixed $value, string $label): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function persist(array $values, ActorContext $actor): void
    {
        $this->transactions->run(fn (): mixed => $this->settings->put($values, $actor, $this->clock->now()));
    }
}
