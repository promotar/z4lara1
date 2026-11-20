<?php

namespace Modules\Store\Application\Shipping;

use RuntimeException;

final class ShippingRegionCatalog
{
    /** @var array<string, string> */
    private const PRIMARY_TIMEZONES = [
        'AE' => 'Asia/Dubai',
        'AR' => 'America/Argentina/Buenos_Aires',
        'AU' => 'Australia/Sydney',
        'BR' => 'America/Sao_Paulo',
        'CA' => 'America/Toronto',
        'CD' => 'Africa/Kinshasa',
        'CL' => 'America/Santiago',
        'EC' => 'America/Guayaquil',
        'EG' => 'Africa/Cairo',
        'ES' => 'Europe/Madrid',
        'GB' => 'Europe/London',
        'GL' => 'America/Nuuk',
        'ID' => 'Asia/Jakarta',
        'JO' => 'Asia/Amman',
        'KZ' => 'Asia/Almaty',
        'MX' => 'America/Mexico_City',
        'MY' => 'Asia/Kuala_Lumpur',
        'NZ' => 'Pacific/Auckland',
        'PT' => 'Europe/Lisbon',
        'RU' => 'Europe/Moscow',
        'SA' => 'Asia/Riyadh',
        'UA' => 'Europe/Kyiv',
        'US' => 'America/New_York',
        'UZ' => 'Asia/Tashkent',
    ];

    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    /** @return array<string, string> */
    public function countries(): array
    {
        $countries = [];
        foreach ((array) ($this->data()['countries'] ?? []) as $code => $country) {
            $code = (string) $code;
            $countries[$code] = $this->countryName($code, (string) ($country['name'] ?? $code));
        }

        return $countries;
    }

    /** @return array<string, array<string, string>> */
    public function countriesByContinent(): array
    {
        $data = $this->data();
        $result = [];

        foreach ((array) ($data['continents'] ?? []) as $continent => $codes) {
            $result[(string) $continent] = [];
            foreach ((array) $codes as $code) {
                $country = $data['countries'][$code] ?? null;
                if (is_array($country)) {
                    $code = (string) $code;
                    $result[(string) $continent][$code] = $this->countryName($code, (string) ($country['name'] ?? $code));
                }
            }
        }

        return $result;
    }

    /** @return array<string, list<string>> */
    public function governoratesByCountry(): array
    {
        $regions = [];
        foreach ((array) ($this->data()['countries'] ?? []) as $code => $country) {
            $regions[(string) $code] = array_values(array_map(
                static fn (mixed $region): string => (string) $region,
                (array) ($country['regions'] ?? []),
            ));
        }

        return $regions;
    }

    /** @return list<string> */
    public function governorates(string $countryCode): array
    {
        return $this->governoratesByCountry()[strtoupper(trim($countryCode))] ?? [];
    }

    private function countryName(string $countryCode, string $fallback): string
    {
        if (class_exists(\Locale::class)) {
            $localized = \Locale::getDisplayRegion('und_'.strtoupper($countryCode), app()->getLocale());
            if (is_string($localized) && $localized !== '') {
                return $localized;
            }
        }

        return $fallback;
    }

    public function timezone(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        $preferred = self::PRIMARY_TIMEZONES[$countryCode] ?? null;

        try {
            $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $countryCode);
        } catch (\Throwable) {
            $timezones = [];
        }

        if ($preferred !== null
            && in_array($preferred, \DateTimeZone::listIdentifiers(), true)
            && ($timezones === [] || in_array($preferred, $timezones, true))) {
            return $preferred;
        }

        return $timezones[0] ?? 'UTC';
    }

    /** @return array<string, string> */
    public function timezonesByCountry(): array
    {
        $timezones = [];
        foreach (array_keys($this->countries()) as $countryCode) {
            $timezones[$countryCode] = $this->timezone($countryCode);
        }

        return $timezones;
    }

    /** @return array<string, string> */
    public function timezoneLabelsByCountry(): array
    {
        $labels = [];
        foreach ($this->timezonesByCountry() as $countryCode => $timezone) {
            $labels[$countryCode] = $this->timezoneLabel($timezone);
        }

        return $labels;
    }

    private function timezoneLabel(string $timezone): string
    {
        if (class_exists(\IntlTimeZone::class)) {
            $zone = \IntlTimeZone::createTimeZone($timezone);
            $style = defined('IntlTimeZone::DISPLAY_GENERIC_LOCATION')
                ? \IntlTimeZone::DISPLAY_GENERIC_LOCATION
                : \IntlTimeZone::DISPLAY_LONG;
            $label = $zone->getDisplayName(false, $style, app()->getLocale());
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return $timezone;
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = dirname(__DIR__, 3).'/resources/data/world-regions.json';
        $decoded = is_file($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;

        if (! is_array($decoded) || ! is_array($decoded['countries'] ?? null) || ! is_array($decoded['continents'] ?? null)) {
            throw new RuntimeException('The world shipping region catalog is unavailable.');
        }

        return $this->catalog = $decoded;
    }
}
