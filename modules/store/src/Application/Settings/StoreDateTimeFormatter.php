<?php

namespace Modules\Store\Application\Settings;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;

final class StoreDateTimeFormatter
{
    private ?string $timezone = null;

    /** @var array<string, mixed>|null */
    private ?array $context = null;

    public function __construct(
        private readonly StoreSettingsService $settings,
        private readonly ShippingRegionCatalog $shippingRegions,
    ) {}

    public function format(DateTimeInterface|string|null $value, string $format, ?string $timezone = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, (string) config('app.timezone', 'UTC'));

        return $date->setTimezone($timezone ?? $this->timezone())->format($format);
    }

    public function timezone(): string
    {
        return $this->timezone ??= (string) $this->context()['timezone'];
    }

    public function countryTimeLabel(): string
    {
        $context = $this->context();
        $countryCode = strtoupper((string) ($context['store_country'] ?? ''));
        $countryName = $this->shippingRegions->countries()[$countryCode] ?? $countryCode;

        return trim($countryName) !== '' ? $countryName.' time' : $this->timezone().' time';
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        return $this->context ??= $this->settings->current();
    }
}
