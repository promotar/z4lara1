<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Menus\MenuManager;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PluginMenuRegistry
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly MenuManager $menuManager,
    ) {
        //
    }

    public function register(PluginManifest $manifest): void
    {
        $menus = data_get($manifest->manifest, 'menus', []);

        if (! is_array($menus)) {
            return;
        }

        $registry = $this->all();
        $registry[$manifest->slug] = [
            'visible' => true,
            'items' => $menus,
        ];
        $this->write($registry);

        $plugin = $this->plugins->findBySlug($manifest->slug);

        if ($plugin !== null) {
            $this->menuManager->syncPluginMenus($plugin, $menus);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $slug): ?array
    {
        $registry = $this->all();

        return isset($registry[$slug]) && is_array($registry[$slug])
            ? $registry[$slug]
            : null;
    }

    public function hide(string $slug): void
    {
        $registry = $this->all();

        if (! isset($registry[$slug]) || ! is_array($registry[$slug])) {
            return;
        }

        $registry[$slug] = $this->normalizedEntry($registry[$slug], false);
        $this->write($registry);

        $plugin = $this->plugins->findBySlug($slug);

        if ($plugin !== null) {
            $this->menuManager->hidePluginMenus($plugin);
        }
    }

    public function show(string $slug): void
    {
        $registry = $this->all();

        if (! isset($registry[$slug]) || ! is_array($registry[$slug])) {
            return;
        }

        $registry[$slug] = $this->normalizedEntry($registry[$slug], true);
        $this->write($registry);

        $plugin = $this->plugins->findBySlug($slug);

        if ($plugin !== null) {
            $this->menuManager->showPluginMenus($plugin);
        }
    }

    /**
     * @param array<string, mixed>|null $menus
     */
    public function restore(string $slug, ?array $menus): void
    {
        if ($menus === null) {
            $this->unregister($slug);

            return;
        }

        $registry = $this->all();
        $registry[$slug] = $menus;
        $this->write($registry);

        $plugin = $this->plugins->findBySlug($slug);

        if ($plugin !== null) {
            $items = $menus['items'] ?? $menus;

            if (is_array($items)) {
                $this->menuManager->syncPluginMenus($plugin, $items);
            }
        }
    }

    public function unregister(string $slug): void
    {
        $registry = $this->all();
        unset($registry[$slug]);
        $this->write($registry);

        $plugin = $this->plugins->findBySlug($slug);

        if ($plugin !== null) {
            $this->menuManager->removePluginMenus($plugin);
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function normalizedEntry(array $entry, bool $visible): array
    {
        if (array_key_exists('items', $entry)) {
            $entry['visible'] = $visible;

            return $entry;
        }

        return [
            'visible' => $visible,
            'items' => $entry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            return [];
        }

        if (! DB::table('platform_plugin_registry_entries')->where('registry_type', 'menus')->exists()) {
            $this->importLegacyFile();
        }

        return DB::table('platform_plugin_registry_entries')
            ->where('registry_type', 'menus')
            ->get(['plugin_slug', 'payload'])
            ->mapWithKeys(function (object $entry): array {
                $payload = json_decode((string) $entry->payload, true);

                return [$entry->plugin_slug => is_array($payload) ? $payload : []];
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $registry
     */
    private function write(array $registry): void
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            return;
        }

        $slugs = array_keys($registry);

        DB::table('platform_plugin_registry_entries')
            ->where('registry_type', 'menus')
            ->when($slugs !== [], fn ($query) => $query->whereNotIn('plugin_slug', $slugs))
            ->when($slugs === [], fn ($query) => $query)
            ->delete();

        foreach ($registry as $slug => $payload) {
            DB::table('platform_plugin_registry_entries')->updateOrInsert(
                ['registry_type' => 'menus', 'plugin_slug' => $slug],
                [
                    'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function importLegacyFile(): void
    {
        $path = storage_path('app/platform/plugin-menus.json');

        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (is_array($data) && $data !== []) {
            $this->write($data);
        }
    }
}
