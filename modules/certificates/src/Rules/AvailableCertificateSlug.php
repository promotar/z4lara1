<?php

namespace Modules\Certificates\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AvailableCertificateSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || str_contains($value, '/')) {
            return;
        }

        $reserved = ['certificate-search', 'certificate-media', 'certificate-assets', 'admin', 'api', 'install', 'login', 'logout', 'register', 'storage', 'up', 'robots.txt', 'sitemap.xml'];
        if (in_array(mb_strtolower($value), $reserved, true)) {
            $fail('This path is reserved by the platform.');
            return;
        }

        $candidate = Request::create(url('/'.rawurlencode($value)), 'GET');
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'certificates.')) {
                continue;
            }
            if ($route->matches($candidate)) {
                $fail('This path is used by another page or feature.');
                return;
            }
        }

        if (Schema::hasTable('platform_pages') && DB::table('platform_pages')->where('slug', $value)->exists()) {
            $fail('This path is used by another page.');
        }
    }
}
