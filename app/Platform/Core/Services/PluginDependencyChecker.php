<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;

class PluginDependencyChecker
{
    /**
     * @param iterable<int|string, mixed> $availablePlugins
     * @return array<int, string>
     */
    public function missingDependencies(PluginManifest $manifest, iterable $availablePlugins): array
    {
        $availableSlugs = $this->normalizeAvailableSlugs($availablePlugins);

        return array_values(array_filter(
            $this->dependencySlugs($manifest),
            fn (string $slug): bool => ! in_array($slug, $availableSlugs, true),
        ));
    }

    /**
     * @param iterable<int|string, mixed> $availablePlugins
     */
    public function dependenciesAreSatisfied(PluginManifest $manifest, iterable $availablePlugins): bool
    {
        return $this->missingDependencies($manifest, $availablePlugins) === [];
    }

    /**
     * @return array<int, string>
     */
    public function dependencySlugs(PluginManifest $manifest): array
    {
        $dependencies = [];

        foreach ($manifest->dependencies as $key => $value) {
            $dependencies[] = is_string($key) ? $key : (string) $value;
        }

        return array_values(array_unique(array_filter($dependencies)));
    }

    /**
     * @param iterable<int|string, mixed> $availablePlugins
     * @return array<int, string>
     */
    private function normalizeAvailableSlugs(iterable $availablePlugins): array
    {
        $slugs = [];

        foreach ($availablePlugins as $key => $plugin) {
            if ($plugin instanceof Plugin) {
                $slugs[] = $plugin->slug;
                continue;
            }

            if (is_string($key) && ! is_int($key)) {
                $slugs[] = $key;
                continue;
            }

            if (is_string($plugin)) {
                $slugs[] = $plugin;
                continue;
            }

            if (is_array($plugin) && isset($plugin['slug'])) {
                $slugs[] = (string) $plugin['slug'];
            }
        }

        return array_values(array_unique(array_filter($slugs)));
    }
}
