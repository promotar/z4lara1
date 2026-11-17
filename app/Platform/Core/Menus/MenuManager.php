<?php

namespace App\Platform\Core\Menus;

use App\Models\User;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Collection;

class MenuManager
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly MenuRegistrar $registrar,
        private readonly MenuVisibilityResolver $visibility,
        private readonly HookManager $hooks,
    ) {
        //
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMenu(string $location, ?User $user = null): array
    {
        $menus = $this->menus->activeByLocation($location);

        return $menus
            ->flatMap(fn (Menu $menu) => $location === 'frontend'
                ? $this->frontendItems($menu, $user)
                : $this->tree($menu->items, $user))
            ->values()
            ->all();
    }

    /**
     * Every active admin Menu record is one visible sidebar section. No
     * synthetic sections or links are added outside the stored settings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminMenu(?User $user = null): array
    {
        return $this->menus->activeByLocation('admin')
            ->map(function ($menu) use ($user): ?array {
                $children = $this->tree($menu->items, $user);

                if ($children === []) {
                    return null;
                }

                return [
                    'id' => 'admin-menu-'.$menu->id,
                    'title' => $menu->name,
                    'label' => $menu->name,
                    'type' => 'group',
                    'url' => null,
                    'route_name' => null,
                    'route_params' => [],
                    'icon' => strtoupper(substr((string) $menu->name, 0, 1)),
                    'target' => null,
                    'permission' => null,
                    'metadata' => [
                        'is_admin_group' => true,
                        'menu_id' => $menu->id,
                        'menu_key' => $menu->key,
                    ],
                    'sort_order' => $menu->sort_order,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFrontendMenu(?User $user = null): array
    {
        return $this->getMenu('frontend', $user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFrontendMenuByKey(string $key, ?User $user = null): array
    {
        $menu = $this->menus->activeByKey($key, 'frontend');

        return $menu !== null ? $this->frontendItems($menu, $user) : [];
    }

    /**
     * This is the public Page Builder hook boundary. Plugins may filter the
     * stored tree, but the database remains the source of menu structure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function frontendItems(Menu $menu, ?User $user): array
    {
        $items = $this->tree($menu->items, $user);
        $filtered = $this->hooks->applyFilters('frontend.menu.items', $items, $menu->key, $menu, $user);

        return is_array($filtered) ? array_values($filtered) : $items;
    }

    /**
     * @param  array<string, mixed>  $menuDefinition
     */
    public function register(array $menuDefinition): void
    {
        $this->registrar->register($menuDefinition);
    }

    /**
     * @param  array<int, mixed>  $menus
     */
    public function registerPluginMenus(Plugin $plugin, array $menus): void
    {
        $this->syncPluginMenus($plugin, $menus);
    }

    /**
     * @param  array<int, mixed>  $menus
     */
    public function syncPluginMenus(Plugin $plugin, array $menus): void
    {
        $this->registrar->syncPluginMenus($plugin, $menus);
    }

    public function removePluginMenus(Plugin $plugin): int
    {
        return $this->menus->deletePluginMenus($plugin);
    }

    public function hidePluginMenus(Plugin $plugin): int
    {
        return $this->menus->setPluginMenusActive($plugin, false);
    }

    public function showPluginMenus(Plugin $plugin): int
    {
        return $this->menus->setPluginMenusActive($plugin, true);
    }

    /**
     * @param  iterable<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function tree(iterable $items, ?User $user): array
    {
        $grouped = collect($items)->groupBy(fn (MenuItem $item): int => $item->parent_id ?? 0);

        return $this->childrenFor(0, $grouped, $user);
    }

    /**
     * @param  Collection<int|string, Collection<int, MenuItem>>  $grouped
     * @return array<int, array<string, mixed>>
     */
    private function childrenFor(int $parentId, $grouped, ?User $user): array
    {
        return ($grouped->get($parentId) ?? collect())
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(function (MenuItem $item) use ($grouped, $user): ?array {
                $children = $this->childrenFor($item->id, $grouped, $user);
                $visible = $this->visibility->visible($item, $user);
                $hasTarget = $item->url !== null || $item->route_name !== null;

                if (! $visible || (! $hasTarget && $children === [])) {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'label' => $item->label ?: $item->title,
                    'type' => $item->type,
                    'url' => $item->url,
                    'route_name' => $item->route_name,
                    'route_params' => $item->route_params ?? [],
                    'icon' => $item->icon,
                    'target' => $item->target,
                    'permission' => $item->permission,
                    'metadata' => $item->metadata ?? [],
                    'sort_order' => $item->sort_order,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
