<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapGenerator;

final class GenerateSitemap extends Command
{
    protected $signature = 'seo:sitemap:generate {--queue : Dispatch the rebuild to the queue} {--site= : Site ID}';

    protected $description = 'Generate or queue the SEO sitemap index and chunks.';

    public function handle(SitemapGenerator $generator, SeoSiteResolver $sites): int
    {
        $siteId = $this->option('site') !== null
            ? (int) $this->option('site')
            : $sites->id();

        if ((bool) $this->option('queue')) {
            $generator->queue($siteId);
            $this->info('Sitemap rebuild queued for site '.$siteId.'.');

            return self::SUCCESS;
        }

        $run = $generator->generate($siteId);

        if ($run->status !== 'completed') {
            $this->error($run->error ?: 'Sitemap generation failed.');

            return self::FAILURE;
        }

        $this->info('Sitemap generated: '.$run->urls_count.' URLs in '.$run->chunks_count.' chunks.');

        return self::SUCCESS;
    }
}
