<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PluginMetadataRemover
{
    /**
     * @return array<string, int>
     */
    public function remove(
        Plugin $plugin,
        PluginManifest $manifest,
        int $exceptOperationId,
    ): array {
        $removed = [
            'registry_entries' => 0,
            'plugin_updates' => 0,
            'backup_checkpoints' => 0,
            'previous_operation_logs' => 0,
        ];

        if (Schema::hasTable('platform_plugin_registry_entries')) {
            $removed['registry_entries'] = DB::table('platform_plugin_registry_entries')
                ->where('plugin_slug', $plugin->slug)
                ->delete();
        }

        if (Schema::hasTable('plugin_updates')) {
            $updates = DB::table('plugin_updates');

            if (Schema::hasColumn('plugin_updates', 'plugin_id')) {
                $updates->where('plugin_id', $plugin->id);
            }

            if (Schema::hasColumn('plugin_updates', 'plugin_slug')) {
                if (Schema::hasColumn('plugin_updates', 'plugin_id')) {
                    $updates->orWhere('plugin_slug', $plugin->slug);
                } else {
                    $updates->where('plugin_slug', $plugin->slug);
                }
            }

            $removed['plugin_updates'] = $updates->delete();
        }

        if (Schema::hasTable('backup_checkpoints')) {
            $checkpoints = DB::table('backup_checkpoints')
                ->where('target_type', 'plugin')
                ->where('target_slug', $plugin->slug)
                ->get(['id', 'path']);

            foreach ($checkpoints as $checkpoint) {
                $this->deleteCheckpointFile($checkpoint->path);
            }

            $removed['backup_checkpoints'] = DB::table('backup_checkpoints')
                ->whereIn('id', $checkpoints->pluck('id')->all())
                ->delete();
        }

        if (Schema::hasTable('operation_logs')) {
            $prefixes = array_values((array) data_get(
                $manifest->manifest,
                'uninstall.operation_target_prefixes',
                [],
            ));
            $logs = DB::table('operation_logs')
                ->where(function ($query) use ($plugin, $prefixes): void {
                    $query->where('target_slug', $plugin->slug);

                    foreach ($prefixes as $prefix) {
                        $query->orWhere('target_slug', 'like', $prefix.'%');
                    }
                })
                ->where('id', '!=', $exceptOperationId)
                ->delete();
            $removed['previous_operation_logs'] = $logs;
        }

        return $removed;
    }

    private function deleteCheckpointFile(mixed $path): void
    {
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $platformRoot = realpath(storage_path('app/platform'));
        $resolved = realpath($path);

        if (
            $platformRoot !== false
            && $resolved !== false
            && is_file($resolved)
            && str_starts_with($resolved, rtrim($platformRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
        ) {
            File::delete($resolved);
        }
    }
}
