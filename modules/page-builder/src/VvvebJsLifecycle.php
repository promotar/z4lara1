<?php

namespace Modules\PageBuilder;

use App\Platform\Core\Menus\MenuLabelResolver;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VvvebJsLifecycle
{
    /**
     * Ensure the Page Builder entries exist without replacing administrator
     * edits: existing items keep their title, order and translations.
     */
    public function activate(): void
    {
        $pluginId = Schema::hasTable('plugins') ? DB::table('plugins')->where('slug', 'page-builder')->value('id') : null;

        if ($pluginId && Schema::hasTable('menu_items')) {
            $this->removeLegacyItems();

            $contentMenuId = Schema::hasTable('menus')
                ? DB::table('menus')->where('key', 'platform.admin.content-management')->where('location', 'admin')->value('id')
                : null;

            if ($contentMenuId) {
                $this->ensureMenuItem((int) $contentMenuId, (int) $pluginId, [
                    'route' => 'admin.pages.index',
                    'title' => 'VvvebJs Builder',
                    'icon' => 'V',
                    'permission' => 'pages.manage',
                    'sort_order' => 10,
                ]);
            }

            $this->ensureMenuItem(null, (int) $pluginId, [
                'route' => 'admin.vvveb.layout',
                'title' => 'Theme Layout',
                'icon' => 'T',
                'permission' => 'pages.manage',
            ]);
        }

        if (Schema::hasTable('platform_plugin_registry_entries')) {
            DB::table('platform_plugin_registry_entries')->where('plugin_slug', 'front-builder')->delete();
        }

        if (Schema::hasTable('platform_settings')) {
            DB::table('platform_settings')->where('value', 'like', 'front-builder:%')->update([
                'value' => DB::raw("REPLACE(value, 'front-builder:', 'platform-page:')"),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('permissions')) {
            $legacyIds = DB::table('permissions')->whereIn('name', [
                'front-builder.manage',
                'theme-builder.manage',
            ])->pluck('id');

            foreach ($legacyIds as $legacyId) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->where('permission_id', $legacyId)->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->where('permission_id', $legacyId)->delete();
                }
            }

            DB::table('permissions')->whereIn('id', $legacyIds)->delete();
        }
    }

    /**
     * Remove rows that point to routes the plugin no longer exposes.
     */
    private function removeLegacyItems(): void
    {
        foreach (['admin.front-builder.pages.index', 'admin.theme-builder.index'] as $legacyRoute) {
            if (! Route::has($legacyRoute)) {
                DB::table('menu_items')->where('route_name', $legacyRoute)->delete();
            }
        }
    }

    /**
     * @param  array{route: string, title: string, icon?: string, permission?: string, sort_order?: int}  $definition
     */
    private function ensureMenuItem(?int $menuId, int $pluginId, array $definition): void
    {
        $existing = DB::table('menu_items')
            ->where('route_name', $definition['route'])
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            $updates = [];

            if ((int) $existing->plugin_id === 0) {
                $updates['plugin_id'] = $pluginId;
            }

            if (empty($existing->title_translations)) {
                $labels = $this->labels($pluginId, $definition['route'], $definition['title']);

                if ($labels !== []) {
                    $updates['title_translations'] = json_encode($labels, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($updates !== []) {
                DB::table('menu_items')->where('id', $existing->id)->update($updates + ['updated_at' => now()]);
            }

            return;
        }

        if ($menuId === null) {
            return;
        }

        DB::table('menu_items')->insert([
            'menu_id' => $menuId,
            'parent_id' => null,
            'plugin_id' => $pluginId,
            'title' => $definition['title'],
            'label' => $definition['title'],
            'title_translations' => json_encode($this->labels($pluginId, $definition['route'], $definition['title']), JSON_UNESCAPED_UNICODE),
            'type' => 'route',
            'url' => null,
            'route_name' => $definition['route'],
            'route_params' => null,
            'icon' => $definition['icon'] ?? null,
            'target' => '_self',
            'permission' => $definition['permission'] ?? null,
            'metadata' => null,
            'is_active' => true,
            'sort_order' => $definition['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function labels(int $pluginId, string $route, string $title): array
    {
        $plugin = Plugin::query()->find($pluginId);

        return $plugin === null
            ? []
            : app(MenuLabelResolver::class)->forItem($plugin, $route, null, $title);
    }
}
