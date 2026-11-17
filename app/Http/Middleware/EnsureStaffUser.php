<?php

namespace App\Http\Middleware;

use App\Platform\Core\Services\PluginOwnedPageGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $hasStaffRole = $user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee']);
        $hasAdminPermission = $user->getAllPermissions()
            ->contains(fn ($permission): bool => str_starts_with((string) $permission->name, 'route.admin.')
                || $permission->name === 'route.dashboard');

        if (! $hasStaffRole && ! $hasAdminPermission) {
            if ($user->hasRole('student') && $this->pluginRouteIsAvailable('lms.front.student.overview')) {
                return redirect()->route('lms.front.student.overview');
            }

            if ($user->hasRole('instructor') && $this->pluginRouteIsAvailable('lms.front.instructor.courses')) {
                return redirect()->route('lms.front.instructor.courses');
            }

            return redirect()->route('front.account');
        }

        return $next($request);
    }

    private function pluginRouteIsAvailable(string $routeName): bool
    {
        try {
            return app(PluginOwnedPageGuard::class)->isRouteAvailable($routeName);
        } catch (\Throwable) {
            return false;
        }
    }
}
