<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Support\Str;

/**
 * Central policy for URLs allowed into a sitemap.
 */
final class SitemapUrlPolicy
{
    public function __construct(
        private readonly SeoRedirectResolver $redirects,
        private readonly PlatformLocale $locale,
        private readonly SeoContentTypeRegistry $registry,
        private readonly ContentSeoService $contentSeo,
    ) {
    }

    public function allows(string $url, int $siteId): bool
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $siteUrl = (string) (config('seo.canonical.host') ?: config('app.url', 'http://localhost'));
        $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));

        if ($host === '' || ($siteHost !== '' && $host !== $siteHost)) {
            return false;
        }

        if (parse_url($url, PHP_URL_QUERY) !== null) {
            return false;
        }

        $path = '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $cleanPath = $this->withoutLocalePrefix($path);
        $relative = ltrim($cleanPath, '/');

        foreach ((array) config('seo.noindex.paths', []) as $pattern) {
            $pattern = trim((string) $pattern);
            $pattern = ltrim($pattern, '/');

            if ($pattern === '') {
                continue;
            }

            if (Str::is($pattern, $relative) || Str::is('/'.$pattern, $cleanPath)) {
                return false;
            }
        }

        $redirects = $this->redirects->redirects($siteId);
        if ($this->redirects->match($redirects, $cleanPath) !== null) {
            return false;
        }

        $record = $this->registry->recordForUrl($url);
        if ($record !== null && $this->contentSeo->isNoindex($record['type'], $record['id'])) {
            return false;
        }

        return true;
    }

    private function withoutLocalePrefix(string $path): string
    {
        foreach (array_keys($this->locale->languages()) as $code) {
            $code = (string) $code;

            if ($code === '') {
                continue;
            }

            if ($path === '/'.$code) {
                return '/';
            }

            if (str_starts_with($path, '/'.$code.'/')) {
                return substr($path, strlen($code) + 1);
            }
        }

        return $path;
    }
}
