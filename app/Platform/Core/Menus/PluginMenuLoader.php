<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginRuntimeGate;

class PluginMenuLoader
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly MenuRegistrar $registrar,
        private readonly PluginRuntimeGate $gate,
    ) {
        //
    }

    public function loadActivePluginMenus(): int
    {
        $loaded = 0;

        foreach ($this->plugins->findActive() as $plugin) {
            if (! $this->gate->allows($plugin->slug)) {
                continue;
            }

            $menus = data_get($plugin->manifest, 'menus', []);

            if (! is_array($menus) || $menus === []) {
                continue;
            }

            $loaded += $this->registrar->syncPluginMenus($plugin, $menus);
        }

        return $loaded;
    }
}
