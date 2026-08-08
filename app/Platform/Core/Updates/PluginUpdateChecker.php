<?php

namespace App\Platform\Core\Updates;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Models\PluginUpdate;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Carbon;

class PluginUpdateChecker
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly VersionComparator $versions,
    ) {
        //
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function check(): array
    {
        return $this->plugins->all()
            ->mapWithKeys(fn (Plugin $plugin): array => [$plugin->slug => $this->checkPlugin($plugin)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkPlugin(Plugin $plugin): array
    {
        $metadata = $this->metadata($plugin->manifest ?? []);
        $availableVersion = $this->availableVersion($metadata) ?? (string) $plugin->version;
        $updateAvailable = $this->versions->isUpdateAvailable((string) $plugin->version, $availableVersion);
        $record = null;

        if ($metadata !== []) {
            $record = PluginUpdate::query()->updateOrCreate([
                'plugin_id' => $plugin->id,
                'available_version' => $availableVersion,
            ], [
                'plugin_slug' => $plugin->slug,
                'version' => $availableVersion,
                'current_version' => (string) $plugin->version,
                'changelog' => $this->changelog($metadata),
                'package_url' => $this->packageUrl($metadata),
                'checked_at' => Carbon::now(),
            ]);
        }

        return [
            'type' => 'plugin',
            'slug' => $plugin->slug,
            'current_version' => (string) $plugin->version,
            'available_version' => $availableVersion,
            'update_available' => $updateAvailable,
            'record_id' => $record?->id,
            'changelog' => $this->changelog($metadata),
            'package_url' => $this->packageUrl($metadata),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function metadata(array $manifest): array
    {
        $metadata = data_get($manifest, 'updates', data_get($manifest, 'update', []));

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function availableVersion(array $metadata): ?string
    {
        $version = data_get($metadata, 'available_version', data_get($metadata, 'version'));

        return is_string($version) && trim($version) !== '' ? $version : null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>|null
     */
    private function changelog(array $metadata): ?array
    {
        $changelog = data_get($metadata, 'changelog');

        if (is_array($changelog)) {
            return $changelog;
        }

        return is_string($changelog) ? ['text' => $changelog] : null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function packageUrl(array $metadata): ?string
    {
        $url = data_get($metadata, 'package_url');

        return is_string($url) && trim($url) !== '' ? $url : null;
    }
}
