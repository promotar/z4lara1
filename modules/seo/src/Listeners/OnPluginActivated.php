<?php

namespace Modules\Seo\Listeners;

use App\Platform\Core\Models\Plugin;
use Modules\Seo\Services\SeoModuleSyncManager;

final class OnPluginActivated
{
    public function __construct(private readonly SeoModuleSyncManager $sync)
    {
    }

    public function handle(Plugin $plugin): void
    {
        if ($plugin->slug === 'seo') {
            return;
        }

        $this->sync->activate((string) $plugin->slug);
    }
}
