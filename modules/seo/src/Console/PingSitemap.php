<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapPing;

final class PingSitemap extends Command
{
    protected $signature = 'seo:sitemap:ping {--site= : Site ID}';

    protected $description = 'Notify Google and Bing about the sitemap URL.';

    public function handle(SitemapPing $ping, SeoSiteResolver $sites): int
    {
        $siteId = $this->option('site') !== null ? (int) $this->option('site') : $sites->id();
        $result = $ping->ping($siteId);

        $this->table(
            ['Sitemap', 'Google', 'Bing'],
            [[$result['sitemap'], $result['google'] ?? 'failed', $result['bing'] ?? 'failed']],
        );

        return self::SUCCESS;
    }
}
