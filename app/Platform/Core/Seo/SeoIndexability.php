<?php

namespace App\Platform\Core\Seo;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Central indexability policy. Private, transactional and preview routes are
 * never indexable even if a module forgets to set robots itself.
 */
final class SeoIndexability
{
    public function shouldNoindex(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $route = (string) ($request->route()?->getName() ?? '');

        foreach ((array) config('seo.noindex.routes', []) as $pattern) {
            if (is_string($pattern) && $pattern !== '' && Str::is($pattern, $route)) {
                return true;
            }
        }

        foreach ((array) config('seo.noindex.paths', []) as $pattern) {
            if (is_string($pattern) && $pattern !== '' && Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
