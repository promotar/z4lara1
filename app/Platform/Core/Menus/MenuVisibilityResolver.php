<?php

namespace App\Platform\Core\Menus;

use App\Models\User;
use App\Platform\Core\Access\RouteAccessGate;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use App\Platform\Core\Services\PluginRuntimeGate;
use Illuminate\Support\Facades\Route;

class MenuVisibilityResolver
{
    /** @var array<string, bool> */
    private array $reachableCache = [];

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
        if (! $this->targetIsReachable($item)) {
            return false;
        }

        // Audience from the menu item settings (all / guest / authenticated).
        $audience = strtolower((string) ($item->metadata['audience'] ?? 'all'));

        if ($audience === 'guest' && $user !== null) {
            return false;
        }

        if ($audience === 'authenticated' && $user === null) {
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

    /**
     * يمنع ظهور روابط ميتة: عنصر قائمة يشير إلى مسار/راوت لم يعد مسجلاً
     * (مثل رابط موديول تم تعطيله) يجب أن يُخفى من القوائم.
     */
    private function targetIsReachable(MenuItem $item): bool
    {
        $routeName = trim((string) $item->route_name);

        if ($routeName !== '') {
            return Route::has($routeName);
        }

        $url = trim((string) $item->url);

        if ($url === '' || str_starts_with($url, '#')) {
            return true;
        }

        if (preg_match('~^(https?:)?//~i', $url) === 1) {
            return true;
        }

        // Contact links are always valid navigation targets.
        if (preg_match('~^(tel|mailto|sms|whatsapp):~i', $url) === 1) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $url;

        return $this->pathResolves($path);
    }

    private function pathResolves(string $path): bool
    {
        $key = $path;

        if (array_key_exists($key, $this->reachableCache)) {
            return $this->reachableCache[$key];
        }

        try {
            Route::getRoutes()->match(
                \Illuminate\Http\Request::create($path, 'GET')
            );

            return $this->reachableCache[$key] = true;
        } catch (\Throwable) {
            return $this->reachableCache[$key] = false;
        }
    }
}
