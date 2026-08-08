<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Assets\AssetManager;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PluginAssetRegistry
{
    /** @var array<string, array{styles: list<array<string, string>>, scripts: list<array<string, string>>}> */
    private array $resolved = [];

    /** @var array<string, bool> */
    private array $published = [];

    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginFilesystem $filesystem,
        private readonly PluginRuntimeGate $gate,
        private readonly AssetManager $assets,
    ) {}

    /**
     * @return array{styles: list<array<string, string>>, scripts: list<array<string, string>>}
     */
    public function assets(string $scope): array
    {
        $scope = $this->scope($scope);

        if (isset($this->resolved[$scope])) {
            return $this->resolved[$scope];
        }

        $result = ['styles' => [], 'scripts' => []];

        if (! Schema::hasTable('plugins')) {
            return $this->resolved[$scope] = $result;
        }

        $plugins = $this->plugins->findActive()
            ->filter(fn (Plugin $plugin): bool => $this->gate->allows($plugin->slug))
            ->sortBy(fn (Plugin $plugin): string => sprintf(
                '%06d:%s',
                $this->priority($plugin, $scope),
                $plugin->slug,
            ));

        foreach ($plugins as $plugin) {
            $manifest = $this->filesystem->manifest($plugin);
            $root = $this->filesystem->path($plugin);

            if ($root === null) {
                continue;
            }

            $this->publishWhenRequired($plugin, $root, $manifest);

            foreach (['styles' => 'css', 'scripts' => 'js'] as $kind => $extension) {
                foreach ($this->declaredPaths($manifest, $scope, $kind, $extension) as $path) {
                    $entry = $this->entry($plugin, $path, $scope, $kind);

                    if ($entry !== null) {
                        $result[$kind][] = $entry;
                    }

                    if ($scope === 'admin' && $kind === 'styles') {
                        $override = $this->styleOverrideEntry($plugin->slug, $path);

                        if ($override !== null) {
                            $result[$kind][] = $override;
                        }
                    }
                }
            }
        }

        return $this->resolved[$scope] = $result;
    }

    /** @return list<array<string, string>> */
    public function styles(string $scope): array
    {
        return $this->assets($scope)['styles'];
    }

    /** @return list<array<string, string>> */
    public function scripts(string $scope): array
    {
        return $this->assets($scope)['scripts'];
    }

    /**
     * Register canonical package metadata and publish its assets.
     *
     * @return array<string, mixed>
     */
    public function synchronize(Plugin $plugin): array
    {
        $root = $this->filesystem->path($plugin);

        if ($root === null) {
            return ['published' => false, 'copied_files' => [], 'reason' => 'plugin_path_missing'];
        }

        $packageManifest = $this->packageManifest($root);
        $storedManifest = is_array($plugin->manifest) ? $plugin->manifest : [];
        $attributes = [];

        if ($packageManifest !== [] && $packageManifest !== $storedManifest) {
            $attributes['manifest'] = $packageManifest;
        }

        foreach (['name', 'version', 'description', 'author', 'provider'] as $key) {
            if (
                array_key_exists($key, $packageManifest)
                && $plugin->{$key} !== $packageManifest[$key]
            ) {
                $attributes[$key] = $packageManifest[$key];
            }
        }

        $dependencies = $packageManifest['dependencies'] ?? [];
        if (is_array($dependencies) && $plugin->dependencies !== $dependencies) {
            $attributes['dependencies'] = $dependencies;
        }

        if ($plugin->path !== $root) {
            $attributes['path'] = $root;
        }

        if ($attributes !== []) {
            $plugin = $this->plugins->update($plugin, $attributes);
            $this->filesystem->flush($plugin->slug);
        }

        $manifest = $this->filesystem->manifest($plugin);
        $result = $this->assets->publishPluginAssets($plugin, $root, $manifest);
        $this->published[$plugin->slug] = true;
        $this->resolved = [];

        return $result;
    }

    /**
     * @return list<string>
     */
    public function sourceStyles(): array
    {
        $files = [];

        if (! Schema::hasTable('plugins')) {
            return [];
        }

        foreach ($this->plugins->findActive() as $plugin) {
            if (! $this->gate->allows($plugin->slug)) {
                continue;
            }

            $root = $this->filesystem->path($plugin);
            $manifest = $this->filesystem->manifest($plugin);
            if ($root === null) {
                continue;
            }

            $source = $this->assetSource($root, $manifest);
            foreach (['admin', 'frontend', 'guest'] as $scope) {
                foreach ($this->declaredPaths($manifest, $scope, 'styles', 'css') as $path) {
                    $file = realpath($source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
                    $prefix = rtrim($source, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                    if ($file !== false && is_file($file) && is_readable($file) && str_starts_with($file, $prefix)) {
                        $files[$file] = $file;
                    }
                }
            }
        }

        return array_values($files);
    }

    public function flush(?string $slug = null): void
    {
        $this->resolved = [];

        if ($slug === null) {
            $this->published = [];
            return;
        }

        unset($this->published[$slug]);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function publishWhenRequired(Plugin $plugin, string $root, array $manifest): void
    {
        if (isset($this->published[$plugin->slug])) {
            return;
        }

        $source = $this->assetSource($root, $manifest);
        $destination = public_path('platform/plugins/'.$plugin->slug);
        $requiresPublication = false;

        foreach (['admin', 'frontend', 'guest'] as $scope) {
            foreach (['styles' => 'css', 'scripts' => 'js'] as $kind => $extension) {
                foreach ($this->declaredPaths($manifest, $scope, $kind, $extension) as $path) {
                    $sourceFile = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                    $publicFile = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

                    if (
                        is_file($sourceFile)
                        && (
                            ! is_file($publicFile)
                            || filesize($sourceFile) !== filesize($publicFile)
                            || filemtime($sourceFile) > filemtime($publicFile)
                        )
                    ) {
                        $requiresPublication = true;
                        break 3;
                    }
                }
            }
        }

        if ($requiresPublication) {
            $this->assets->publishPluginAssets($plugin, $root, $manifest);
        }

        $this->published[$plugin->slug] = true;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function declaredPaths(array $manifest, string $scope, string $kind, string $extension): array
    {
        $paths = data_get($manifest, "assets.{$scope}.{$kind}");

        if ($paths === null && $scope === 'admin' && $kind === 'styles') {
            $paths = data_get($manifest, 'assets.admin_styles', []);
        }

        if (is_string($paths)) {
            $paths = [$paths];
        }

        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path))
            ->map(fn (string $path): string => str_replace('\\', '/', trim($path)))
            ->filter(fn (string $path): bool => $this->isSafePath($path, $extension))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    private function entry(Plugin $plugin, string $path, string $scope, string $kind): ?array
    {
        if (! is_file(public_path('platform/plugins/'.$plugin->slug.'/'.$path))) {
            return null;
        }

        return [
            'slug' => $plugin->slug,
            'path' => $path,
            'scope' => $scope,
            'kind' => $kind,
            'type' => $this->pluginType($plugin),
            'url' => $this->assets->versionedUrl(asset('platform/plugins/'.$plugin->slug.'/'.$path)),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function styleOverrideEntry(string $slug, string $path): ?array
    {
        $relative = 'theme-overrides/plugin-'.$slug.'/'.$path;
        $recorded = null;

        if (Schema::hasTable('theme_editor_overrides')) {
            $recorded = DB::table('theme_editor_overrides')
                ->where('scope', 'plugin:'.$slug)
                ->where('relative_path', $path)
                ->value('public_path');
        }

        if (is_string($recorded) && trim($recorded) !== '') {
            $pathOnly = strtok(trim($recorded), '?') ?: trim($recorded);

            if (
                str_starts_with($pathOnly, '/theme-overrides/plugin-'.$slug.'/')
                && ! str_contains($pathOnly, '..')
                && str_ends_with(strtolower($pathOnly), '.css')
                && is_file(public_path(ltrim($pathOnly, '/')))
            ) {
                return [
                    'slug' => $slug,
                    'path' => 'theme-override:'.$path,
                    'scope' => 'admin',
                    'kind' => 'styles',
                    'type' => 'theme-override',
                    'url' => url($recorded),
                ];
            }
        }

        if (! is_file(public_path($relative))) {
            return null;
        }

        return [
            'slug' => $slug,
            'path' => 'theme-override:'.$path,
            'scope' => 'admin',
            'kind' => 'styles',
            'type' => 'theme-override',
            'url' => $this->assets->versionedUrl(asset($relative)),
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assetSource(string $root, array $manifest): string
    {
        $configured = data_get($manifest, 'assets.source')
            ?: data_get($manifest, 'assets.public')
            ?: 'resources/assets';

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $configured),
            DIRECTORY_SEPARATOR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function packageManifest(string $root): array
    {
        $path = $root.DIRECTORY_SEPARATOR.'module.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function priority(Plugin $plugin, string $scope): int
    {
        $manifest = $this->filesystem->manifest($plugin);
        $configured = data_get($manifest, "assets.{$scope}.priority", data_get($manifest, 'assets.priority'));

        if (is_numeric($configured)) {
            return (int) $configured;
        }

        return $this->pluginType($plugin) === 'theme' ? 1000 : 100;
    }

    private function pluginType(Plugin $plugin): string
    {
        return strtolower(trim((string) data_get($this->filesystem->manifest($plugin), 'type', 'feature')));
    }

    private function isSafePath(string $path, string $extension): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && str_ends_with(strtolower($path), '.'.$extension);
    }

    private function scope(string $scope): string
    {
        return in_array($scope, ['admin', 'frontend', 'guest'], true) ? $scope : 'frontend';
    }
}
