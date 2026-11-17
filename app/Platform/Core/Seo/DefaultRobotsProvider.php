<?php

namespace App\Platform\Core\Seo;

final class DefaultRobotsProvider implements RobotsProvider
{
    public function lines(): array
    {
        $disallow = config('seo.robots.disallow', [
            '/admin',
            '/dashboard',
            '/account',
            '/api',
            '/install',
            '/up',
            '/store/cart',
            '/store/checkout',
            '/store/search',
        ]);

        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach ((array) $disallow as $path) {
            $path = '/'.ltrim(trim((string) $path), '/');

            if ($path !== '/') {
                $lines[] = 'Disallow: '.$path;
            }
        }

        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return $lines;
    }
}
