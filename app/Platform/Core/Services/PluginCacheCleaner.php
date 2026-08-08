<?php

namespace App\Platform\Core\Services;

use Illuminate\Support\Facades\Artisan;

class PluginCacheCleaner
{
    public function clear(): void
    {
        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            Artisan::call($command);
        }
    }
}
