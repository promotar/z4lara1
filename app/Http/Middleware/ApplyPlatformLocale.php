<?php

namespace App\Http\Middleware;

use App\Platform\Core\Localization\PlatformLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyPlatformLocale
{
    public function __construct(private PlatformLocale $locale) {}

    public function handle(Request $request, Closure $next): Response
    {
        $current = $this->locale->apply();
        $direction = $this->locale->direction($current);

        View::share('platformLocale', $current);
        View::share('platformDirection', $direction);

        $response = $next($request);
        $response->headers->set('Content-Language', $current);

        return $response;
    }
}
