<?php

namespace Modules\Blog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MoveBlogSeoToHeadStart
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();
        if (! is_string($html) || ! str_contains($html, '<!-- blog-seo:start -->')) {
            return $response;
        }

        if (preg_match('/<!-- blog-seo:start -->(.*?)<!-- blog-seo:end -->/s', $html, $match) !== 1) {
            return $response;
        }

        $seo = trim($match[1]);
        $html = str_replace($match[0], '', $html);
        $patterns = [
            '/(<meta\s+name=["\']csrf-token["\'][^>]*>)/i',
            '/(<meta\s+name=["\']viewport["\'][^>]*>)/i',
            '/(<meta\s+charset=["\'][^"\']+["\'][^>]*>)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                $html = preg_replace($pattern, '$1'."\n        ".$seo, $html, 1) ?? $html;
                $response->setContent($html);

                return $response;
            }
        }

        $response->setContent(preg_replace('/<head([^>]*)>/i', '<head$1>'."\n        ".$seo, $html, 1) ?? $html);

        return $response;
    }
}
