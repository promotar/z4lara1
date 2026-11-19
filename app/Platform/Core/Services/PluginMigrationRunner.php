<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PluginMigrationRunner
{
    public function run(string $pluginPath, PluginManifest $manifest): bool
    {
        $path = $this->migrationPath($pluginPath, $manifest);

        if ($path === null || ! is_dir($path) || glob($path.DIRECTORY_SEPARATOR.'*.php') === []) {
            return false;
        }

        $this->forgetPluginMigrationsIfOwnedTablesAreMissing($path, $manifest);

        Artisan::call('migrate', [
            '--path' => [$path],
            '--realpath' => true,
            '--force' => true,
        ]);

        return true;
    }

    public function rollback(string $pluginPath, PluginManifest $manifest): void
    {
        $path = $this->migrationPath($pluginPath, $manifest);

        if ($path === null || ! is_dir($path)) {
            return;
        }

        Artisan::call('migrate:rollback', [
            '--path' => [$path],
            '--realpath' => true,
            '--step' => 1,
            '--force' => true,
        ]);
    }

    private function migrationPath(string $pluginPath, PluginManifest $manifest): ?string
    {
        $configured = data_get($manifest->manifest, 'install.migrations')
            ?: data_get($manifest->manifest, 'migrations');

        if (is_string($configured) && trim($configured) !== '') {
            return $pluginPath.DIRECTORY_SEPARATOR.ltrim($configured, '/\\');
        }

        return $pluginPath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
    }

    private function forgetPluginMigrationsIfOwnedTablesAreMissing(string $path, PluginManifest $manifest): void
    {
        $tables = data_get($manifest->manifest, 'uninstall.tables', []);

        if (! is_array($tables) || $tables === []) {
            return;
        }

        $declared = collect($tables)
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->unique()
            ->values();

        if ($declared->isEmpty()) {
            return;
        }

        $missing = $declared->reject(fn (string $table): bool => Schema::hasTable($table));

        if ($missing->isEmpty()) {
            return;
        }

        // A release may declare a table that is created by one of its own pending
        // migrations (for example: a new table added to `uninstall.tables` while
        // the matching migration has not run yet). Treating that single missing
        // table as "plugin was never installed" used to purge every migration
        // record for the plugin, so the runner replayed the whole migration
        // history and failed with "table already exists".
        //
        // The migration bookkeeping is only discarded when none of the owned
        // tables exist, which is the only state that proves the tables are gone.
        if ($missing->count() < $declared->count()) {
            Log::warning('Plugin migration history kept despite missing owned tables.', [
                'plugin' => $manifest->slug,
                'missing' => $missing->values()->all(),
                'declared_tables' => $declared->count(),
            ]);

            return;
        }

        $migrationNames = collect(glob($path.DIRECTORY_SEPARATOR.'*.php') ?: [])
            ->map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME))
            ->all();

        if ($migrationNames === []) {
            return;
        }

        DB::table('migrations')
            ->whereIn('migration', $migrationNames)
            ->delete();
    }
}
