<?php

namespace App\Platform\Core\Hooks;

class HookLoader
{
    public function __construct(
        private readonly HookManager $hooks,
        private readonly PluginHookLoader $plugins,
    ) {
        //
    }

    public function load(): int
    {
        return $this->plugins->loadActiveHooks($this->hooks);
    }
}
