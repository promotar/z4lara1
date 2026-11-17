<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;

class PluginManager
{
    public function __construct(
        private readonly PluginLoader $loader,
        private readonly PluginPackageValidator $packageValidator,
        private readonly PluginInstaller $installer,
        private readonly PluginActivator $activator,
        private readonly PluginDeactivator $deactivator,
        private readonly PluginUninstaller $uninstaller,
    ) {
        //
    }

    /**
     * @return Collection<int, Plugin>
     */
    public function all(): Collection
    {
        return $this->loader->all();
    }

    /**
     * @return Collection<int, Plugin>
     */
    public function active(): Collection
    {
        return $this->loader->active();
    }

    public function findBySlug(string $slug): ?Plugin
    {
        return $this->loader->findBySlug($slug);
    }

    public function manifest(string $pluginPath): PluginManifest
    {
        return $this->loader->manifest($pluginPath);
    }

    public function validatePackage(string $pluginPath): PluginManifest
    {
        return $this->packageValidator->validate($pluginPath);
    }

    /**
     * @return array<string, PluginManifest>
     */
    public function discover(string $pluginsPath): array
    {
        return $this->loader->discover($pluginsPath);
    }

    public function install(string $pluginPath): Plugin
    {
        return $this->installer->install($pluginPath);
    }

    public function update(string $pluginPath): Plugin
    {
        return $this->installer->install(
            $pluginPath,
            preserveMigrationDataOnFailure: true,
            operationType: 'plugin.update',
            successMessage: 'Plugin update completed.',
        );
    }

    public function activate(Plugin|string $plugin): Plugin
    {
        return $this->activator->activate($plugin);
    }

    public function deactivate(Plugin|string $plugin): Plugin
    {
        return $this->deactivator->deactivate($plugin);
    }

    /**
     * @return array<string, mixed>
     */
    public function uninstall(Plugin|string $plugin): array
    {
        return $this->uninstaller->purge($plugin);
    }

    /**
     * Alias for the destructive uninstall contract.
     *
     * @return array<string, mixed>
     */
    public function purge(Plugin|string $plugin): array
    {
        return $this->uninstaller->purge($plugin);
    }
}
