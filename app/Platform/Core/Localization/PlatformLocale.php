<?php

namespace App\Platform\Core\Localization;

use App\Platform\Core\Services\SettingsRepository;
use Throwable;

final readonly class PlatformLocale
{
    public const DEFAULT = 'en';

    public function __construct(private SettingsRepository $settings) {}

    public function current(): string
    {
        try {
            $prefixLocale = request()->attributes->get('seo_locale');

            if (is_string($prefixLocale) && $prefixLocale !== '' && $this->isAvailable($prefixLocale)) {
                return $prefixLocale;
            }
        } catch (Throwable) {
            // Console and queue contexts have no HTTP request.
        }

        $queryLocale = null;

        try {
            $value = request()->query('lang');

            if (is_string($value)) {
                $queryLocale = $this->normalize($value);
            }
        } catch (Throwable) {
            $queryLocale = null;
        }

        if ($queryLocale !== null && $this->isAvailable($queryLocale)) {
            return $queryLocale;
        }

        // Panel requests (admin dashboard + /account) follow the signed-in
        // user's own language: session first (instant switch), then the
        // persisted users.locale preference.
        if ($this->isPanelRequest()) {
            // Panel language comes only from the signed-in user's stored
            // preference — never from the page-language session, so switching
            // the public site language cannot move the dashboard.
            $userLocale = $this->userLocale();

            if ($userLocale !== null) {
                return $userLocale;
            }

            // The panel must not follow the public site language: changing
            // general.site_language has to leave every dashboard untouched.
            return $this->panelDefaultCode();
        }

        // Visitors (signed in or not) may switch the language of the page they
        // are browsing; that choice lives in the session and never changes the
        // site default, which only a super admin can set.
        $siteSession = $this->siteSessionLocale();

        if ($siteSession !== null) {
            return $siteSession;
        }

        return $this->defaultCode();
    }

    /**
     * Default locale used for x-default and for clean (non-prefixed) URLs.
     */
    public function defaultCode(): string
    {
        try {
            $locale = (string) ($this->settings->values()['general.site_language'] ?? '');
        } catch (Throwable) {
            $locale = '';
        }

        $normalized = $this->normalize($locale);

        if ($normalized !== null && $this->isAvailable($normalized)) {
            return $normalized;
        }

        $fallback = $this->normalize((string) config('app.locale')) ?? self::DEFAULT;

        return $this->isAvailable($fallback) ? $fallback : self::DEFAULT;
    }

    public function fallback(): string
    {
        return $this->normalize((string) config('app.fallback_locale')) ?? self::DEFAULT;
    }

    private function sessionLocale(): ?string
    {
        try {
            $request = app('request');

            if ($request === null || ! $request->hasSession() || ! $request->user()) {
                return null;
            }

            $locale = $this->normalize((string) $request->session()->get('platform_locale', ''));

            return $locale !== null && $this->isAvailable($locale) ? $locale : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function apply(): string
    {
        $locale = $this->current();
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        return $locale;
    }

    public function direction(?string $locale = null): string
    {
        $language = strtolower(strtok($locale ?? $this->current(), '-_') ?: self::DEFAULT);

        return in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    }

    public function set(string $locale): string
    {
        $normalized = $this->normalize($locale);

        if ($normalized === null || ! $this->isAvailable($normalized)) {
            $normalized = self::DEFAULT;
        }

        try {
            $request = app('request');

            if ($request !== null && $request->hasSession()) {
                $request->session()->put('platform_locale', $normalized);
            }
        } catch (Throwable) {
            // Console and queue contexts have no HTTP session.
        }

        app()->setLocale($normalized);

        return $normalized;
    }

    /**
     * Locales that have a text language file on disk.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $locales = [];

        foreach (glob(lang_path('*/core.txt')) ?: [] as $file) {
            $code = basename(dirname($file));

            if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) === 1) {
                $locales[] = $code;
            }
        }

        if (! in_array(self::DEFAULT, $locales, true)) {
            array_unshift($locales, self::DEFAULT);
        }

        $locales = array_values(array_unique($locales));
        usort($locales, static function (string $a, string $b): int {
            if ($a === self::DEFAULT) {
                return -1;
            }

            if ($b === self::DEFAULT) {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $locales;
    }

    /**
     * Label shown in the language switcher: code => native label.
     *
     * @return array<string, string>
     */
    public function languages(): array
    {
        $labels = [];
        $path = lang_path('languages.txt');

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$code, $label] = explode('=', $line, 2);
                $code = trim($code);
                $label = trim($label);

                if (strlen($label) >= 2 && str_starts_with($label, '"') && str_ends_with($label, '"')) {
                    $label = stripcslashes(substr($label, 1, -1));
                }

                if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) === 1 && $label !== '') {
                    $labels[$code] = $label;
                }
            }
        }

        $languages = [];

        foreach ($this->available() as $code) {
            $languages[$code] = $labels[$code] ?? strtoupper($code);
        }

        return $languages;
    }

    public function isAvailable(string $locale): bool
    {
        return in_array($locale, $this->available(), true);
    }

    /**
     * Flag emoji per language code.
     *
     * @return array<string, string>
     */
    public function flags(): array
    {
        $stored = [];
        $path = lang_path('flags.txt');

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$code, $flag] = explode('=', $line, 2);
                $code = trim($code);
                $flag = trim($flag);

                if (strlen($flag) >= 2 && str_starts_with($flag, '"') && str_ends_with($flag, '"')) {
                    $flag = stripcslashes(substr($flag, 1, -1));
                }

                if ($code !== '' && $flag !== '') {
                    $stored[$code] = $flag;
                }
            }
        }

        $defaults = [
            'en' => 'ðŸ‡¬ðŸ‡§', 'ar' => 'ðŸ‡¸ðŸ‡¦', 'es' => 'ðŸ‡ªðŸ‡¸', 'fr' => 'ðŸ‡«ðŸ‡·', 'de' => 'ðŸ‡©ðŸ‡ª',
            'tr' => 'ðŸ‡¹ðŸ‡·', 'it' => 'ðŸ‡®ðŸ‡¹', 'pt' => 'ðŸ‡µðŸ‡¹', 'ru' => 'ðŸ‡·ðŸ‡º', 'zh' => 'ðŸ‡¨ðŸ‡³',
            'hi' => 'ðŸ‡®ðŸ‡³', 'fa' => 'ðŸ‡®ðŸ‡·', 'he' => 'ðŸ‡®ðŸ‡±', 'ur' => 'ðŸ‡µðŸ‡°', 'nl' => 'ðŸ‡³ðŸ‡±',
            'pl' => 'ðŸ‡µðŸ‡±', 'ja' => 'ðŸ‡¯ðŸ‡µ', 'ko' => 'ðŸ‡°ðŸ‡·', 'id' => 'ðŸ‡®ðŸ‡©', 'ms' => 'ðŸ‡²ðŸ‡¾',
        ];

        $flags = [];

        foreach ($this->available() as $code) {
            $base = strtolower(strtok($code, '-') ?: $code);
            $flags[$code] = $stored[$code] ?? $defaults[$code] ?? $defaults[$base] ?? 'ðŸŒ';
        }

        return $flags;
    }

    private function normalize(string $locale): ?string
    {
        $locale = str_replace('_', '-', trim($locale));

        return preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/i', $locale) === 1 ? $locale : null;
    }

    private function isPanelRequest(): bool
    {
        try {
            $path = strtolower(trim((string) app('request')->path(), '/'));
        } catch (Throwable) {
            return false;
        }

        if ($path === '') {
            return false;
        }

        foreach (['admin', 'account', 'dashboard', 'profile'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function userLocale(): ?string
    {
        try {
            $user = app('request')->user();

            if ($user === null) {
                return null;
            }

            $locale = $this->normalize((string) ($user->locale ?? ''));

            return $locale !== null && $this->isAvailable($locale) ? $locale : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function siteSessionLocale(): ?string
    {
        try {
            $request = app('request');

            if ($request === null || ! $request->hasSession()) {
                return null;
            }

            $locale = $this->normalize((string) $request->session()->get('site_locale', ''));

            return $locale !== null && $this->isAvailable($locale) ? $locale : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Store the visitor's page language for this session (not the site default).
     */
    public function setSite(string $locale): string
    {
        $normalized = $this->normalize($locale);

        if ($normalized === null || ! $this->isAvailable($normalized)) {
            $normalized = $this->defaultCode();
        }

        try {
            $request = app('request');

            if ($request !== null && $request->hasSession()) {
                $request->session()->put('site_locale', $normalized);
            }
        } catch (Throwable) {
            // Console and queue contexts have no HTTP session.
        }

        app()->setLocale($normalized);

        return $normalized;
    }

    /**
     * Panel language fallback — independent from general.site_language.
     */
    private function panelDefaultCode(): string
    {
        $fallback = $this->normalize((string) config('app.locale')) ?? self::DEFAULT;

        return $this->isAvailable($fallback) ? $fallback : self::DEFAULT;
    }
}

