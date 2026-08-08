<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuRegistrar
{
    public function __construct(
        private readonly MenuRepository $menus,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $menuDefinition
     */
    public function register(array $menuDefinition, ?Plugin $plugin = null): Menu
    {
        $definition = $this->normalizeMenuDefinition($menuDefinition, $plugin);

        $menu = $this->menus->updateOrCreateMenu([
            'key' => $definition['key'],
            'name' => $definition['name'],
            'location' => $definition['location'],
            'description' => $definition['description'],
            'source' => $definition['source'],
            'plugin_id' => $plugin?->id,
            'is_active' => $definition['is_active'],
            'sort_order' => $definition['sort_order'],
        ]);

        $this->menus->deleteMenuItems($menu);
        $this->createItems($menu, $definition['items'], null, $plugin);

        return $menu;
    }

    /**
     * @param array<int, mixed> $definitions
     */
    public function syncPluginMenus(Plugin $plugin, array $definitions): int
    {
        $count = 0;

        foreach ($this->normalizePluginMenus($definitions) as $definition) {
            $this->register($definition, $plugin);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function normalizeMenuDefinition(array $definition, ?Plugin $plugin): array
    {
        $location = isset($definition['location']) ? (string) $definition['location'] : 'admin';
        $items = $definition['items'] ?? [];

        if (! is_array($items)) {
            throw new InvalidArgumentException('Menu definition items must be an array.');
        }

        $name = isset($definition['name'])
            ? (string) $definition['name']
            : Str::headline($location.' menu');

        $key = isset($definition['key'])
            ? (string) $definition['key']
            : (($plugin?->slug ?? 'platform').'.'.$location);

        return [
            'key' => $key,
            'name' => $name,
            'location' => $location,
            'description' => isset($definition['description']) ? (string) $definition['description'] : null,
            'source' => isset($definition['source']) ? (string) $definition['source'] : ($plugin ? 'plugin' : 'platform'),
            'is_active' => (bool) ($definition['is_active'] ?? ($plugin === null || $plugin->status === Plugin::STATUS_ACTIVE)),
            'sort_order' => (int) ($definition['sort_order'] ?? $definition['order'] ?? 0),
            'items' => $items,
        ];
    }

    /**
     * @param array<int, mixed> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function normalizePluginMenus(array $definitions): array
    {
        if ($definitions === []) {
            return [];
        }

        $first = reset($definitions);

        if (is_array($first) && array_key_exists('location', $first)) {
            return array_values(array_filter($definitions, 'is_array'));
        }

        return [[
            'location' => 'admin',
            'items' => $definitions,
        ]];
    }

    /**
     * @param array<int, mixed> $items
     */
    private function createItems(Menu $menu, array $items, ?int $parentId, ?Plugin $plugin): void
    {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];

            foreach (['group', 'admin_group', 'parent', 'admin_parent', 'admin_sort_order', 'admin_order', 'group_sort_order'] as $metadataKey) {
                if (array_key_exists($metadataKey, $item) && ! array_key_exists($metadataKey, $metadata)) {
                    $metadata[$metadataKey] = $item[$metadataKey];
                }
            }

            $created = $this->menus->createMenuItem([
                'menu_id' => $menu->id,
                'parent_id' => $parentId,
                'plugin_id' => $plugin?->id,
                'title' => (string) ($item['title'] ?? $item['label'] ?? 'Untitled'),
                'label' => isset($item['label']) ? (string) $item['label'] : null,
                'type' => (string) ($item['type'] ?? 'link'),
                'url' => isset($item['url']) ? (string) $item['url'] : null,
                'route_name' => isset($item['route']) ? (string) $item['route'] : (isset($item['route_name']) ? (string) $item['route_name'] : null),
                'route_params' => isset($item['route_params']) && is_array($item['route_params']) ? $item['route_params'] : null,
                'icon' => isset($item['icon']) ? (string) $item['icon'] : null,
                'target' => isset($item['target']) ? (string) $item['target'] : null,
                'permission' => isset($item['permission']) ? (string) $item['permission'] : null,
                'metadata' => $metadata,
                'is_active' => (bool) ($item['is_active'] ?? true),
                'sort_order' => (int) ($item['sort_order'] ?? $item['order'] ?? $index),
            ]);

            $children = $item['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $this->createItems($menu, $children, $created->id, $plugin);
            }
        }
    }
}
