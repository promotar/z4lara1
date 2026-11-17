<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RequiredCorePluginSynchronizer
{
    public function __construct(
        private readonly PluginManifestReader $manifests,
        private readonly ?AdminThemeManager $adminThemes = null,
    ) {}

    public function synchronize(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        foreach (glob(base_path('modules/*/module.json')) ?: [] as $manifestPath) {
            try {
                $manifest = $this->manifests->read($manifestPath);
            } catch (Throwable $exception) {
                Log::warning('Required plugin discovery skipped an invalid manifest.', [
                    'manifest' => $manifestPath,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! $this->isRequiredCorePlugin($manifest->manifest)) {
                if ($this->isDefaultAdminTheme($manifest->manifest)) {
                    $this->synchronizeDefaultAdminTheme($manifest, $manifestPath);
                }

                continue;
            }

            $plugin = Plugin::query()->firstOrNew(['slug' => $manifest->slug]);
            $plugin->forceFill([
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'author' => $manifest->author,
                'status' => Plugin::STATUS_ACTIVE,
                'path' => dirname($manifestPath),
                'provider' => $manifest->provider,
                'manifest' => $manifest->manifest,
                'dependencies' => $manifest->dependencies,
                'installed_at' => $plugin->installed_at ?? now(),
                'activated_at' => $plugin->activated_at ?? now(),
                'disabled_at' => null,
            ]);

            if (! $plugin->exists || $plugin->isDirty()) {
                $plugin->save();
            }

            $this->enableRuntime($manifest->slug);
        }

        $this->adminThemes?->ensureDefaultWhenNoAdminThemeActive();
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function isRequiredCorePlugin(array $manifest): bool
    {
        $core = data_get($manifest, 'core', data_get($manifest, 'lifecycle.core', false));

        return $core === true
            || (is_string($core) && in_array(strtolower(trim($core)), ['1', 'true', 'yes', 'on'], true))
            || (is_int($core) && $core === 1);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function isDefaultAdminTheme(array $manifest): bool
    {
        return (string) data_get($manifest, 'slug') === AdminThemeManager::DEFAULT_SLUG
            && strtolower((string) data_get($manifest, 'type')) === 'theme'
            && strtolower((string) data_get($manifest, 'theme.scope')) === 'admin'
            && (bool) data_get($manifest, 'theme.default', false);
    }

    private function synchronizeDefaultAdminTheme(\App\Platform\Core\DTOs\PluginManifest $manifest, string $manifestPath): void
    {
        $plugin = Plugin::query()->firstOrNew(['slug' => $manifest->slug]);
        $status = Plugin::query()
            ->where('slug', '!=', $manifest->slug)
            ->where('status', Plugin::STATUS_ACTIVE)
            ->get()
            ->filter(fn (Plugin $plugin): bool => $plugin->isAdminTheme())
            ->isNotEmpty()
                ? Plugin::STATUS_DISABLED
                : Plugin::STATUS_ACTIVE;

        $plugin->forceFill([
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'author' => $manifest->author,
            'status' => $status,
            'path' => dirname($manifestPath),
            'provider' => $manifest->provider,
            'manifest' => $manifest->manifest,
            'dependencies' => $manifest->dependencies,
            'installed_at' => $plugin->installed_at ?? now(),
            'activated_at' => $status === Plugin::STATUS_ACTIVE ? ($plugin->activated_at ?? now()) : null,
            'disabled_at' => $status === Plugin::STATUS_DISABLED ? ($plugin->disabled_at ?? now()) : null,
        ]);

        if (! $plugin->exists || $plugin->isDirty()) {
            $plugin->save();
        }

        if ($status === Plugin::STATUS_ACTIVE) {
            $this->enableRuntime($manifest->slug);
        }
    }

    private function enableRuntime(string $slug): void
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            return;
        }

        $existing = DB::table('platform_plugin_registry_entries')
            ->where('registry_type', 'runtime')
            ->where('plugin_slug', $slug)
            ->value('payload');
        $payload = is_string($existing) ? json_decode($existing, true) : [];
        $payload = is_array($payload) ? $payload : [];
        $payload['runtime_enabled'] = true;

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
