<?php

namespace App\Console\Commands;

use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginAssetRegistry;
use Illuminate\Console\Command;

class SyncPluginAssets extends Command
{
    protected $signature = 'platform:sync-plugin-assets {--all : Include disabled plugins}';

    protected $description = 'Register plugin asset manifests and publish their declared files';

    public function handle(PluginRepository $plugins, PluginAssetRegistry $registry): int
    {
        $records = $this->option('all') ? $plugins->all() : $plugins->findActive();

        foreach ($records as $plugin) {
            $result = $registry->synchronize($plugin);
            $count = count($result['copied_files'] ?? []);
            $this->line("{$plugin->slug}: {$count} file(s) published");
        }

        $this->info("Synchronized {$records->count()} plugin(s).");

        return self::SUCCESS;
    }
}
