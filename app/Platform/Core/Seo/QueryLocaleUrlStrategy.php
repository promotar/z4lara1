<?php

namespace App\Platform\Core\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Http\Request;

/**
 * Deterministic query-based locale URLs:
 * - default locale uses the clean URL
 * - other locales use ?lang=xx
 * - no session locale is consulted
 */
final class QueryLocaleUrlStrategy implements LocaleUrlStrategy
{
    public function __construct(private readonly PlatformLocale $locale)
    {
    }

    public function current(Request $request): string
    {
        $value = $request->query('lang');
        $code = is_string($value) ? strtolower(strtok(str_replace('_', '-', trim($value)), '-_') ?: '') : '';

        return $code !== '' && $this->locale->isAvailable($code)
            ? $code
            : $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        $platformDefault = $this->locale->defaultCode();

        if ($platformDefault !== '') {
            return $platformDefault;
        }

        $configured = trim((string) config('seo.locale.default', ''));

        return $configured !== '' && $this->locale->isAvailable($configured)
            ? $configured
            : $platformDefault;
    }

    public function canonical(Request $request): string
    {
        $url = $this->cleanBase($request);
        $current = $this->current($request);

        if ($current !== $this->defaultLocale()) {
            $url .= (str_contains($url, '?') ? '&' : '?').'lang='.urlencode($current);
        }

        return $url;
    }

    public function urlFor(Request $request, string $locale): string
    {
        $query = $request->query();
        unset($query['lang']);

        if ($locale !== $this->defaultLocale()) {
            $query['lang'] = $locale;
        }

        return $this->baseUrl($request).($query !== [] ? '?'.http_build_query($query) : '');
    }

    public function alternates(Request $request): array
    {
        $base = $this->cleanBase($request);
        $default = $this->defaultLocale();
        $alternates = [];

        foreach (array_keys($this->locale->languages()) as $code) {
            $url = $base;

            if ($code !== $default) {
                $url .= (str_contains($url, '?') ? '&' : '?').'lang='.urlencode($code);
            }

            $alternates[$code] = $url;
        }

        $alternates['x-default'] = $base;

        return $alternates;
    }

    public function withLocale(string $url, string $locale): string
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return $url;
        }

        $baseUrl = trim((string) config('seo.canonical.host'));

        if ($baseUrl === '') {
            try {
                $baseUrl = request()->getSchemeAndHttpHost();
            } catch (\Throwable) {
                $baseUrl = url('/');
            }
        }

        $base = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? ($base['scheme'] ?? 'http');
        $host = $parts['host'] ?? ($base['host'] ?? 'localhost');
        $port = isset($parts['port'])
            ? ':'.$parts['port']
            : (isset($base['port']) ? ':'.$base['port'] : '');
        $path = $parts['path'] ?? '/';
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        unset($query['lang']);

        if ($locale !== $this->defaultLocale()) {
            $query['lang'] = $locale;
        }

        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$path.($query !== [] ? '?'.http_build_query($query) : '').$fragment;
    }

    private function baseUrl(Request $request): string
    {
        $host = trim((string) config('seo.canonical.host', ''));

        if ($host !== '') {
            $host = rtrim($host, '/');
            $path = '/'.ltrim($request->path(), '/');

            return $host.($path === '/' ? '/' : $path);
        }

        return $request->url();
    }

    private function cleanBase(Request $request): string
    {
        return $this->baseUrl($request).$this->canonicalQuery($request);
    }

    private function canonicalQuery(Request $request): string
    {
        $page = $request->query('page');

        if (! is_scalar($page) || (int) $page <= 1) {
            return '';
        }

        return '?page='.(int) $page;
    }
}
