<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Backups\BackupManager;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Carbon;

class PluginInstallBackup
{
    public function __construct(
        private readonly PluginMenuRegistry $menus,
        private readonly BackupManager $backups,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PluginManifest $manifest, string $pluginPath, ?Plugin $existing): array
    {
        $checkpoint = [
            'created_at' => Carbon::now()->toIso8601String(),
            'plugin_path' => $pluginPath,
            'slug' => $manifest->slug,
            'existing_plugin' => $existing?->getAttributes(),
            'existing_menus' => $this->menus->get($manifest->slug),
        ];

        $directory = storage_path('app/platform/plugin-install-checkpoints');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.Carbon::now()->format('YmdHis').'-'.$manifest->slug.'.json';
        file_put_contents($path, json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $backup = $this->backups->createCheckpoint('plugin.install', 'plugin', $manifest->slug, $checkpoint, 'plugin-install');
        $this->backups->addRestoreNote($backup, 'Restore guidance: inspect the checkpoint metadata, rollback plugin migrations if they ran, restore prior plugin and menu records from metadata, and clear application caches.');
        $this->backups->markCheckpointCompleted($backup);

        $checkpoint['path'] = $path;
        $checkpoint['backup_checkpoint_id'] = $backup->id;

        return $checkpoint;
    }
}
