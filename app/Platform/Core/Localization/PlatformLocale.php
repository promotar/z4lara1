<?php

namespace App\Platform\Core\Localization;

use App\Platform\Core\Services\SettingsRepository;
use Throwable;

final readonly class PlatformLocale
{
    public function __construct(private SettingsRepository $settings) {}

    public function current(): string
    {
        try {
            $locale = (string) ($this->settings->values()['general.site_language'] ?? '');
        } catch (Throwable) {
            $locale = '';
        }

        return $this->normalize($locale) ?? $this->normalize((string) config('app.locale')) ?? 'en';
    }

    public function fallback(): string
    {
        return $this->normalize((string) config('app.fallback_locale')) ?? 'en';
    }

    public function apply(): string
    {
        $locale = $this->current();
        app()->setLocale($locale);

        return $locale;
    }

    public function direction(?string $locale = null): string
    {
        $language = strtolower(strtok($locale ?? $this->current(), '-_') ?: 'en');

        return in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    }

    private function normalize(string $locale): ?string
    {
        $locale = str_replace('_', '-', trim($locale));

        return preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/i', $locale) === 1 ? $locale : null;
    }
}
