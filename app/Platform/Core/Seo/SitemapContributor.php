<?php

namespace App\Platform\Core\Seo;

/**
 * Optional sitemap contract implemented by active modules.
 */
interface SitemapContributor
{
    public function module(): string;

    /**
     * @return iterable<int, array{
     *     loc: string,
     *     lastmod?: string|null,
     *     changefreq?: string|null,
     *     priority?: float|null,
     *     alternates?: array<string, string>
     * }>
     */
    public function sitemapEntries(): iterable;
}
