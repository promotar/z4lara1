<?php

namespace App\Platform\Core\Seo;

/**
 * Optional contract for a provider that serves a sitemap index.
 */
interface SitemapIndexProvider
{
    /**
     * @return array<int, array{loc: string, lastmod?: string|null}>
     */
    public function sitemapIndex(): array;
}
