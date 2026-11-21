<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reconciles plugin menu definitions with the database without ever replacing
 * administrator edits.
 *
 * Rules:
 *  - A menu is matched by plugin + location (then by key) and is never renamed
 *    or duplicated when it already exists.
 *  - Menu items are matched by route/url (then title) inside their parent; an
 *    existing item keeps its title, label, icon, order and visibility.
 *  - Only missing menus/items are created, with translations taken from the
 *    definition or from `modules/<slug>/lang/menus.json`.
 */
class MenuRegistrar
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly MenuLabelResolver $labels,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $menuDefinition
     */
    public function register(array $menuDefinition, ?Plugin $plugin = null): Menu
    {
        $definition = $this->normalizeMenuDefinition($menuDefinition, $plugin);
        $menu = $this->findExistingMenu($definition, $plugin);

        if ($menu === null) {
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

            $translations = $this->menuTranslations($plugin, $definition, (string) $menu->name);

            if ($translations !== []) {
                $menu->forceFill(['name_translations' => $translations])->save();
            }
        } else {
            $this->refreshMenu($menu, $definition, $plugin);
        }

        $this->reconcileItems($menu, $definition['items'], null, $plugin);

        return $menu;
    }

    /**
     * @param  array<int, mixed>  $definitions
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
     * @param  array<string, mixed>  $definition
     */
    private function findExistingMenu(array $definition, ?Plugin $plugin): ?Menu
    {
        if ($plugin !== null) {
            $menu = Menu::query()
                ->where('plugin_id', $plugin->id)
                ->where('location', $definition['location'])
                ->orderBy('id')
                ->first();

            if ($menu !== null) {
                return $menu;
            }
        }

        $menu = Menu::query()->where('key', $definition['key'])->first();

        if ($menu !== null) {
            return $menu;
        }

        // Legacy installs may keep a plugin menu under an older key with no
        // plugin owner; adopt it when its items point at the same routes.
        $routes = collect($this->flattenRoutes($definition['items']))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($routes === []) {
            return null;
        }

        return Menu::query()
            ->where('location', $definition['location'])
            ->whereHas('items', fn ($query) => $query->whereIn('route_name', $routes))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function flattenRoutes(array $items): array
    {
        $routes = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $route = $item['route'] ?? $item['route_name'] ?? null;

            if (is_string($route) && $route !== '') {
                $routes[] = $route;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $routes = array_merge($routes, $this->flattenRoutes($item['children']));
            }
        }

        return $routes;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function refreshMenu(Menu $menu, array $definition, ?Plugin $plugin): void
    {
        $updates = [];

        if ($plugin !== null && (int) $menu->plugin_id !== (int) $plugin->id) {
            $updates['plugin_id'] = $plugin->id;
        }

        if (empty($menu->name_translations)) {
            $translations = $this->menuTranslations($plugin, $definition, (string) $menu->name);

            if ($translations !== []) {
                $updates['name_translations'] = $translations;
            }
        } else {
            $merged = $this->withMissingTranslations(
                $this->menuTranslations($plugin, $definition, (string) $menu->name),
                $menu->name_translations,
            );

            if ($merged !== $menu->name_translations) {
                $updates['name_translations'] = $merged;
            }
        }

        if (trim((string) $menu->description) === '' && $definition['description'] !== null) {
            $updates['description'] = $definition['description'];
        }

        if ($updates !== []) {
            $menu->forceFill($updates)->save();
        }
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function reconcileItems(Menu $menu, array $items, ?int $parentId, ?Plugin $plugin): void
    {
        if ($items === []) {
            return;
        }

        $existing = $menu->items()
            ->where('parent_id', $parentId)
            ->orderBy('id')
            ->get()
            ->all();
        $claimed = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $identity = $this->definitionIdentity($item);
            $match = null;

            foreach ($existing as $candidate) {
                if (in_array($candidate->id, $claimed, true)) {
                    continue;
                }

                if ($this->itemIdentity($candidate) === $identity) {
                    $match = $candidate;

                    break;
                }
            }

            // Legacy installs may keep manifest children at the top level; adopt
            // the existing row (with its administrator edits) instead of
            // duplicating it under the intended parent.
            if ($match === null) {
                foreach ($menu->items()->orderBy('id')->get() as $candidate) {
                    if (in_array($candidate->id, $claimed, true)) {
                        continue;
                    }

                    if ($this->itemIdentity($candidate) === $identity) {
                        $match = $candidate;
                        $match->forceFill(['parent_id' => $parentId])->save();

                        break;
                    }
                }
            }

            if ($match !== null) {
                $claimed[] = $match->id;
                $this->refreshItem($match, $item, $plugin);
                $itemId = (int) $match->id;
            } else {
                $created = $this->createItem($menu, $item, $parentId, $plugin, $index);
                $claimed[] = (int) $created->id;
                $itemId = (int) $created->id;
            }

            $children = $item['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $this->reconcileItems($menu, $children, $itemId, $plugin);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function refreshItem(MenuItem $existing, array $item, ?Plugin $plugin): void
    {
        $updates = [];

        if (empty($existing->title_translations)) {
            $translations = $this->itemTranslations($plugin, $item, (string) $existing->title);

            if ($translations !== []) {
                $updates['title_translations'] = $translations;
            }
        } else {
            $merged = $this->withMissingTranslations(
                $this->itemTranslations($plugin, $item, (string) $existing->title),
                $existing->title_translations,
            );

            if ($merged !== $existing->title_translations) {
                $updates['title_translations'] = $merged;
            }
        }

        if (trim((string) $existing->icon) === '' && isset($item['icon']) && trim((string) $item['icon']) !== '') {
            $updates['icon'] = (string) $item['icon'];
        }

        if (empty($existing->route_params) && isset($item['route_params']) && is_array($item['route_params']) && $item['route_params'] !== []) {
            $updates['route_params'] = $item['route_params'];
        }

        if ($updates !== []) {
            $existing->forceFill($updates)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItem(Menu $menu, array $item, ?int $parentId, ?Plugin $plugin, int $index): MenuItem
    {
        $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];

        foreach (['group', 'admin_group', 'parent', 'admin_parent', 'admin_sort_order', 'admin_order', 'group_sort_order'] as $metadataKey) {
            if (array_key_exists($metadataKey, $item) && ! array_key_exists($metadataKey, $metadata)) {
                $metadata[$metadataKey] = $item[$metadataKey];
            }
        }

        $title = (string) ($item['title'] ?? $item['label'] ?? 'Untitled');

        $created = $this->menus->createMenuItem([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'plugin_id' => $plugin?->id,
            'title' => $title,
            'label' => isset($item['label']) ? (string) $item['label'] : null,
            'title_translations' => $this->itemTranslations($plugin, $item, $title) ?: null,
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

        return $created;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    private function menuTranslations(?Plugin $plugin, array $definition, string $fallback): array
    {
        $provided = $definition['name_translations'] ?? $definition['translations'] ?? [];
        $provided = is_array($provided) ? $provided : [];

        return $this->mergeTranslations($provided, $this->labels->forMenu($plugin, $definition['key']), $fallback);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function itemTranslations(?Plugin $plugin, array $item, string $fallback): array
    {
        $provided = $item['title_translations'] ?? $item['translations'] ?? [];
        $provided = is_array($provided) ? $provided : [];
        $route = isset($item['route']) ? (string) $item['route'] : (isset($item['route_name']) ? (string) $item['route_name'] : null);
        $url = isset($item['url']) ? (string) $item['url'] : null;

        return $this->mergeTranslations(
            $provided,
            $this->labels->forItem($plugin, $route, $url, (string) ($item['title'] ?? $item['label'] ?? $fallback)),
            $fallback,
        );
    }

    /**
     * Merge resolved labels into the stored map without overwriting any
     * administrator-provided translation.
     *
     * @param  array<string, string>  $resolved
     * @param  mixed  $current
     * @return array<string, string>
     */
    private function withMissingTranslations(array $resolved, mixed $current): array
    {
        $result = [];

        if (is_array($current)) {
            foreach ($current as $locale => $label) {
                $label = trim((string) $label);

                if ($locale !== '' && $label !== '') {
                    $result[(string) $locale] = $label;
                }
            }
        }

        foreach ($resolved as $locale => $label) {
            if (! array_key_exists($locale, $result)) {
                $result[$locale] = $label;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $provided
     * @param  array<string, string>  $resolved
     * @return array<string, string>
     */
    private function mergeTranslations(array $provided, array $resolved, string $default): array
    {
        $result = [];

        foreach ([$resolved, $provided] as $source) {
            foreach ($source as $locale => $label) {
                $locale = str_replace('_', '-', trim((string) $locale));
                $label = trim((string) $label);

                if ($locale !== '' && $label !== '') {
                    $result[$locale] = $label;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $definition
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
     * @param  array<int, mixed>  $definitions
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
     * @param  array<string, mixed>  $item
     */
    private function definitionIdentity(array $item): string
    {
        $identity = trim((string) ($item['route'] ?? $item['route_name'] ?? $item['url'] ?? $item['title'] ?? $item['label'] ?? ''));

        return strtolower($identity);
    }

    private function itemIdentity(object $item): string
    {
        $identity = trim((string) ($item->route_name ?: $item->url ?: $item->title));

        return strtolower($identity);
    }
}
