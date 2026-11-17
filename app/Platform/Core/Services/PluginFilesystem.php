<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\Log;

final class PluginFilesystem
{
    /** @var array<string, string|null> */
    private array $resolvedPaths = [];

    /** @var array<string, array<string, mixed>> */
    private array $manifests = [];

    public function path(Plugin $plugin): ?string
    {
        if (isset($this->resolvedPaths[$plugin->slug])) {
            return $this->resolvedPaths[$plugin->slug];
        }

        foreach ($this->pathCandidates($plugin) as $candidate) {
            $resolved = realpath($candidate);

            if ($resolved !== false && is_dir($resolved)) {
                return $this->resolvedPaths[$plugin->slug] = $resolved;
            }
        }

        $discovered = $this->discoverPathByManifestSlug($plugin->slug);

        if ($discovered !== null) {
            $this->resolvedPaths[$plugin->slug] = $discovered;
        }

        return $discovered;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(Plugin $plugin): array
    {
        if (isset($this->manifests[$plugin->slug])) {
            return $this->manifests[$plugin->slug];
        }

        $stored = is_array($plugin->manifest) ? $plugin->manifest : [];
        $path = $this->path($plugin);

        if ($path === null) {
            return $this->manifests[$plugin->slug] = $stored;
        }

        $manifestPath = $path.DIRECTORY_SEPARATOR.'module.json';
        if (! is_file($manifestPath)) {
            return $this->manifests[$plugin->slug] = $stored;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        $fileManifest = is_array($decoded) ? $decoded : [];

        // Installed registry metadata is authoritative when it intentionally
        // overrides package defaults, while new package fields remain visible.
        return $this->manifests[$plugin->slug] = array_replace_recursive($fileManifest, $stored);
    }

    public function file(Plugin $plugin, string $relativePath): ?string
    {
        $root = $this->path($plugin);
        $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if ($root === null || $relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $candidate = $root.DIRECTORY_SEPARATOR.$relativePath;
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved)) {
            return null;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($resolved, $rootPrefix)) {
            Log::warning('Plugin file was rejected because it escapes the plugin directory.', [
                'plugin' => $plugin->slug,
                'relative_path' => $relativePath,
            ]);

            return null;
        }

        return $resolved;
    }

    public function flush(?string $slug = null): void
    {
        if ($slug === null) {
            $this->resolvedPaths = [];
            $this->manifests = [];

            return;
        }

        unset($this->resolvedPaths[$slug], $this->manifests[$slug]);
    }

    /**
     * @return list<string>
     */
    private function pathCandidates(Plugin $plugin): array
    {
        $configuredPaths = array_filter([
            $plugin->path,
            $plugin->getAttribute('installed_path'),
            $plugin->getAttribute('source_path'),
        ], fn (mixed $path): bool => is_string($path) && trim($path) !== '');

        $paths = [];
        foreach ($configuredPaths as $configuredPath) {
            $paths[] = $configuredPath;

            $normalized = str_replace('\\', '/', trim($configuredPath));
            if (preg_match('~/modules/([^/]+)$~i', $normalized, $matches) === 1) {
                $paths[] = base_path('modules/'.$matches[1]);
            }
        }

        $paths[] = base_path('modules/'.$plugin->slug);

        return array_values(array_unique(array_map(function (string $path): string {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

            return $this->isAbsolutePath($path) ? $path : base_path($path);
        }, $paths)));
    }

    private function discoverPathByManifestSlug(string $slug): ?string
    {
        foreach (glob(base_path('modules/*/module.json')) ?: [] as $manifestPath) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($decoded) && ($decoded['slug'] ?? null) === $slug) {
                $resolved = realpath(dirname($manifestPath));

                return $resolved !== false ? $resolved : null;
            }
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
