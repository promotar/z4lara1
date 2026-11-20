<?php

namespace Modules\Store\Application\Settings;

final readonly class StoreTaxCalculator
{
    public function __construct(private StoreSettingsService $settings) {}

    /** @return array{enabled: bool, tax_minor: int, tax_name: ?string, prices_include_tax: bool, total_addition_minor: int} */
    public function calculate(int $discountedProductsMinor): array
    {
        $settings = $this->settings->current();
        $rate = (int) $settings['tax_basis_points'];
        $enabled = $rate > 0;
        $inclusive = (bool) $settings['prices_include_tax'];
        $base = max(0, $discountedProductsMinor);
        $tax = ! $enabled ? 0 : intdiv($base * $rate, 10_000);

        return [
            'enabled' => $enabled,
            'tax_minor' => $tax,
            'tax_name' => $enabled ? (string) $settings['tax_name'] : null,
            'prices_include_tax' => $inclusive,
            'total_addition_minor' => $tax,
        ];
    }
}
