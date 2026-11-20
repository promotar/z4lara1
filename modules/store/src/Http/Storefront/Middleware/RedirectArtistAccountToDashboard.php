<?php

namespace Modules\Store\Http\Storefront\Middleware;

use App\Platform\Core\Services\PluginOwnedPageGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class RedirectArtistAccountToDashboard
{
    public function __construct(private PluginOwnedPageGuard $pluginPages) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (
                $request->user()?->hasRole('artist')
                && $this->pluginPages->isRouteAvailable('exhibitions.dashboard.overview')
            ) {
                return redirect()->route('exhibitions.dashboard.overview');
            }
        } catch (Throwable) {
            // Store customer accounts remain available when Exhibitions is absent or inactive.
        }

        return $next($request);
    }
}
