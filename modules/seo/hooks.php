<?php

use App\Platform\Core\Hooks\HookManager;
use Modules\Seo\Listeners\OnPluginActivated;
use Modules\Seo\Listeners\OnPluginDeactivated;
use Modules\Seo\Listeners\OnPluginUninstalled;

return static function (HookManager $hooks): void {
    $hooks->addAction(
        'plugin.activated',
        [app(OnPluginActivated::class), 'handle'],
        priority: 10,
        acceptedArgs: 1,
    );

    $hooks->addAction(
        'plugin.deactivated',
        [app(OnPluginDeactivated::class), 'handle'],
        priority: 10,
        acceptedArgs: 1,
    );

    $hooks->addAction(
        'plugin.uninstalled',
        [app(OnPluginUninstalled::class), 'handle'],
        priority: 10,
        acceptedArgs: 2,
    );
};
