<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PluginMigrationRecordRemover
{
    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        if (! Schema::hasTable('migrations')) {
            return [];
        }

        $path = $this->migrationPath($manifest);

        if ($path === null || ! is_dir($path)) {
            return [];
        }

        $migrations = collect(glob($path.DIRECTORY_SEPARATOR.'*.php') ?: [])
            ->map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($migrations === []) {
            return [];
        }

        $removed = DB::table('migrations')
            ->whereIn('migration', $migrations)
            ->pluck('migration')
            ->all();

        DB::table('migrations')
            ->whereIn('migration', $migrations)
            ->delete();

        return array_values($removed);
    }

    private function migrationPath(PluginManifest $manifest): ?string
    {
        $source = $manifest->sourcePath;

        if (! is_string($source) || trim($source) === '') {
            return null;
        }

        $root = is_file($source) ? dirname($source) : $source;
        $configured = data_get($manifest->manifest, 'install.migrations')
            ?: data_get($manifest->manifest, 'migrations')
            ?: 'database/migrations';

        if (! is_string($configured) || trim($configured) === '') {
            return null;
        }

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.ltrim($configured, '/\\');
    }
}
