<?php

namespace Modules\Certificates\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Certificates\Models\Certificate;

class CertificateRoutes
{
    public static function pattern(): string
    {
        // Numeric misses are handled here as well, so automated guesses share the limit.
        $patterns = ['[0-9]+(?:-[0-9]+)*'];
        if (Schema::hasTable('certificates')) {
            foreach (Certificate::query()->pluck('slug') as $slug) {
                if (preg_match('/^[0-9]+(?:-[0-9]+)*$/D', $slug) !== 1) {
                    $patterns[] = preg_quote($slug, '#');
                }
            }
        }

        return '(?:'.implode('|', $patterns).')';
    }

    public static function refresh(): void
    {
        if ($route = Route::getRoutes()->getByName('certificates.show')) {
            // Laravel caches a route's compiled regex after its first match.
            // Replace our own route instead of changing a compiled core object.
            $replacement = new \Illuminate\Routing\Route($route->methods(), $route->uri(), $route->getAction());
            $replacement->setRouter(app('router'))->setContainer(app());
            $replacement->where('certificateSlug', static::pattern());
            Route::getRoutes()->add($replacement);
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        }

        // Cached database-derived patterns must not keep old slugs alive after editing.
        if (is_file(app()->getCachedRoutesPath())) {
            Artisan::call('route:clear');
            app()->forgetInstance('routes.cached');
        }
    }
}
