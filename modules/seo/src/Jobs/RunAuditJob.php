<?php

namespace Modules\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Seo\Services\SeoAuditEngine;

final class RunAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly int $siteId,
        public readonly ?int $limit = null,
    ) {
    }

    public function handle(SeoAuditEngine $engine): void
    {
        $engine->run($this->siteId, $this->limit);
    }
}
