<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PluginOwnedStorageRemover
{
    public function preflight(PluginManifest $manifest): void
    {
        foreach ($this->definitions($manifest) as $definition) {
            $disk = Storage::disk($definition['disk']);

            if (! $disk->exists($definition['path'])) {
                continue;
            }

            $absolute = $this->guardedAbsolutePath($disk, $definition['path']);
            $directories = is_dir($absolute)
                ? $this->directoriesRecursively($absolute)
                : [dirname($absolute)];

            foreach ($directories as $directory) {
                if (! is_writable($directory)) {
                    throw new RuntimeException(
                        "Plugin storage path [{$definition['disk']}:{$definition['path']}] "
                        .'cannot be deleted by the runtime user.'
                    );
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        $removed = [];

        foreach ($this->definitions($manifest) as $definition) {
            $disk = Storage::disk($definition['disk']);
            $path = $definition['path'];

            if (! $disk->exists($path)) {
                continue;
            }

            $this->guardedAbsolutePath($disk, $path);
            $deleted = $disk->directoryExists($path)
                ? $disk->deleteDirectory($path)
                : $disk->delete($path);

            if (! $deleted || $disk->exists($path)) {
                throw new RuntimeException(
                    "Unable to permanently delete plugin storage path [{$definition['disk']}:{$path}]."
                );
            }

            $removed[] = $definition['disk'].':'.$path;
        }

        return $removed;
    }

    /**
     * @return array<int, array{disk: string, path: string}>
     */
    private function definitions(PluginManifest $manifest): array
    {
        return collect((array) data_get($manifest->manifest, 'uninstall.storage_paths', []))
            ->map(fn (mixed $definition): array => [
                'disk' => (string) data_get($definition, 'disk'),
                'path' => trim((string) data_get($definition, 'path'), '/\\'),
            ])
            ->unique(fn (array $definition): string => $definition['disk'].':'.$definition['path'])
            ->values()
            ->all();
    }

    private function guardedAbsolutePath(FilesystemAdapter $disk, string $path): string
    {
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($path)) ?: $disk->path($path);

        if (
            $root === false
            || ! str_starts_with(
                rtrim($absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
                rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            )
        ) {
            throw new RuntimeException('Refusing to delete plugin storage outside the configured disk root.');
        }

        return $absolute;
    }

    /**
     * @return array<int, string>
     */
    private function directoriesRecursively(string $root): array
    {
        $directories = [$root];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                $directories[] = $entry->getPathname();
            }
        }

        return $directories;
    }
}
