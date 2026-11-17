<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Carbon;
use Throwable;

class PluginInstaller
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginPackageValidator $packages,
        private readonly PluginInstallBackup $backups,
        private readonly PluginMigrationRunner $migrations,
        private readonly PluginSeederRunner $seeders,
        private readonly PluginPermissionRegistrar $permissions,
        private readonly PluginMenuRegistry $menus,
        private readonly PluginAssetPublisher $assets,
        private readonly PluginLifecycleHookRunner $lifecycleHooks,
        private readonly PluginCacheCleaner $cache,
        private readonly OperationLogger $operations,
        private readonly FailedOperationLogger $failedOperations,
        private readonly StepBackupper $stepBackups,
    ) {
        //
    }

    public function install(
        string $pluginPath,
        bool $preserveMigrationDataOnFailure = false,
        string $operationType = 'plugin.install',
        string $successMessage = 'Plugin install completed.',
    ): Plugin {
        $manifest = $this->packages->validate($pluginPath);

        $existing = $this->plugins->findBySlug($manifest->slug);
        $attributes = $this->attributesFromManifest($manifest, $pluginPath, $existing);
        $checkpoint = $this->backups->create($manifest, $pluginPath, $existing);
        $plugin = null;
        $migrationsRan = false;
        $assetState = [];
        $createdPermissions = [];
        $operation = $this->operations->start($operationType, 'plugin', $manifest->slug, [
            'plugin_path' => $pluginPath,
            'version' => $manifest->version,
            'preserve_migration_data_on_failure' => $preserveMigrationDataOnFailure,
        ]);

        try {
            $plugin = $existing
                ? $this->plugins->update($existing, $attributes)
                : $this->plugins->create($attributes);
            $this->checkpointStep($manifest->slug, 'database_registered', [
                'status' => $plugin->status,
            ]);

            $migrationsRan = $this->migrations->run($pluginPath, $manifest);
            $this->checkpointStep($manifest->slug, 'migrations_completed', [
                'migrations_ran' => $migrationsRan,
            ]);
            $this->seeders->run($manifest);
            $this->checkpointStep($manifest->slug, 'seeders_completed');
            $createdPermissions = $this->permissions->register($manifest);
            $this->checkpointStep($manifest->slug, 'permissions_registered', [
                'permissions_created' => $createdPermissions,
            ]);
            $this->menus->register($manifest);
            $this->checkpointStep($manifest->slug, 'menus_registered');
            $assetState = $this->assets->publish($pluginPath, $manifest);
            $this->checkpointStep($manifest->slug, 'assets_published', [
                'assets' => $assetState,
            ]);
            $this->lifecycleHooks->run($manifest, 'install');
            $this->checkpointStep($manifest->slug, 'install_hook_completed');
            $this->cache->clear();
            $this->checkpointStep($manifest->slug, 'cache_cleared');

            $this->operations->success($operation, $successMessage, [
                'migrations_ran' => $migrationsRan,
                'permissions_created' => $createdPermissions,
                'assets' => $assetState,
                'preserve_migration_data_on_failure' => $preserveMigrationDataOnFailure,
            ]);

            return $plugin->refresh();
        } catch (Throwable $exception) {
            $this->rollback(
                $manifest,
                $pluginPath,
                $plugin,
                $checkpoint,
                $migrationsRan,
                $assetState,
                $createdPermissions,
                $preserveMigrationDataOnFailure,
            );

            $this->failedOperations->log($operation, $exception, [
                'migrations_ran' => $migrationsRan,
                'permissions_created' => $createdPermissions,
                'preserve_migration_data_on_failure' => $preserveMigrationDataOnFailure,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @param  array<string, mixed>  $assetState
     * @param  array<int, string>  $createdPermissions
     */
    private function rollback(
        PluginManifest $manifest,
        string $pluginPath,
        ?Plugin $plugin,
        array $checkpoint,
        bool $migrationsRan,
        array $assetState,
        array $createdPermissions,
        bool $preserveMigrationDataOnFailure,
    ): void {
        $this->assets->rollback($assetState);
        $this->menus->restore($manifest->slug, $checkpoint['existing_menus'] ?? null);
        $this->permissions->unregisterCreated($createdPermissions);

        if ($migrationsRan && ! $preserveMigrationDataOnFailure) {
            $this->migrations->rollback($pluginPath, $manifest);
        }

        $existingAttributes = $checkpoint['existing_plugin'] ?? null;

        if (is_array($existingAttributes)) {
            $restored = $plugin ?? $this->plugins->findBySlug($manifest->slug);

            if ($restored) {
                $restored->forceFill($existingAttributes)->save();
            }
        } else {
            ($plugin ?? $this->plugins->findBySlug($manifest->slug))?->delete();
        }

        $this->cache->clear();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromManifest(PluginManifest $manifest, string $pluginPath, ?Plugin $existing): array
    {
        return [
            'name' => $manifest->name,
            'slug' => $manifest->slug,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'author' => $manifest->author,
            'status' => Plugin::STATUS_INSTALLED,
            'path' => rtrim($pluginPath, DIRECTORY_SEPARATOR),
            'provider' => $manifest->provider,
            'manifest' => $manifest->manifest,
            'dependencies' => $manifest->dependencies,
            'installed_at' => $existing?->installed_at ?? Carbon::now(),
            'activated_at' => null,
            'disabled_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function checkpointStep(string $slug, string $step, array $metadata = []): void
    {
        $this->stepBackups->afterStep('plugin.install', 'plugin', $slug, $step, $metadata);
    }
}
