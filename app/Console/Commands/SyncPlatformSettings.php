<?php

namespace App\Console\Commands;

use App\Platform\Core\Services\SettingsDefinitionSynchronizer;
use Illuminate\Console\Command;

final class SyncPlatformSettings extends Command
{
    protected $signature = 'platform:sync-settings';

    protected $description = 'Synchronize code-defined platform setting metadata without replacing stored values';

    public function handle(SettingsDefinitionSynchronizer $synchronizer): int
    {
        $count = $synchronizer->synchronize();
        $this->info("Synchronized {$count} platform setting definitions.");

        return self::SUCCESS;
    }
}
