<?php

namespace App\Console\Commands;

use App\Platform\Core\Assets\ManagedAssetFilesystem;
use Illuminate\Console\Command;

class RepairPluginAssetPermissions extends Command
{
    protected $signature = 'platform:repair-plugin-assets';

    protected $description = 'Normalize permissions for the platform-managed plugin asset tree';

    public function handle(ManagedAssetFilesystem $files): int
    {
        $root = public_path('platform/plugins');
        $files->ensureDirectory($root);
        $counts = $files->repairPermissions($root);

        $this->info(sprintf(
            'Plugin asset permissions repaired: %d directories, %d files.',
            $counts['directories'],
            $counts['files'],
        ));

        return self::SUCCESS;
    }
}
