<?php

namespace App\Platform\Core\Seo;

interface SitemapProvider
{
    /**
     * @return iterable<int, array{
     *     loc: string,
     *     lastmod?: string|null,
     *     changefreq?: string|null,
     *     priority?: float|null,
     *     alternates?: array<string, string>
     * }>
     */
    public function entries(): iterable;
}
