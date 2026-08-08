<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginLifecycleHookRunner;
use App\Platform\Core\Services\PluginManifestReader;
use App\Platform\Core\Services\PluginOwnershipValidator;
use App\Platform\Core\Services\PluginRuntimeRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class PluginUninstallFlow
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginManifestReader $manifests,
        private readonly PluginOwnershipValidator $ownership,
        private readonly PluginUninstallValidator $validator,
        private readonly PluginOwnedRecordRemover $records,
        private readonly PluginTableDropper $tables,
        private readonly PluginOwnedColumnRemover $columns,
        private readonly PluginMigrationRecordRemover $migrations,
        private readonly PluginPermissionRemover $permissions,
        private readonly PluginMenuRemover $menus,
        private readonly PluginSettingsRemover $settings,
        private readonly PluginAssetRemover $assets,
        private readonly PluginOwnedStorageRemover $storage,
        private readonly PluginPackageRemover $packages,
        private readonly PluginMetadataRemover $metadata,
        private readonly PluginUninstallCacheClearer $cache,
        private readonly PluginRuntimeRegistry $runtime,
        private readonly OperationLogger $operations,
        private readonly FailedOperationLogger $failedOperations,
        private readonly ?PluginLifecycleHookRunner $lifecycleHooks = null,
    ) {
        //
    }

    /**
     * Permanently removes a disabled plugin and everything it owns.
     *
     * Deactivation is the data-preserving lifecycle operation. Uninstall is
     * intentionally destructive and leaves only its final audit log.
     *
     * @return array<string, mixed>
     */
    public function purge(Plugin $plugin): array
    {
        $result = [
            'success' => false,
            'plugin' => $plugin->slug,
            'previous_status' => $plugin->status,
            'completed_steps' => [],
            'failed_step' => null,
            'removed_resources' => [],
            'blocked_by_dependencies' => [],
            'message' => null,
            'data_policy' => 'purge',
        ];
        $operation = $this->operations->start('plugin.purge', 'plugin', $plugin->slug, [
            'previous_status' => $plugin->status,
            'purge_data' => true,
        ]);

        if ($plugin->isCore()) {
            $result['failed_step'] = 'validate_core';
            $result['message'] = "Core plugin [{$plugin->slug}] cannot be uninstalled.";
            $this->operations->fail($operation, $result['message'], ['failed_step' => $result['failed_step']]);

            return $result;
        }

        if ($plugin->status === Plugin::STATUS_ACTIVE) {
            $result['failed_step'] = 'validate_status';
            $result['message'] = "Plugin [{$plugin->slug}] must be disabled before uninstall.";
            $this->operations->fail($operation, $result['message'], ['failed_step' => $result['failed_step']]);

            return $result;
        }

        if (! $this->validator->statusAllowsUninstall($plugin)) {
            $result['failed_step'] = 'validate_status';
            $result['message'] = "Plugin [{$plugin->slug}] is not in an uninstallable status.";
            $this->operations->fail($operation, $result['message'], ['failed_step' => $result['failed_step']]);

            return $result;
        }

        $dependents = $this->validator->activeDependents($plugin);

        if ($dependents !== []) {
            $result['failed_step'] = 'validate_dependencies';
            $result['blocked_by_dependencies'] = $dependents;
            $result['message'] = 'Active plugins depend on this plugin: '.implode(', ', $dependents);
            $this->operations->fail($operation, $result['message'], ['blocked_by_dependencies' => $dependents]);

            return $result;
        }

        try {
            $manifest = $this->manifestFromPlugin($plugin);
            $this->ownership->validate($plugin->path, $manifest);
            $result['completed_steps'][] = 'ownership_contract';

            $this->packages->preflight($plugin, $manifest);
            $this->storage->preflight($manifest);
            $result['completed_steps'][] = 'package_preflight';

            $this->lifecycleHooks?->run($manifest, 'purge');
            $result['completed_steps'][] = 'purge_hook';

            $result['removed_resources']['records'] = $this->records->remove($manifest);
            $result['completed_steps'][] = 'records';

            $result['removed_resources']['tables'] = $this->tables->drop($manifest);
            $result['completed_steps'][] = 'tables';

            $result['removed_resources']['columns'] = $this->columns->remove($manifest);
            $result['completed_steps'][] = 'columns';

            $result['removed_resources']['migrations'] = $this->migrations->remove($manifest);
            $result['completed_steps'][] = 'migrations';

            $result['removed_resources']['permissions'] = $this->permissions->remove($manifest);
            $result['completed_steps'][] = 'permissions';

            $result['removed_resources']['menus'] = $this->menus->remove($manifest->slug);
            $result['completed_steps'][] = 'menus';

            $result['removed_resources']['settings'] = $this->settings->remove($manifest);
            $result['completed_steps'][] = 'settings';

            $result['removed_resources']['assets'] = $this->assets->remove($manifest);
            $result['completed_steps'][] = 'assets';

            $result['removed_resources']['storage'] = $this->storage->remove($manifest);
            $result['completed_steps'][] = 'storage';

            $this->runtime->forget($manifest->slug);
            $result['completed_steps'][] = 'runtime';

            $result['removed_resources']['metadata'] = $this->metadata->remove(
                $plugin,
                $manifest,
                exceptOperationId: $operation->id,
            );
            $result['completed_steps'][] = 'metadata';

            $result['removed_resources']['package_files'] = $this->packages->remove($plugin, $manifest);
            $result['completed_steps'][] = 'package_files';

            $this->plugins->delete($plugin);
            $result['completed_steps'][] = 'plugin_record';

            $this->cache->clear();
            $result['completed_steps'][] = 'cache';

            $result['success'] = true;
            $result['message'] = "Plugin [{$manifest->slug}] and all owned data were permanently deleted.";
            $this->operations->success($operation, $result['message'], [
                'completed_steps' => $result['completed_steps'],
                'removed_resources' => $result['removed_resources'],
                'data_policy' => 'purge',
                'deleted_at' => now()->toIso8601String(),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $result['failed_step'] = $this->failedStep($result['completed_steps']);
            $result['message'] = $exception->getMessage();

            Log::error('Plugin purge flow failed.', [
                'plugin' => $plugin->slug,
                'step' => $result['failed_step'],
                'error' => $exception->getMessage(),
            ]);
            $this->failedOperations->log($operation, $exception, ['failed_step' => $result['failed_step']]);

            return $result;
        }
    }

    private function manifestFromPlugin(Plugin $plugin): PluginManifest
    {
        $manifest = is_array($plugin->manifest) ? $plugin->manifest : [];

        $manifest['name'] = $manifest['name'] ?? $plugin->name;
        $manifest['slug'] = $manifest['slug'] ?? $plugin->slug;
        $manifest['version'] = $manifest['version'] ?? $plugin->version ?? '0.0.0';
        $manifest['provider'] = $manifest['provider'] ?? $plugin->provider ?? '';
        $manifest['description'] = $manifest['description'] ?? $plugin->description;
        $manifest['author'] = $manifest['author'] ?? $plugin->author;
        $manifest['dependencies'] = $manifest['dependencies'] ?? $plugin->dependencies ?? [];

        $manifest = $this->manifests->validate($manifest);

        return PluginManifest::fromArray($manifest, $plugin->path);
    }

    /**
     * @param  array<int, string>  $completedSteps
     */
    private function failedStep(array $completedSteps): string
    {
        foreach ([
            'ownership_contract',
            'package_preflight',
            'purge_hook',
            'records',
            'tables',
            'columns',
            'migrations',
            'permissions',
            'menus',
            'settings',
            'assets',
            'storage',
            'runtime',
            'metadata',
            'package_files',
            'plugin_record',
            'cache',
        ] as $step) {
            if (! in_array($step, $completedSteps, true)) {
                return $step;
            }
        }

        return 'unknown';
    }
}
