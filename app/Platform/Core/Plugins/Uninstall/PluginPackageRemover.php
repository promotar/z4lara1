<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class PluginPackageRemover
{
    public function preflight(Plugin $plugin, PluginManifest $manifest): void
    {
        foreach ($this->candidatePaths($plugin, $manifest) as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $parent = dirname($path);

            if (! is_writable($parent)) {
                throw new RuntimeException(
                    "Plugin package path [{$path}] cannot be deleted by the runtime user. "
                    .'Make its parent directory writable, then retry the purge.'
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function remove(Plugin $plugin, PluginManifest $manifest): array
    {
        $removed = [];

        foreach ($this->candidatePaths($plugin, $manifest) as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $deleted = File::isDirectory($path)
                ? File::deleteDirectory($path)
                : File::delete($path);

            if (! $deleted || File::exists($path)) {
                throw new RuntimeException("Unable to permanently delete plugin package path [{$path}].");
            }

            $removed[] = $path;
        }

        return $removed;
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(Plugin $plugin, PluginManifest $manifest): array
    {
        $paths = [];
        $modulesRoot = realpath(base_path('modules'));
        $modulePath = realpath($plugin->path ?: base_path('modules/'.$plugin->slug));

        if ($modulesRoot !== false && $modulePath !== false && is_dir($modulePath)) {
            $this->assertContained($modulePath, $modulesRoot, 'module');
            $paths[] = $modulePath;
        }

        $paths = array_merge(
            $paths,
            $this->matchingChildren(
                storage_path('app/plugin_uninstalls/removed_modules'),
                $manifest->slug,
            ),
            $this->matchingChildren(
                storage_path('app/plugin_updates/backups'),
                $manifest->slug,
            ),
            $this->matchingChildren(
                storage_path('app/platform/plugin-install-checkpoints'),
                $manifest->slug,
            ),
            $this->matchingChildren(
                storage_path('app/platform/plugin-uninstall-checkpoints'),
                $manifest->slug,
            ),
        );

        return array_values(array_unique(array_filter($paths, 'is_string')));
    }

    /**
     * @return array<int, string>
     */
    private function matchingChildren(string $root, string $slug): array
    {
        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            return [];
        }

        $matches = [];

        foreach (File::allFiles($resolvedRoot) as $file) {
            $relative = Str::replaceFirst(
                rtrim($resolvedRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
                '',
                $file->getRealPath(),
            );
            $firstSegment = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];

            if (Str::startsWith(Str::lower($firstSegment), Str::lower($slug).'-')) {
                $candidate = $resolvedRoot.DIRECTORY_SEPARATOR.$firstSegment;
                $this->assertContained($candidate, $resolvedRoot, 'artifact');
                $matches[] = $candidate;
            }
        }

        foreach (File::files($resolvedRoot) as $file) {
            $filename = Str::lower($file->getFilename());

            if (
                Str::startsWith($filename, Str::lower($slug).'-')
                || Str::contains($filename, '-'.Str::lower($slug).'.')
            ) {
                $matches[] = $file->getRealPath();
            }
        }

        return array_values(array_unique($matches));
    }

    private function assertContained(string $path, string $root, string $type): void
    {
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalizedPath = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($normalizedPath, $normalizedRoot)) {
            throw new RuntimeException("Refusing to delete plugin {$type} outside its managed root.");
        }
    }
}
