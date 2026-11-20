<?php

namespace Modules\Seo\Listeners;

use App\Platform\Core\Models\Plugin;
use Modules\Seo\Services\SeoModuleSyncManager;

final class OnPluginUninstalled
{
    public function __construct(private readonly SeoModuleSyncManager $sync)
    {
    }

    public function handle(Plugin $plugin, array $result = []): void
    {
        if ($plugin->slug === 'seo') {
            return;
        }

        // Uninstall archives SEO data. A separate seo:purge is required for
        // permanent deletion after the retention window.
        $this->sync->archive((string) $plugin->slug);
    }
}
