<?php

namespace Modules\Store\Application\Shipping;

use Illuminate\Database\ConnectionInterface;
use Modules\Store\Application\Settings\StoreSettingsService;

final readonly class ShippingQuoteService
{
    private const SPECIFICITY = [
        'country' => 100,
        'governorate' => 200,
    ];

    public function __construct(
        private ConnectionInterface $database,
        private StoreSettingsService $settings,
    ) {}

    /** @param array<string, mixed> $address */
    public function quote(array $address, int $discountedProductsMinor): ShippingQuote
    {
        $normalized = [
            'country' => $this->normalize($address['country_code'] ?? null),
            'governorate' => $this->normalize($address['governorate'] ?? null),
        ];
        $enabledCountries = array_map(
            fn (mixed $country): string => $this->normalize($country),
            (array) $this->settings->current()['shipping_countries'],
        );
        if ($normalized['country'] === ''
            || ! in_array($normalized['country'], $enabledCountries, true)) {
            return $this->unsupported();
        }

        $rows = $this->database->table('store_shipping_zones as zone')
            ->join('store_shipping_zone_locations as location', 'location.shipping_zone_id', '=', 'zone.id')
            ->where('zone.status', 'active')
            ->whereIn('location.match_type', array_keys(self::SPECIFICITY))
            ->get([
                'zone.id',
                'zone.public_id',
                'zone.name',
                'zone.method_code',
                'zone.delivery_cost_minor',
                'zone.free_shipping_threshold_minor',
                'zone.estimated_delivery',
                'zone.customer_message',
                'zone.priority',
                'location.match_type',
                'location.match_value_normalized',
            ]);

        $zones = [];
        foreach ($rows as $row) {
            $type = (string) $row->match_type;
            $zoneId = (int) $row->id;
            if (! isset($zones[$zoneId])) {
                $zones[$zoneId] = [
                    'row' => $row,
                    'locations' => [],
                ];
            }
            $zones[$zoneId]['locations'][$type][] = (string) $row->match_value_normalized;
        }

        $matches = [];
        foreach ($zones as $zoneId => $zone) {
            $locations = $zone['locations'];
            $countries = array_values(array_unique($locations['country'] ?? []));
            $governorates = array_values(array_unique($locations['governorate'] ?? []));
            $restOfWorld = in_array('*', $countries, true);
            $countries = array_values(array_filter(
                $countries,
                static fn (string $country): bool => $country !== '*',
            ));

            if ($governorates !== []) {
                if ($normalized['governorate'] === ''
                    || ! in_array($normalized['governorate'], $governorates, true)
                    || ($countries !== [] && ! in_array($normalized['country'], $countries, true))) {
                    continue;
                }
                $specificity = self::SPECIFICITY['governorate'];
            } elseif ($countries !== []) {
                if ($normalized['country'] === ''
                    || ! in_array($normalized['country'], $countries, true)) {
                    continue;
                }
                $specificity = self::SPECIFICITY['country'];
            } elseif ($restOfWorld) {
                $specificity = 0;
            } else {
                continue;
            }

            $matches[$zoneId] = ['row' => $zone['row'], 'specificity' => $specificity];
        }

        if ($matches === []) {
            return $this->unsupported();
        }

        usort($matches, static fn (array $left, array $right): int => [$right['specificity'], (int) $right['row']->priority, -(int) $right['row']->id]
            <=> [$left['specificity'], (int) $left['row']->priority, -(int) $left['row']->id]
        );
        $zone = $matches[0]['row'];
        $baseCost = max(0, (int) $zone->delivery_cost_minor);
        $threshold = $zone->free_shipping_threshold_minor === null
            ? null
            : max(0, (int) $zone->free_shipping_threshold_minor);
        $products = max(0, $discountedProductsMinor);
        $freeReason = null;
        $remaining = null;
        $cost = $baseCost;
        if ($baseCost === 0) {
            $freeReason = 'zone_rate_zero';
        } elseif ($threshold !== null && $products >= $threshold) {
            $cost = 0;
            $freeReason = 'threshold_reached';
        } elseif ($threshold !== null) {
            $remaining = max(0, $threshold - $products);
        }

        return new ShippingQuote(
            true,
            (int) $zone->id,
            (string) $zone->public_id,
            (string) $zone->name,
            (string) $zone->method_code,
            $cost,
            $freeReason,
            $remaining,
            $zone->estimated_delivery === null ? null : (string) $zone->estimated_delivery,
            $zone->customer_message === null ? null : (string) $zone->customer_message,
        );
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    private function unsupported(): ShippingQuote
    {
        return new ShippingQuote(
            false,
            null,
            null,
            null,
            'delivery',
            0,
            null,
            null,
            null,
            'Delivery is not available for this address.',
        );
    }
}
