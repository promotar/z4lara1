<?php

namespace Modules\PageBuilder;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VvvebJsLifecycle
{
    public function activate(): void
    {
        $pluginId = Schema::hasTable('plugins') ? DB::table('plugins')->where('slug', 'page-builder')->value('id') : null;

        if ($pluginId && Schema::hasTable('menu_items')) {
            DB::table('menu_items')->whereIn('route_name', [
                'admin.front-builder.pages.index',
                'admin.theme-builder.index',
            ])->delete();
            $contentMenuId = Schema::hasTable('menus')
                ? DB::table('menus')->where('key', 'platform.admin.content-management')->where('location', 'admin')->value('id')
                : null;

            if ($contentMenuId) {
                $builderItemIds = DB::table('menu_items')->where('route_name', 'admin.pages.index')->orderBy('id')->pluck('id');
                $builderItemId = $builderItemIds->first();

                if (! $builderItemId) {
                    $builderItemId = DB::table('menu_items')->insertGetId([
                        'menu_id' => $contentMenuId, 'parent_id' => null, 'plugin_id' => $pluginId,
                        'title' => 'VvvebJs Builder', 'label' => 'VvvebJs Builder', 'type' => 'route',
                        'url' => null, 'route_name' => 'admin.pages.index', 'route_params' => null,
                        'icon' => 'V', 'target' => '_self', 'permission' => 'pages.manage', 'metadata' => null,
                        'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                DB::table('menu_items')->where('id', $builderItemId)->update([
                    'menu_id' => $contentMenuId,
                    'plugin_id' => $pluginId,
                    'title' => 'VvvebJs Builder',
                    'label' => 'VvvebJs Builder',
                    'icon' => 'V',
                    'permission' => 'pages.manage',
                    'is_active' => true,
                    'sort_order' => 10,
                    'updated_at' => now(),
                ]);
                DB::table('menu_items')->where('route_name', 'admin.pages.index')->where('id', '!=', $builderItemId)->delete();
                DB::table('menus')->where('key', 'page-builder.admin')->whereNotExists(
                    fn ($query) => $query->selectRaw('1')->from('menu_items')->whereColumn('menu_items.menu_id', 'menus.id')
                )->delete();
            }
            DB::table('menu_items')->where('route_name', 'admin.vvveb.layout')->update([
                'title' => 'Theme Layout',
                'label' => 'Theme Layout',
                'icon' => 'T',
                'permission' => 'pages.manage',
                'is_active' => true,
                'updated_at' => now(),
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
}
