<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\Assets\AssetManager;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Repositories\PluginRepository;

class PluginAssetRemover
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly AssetManager $assets,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        $plugin = $this->plugins->findBySlug($manifest->slug);

        if ($plugin === null) {
            return [];
        }

        return $this->assets->removePluginAssets($plugin)
            ? [public_path('platform/plugins/'.$plugin->slug)]
            : [];
    }
}
