<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the active language prefix to internal anchors/forms on prefixed pages.
 * Runs only when SEO_LOCALE_MODE=prefix and the locale is non-default.
 */
final class PrefixLinkRewriter
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (strtolower((string) config('seo.locale.mode', 'query')) !== 'prefix') {
            return $response;
        }

        $locale = $request->attributes->get('seo_locale');

        if (! is_string($locale) || $locale === '') {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        if (! method_exists($response, 'getContent')) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, '<')) {
            return $response;
        }

        $host = $request->getHost();

        $html = preg_replace_callback(
            '#<(a|form)\b([^>]*?)\b(href|action)=(["\'])([^"\']+)\4([^>]*)>#i',
            function (array $matches) use ($locale, $host): string {
                $tag = $matches[1];
                $before = $matches[2];
                $attribute = $matches[3];
                $quote = $matches[4];
                $url = html_entity_decode($matches[5], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $after = $matches[6];
                $original = $matches[0];

                if ($url === '' || str_starts_with($url, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $url) === 1) {
                    return $original;
                }

                $parts = parse_url($url);

                if (! is_array($parts)) {
                    return $original;
                }

                if (isset($parts['host']) && strtolower((string) $parts['host']) !== strtolower($host)) {
                    return $original;
                }

                $path = $parts['path'] ?? '/';

                if (in_array($path, ['/sitemap.xml', '/robots.txt'], true)
                    || preg_match('#^/(?:admin|api|install|up|build|platform|storage|vendor|livewire|page-builder-assets|certificate-assets)(?:/|$)#i', $path) === 1
                    || str_starts_with($path, '/'.$locale.'/')
                    || $path === '/'.$locale
                    || preg_match('#\.(?:css|js|map|jpg|jpeg|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|pdf|zip)$#i', $path) === 1
                ) {
                    return $original;
                }

                $query = isset($parts['query']) ? '?'.$parts['query'] : '';
                $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
                $newPath = '/'.$locale.($path === '/' ? '' : $path);

                return '<'.$tag.$before.$attribute.'='.$quote.$newPath.$query.$fragment.$quote.$after.'>';
            },
            $html,
        ) ?? $html;

        $response->setContent($html);

        return $response;
    }
}
