<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PluginSettingsRemover
{
    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return [];
        }

        $declared = $this->declaredSettings($manifest);
        $removed = [];

        $settings = DB::table('platform_settings')
            ->get(['id', 'group_key', 'setting_key']);

        foreach ($settings as $setting) {
            $fullKey = $setting->group_key.'.'.$setting->setting_key;

            if ($this->ownedByPlugin($fullKey, $manifest->slug, $declared)
                || $this->ownedByPlugin((string) $setting->group_key, $manifest->slug, $declared)
                || $this->ownedByPlugin((string) $setting->setting_key, $manifest->slug, $declared)
            ) {
                DB::table('platform_settings')->where('id', $setting->id)->delete();
                $removed[] = $fullKey;
            }
        }

        return array_values(array_unique($removed));
    }

    /**
     * @return array<int, string>
     */
    private function declaredSettings(PluginManifest $manifest): array
    {
        $settings = array_merge(
            (array) data_get($manifest->manifest, 'uninstall.settings', []),
            (array) data_get($manifest->manifest, 'settings', []),
        );

        if (! is_array($settings)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (mixed $setting): ?string {
            if (is_string($setting)) {
                return $setting;
            }

            if (is_array($setting) && isset($setting['key'])) {
                return (string) $setting['key'];
            }

            return null;
        }, $settings))));
    }

    /**
     * @param  array<int, string>  $declared
     */
    private function ownedByPlugin(string $key, string $slug, array $declared): bool
    {
        return in_array($key, $declared, true)
            || $key === $slug
            || str_starts_with($key, $slug.'.')
            || str_starts_with($key, $slug.'_')
            || str_starts_with($key, $slug.'::');
    }
}
