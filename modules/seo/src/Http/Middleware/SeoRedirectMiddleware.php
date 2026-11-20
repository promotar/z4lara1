<?php

namespace Modules\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoRedirectResolver;
use Modules\Seo\Services\SeoSiteResolver;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Executes stored redirects for public GET/HEAD requests.
 *
 * Only registered while the SEO plugin is active.
 */
final class SeoRedirectMiddleware
{
    public function __construct(
        private readonly SeoSiteResolver $sites,
        private readonly SeoRedirectResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $this->eligible($request)) {
            return $next($request);
        }

        try {
            $siteId = $this->sites->id();
            $redirects = $this->resolver->redirects($siteId);
            $match = $this->resolver->match($redirects, $request->path());

            if ($match === null) {
                return $next($request);
            }

            $resolved = $this->resolver->resolve($redirects, $match, $request);

            if ($resolved === null) {
                return $next($request);
            }

            $this->recordHits($resolved['ids']);

            if ($resolved['status'] === 410) {
                return response('', 410);
            }

            return redirect()->to((string) $resolved['url'], $resolved['status']);
        } catch (Throwable $exception) {
            report($exception);

            return $next($request);
        }
    }

    private function eligible(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

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
                return false;
            }
        }

        return ! (bool) preg_match('#\.(?:css|js|map|jpg|jpeg|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|pdf|zip)$#i', $path);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function recordHits(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return;
        }

        SeoRedirect::query()
            ->whereIn('id', $ids)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);
    }
}
