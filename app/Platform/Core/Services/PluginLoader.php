<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use DirectoryIterator;
use Illuminate\Database\Eloquent\Collection;

class PluginLoader
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginManifestReader $manifests,
    ) {
        //
    }

    /**
     * @return Collection<int, Plugin>
     */
    public function all(): Collection
    {
        return $this->plugins->all();
    }

    /**
     * @return Collection<int, Plugin>
     */
    public function active(): Collection
    {
        return $this->plugins->findActive();
    }

    public function findBySlug(string $slug): ?Plugin
    {
        return $this->plugins->findBySlug($slug);
    }

    public function manifest(string $pluginPath): PluginManifest
    {
        return $this->manifests->readFromPluginPath($pluginPath);
    }

    /**
     * @return array<string, PluginManifest>
     */
    public function discover(string $pluginsPath): array
    {
        if (! is_dir($pluginsPath)) {
            return [];
        }

        $manifests = [];

        foreach (new DirectoryIterator($pluginsPath) as $entry) {
            if ($entry->isDot() || ! $entry->isDir()) {
                continue;
            }

            $manifestPath = $entry->getPathname().DIRECTORY_SEPARATOR.'module.json';

            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = $this->manifests->read($manifestPath);
            $manifests[$manifest->slug] = $manifest;
        }

        return $manifests;
    }
}
