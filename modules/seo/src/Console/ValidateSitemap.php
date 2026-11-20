<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapValidator;

final class ValidateSitemap extends Command
{
    protected $signature = 'seo:sitemap:validate {--site= : Site ID}';

    protected $description = 'Validate the latest generated sitemap chunks and record issues.';

    public function handle(SitemapValidator $validator, SeoSiteResolver $sites): int
    {
        $siteId = $this->option('site') !== null
            ? (int) $this->option('site')
            : $sites->id();

        $result = $validator->validate($siteId);

        $this->table(
            ['URLs', 'Chunks', 'Errors', 'Warnings'],
            [[$result['urls'], $result['chunks'], $result['errors'], $result['warnings']]],
        );

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
