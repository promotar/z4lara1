<?php

namespace App\Http\Middleware;

use App\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformInstalled
{
    public function __construct(private readonly InstallationState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up') || $request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        if (! $this->state->installed()) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
