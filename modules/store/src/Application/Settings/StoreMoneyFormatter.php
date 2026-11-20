<?php

namespace Modules\Store\Application\Settings;

final class StoreMoneyFormatter
{
    /** @var array<string, mixed>|null */
    private ?array $cached = null;

    public function __construct(private readonly StoreSettingsService $settings) {}

    public function format(int $minor, ?string $currency = null): string
    {
        $settings = $this->current();
        $sourceCurrency = strtoupper($currency ?? 'JOD');
        $displayCurrency = strtoupper((string) $settings['currency']);
        $storageDigits = $sourceCurrency === 'JOD' ? 3 : 2;
        $value = $minor / (10 ** $storageDigits);
        $usdPerJod = ((int) $settings['usd_per_jod_basis_points']) / 10_000;

        if ($sourceCurrency === 'JOD' && $displayCurrency === 'USD') {
            $value *= $usdPerJod;
        } elseif ($sourceCurrency === 'USD' && $displayCurrency === 'JOD') {
            $value /= $usdPerJod;
        }

        $displayDigits = (int) $settings['currency_decimal_places'];
        $amount = number_format(
            $value,
            $displayDigits,
            (string) $settings['currency_decimal_separator'],
            (string) $settings['currency_thousands_separator'],
        );
        $label = $settings['price_display'] === 'symbol'
            ? (string) $settings['currency_symbol']
            : $displayCurrency;

        return $settings['currency_symbol_position'] === 'before'
            ? trim($label.' '.$amount)
            : trim($amount.' '.$label);
    }

    public function formatCatalog(int $minor, ?string $currency = null): string
    {
        return $this->format($this->catalogMinor($minor), $currency);
    }

    public function catalogMinor(int $minor): int
    {
        $settings = $this->current();
        if (! (bool) $settings['prices_include_tax'] || (int) $settings['tax_basis_points'] <= 0) {
            return $minor;
        }

        return $minor + intdiv($minor * (int) $settings['tax_basis_points'], 10_000);
    }

    public function pricesIncludeTax(): bool
    {
        $settings = $this->current();

        return (bool) $settings['prices_include_tax'] && (int) $settings['tax_basis_points'] > 0;
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        return $this->cached ??= $this->settings->current();
    }
}
