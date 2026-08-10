<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Repositories\PluginRepository;
use RuntimeException;

class RequiredCorePluginBootstrapper
{
    public function __construct(
        private readonly RequiredCorePluginSynchronizer $synchronizer,
        private readonly PluginRepository $plugins,
        private readonly PluginFilesystem $filesystem,
        private readonly PluginLoader $loader,
        private readonly PluginMigrationRunner $migrations,
        private readonly PluginSeederRunner $seeders,
        private readonly PluginPermissionRegistrar $permissions,
        private readonly PluginMenuRegistry $menus,
        private readonly PluginAssetPublisher $assets,
        private readonly PluginLifecycleHookRunner $lifecycleHooks,
    ) {}

    public function bootstrap(): void
    {
        $this->synchronizer->synchronize();

        foreach ($this->plugins->findActive() as $plugin) {
            if (! $plugin->isCore() && ! $plugin->isDefaultAdminTheme()) {
                continue;
            }

            $pluginPath = $this->filesystem->path($plugin)
                ?? throw new RuntimeException("Required plugin [{$plugin->slug}] module directory is missing.");
            $manifest = $this->loader->manifest($pluginPath);

            $this->migrations->run($pluginPath, $manifest);
            $this->seeders->run($manifest);
            $this->permissions->register($manifest);
            $this->menus->register($manifest);
            $this->assets->publish($pluginPath, $manifest);
            $this->lifecycleHooks->run($manifest, 'activate');
        }

        $this->synchronizer->synchronize();
    }
}
