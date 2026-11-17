<?php

namespace App\Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PluginRuntimeRegistry
{
    public function disable(string $slug): void
    {
        $registry = $this->all();
        $registry[$slug] = array_merge($registry[$slug] ?? [], [
            'hooks_enabled' => false,
            'runtime_enabled' => false,
        ]);
        $this->write($registry);
    }

    public function enable(string $slug): void
    {
        $registry = $this->all();
        $registry[$slug] = array_merge($registry[$slug] ?? [], [
            'hooks_enabled' => true,
            'runtime_enabled' => true,
        ]);
        $this->write($registry);
    }

    public function hooksEnabled(string $slug): bool
    {
        $registry = $this->all();

        return (bool) data_get($registry, "{$slug}.hooks_enabled", true);
    }

    public function forget(string $slug): void
    {
        $registry = $this->all();
        unset($registry[$slug]);
        $this->write($registry);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            return [];
        }

        if (! DB::table('platform_plugin_registry_entries')->where('registry_type', 'runtime')->exists()) {
            $this->importLegacyFile();
        }

        return DB::table('platform_plugin_registry_entries')
            ->where('registry_type', 'runtime')
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
            ->where('registry_type', 'runtime')
            ->when($slugs !== [], fn ($query) => $query->whereNotIn('plugin_slug', $slugs))
            ->when($slugs === [], fn ($query) => $query)
            ->delete();

        foreach ($registry as $slug => $payload) {
            DB::table('platform_plugin_registry_entries')->updateOrInsert(
                ['registry_type' => 'runtime', 'plugin_slug' => $slug],
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
        $path = storage_path('app/platform/plugin-runtime.json');

        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (is_array($data) && $data !== []) {
            $this->write($data);
        }
    }
}
