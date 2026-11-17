<?php

namespace App\Platform\Core\Assets;

use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Plugin;
use Throwable;

class AssetManager
{
    public function __construct(
        private readonly AssetPublisher $publisher,
        private readonly AssetRemover $remover,
        private readonly AssetUrlGenerator $urls,
        private readonly AssetCacheBuster $cacheBuster,
        private readonly OperationLogger $operations,
        private readonly FailedOperationLogger $failedOperations,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function publishPluginAssets(
        Plugin $plugin,
        ?string $pluginPath = null,
        ?array $manifest = null,
    ): array {
        $manifest ??= $plugin->manifest ?? [];
        $pluginPath ??= (string) $plugin->path;

        return $this->publish('plugin', $plugin->slug, new AssetManifest(
            type: 'plugins',
            slug: $plugin->slug,
            sourcePath: $this->sourcePath($pluginPath, $manifest, 'resources/assets'),
            destinationPath: public_path('platform/plugins/'.$plugin->slug),
            manifest: $manifest,
        ));
    }

    public function removePluginAssets(Plugin $plugin): bool
    {
        return $this->remove('plugin', 'plugins', $plugin->slug);
    }

    /**
     * @param  list<string>  $files
     */
    public function rollbackPublishedPluginAssets(array $files): void
    {
        $this->remover->removePublishedFiles($files, 'plugins');
    }

    public function assetUrl(string $type, string $slug, string $path): string
    {
        return $this->urls->url($type, $slug, $path);
    }

    public function versionedUrl(string $url): string
    {
        return $this->cacheBuster->versionedUrl($url);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function sourcePath(string $root, array $manifest, string $default): string
    {
        $configured = data_get($manifest, 'assets.source')
            ?: data_get($manifest, 'assets.public');

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim(
            is_string($configured) && trim($configured) !== '' ? $configured : $default,
            '/\\',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function publish(string $targetType, string $slug, AssetManifest $manifest): array
    {
        $operation = $this->operations->start('asset.publish', $targetType, $slug, [
            'asset_type' => $manifest->type,
            'source' => $manifest->sourcePath,
            'destination' => $manifest->destinationPath,
        ]);

        try {
            $result = $this->publisher->publish($manifest);
            $this->operations->success($operation, 'Assets published.', $result);

            return $result;
        } catch (Throwable $exception) {
            $this->failedOperations->log($operation, $exception);

            throw $exception;
        }
    }

    private function remove(string $targetType, string $assetType, string $slug): bool
    {
        $operation = $this->operations->start('asset.remove', $targetType, $slug, [
            'asset_type' => $assetType,
        ]);

        try {
            $removed = $this->remover->remove($assetType, $slug);
            $this->operations->success($operation, 'Assets removed.', ['removed' => $removed]);

            return $removed;
        } catch (Throwable $exception) {
            $this->failedOperations->log($operation, $exception);

            throw $exception;
        }
    }
}
