<?php

namespace App\Http\Middleware;

use App\Platform\Core\Seo\SeoData;
use App\Platform\Core\Seo\SeoHeadInjector;
use App\Platform\Core\Seo\SeoManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Shares Core-owned SEO data with the frontend layout and applies a safe
 * noindex header to error responses.
 */
final class SeoMiddleware
{
    public function __construct(
        private readonly SeoManager $seo,
        private readonly SeoHeadInjector $injector,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $seo = null;

        try {
            $seo = $this->seo->forRequest($request);
            View::share('seo', $seo);
        } catch (Throwable $exception) {
            report($exception);
        }

        $response = $next($request);

        if ($seo instanceof SeoData) {
            $this->injector->inject($response, $seo);

            if (! $seo->robots->isIndexable()) {
                $response->headers->set('X-Robots-Tag', $seo->robots->toMeta());
            }
        }

        if ($response->getStatusCode() >= 400) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
