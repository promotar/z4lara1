<?php

namespace App\Platform\Core\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Http\Request;

/**
 * Prefix locale policy:
 * - default locale uses the clean path (/)
 * - other locales use /{locale}/...
 * - canonical/hreflang use the prefixed forms
 */
final class PrefixLocaleUrlStrategy implements LocaleUrlStrategy
{
    public function __construct(private readonly PlatformLocale $locale)
    {
    }

    public function current(Request $request): string
    {
        $prefix = $request->attributes->get('seo_locale');

        if (is_string($prefix) && $prefix !== '' && $this->locale->isAvailable($prefix)) {
            return $prefix;
        }

        $value = $request->query('lang');
        $code = is_string($value) ? strtolower(strtok(str_replace('_', '-', trim($value)), '-_') ?: '') : '';

        return $code !== '' && $this->locale->isAvailable($code)
            ? $code
            : $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        return $this->locale->defaultCode();
    }

    public function canonical(Request $request): string
    {
        $path = $this->pathFor($this->current($request), $this->cleanPath($request));
        $url = $this->baseUrl($request).$path;
        $page = $request->query('page');

        if (is_scalar($page) && (int) $page > 1) {
            $url .= '?page='.(int) $page;
        }

        return $url;
    }

    public function urlFor(Request $request, string $locale): string
    {
        $query = $request->query();
        unset($query['lang']);

        return $this->baseUrl($request).$this->pathFor($locale, $this->cleanPath($request))
            .($query !== [] ? '?'.http_build_query($query) : '');
    }

    public function alternates(Request $request): array
    {
        $base = $this->baseUrl($request).$this->cleanPath($request);
        $alternates = [];

        foreach (array_keys($this->locale->languages()) as $code) {
            $alternates[(string) $code] = $this->baseUrl($request).$this->pathFor((string) $code, $this->cleanPath($request));
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
        $path = $this->cleanPathFromString($parts['path'] ?? '/');
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        unset($query['lang']);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$this->pathFor($locale, $path)
            .($query !== [] ? '?'.http_build_query($query) : '').$fragment;
    }

    private function cleanPath(Request $request): string
    {
        $stored = $request->attributes->get('seo_original_path');

        return is_string($stored) && $stored !== ''
            ? $this->cleanPathFromString($stored)
            : $this->cleanPathFromString('/'.ltrim($request->path(), '/'));
    }

    private function cleanPathFromString(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        foreach (array_keys($this->locale->languages()) as $code) {
            $code = (string) $code;

            if ($code === '') {
                continue;
            }

            if ($path === '/'.$code) {
                return '/';
            }

            if (str_starts_with($path, '/'.$code.'/')) {
                $path = substr($path, strlen($code) + 1);
                break;
            }
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return $path === '' ? '/' : $path;
    }

    private function pathFor(string $locale, string $cleanPath): string
    {
        $cleanPath = $this->cleanPathFromString($cleanPath);

        if ($locale === $this->defaultLocale()) {
            return $cleanPath;
        }

        return '/'.$locale.($cleanPath === '/' ? '' : $cleanPath);
    }

    private function baseUrl(Request $request): string
    {
        $host = trim((string) config('seo.canonical.host', ''));

        return $host !== ''
            ? rtrim($host, '/')
            : $request->getSchemeAndHttpHost();
    }
}
