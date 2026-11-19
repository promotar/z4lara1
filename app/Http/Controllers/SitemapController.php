<?php

namespace App\Http\Controllers;

use App\Platform\Core\Seo\SitemapChunkProvider;
use App\Platform\Core\Seo\SitemapIndexProvider;
use App\Platform\Core\Seo\SitemapProvider;
use App\Platform\Core\Seo\SitemapXmlRenderer;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(SitemapProvider $provider, SitemapXmlRenderer $renderer): Response
    {
        if ($provider instanceof SitemapIndexProvider) {
            $sitemaps = $provider->sitemapIndex();

            if ($sitemaps !== []) {
                return $this->xml($renderer->sitemapIndex($sitemaps));
            }
        }

        return $this->xml($renderer->urlset($provider->entries()));
    }

    public function chunk(int $chunk, SitemapProvider $provider): Response
    {
        abort_unless($provider instanceof SitemapChunkProvider, 404);

        $xml = $provider->sitemapChunk($chunk);
        abort_if($xml === null || trim($xml) === '', 404);

        return $this->xml($xml);
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.sitemap.cache_ttl', 300),
        ]);
    }
}
