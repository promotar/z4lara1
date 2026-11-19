<?php

namespace App\Http\Middleware;

use App\Platform\Core\Seo\SeoIndexability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global safety net: error responses and private paths must never be
 * indexable, even when the route never reaches the web middleware group
 * (for example a 404 or the /up health endpoint).
 */
final class SeoErrorMiddleware
{
    public function __construct(private readonly SeoIndexability $indexability)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $noindex = $response->getStatusCode() >= 400 || $this->indexability->shouldNoindex($request);

        if ($noindex) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            if (method_exists($response, 'getContent')) {
                $html = $response->getContent();

                if (is_string($html) && str_contains($html, '</head>')) {
                    $html = preg_replace('#<link[^>]*rel=["\']canonical["\'][^>]*>\s*#i', '', $html) ?? $html;
                    $html = preg_replace('#<link[^>]*hreflang=["\'][^"\']+["\'][^>]*>\s*#i', '', $html) ?? $html;

                    if (preg_match('#<meta\s+name=["\']robots["\']#i', $html) !== 1) {
                        $html = str_replace(
                            '</head>',
                            '<meta name="robots" content="noindex, follow"></head>',
                            $html,
                        );
                    }

                    $response->setContent($html);
                }
            }
        }

        return $response;
    }
}
