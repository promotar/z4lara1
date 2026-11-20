<?php

namespace Modules\Certificates\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LimitCertificateAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = hash('sha256', (string) $request->ip());

        $key = 'certificates:attempts:'.$ip;
        $allowed = Cache::lock('certificates:attempt-lock:'.$ip, 5)->block(3, static function () use ($key, $request, $ip): bool {
            if ($request->routeIs('certificates.show')) {
                $nonce = $request->session()->pull('certificates.view_grant');
                // Consume server-side under the IP lock; parallel requests may
                // otherwise read the same not-yet-saved session grant twice.
                $grant = is_string($nonce) ? Cache::pull('certificates:view-grant:'.$nonce) : null;
                if (is_array($grant)
                    && ($grant['slug'] ?? null) === $request->route('certificateSlug')
                    && ($grant['ip'] ?? null) === $ip) {
                    return true;
                }
            }
            if (RateLimiter::tooManyAttempts($key, 30)) {
                return false;
            }
            RateLimiter::hit($key, 60);
            return true;
        });

        if (! $allowed) {
            $seconds = max(1, RateLimiter::availableIn($key));
            return response()->view('certificates::public.message', [
                'title' => __('certificates::message.too_many_title'),
                'message' => __('certificates::message.too_many', ['seconds' => $seconds]),
            ], 429, ['Retry-After' => (string) $seconds]);
        }

        return $next($request);
    }
}
