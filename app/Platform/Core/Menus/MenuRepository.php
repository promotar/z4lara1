<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;

class MenuRepository
{
    /**
     * @return Collection<int, Menu>
     */
    public function activeByLocation(string $location): Collection
    {
        return Menu::query()
            ->active()
            ->where('location', $location)
            ->where(function ($query): void {
                $query->whereNull('plugin_id')
                    ->orWhereHas('plugin', fn ($plugin): mixed => $plugin->where('status', Plugin::STATUS_ACTIVE));
            })
            ->with(['items.plugin'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function activeByKey(string $key, string $location): ?Menu
    {
        return Menu::query()
            ->active()
            ->where('key', $key)
            ->where('location', $location)
            ->where(function ($query): void {
                $query->whereNull('plugin_id')
                    ->orWhereHas('plugin', fn ($plugin): mixed => $plugin->where('status', Plugin::STATUS_ACTIVE));
            })
            ->with(['items.plugin'])
            ->first();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateOrCreateMenu(array $attributes): Menu
    {
        return Menu::query()->updateOrCreate([
            'key' => $attributes['key'],
            'location' => $attributes['location'],
        ], $attributes);
    }

    public function deleteMenuItems(Menu $menu): void
    {
        MenuItem::query()
            ->where('menu_id', $menu->id)
            ->delete();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createMenuItem(array $attributes): MenuItem
    {
        return MenuItem::query()->create($attributes);
    }

    public function setPluginMenusActive(Plugin $plugin, bool $active): int
    {
        $menuIds = Menu::query()
            ->where('plugin_id', $plugin->id)
            ->pluck('id');

        Menu::query()
            ->whereIn('id', $menuIds)
            ->update(['is_active' => $active]);

        return MenuItem::query()
            ->where(function ($query) use ($plugin, $menuIds): void {
                $query->where('plugin_id', $plugin->id)
                    ->orWhereIn('menu_id', $menuIds);
            })
            ->update(['is_active' => $active]);
    }

    public function deletePluginMenus(Plugin $plugin): int
    {
        $menuIds = Menu::query()
            ->where('plugin_id', $plugin->id)
            ->pluck('id');

        $items = MenuItem::query()
            ->where(function ($query) use ($plugin, $menuIds): void {
                $query->where('plugin_id', $plugin->id)
                    ->orWhereIn('menu_id', $menuIds);
            })
            ->count();

        Menu::query()
            ->whereIn('id', $menuIds)
            ->delete();

        return $items + $menuIds->count();
    }
}
