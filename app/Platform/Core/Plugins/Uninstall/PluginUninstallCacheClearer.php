<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\Services\PluginCacheCleaner;

class PluginUninstallCacheClearer
{
    public function __construct(
        private readonly PluginCacheCleaner $cache,
    ) {
        //
    }

    public function clear(): void
    {
        $this->cache->clear();
    }
}
