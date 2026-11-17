<?php

namespace App\Http\Middleware;

use App\Platform\Core\Localization\PlatformLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rewrites /{locale}/... public URLs before routing and redirects legacy
 * ?lang= queries to the prefix form. Only active with SEO_LOCALE_MODE=prefix.
 */
final readonly class PrefixLocaleMiddleware
{
    public function __construct(private PlatformLocale $locale)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (strtolower((string) config('seo.locale.mode', 'query')) !== 'prefix') {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($this->skipped($path)) {
            return $next($request);
        }

        $default = $this->locale->defaultCode();
        $available = array_map('strval', array_keys($this->locale->languages()));
        $queryLang = $this->queryLocale($request);

        if (in_array($request->method(), ['GET', 'HEAD'], true) && $queryLang !== null) {
            $targetLocale = $queryLang === $default ? $default : $queryLang;
            $clean = $this->stripPrefix($path, $default, $available);
            $targetPath = $targetLocale === $default ? $clean : '/'.$targetLocale.($clean === '/' ? '' : $clean);
            $query = $request->query();
            unset($query['lang']);

            return redirect()->to(url($targetPath.($query !== [] ? '?'.http_build_query($query) : '')), 301);
        }

        foreach ($available as $prefix) {
            if ($prefix === '' || $prefix === $default) {
                continue;
            }

            if ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/')) {
                $newPath = $path === '/'.$prefix ? '/' : substr($path, strlen($prefix) + 1);
                $newPath = '/'.ltrim((string) $newPath, '/');
                $query = $request->query();
                unset($query['lang']);

                $response = $next($this->rewrite($request, $newPath, $prefix, $query));

                return $this->prefixRedirect($response, $prefix, $request);
            }
        }

        if ($path === '/'.$default || str_starts_with($path, '/'.$default.'/')) {
            $clean = $this->stripPrefix($path, $default, $available);

            return redirect()->to(url($clean), 301);
        }

        return $next($request);
    }

    private function rewrite(Request $request, string $path, string $locale, array $query): Request
    {
        $uri = $path.($query !== [] ? '?'.http_build_query($query) : '');
        $server = $request->server->all();
        $new = Request::create(
            $uri,
            $request->getMethod(),
            $request->request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
        $new->headers->replace($request->headers->all());
        $new->server->set('REQUEST_URI', $uri);
        $new->attributes->set('seo_locale', $locale);
        $new->attributes->set('seo_prefix', $locale);
        $new->attributes->set('seo_original_path', $path);

        app()->instance('request', $new);
        Facade::clearResolvedInstance('request');

        return $new;
    }

    /**
     * Keep internal redirects inside the active locale when a module builds a
     * redirect with route() outside the prefix-aware URL strategy.
     */
    private function prefixRedirect(Response $response, string $locale, Request $request): Response
    {
        if (! $response->isRedirect()) {
            return $response;
        }

        $location = trim((string) $response->headers->get('Location', ''));

        if ($location === '') {
            return $response;
        }

        $parts = parse_url($location);

        if (! is_array($parts)) {
            return $response;
        }

        if (isset($parts['host']) && strtolower((string) $parts['host']) !== strtolower($request->getHost())) {
            return $response;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');

        if ($this->skipped($path)) {
            return $response;
        }

        if ($path === '/'.$locale || str_starts_with($path, '/'.$locale.'/')) {
            return $response;
        }

        $target = '/'.$locale.($path === '/' ? '' : $path);
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if (isset($parts['scheme'], $parts['host'])) {
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';
            $target = $parts['scheme'].'://'.$parts['host'].$port.$target;
        }

        $response->headers->set('Location', $target.$query.$fragment);

        return $response;
    }

    private function queryLocale(Request $request): ?string
    {
        $value = $request->query('lang');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $code = strtolower(strtok(str_replace('_', '-', trim($value)), '-_') ?: '');

        return $code !== '' && $this->locale->isAvailable($code) ? $code : null;
    }

    /**
     * @param  array<int, string>  $available
     */
    private function stripPrefix(string $path, string $default, array $available): string
    {
        foreach ($available as $prefix) {
            if ($prefix === '') {
                continue;
            }

            if ($path === '/'.$prefix) {
                return '/';
            }

            if (str_starts_with($path, '/'.$prefix.'/')) {
                return '/'.ltrim(substr($path, strlen($prefix) + 1), '/');
            }
        }

        return $path === '' ? '/' : $path;
    }

    private function skipped(string $path): bool
    {
        foreach ([
            '/admin',
            '/api',
            '/install',
            '/up',
            '/build',
            '/platform',
            '/storage',
            '/vendor',
            '/livewire',
            '/sitemap.xml',
            '/robots.txt',
            '/.well-known',
        ] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
