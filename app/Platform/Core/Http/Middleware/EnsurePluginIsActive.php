<?php

namespace App\Platform\Core\Http\Middleware;

use App\Platform\Core\Services\PluginRuntimeGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePluginIsActive
{
    public function __construct(private readonly PluginRuntimeGate $gate) {}

    public function handle(Request $request, Closure $next, ?string $slug = null): Response
    {
        $slug = $slug ?: (string) ($request->route()?->defaults['_plugin_slug'] ?? '');

        if ($slug !== '' && ! $this->gate->allows($slug)) {
            abort(404, 'Plugin route is not available.');
        }

        return $next($request);
    }
}
