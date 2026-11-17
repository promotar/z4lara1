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
        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && trim($queryLocale) !== '') {
            $normalized = str_replace('_', '-', trim($queryLocale));

            if ($this->locale->isAvailable($normalized)) {
                $this->locale->set($normalized);
            }
        }

        $current = $this->locale->apply();
        $direction = $this->locale->direction($current);

        View::share('platformLocale', $current);
        View::share('platformDirection', $direction);
        View::share('platformLanguages', $this->locale->languages());

        $response = $next($request);

        app(ReplaceTranslationTokens::class)->process($response);

        $response->headers->set('Content-Language', $current);

        return $response;
    }
}
