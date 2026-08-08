<?php

namespace App\Platform\Core\Access;

use App\Models\User;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

final class UnauthorizedRouteElementFilter
{
    public function __construct(
        private readonly Router $router,
        private readonly RouteAccessGate $access,
    ) {}

    public function filter(Response $response, Request $request, User $user): Response
    {
        $contentType = (string) $response->headers->get('Content-Type');

        if ($this->access->isSuperAdmin($user) || ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return $response;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if (! $loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $response;
        }

        $remove = [];

        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $element) {
            if ($element instanceof DOMElement && ! $this->targetAllowed($element->getAttribute('href'), 'GET', $request, $user)) {
                $remove[] = $element;
            }
        }

        foreach (iterator_to_array($dom->getElementsByTagName('form')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $method = strtoupper($element->getAttribute('method') ?: 'GET');
            foreach ($element->getElementsByTagName('input') as $input) {
                if (strtolower($input->getAttribute('name')) === '_method' && $input->getAttribute('value') !== '') {
                    $method = strtoupper($input->getAttribute('value'));
                    break;
                }
            }

            if (! $this->targetAllowed($element->getAttribute('action'), $method, $request, $user)) {
                $remove[] = $element;
            }
        }

        foreach ($remove as $element) {
            $element->parentNode?->removeChild($element);
        }

        if ($remove !== []) {
            $rendered = $dom->saveHTML();
            $rendered = preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', (string) $rendered) ?? (string) $rendered;
            $response->setContent($rendered);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $response;
    }

    private function targetAllowed(string $target, string $method, Request $request, User $user): bool
    {
        if ($target === '' || str_starts_with($target, '#') || str_starts_with($target, 'javascript:')) {
            return true;
        }

        $parts = parse_url($target);

        if ($parts === false || (isset($parts['host']) && strcasecmp((string) $parts['host'], $request->getHost()) !== 0)) {
            return true;
        }

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        try {
            $probe = Request::create($path.$query, $method);
            $route = $this->router->getRoutes()->match($probe);

            return $this->access->allows($user, $route);
        } catch (\Throwable) {
            return true;
        }
    }
}
