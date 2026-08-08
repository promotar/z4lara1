<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use RuntimeException;

class PluginDeactivator
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginMenuRegistry $menus,
        private readonly PluginRuntimeRegistry $runtime,
        private readonly PluginCacheCleaner $cache,
        private readonly OperationLogger $operations,
        private readonly FailedOperationLogger $failedOperations,
        private readonly StepBackupper $stepBackups,
        private readonly ?AdminThemeManager $adminThemes = null,
        private readonly ?PluginLifecycleHookRunner $lifecycleHooks = null,
    ) {
        //
    }

    public function deactivate(Plugin|string $plugin): Plugin
    {
        $resolved = $this->resolvePlugin($plugin);

        if ($resolved->isCore()) {
            throw new RuntimeException("Core plugin [{$resolved->slug}] cannot be deactivated.");
        }

        if ($this->adminThemes !== null) {
            $this->adminThemes->guardManualDeactivation($resolved);
        }

        $operation = $this->operations->start('plugin.disable', 'plugin', $resolved->slug);

        try {
            if ($this->lifecycleHooks !== null) {
                $this->lifecycleHooks->run($this->manifestFromPlugin($resolved), 'deactivate');
                $this->checkpointStep($resolved->slug, 'deactivate_hook_completed');
            }
            $plugin = $this->plugins->markDisabled($resolved);
            $this->checkpointStep($plugin->slug, 'status_marked_disabled');
            $this->menus->hide($plugin->slug);
            $this->checkpointStep($plugin->slug, 'menus_hidden');
            $this->runtime->disable($plugin->slug);
            $this->checkpointStep($plugin->slug, 'runtime_disabled');
            $this->flushRuntimeGate($plugin->slug);
            if ($this->adminThemes !== null) {
                $this->adminThemes->afterDeactivation($plugin);
                $this->checkpointStep($plugin->slug, 'admin_theme_fallback_enforced');
            }
            $this->cache->clear();
            $this->checkpointStep($plugin->slug, 'cache_cleared');

            $this->operations->success($operation, 'Plugin disabled.');

            return $plugin;
        } catch (\Throwable $exception) {
            $this->failedOperations->log($operation, $exception);

            throw $exception;
        }
    }

    private function resolvePlugin(Plugin|string $plugin): Plugin
    {
        if ($plugin instanceof Plugin) {
            return $plugin;
        }

        return $this->plugins->findBySlug($plugin)
            ?? throw new RuntimeException("Plugin [{$plugin}] is not installed.");
    }

    private function checkpointStep(string $slug, string $step): void
    {
        $this->stepBackups->afterStep('plugin.disable', 'plugin', $slug, $step);
    }

    private function manifestFromPlugin(Plugin $plugin): \App\Platform\Core\DTOs\PluginManifest
    {
        $manifest = is_array($plugin->manifest) ? $plugin->manifest : [];
        $manifest['name'] = $manifest['name'] ?? $plugin->name;
        $manifest['slug'] = $manifest['slug'] ?? $plugin->slug;
        $manifest['version'] = $manifest['version'] ?? $plugin->version ?? '0.0.0';
        $manifest['provider'] = $manifest['provider'] ?? $plugin->provider ?? '';
        $manifest['dependencies'] = $manifest['dependencies'] ?? $plugin->dependencies ?? [];

        return \App\Platform\Core\DTOs\PluginManifest::fromArray($manifest, $plugin->path);
    }

    private function flushRuntimeGate(string $slug): void
    {
        if (app()->bound(PluginRuntimeGate::class)) {
            app(PluginRuntimeGate::class)->flush($slug);
        }
    }
}
