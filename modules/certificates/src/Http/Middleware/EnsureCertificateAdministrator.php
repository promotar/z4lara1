<?php

namespace Modules\Certificates\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCertificateAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        return $next($request);
    }
}
