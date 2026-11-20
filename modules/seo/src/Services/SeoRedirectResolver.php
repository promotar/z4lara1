<?php

namespace Modules\Seo\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Seo\Models\SeoRedirect;
use Throwable;

/**
 * Resolves public redirects without trusting user input as a redirect target.
 *
 * Rules:
 * - Exact paths win before wildcard paths.
 * - Higher priority wins inside each group.
 * - External targets must be allow-listed.
 * - Chains are flattened up to the configured hop limit.
 * - Loops are never emitted.
 */
final class SeoRedirectResolver
{
    public static function cacheKey(int $siteId): string
    {
        return 'seo:redirects:'.$siteId;
    }

    public static function flush(int $siteId): void
    {
        Cache::forget(self::cacheKey($siteId));
    }

    /**
     * @return array<int, SeoRedirect>
     */
    public function redirects(int $siteId): array
    {
        try {
            return Cache::remember(
                self::cacheKey($siteId),
                (int) config('seo.redirects.cache_ttl', 300),
                static fn (): array => SeoRedirect::query()
                    ->where('site_id', $siteId)
                    ->where('is_active', true)
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->get()
                    ->all(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<int, SeoRedirect>  $redirects
     */
    public function match(array $redirects, string $path): ?SeoRedirect
    {
        $path = $this->normalizePath($path);

        foreach ($redirects as $redirect) {
            if ($this->normalizePath((string) $redirect->from_path) === $path) {
                return $redirect;
            }
        }

        foreach ($redirects as $redirect) {
            $from = $this->normalizePath((string) $redirect->from_path);

            if (str_contains($from, '*') && Str::is($from, $path)) {
                return $redirect;
            }
        }

        return null;
    }

    /**
     * @param  array<int, SeoRedirect>  $redirects
     * @return array{status: int, url: string|null, ids: array<int, int>}|null
     */
    public function resolve(array $redirects, SeoRedirect $match, Request $request): ?array
    {
        $requestPath = $this->normalizePath($request->path());
        $current = $match;
        $ids = [];
        $visited = [$requestPath];
        $status = (int) $match->status_code;
        $maxHops = max(1, (int) config('seo.redirects.max_hops', 5));

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            $ids[] = (int) $current->id;

            if ((int) $current->status_code === 410) {
                return ['status' => 410, 'url' => null, 'ids' => $ids];
            }

            $target = $this->target($current);

            if ($target['external']) {
                if (! $this->externalAllowed($target['url'], $request->getHost())) {
                    return null;
                }

                return [
                    'status' => $status,
                    'url' => $this->appendQuery($target['url'], $request, (string) $match->query_behavior),
                    'ids' => $ids,
                ];
            }

            $targetPath = $this->normalizePath($target['path']);

            if (in_array($targetPath, $visited, true)) {
                return null;
            }

            $next = $this->match($redirects, $targetPath);

            if ($next === null || $hop >= $maxHops) {
                $targetUrl = url($target['path']);
                if (filled($target['query'] ?? null)) {
                    $targetUrl .= '?'.ltrim((string) $target['query'], '?');
                }

                return [
                    'status' => $status,
                    'url' => $this->appendQuery($targetUrl, $request, (string) $match->query_behavior),
                    'ids' => $ids,
                ];
            }

            if (in_array((int) $next->id, $ids, true)) {
                return null;
            }

            $visited[] = $targetPath;
            $current = $next;
        }

        return null;
    }

    /**
     * @param  array<int, SeoRedirect>  $redirects
     */
    public function wouldLoop(array $redirects, string $from, string $to): bool
    {
        $from = $this->normalizePath($from);
        $target = $this->pathFromTarget($to);

        if ($target === null) {
            return false;
        }

        $visited = [$from];
        $path = $target;
        $maxHops = max(1, (int) config('seo.redirects.max_hops', 5));

        for ($hop = 0; $hop < $maxHops; $hop++) {
            if (in_array($path, $visited, true)) {
                return true;
            }

            $visited[] = $path;
            $next = $this->match($redirects, $path);

            if ($next === null || (int) $next->status_code === 410) {
                return false;
            }

            $nextTarget = $this->target($next);

            if ($nextTarget['external']) {
                return false;
            }

            $path = $this->normalizePath($nextTarget['path']);
        }

        return false;
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.ltrim($path, '/');
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function isExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', trim($url));
    }

    public function externalAllowed(string $url, ?string $requestHost = null): bool
    {
        if (! $this->isExternal($url)) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $requestHost = strtolower((string) ($requestHost ?: request()->getHost()));

        if ($host === $requestHost || $host === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
            return true;
        }

        foreach ((array) config('seo.redirects.external_allowlist', []) as $allowed) {
            $allowed = strtolower(trim((string) $allowed));

            if ($allowed === '') {
                continue;
            }

            if (str_starts_with($allowed, '*.')) {
                $suffix = substr($allowed, 1);
                if (str_ends_with($host, $suffix)) {
                    return true;
                }

                continue;
            }

            if ($host === $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{external: bool, path: string, query: string|null, url: string}
     */
    private function target(SeoRedirect $redirect): array
    {
        $to = trim((string) $redirect->to_path);

        if ($this->isExternal($to)) {
            return ['external' => true, 'path' => '', 'query' => null, 'url' => $to];
        }

        if (! str_starts_with($to, '/')) {
            $to = '/'.ltrim($to, '/');
        }

        $path = (string) parse_url($to, PHP_URL_PATH);
        $query = parse_url($to, PHP_URL_QUERY);
        $path = $this->normalizePath($path !== '' ? $path : '/');

        return [
            'external' => false,
            'path' => $path,
            'query' => is_string($query) && $query !== '' ? $query : null,
            'url' => $to,
        ];
    }

    private function pathFromTarget(string $target): ?string
    {
        $target = trim($target);

        if ($target === '' || $this->isExternal($target)) {
            return null;
        }

        if (! str_starts_with($target, '/')) {
            $target = '/'.ltrim($target, '/');
        }

        $path = (string) parse_url($target, PHP_URL_PATH);

        return $this->normalizePath($path !== '' ? $path : '/');
    }

    private function appendQuery(string $url, Request $request, string $behavior): string
    {
        $query = trim((string) $request->getQueryString());

        if ($query === '' || $behavior === 'ignore') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }
}
