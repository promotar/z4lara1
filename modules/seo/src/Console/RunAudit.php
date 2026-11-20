<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Services\SeoAuditEngine;
use Modules\Seo\Services\SeoSiteResolver;

final class RunAudit extends Command
{
    protected $signature = 'seo:audit {--site= : Site ID} {--limit= : Maximum number of URLs}';

    protected $description = 'Run the SEO audit against sitemap URLs and record findings.';

    public function handle(SeoAuditEngine $engine, SeoSiteResolver $sites): int
    {
        $siteId = $this->option('site') !== null ? (int) $this->option('site') : $sites->id();
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $result = $engine->run($siteId, $limit);

        $this->table(
            ['URLs', 'Errors', 'Warnings'],
            [[$result['urls'], $result['errors'], $result['warnings']]],
        );

        return self::SUCCESS;
    }
}
