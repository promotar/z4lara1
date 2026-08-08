<?php

namespace App\Platform\Core\Menus;

use App\Models\User;
use App\Platform\Core\Access\RouteAccessGate;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use App\Platform\Core\Services\PluginRuntimeGate;

class MenuVisibilityResolver
{
    public function __construct(
        private readonly PluginRuntimeGate $gate,
        private readonly PluginOwnedPageGuard $pluginPages,
        private readonly RouteAccessGate $routeAccess,
    ) {}

    public function visible(MenuItem $item, ?User $user = null): bool
    {
        if (! $item->is_active) {
            return false;
        }

        if (
            $item->plugin_id !== null
            && (
                $item->plugin?->status !== Plugin::STATUS_ACTIVE
                || ! $item->plugin
                || ! $this->gate->allows($item->plugin->slug)
            )
        ) {
            return false;
        }

        if (! $this->pluginPages->isNavigationAvailable(
            $item->route_name,
            $item->url,
            is_array($item->route_params) ? $item->route_params : [],
        )) {
            return false;
        }

        if (! $this->routeAccess->allowsRouteName($user, $item->route_name)) {
            return false;
        }

        if ($item->permission === null || $item->permission === '') {
            return true;
        }

        return $user !== null && $user->can($item->permission);
    }
}
