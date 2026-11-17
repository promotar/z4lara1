<?php

namespace App\Platform\Core\Seo;

/**
 * Optional contract for providers that serve pre-built sitemap chunks.
 */
interface SitemapChunkProvider
{
    public function sitemapChunk(int $chunk): ?string;
}
