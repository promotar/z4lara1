<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Licensing\LicenseManager;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use RuntimeException;

class PluginActivator
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginDependencyChecker $dependencies,
        private readonly PluginMenuRegistry $menus,
        private readonly LicenseManager $licenses,
        private readonly PluginRuntimeRegistry $runtime,
        private readonly PluginCacheCleaner $cache,
        private readonly StepBackupper $stepBackups,
        private readonly PluginPackageValidator $packageValidator,
        private readonly PluginFilesystem $filesystem,
        private readonly ?AdminThemeManager $adminThemes = null,
        private readonly ?PluginLifecycleHookRunner $lifecycleHooks = null,
        private readonly ?PluginAssetRegistry $assets = null,
    ) {
        //
    }

    public function activate(Plugin|string $plugin): Plugin
    {
        $plugin = $this->resolvePlugin($plugin);
        $wasDisabled = $plugin->status === Plugin::STATUS_DISABLED;
        $pluginPath = $this->filesystem->path($plugin)
            ?? throw new RuntimeException("Plugin [{$plugin->slug}] module directory is missing.");
        $this->packageValidator->validate($pluginPath);
        $manifest = $this->manifestFromPlugin($plugin);
        $missingDependencies = $this->dependencies->missingDependencies(
            $manifest,
            $this->plugins->findActive(),
        );

        if ($missingDependencies !== []) {
            throw new RuntimeException('Missing active plugin dependencies: '.implode(', ', $missingDependencies));
        }

        if (! $this->licenses->canActivatePlugin($plugin->slug)) {
            throw new RuntimeException("Plugin [{$plugin->slug}] requires a valid license before activation.");
        }

        if ($this->lifecycleHooks !== null) {
            $this->lifecycleHooks->run($manifest, $wasDisabled ? 'reactivate' : 'activate');
            $this->checkpointStep($plugin->slug, $wasDisabled ? 'reactivate_hook_completed' : 'activate_hook_completed');
        }
        $plugin = $this->plugins->markActivated($plugin);
        $this->checkpointStep($plugin->slug, 'status_marked_active');
        $this->menus->show($plugin->slug);
        $this->checkpointStep($plugin->slug, 'menus_shown');
        $this->runtime->enable($plugin->slug);
        $this->checkpointStep($plugin->slug, 'runtime_enabled');
        $this->flushRuntimeGate($plugin->slug);
        if ($this->adminThemes !== null) {
            $plugin = $this->adminThemes->afterActivation($plugin);
            $this->checkpointStep($plugin->slug, 'admin_theme_policy_enforced');
        }
        if ($this->assets !== null) {
            $assetState = $this->assets->synchronize($plugin);
            $this->checkpointStep($plugin->slug, 'assets_synchronized', [
                'assets' => $assetState,
            ]);
        }
        $this->cache->clear();
        $this->checkpointStep($plugin->slug, 'cache_cleared');

        return $plugin;
    }

    private function resolvePlugin(Plugin|string $plugin): Plugin
    {
        if ($plugin instanceof Plugin) {
            return $plugin;
        }

        return $this->plugins->findBySlug($plugin)
            ?? throw new RuntimeException("Plugin [{$plugin}] is not installed.");
    }

    private function manifestFromPlugin(Plugin $plugin): PluginManifest
    {
        return new PluginManifest(
            name: $plugin->name,
            slug: $plugin->slug,
            version: (string) $plugin->version,
            provider: (string) $plugin->provider,
            description: $plugin->description,
            author: $plugin->author,
            dependencies: $this->arrayValue($plugin->dependencies),
            manifest: $this->arrayValue($plugin->manifest),
            sourcePath: $plugin->path,
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function checkpointStep(string $slug, string $step): void
    {
        $this->stepBackups->afterStep('plugin.activate', 'plugin', $slug, $step);
    }

    private function flushRuntimeGate(string $slug): void
    {
        if (app()->bound(PluginRuntimeGate::class)) {
            app(PluginRuntimeGate::class)->flush($slug);
        }
    }
}
