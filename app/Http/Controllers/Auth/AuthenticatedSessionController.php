<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user() ?? Auth::user();

        if ($user?->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($user?->hasRole('student')) {
            if (! $this->pluginRouteIsAvailable('lms.front.student.overview')) {
                return redirect()->route('front.account');
            }

            return $this->redirectToAllowedIntended($request, ['my-learning', 'courses'], 'lms.front.student.overview');
        }

        if ($user?->hasRole('instructor')) {
            if (! $this->pluginRouteIsAvailable('lms.front.instructor.courses')) {
                return redirect()->route('front.account');
            }

            return $this->redirectToAllowedIntended($request, ['instructor-dashboard'], 'lms.front.instructor.courses');
        }

        return redirect()->intended(route('front.account', absolute: false));
    }

    private function redirectToAllowedIntended(LoginRequest $request, array $allowedPrefixes, string $fallbackRoute): RedirectResponse
    {
        $intended = (string) $request->session()->pull('url.intended', '');

        if ($intended !== '') {
            $path = trim((string) parse_url($intended, PHP_URL_PATH), '/');

            foreach ($allowedPrefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return redirect()->to($intended);
                }
            }
        }

        return redirect()->route($fallbackRoute);
    }

    private function pluginRouteIsAvailable(string $routeName): bool
    {
        try {
            return app(PluginOwnedPageGuard::class)->isRouteAvailable($routeName);
        } catch (\Throwable) {
            return false;
        }
    }

    public function destroy(Request $request): RedirectResponse    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
