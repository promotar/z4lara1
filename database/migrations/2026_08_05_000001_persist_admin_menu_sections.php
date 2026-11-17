<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array{name:string, sort:int}> */
    private array $sections = [
        'overview' => ['name' => 'Overview', 'sort' => 10],
        'content-management' => ['name' => 'Content Management', 'sort' => 20],
        'platform' => ['name' => 'Platform', 'sort' => 30],
        'users-access' => ['name' => 'Users & Access', 'sort' => 50],
        'system' => ['name' => 'System', 'sort' => 60],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        DB::transaction(function (): void {
            $menuIds = $this->createSections();
            $this->moveRegisteredItems($menuIds);
            $this->insertMissingItems($menuIds);
            $this->removeEmptyLegacyMenus(array_values($menuIds));
        });
    }

    public function down(): void
    {
        // Admin sections are user-editable content and are intentionally retained.
    }

    /** @return array<string, int> */
    private function createSections(): array
    {
        $ids = [];

        foreach ($this->sections as $key => $section) {
            $menu = DB::table('menus')->where('key', 'platform.admin.'.$key)->where('location', 'admin')->first();

            if ($menu === null) {
                $ids[$key] = DB::table('menus')->insertGetId([
                    'key' => 'platform.admin.'.$key,
                    'name' => $section['name'],
                    'location' => 'admin',
                    'description' => $section['name'].' admin sidebar section.',
                    'source' => 'platform',
                    'plugin_id' => null,
                    'is_active' => true,
                    'sort_order' => $section['sort'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $ids[$key] = (int) $menu->id;
            }
        }

        return $ids;
    }

    /** @param array<string, int> $menuIds */
    private function moveRegisteredItems(array $menuIds): void
    {
        $items = DB::table('menu_items')
            ->join('menus', 'menus.id', '=', 'menu_items.menu_id')
            ->where('menus.location', 'admin')
            ->select('menu_items.*')
            ->orderByRaw('menu_items.parent_id IS NOT NULL')
            ->orderBy('menu_items.id')
            ->get();
        $targets = [];

        foreach ($items as $item) {
            $parentTarget = $item->parent_id ? ($targets[(int) $item->parent_id] ?? null) : null;
            $section = $parentTarget ?: $this->sectionFor($item);
            $targets[(int) $item->id] = $section;
            DB::table('menu_items')->where('id', $item->id)->update([
                'menu_id' => $menuIds[$section],
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, int> $menuIds */
    private function insertMissingItems(array $menuIds): void
    {
        $items = [
            ['overview', 'Dashboard', 'dashboard', 'D', null, 10],
            ['overview', 'Documentation', 'admin.documentation.index', 'O', 'documentation.manage', 20],
            ['content-management', 'VvvebJs Builder', 'admin.pages.index', 'V', 'pages.manage', 10],
            ['content-management', 'Theme Layout', 'admin.vvveb.layout', 'T', 'pages.manage', 20],
            ['content-management', 'Menus', 'admin.menus.index', 'N', 'menus.manage', 30],
            ['content-management', 'Media', 'admin.media.index', 'M', 'media.manage', 40],
            ['platform', 'Platform Registry', 'admin.platform-registry.index', 'R', 'platform-registry.view', 10],
            ['platform', 'Plugins', 'admin.plugins.index', 'P', 'plugins.view', 20],
            ['platform', 'Install Plugin', 'admin.plugins.create', 'I', 'plugins.install', 30],
            ['users-access', 'Users', 'admin.users.index', 'U', 'users.manage', 10],
            ['users-access', 'Roles', 'admin.roles.index', 'R', 'roles.manage', 20],
            ['users-access', 'Permissions', 'admin.permissions.index', 'P', 'permissions.manage', 30],
            ['system', 'Settings', 'admin.settings.index', 'S', 'settings.manage', 10],
            ['system', 'Backup', 'admin.backups.index', 'B', 'platform-registry.view', 20],
        ];

        foreach ($items as [$section, $title, $route, $icon, $permission, $sort]) {
            $existingIds = DB::table('menu_items')
                ->join('menus', 'menus.id', '=', 'menu_items.menu_id')
                ->where('menus.location', 'admin')
                ->where('menu_items.route_name', $route)
                ->pluck('menu_items.id');

            if ($existingIds->isNotEmpty()) {
                DB::table('menu_items')->whereIn('id', $existingIds)
                    ->update([
                        'menu_id' => $menuIds[$section],
                        'sort_order' => $sort,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('menu_items')->insert([
                'menu_id' => $menuIds[$section],
                'parent_id' => null,
                'plugin_id' => null,
                'title' => $title,
                'label' => $title,
                'type' => 'route',
                'url' => null,
                'route_name' => $route,
                'route_params' => null,
                'icon' => $icon,
                'target' => '_self',
                'permission' => $permission,
                'metadata' => null,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function sectionFor(object $item): string
    {
        $metadata = is_string($item->metadata) ? json_decode($item->metadata, true) : $item->metadata;
        $group = is_array($metadata) ? ($metadata['admin_group'] ?? $metadata['group'] ?? null) : null;

        if (is_string($group) && isset($this->sections[Str::slug($group)])) {
            return Str::slug($group);
        }

        return match ((string) $item->route_name) {
            'dashboard', 'admin.documentation.index' => 'overview',
            'admin.pages.index', 'admin.vvveb.layout', 'admin.menus.index', 'admin.media.index',
            'admin.plugins.blog.index', 'admin.plugins.blog.posts.index', 'admin.plugins.blog.posts.create',
            'admin.plugins.blog.categories.index', 'admin.plugins.blog.categories.create',
            'admin.plugins.blog.settings.edit' => 'content-management',
            'admin.users.index', 'admin.roles.index', 'admin.permissions.index' => 'users-access',
            'admin.settings.index', 'admin.backups.index' => 'system',
            default => 'platform',
        };
    }

    /** @param list<int> $canonicalMenuIds */
    private function removeEmptyLegacyMenus(array $canonicalMenuIds): void
    {
        DB::table('menus')
            ->where('location', 'admin')
            ->whereNotIn('id', $canonicalMenuIds)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('menu_items')->whereColumn('menu_items.menu_id', 'menus.id'))
            ->delete();
    }
};
