<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Platform\Core\Access\RouteAccessGate;
use App\Platform\Core\Access\UnauthorizedRouteElementFilter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceRoutePermission
{
    public function __construct(
        private readonly RouteAccessGate $access,
        private readonly UnauthorizedRouteElementFilter $elements,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        $route = $request->route();
        $user = $request->user();

        $this->access->beginRequest($user);

        if ($route !== null && ! $this->access->allows($user, $route)) {
            if ($user === null) {
                throw new AuthenticationException;
            }

            abort(403, 'You do not have permission to access this route.');
        }

        $response = $next($request);

        return $user instanceof User
            ? $this->elements->filter($response, $request, $user)
            : $response;
    }
}
