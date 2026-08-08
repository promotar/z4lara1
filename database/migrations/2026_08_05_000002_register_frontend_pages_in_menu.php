<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items') || ! Schema::hasTable('platform_pages')) {
            return;
        }

        $now = now();
        $menuId = DB::table('menus')->where('location', 'frontend')->where('key', 'platform.frontend')->value('id');

        if (! $menuId) {
            $menuId = DB::table('menus')->insertGetId([
                'key' => 'platform.frontend',
                'name' => 'Frontend Menu',
                'location' => 'frontend',
                'description' => 'Editable frontend navigation menu.',
                'source' => 'platform',
                'plugin_id' => null,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->ensureItem((int) $menuId, 'route', 'front.home', null, [
            'title' => 'Home',
            'label' => 'Home',
            'parent_id' => null,
            'sort_order' => 0,
            'metadata' => ['audience' => 'all', 'action_method' => 'get', 'source' => 'frontend-page-sync'],
        ]);

        $pagesParentId = $this->ensureItem((int) $menuId, 'header', null, null, [
            'title' => 'Pages',
            'label' => 'Pages',
            'parent_id' => null,
            'sort_order' => 10,
            'metadata' => ['audience' => 'all', 'action_method' => 'get', 'source' => 'frontend-page-sync'],
        ]);

        $pageColumns = ['id', 'title', 'slug', 'status'];
        foreach (['parent_id', 'menu_label', 'sort_order'] as $column) {
            if (Schema::hasColumn('platform_pages', $column)) {
                $pageColumns[] = $column;
            }
        }

        $pages = DB::table('platform_pages')
            ->when(Schema::hasColumn('platform_pages', 'content_type'), fn ($query) => $query->where('content_type', 'page'))
            ->where('status', 'published')
            ->when(Schema::hasColumn('platform_pages', 'sort_order'), fn ($query) => $query->orderBy('sort_order'))
            ->orderBy('id')
            ->get($pageColumns);

        $pageItems = [];

        foreach ($pages as $index => $page) {
            $url = '/pages/'.ltrim((string) $page->slug, '/');
            $label = trim((string) (($page->menu_label ?? null) ?: $page->title));
            $pageItems[(int) $page->id] = $this->ensureItem((int) $menuId, 'link', null, $url, [
                'title' => (string) $page->title,
                'label' => $label,
                'parent_id' => $pagesParentId,
                'sort_order' => ((int) ($page->sort_order ?? 0) * 100) + (($index + 1) * 10),
                'metadata' => [
                    'audience' => 'all',
                    'action_method' => 'get',
                    'source' => 'frontend-page-sync',
                    'platform_page_id' => (int) $page->id,
                ],
            ]);
        }

        foreach ($pages as $page) {
            $pageId = (int) $page->id;
            $parentPageId = (int) ($page->parent_id ?? 0);

            if ($parentPageId > 0 && isset($pageItems[$pageId], $pageItems[$parentPageId])) {
                DB::table('menu_items')->where('id', $pageItems[$pageId])->update([
                    'parent_id' => $pageItems[$parentPageId],
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Registered pages become user-managed menu data after migration.
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function ensureItem(int $menuId, string $type, ?string $routeName, ?string $url, array $values): int
    {
        $query = DB::table('menu_items')->where('menu_id', $menuId)->where('type', $type);

        if ($routeName !== null) {
            $query->where('route_name', $routeName);
        } elseif ($url !== null) {
            $query->where('url', $url);
        } else {
            $query->where('title', $values['title']);
        }

        $existing = $query->first();
        $metadata = is_array($values['metadata'] ?? null) ? $values['metadata'] : [];

        if ($existing) {
            $storedMetadata = json_decode((string) ($existing->metadata ?? ''), true);
            $payload = $values;
            $payload['metadata'] = json_encode(array_replace(is_array($storedMetadata) ? $storedMetadata : [], $metadata), JSON_THROW_ON_ERROR);
            $payload['is_active'] = true;
            $payload['updated_at'] = now();
            DB::table('menu_items')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table('menu_items')->insertGetId([
            'menu_id' => $menuId,
            'parent_id' => $values['parent_id'] ?? null,
            'plugin_id' => null,
            'title' => $values['title'],
            'label' => $values['label'] ?? null,
            'type' => $type,
            'url' => $url,
            'route_name' => $routeName,
            'route_params' => null,
            'icon' => null,
            'target' => '_self',
            'permission' => null,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'sort_order' => $values['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
