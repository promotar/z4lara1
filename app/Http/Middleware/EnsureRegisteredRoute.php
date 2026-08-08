<?php

namespace App\Http\Middleware;

use App\Platform\Core\Logs\PlatformLogManager;
use App\Platform\Core\Registry\PlatformRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredRoute
{
    public function __construct(
        private readonly PlatformRegistry $registry,
        private readonly PlatformLogManager $logs,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        $route = $request->route();
        $name = $route?->getName();

        if (! $this->registry->routeIsRegistered($name, $route?->uri(), $request->method())) {
            $this->logs->error('Blocked unregistered route request.', [
                'route' => $name ?: '(unnamed)',
                'uri' => $request->path(),
                'method' => $request->method(),
            ]);

            abort(403, 'This route is not registered in the platform registry.');
        }

        return $next($request);
    }
}
