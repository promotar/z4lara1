<?php

namespace Modules\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Seo\Http\Controllers\SitemapSectionsController;
use Modules\Seo\Services\SeoSitemapProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves the plugin sitemap at the core paths without modifying core files:
 *   /sitemap.xml      -> styled per-content-type index
 *   /sitemap/{n}.xml  -> styled chunk
 */
final class SitemapOverrideMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        try {
            if ($path === '/sitemap.xml') {
                $sections = app(SeoSitemapProvider::class)->sitemapSections();

                if ($sections === []) {
                    return $next($request);
                }

                return app(SitemapSectionsController::class)->index();
            }

            if (preg_match('#^/sitemap/(\d+)\.xml$#', $path, $matches) === 1) {
                $xml = app(SeoSitemapProvider::class)->sitemapChunk((int) $matches[1]);

                if ($xml === null || trim($xml) === '') {
                    return $next($request);
                }

                return app(SitemapSectionsController::class)->legacyChunk((int) $matches[1]);
            }
        } catch (Throwable $exception) {
            report($exception);

            return $next($request);
        }

        return $next($request);
    }
}
