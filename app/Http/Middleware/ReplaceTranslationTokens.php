<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces translation tokens written directly in Blade, for example:
 *
 *   <p>{.login}</p>
 *
 * The token ".login" resolves to core.login from the text language file.
 * Script and style blocks are left untouched.
 */
final class ReplaceTranslationTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->process($response);

        return $response;
    }

    public function process(Response $response): void
    {
        if (! method_exists($response, 'getContent')) {
            return;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, '{.')) {
            return;
        }

        $response->setContent($this->replace($content));
    }

    private function replace(string $html): string
    {
        $parts = preg_split(
            '/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }

            $parts[$index] = preg_replace_callback('/\{\.([A-Za-z0-9_.\-]+)\}/', static function (array $matches): string {
                $key = 'core.'.ltrim($matches[1], '.');
                $translator = app('translator');

                return $translator->has($key) ? (string) $translator->get($key) : $matches[0];
            }, $part);
        }

        return implode('', $parts);
    }
}
