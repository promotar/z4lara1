<?php

namespace Modules\Exhibition\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectArtistFromStaffDashboard
{
    public function __construct(
        private ArtistApplicationService $applications,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->is('dashboard')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null || $user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])) {
            return $next($request);
        }

        $userId = (int) $user->getAuthIdentifier();
        if ($this->applications->forUser($userId) === null) {
            return $next($request);
        }

        return redirect()->route('exhibitions.dashboard.overview');
    }
}
