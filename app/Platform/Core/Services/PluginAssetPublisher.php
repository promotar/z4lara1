<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Assets\AssetManager;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Repositories\PluginRepository;

class PluginAssetPublisher
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly AssetManager $assets,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(string $pluginPath, PluginManifest $manifest): array
    {
        $plugin = $this->plugins->findBySlug($manifest->slug);

        if ($plugin === null) {
            return ['published' => false, 'copied_files' => []];
        }

        return $this->assets->publishPluginAssets($plugin, $pluginPath, $manifest->manifest);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function rollback(array $state): void
    {
        if (($state['published'] ?? false) !== true) {
            return;
        }

        $files = array_values(array_filter(
            $state['created_files'] ?? [],
            fn (mixed $file): bool => is_string($file),
        ));

        $this->assets->rollbackPublishedPluginAssets($files);
    }
}
