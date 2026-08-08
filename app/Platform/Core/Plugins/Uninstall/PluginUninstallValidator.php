<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;

class PluginUninstallValidator
{
    public function __construct(
        private readonly PluginRepository $plugins,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function activeDependents(Plugin $plugin): array
    {
        $dependents = [];

        foreach ($this->plugins->all() as $candidate) {
            if ($candidate->id === $plugin->id || $candidate->status !== Plugin::STATUS_ACTIVE) {
                continue;
            }

            if (in_array($plugin->slug, $this->dependencySlugs($candidate), true)) {
                $dependents[] = $candidate->slug;
            }
        }

        return array_values(array_unique($dependents));
    }

    public function statusAllowsUninstall(Plugin $plugin): bool
    {
        return in_array($plugin->status, [
            Plugin::STATUS_INSTALLED,
            Plugin::STATUS_DISABLED,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function dependencySlugs(Plugin $plugin): array
    {
        $dependencies = $plugin->dependencies;

        if (! is_array($dependencies) || $dependencies === []) {
            $dependencies = data_get($plugin->manifest, 'dependencies', []);
        }

        if (! is_array($dependencies)) {
            return [];
        }

        $slugs = [];

        foreach ($dependencies as $key => $value) {
            if (is_string($key)) {
                $slugs[] = $key;

                continue;
            }

            if (is_string($value)) {
                $slugs[] = $value;

                continue;
            }

            if (is_array($value) && isset($value['slug'])) {
                $slugs[] = (string) $value['slug'];
            }
        }

        return array_values(array_unique(array_filter($slugs)));
    }
}
