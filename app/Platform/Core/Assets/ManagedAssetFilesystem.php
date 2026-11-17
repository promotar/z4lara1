<?php

namespace App\Platform\Core\Assets;

use RuntimeException;

final class ManagedAssetFilesystem
{
    public const DIRECTORY_MODE = 0775;

    public const FILE_MODE = 0664;

    public function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, self::DIRECTORY_MODE, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create managed asset directory [{$directory}].");
        }

        $this->applyAccess($directory, self::DIRECTORY_MODE);

        if (! is_writable($directory)) {
            throw $this->permissionException($directory);
        }
    }

    public function replaceFile(string $source, string $destination): void
    {
        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException("Managed asset source is not readable [{$source}].");
        }

        $directory = dirname($destination);
        $this->ensureDirectory($directory);

        $temporary = tempnam($directory, '.asset-');

        if ($temporary === false) {
            throw new RuntimeException("Unable to create a temporary asset in [{$directory}].");
        }

        try {
            if (! @copy($source, $temporary)) {
                throw new RuntimeException("Unable to stage managed asset [{$destination}].");
            }

            $this->applyAccess($temporary, self::FILE_MODE);

            if (! @rename($temporary, $destination)) {
                if (is_file($destination) && ! @unlink($destination)) {
                    throw $this->permissionException($destination);
                }

                if (! @rename($temporary, $destination)) {
                    throw new RuntimeException("Unable to publish managed asset [{$destination}].");
                }
            }

            $this->applyAccess($destination, self::FILE_MODE);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function removeDirectory(string $directory, string $approvedBase): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $this->guardExistingPath($directory, $approvedBase);
        $this->prepareRemoval($directory);
        $this->removePreparedDirectory($directory);
    }

    /**
     * @param  list<string>  $files
     */
    public function removeFiles(array $files, string $approvedBase): void
    {
        $parents = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $this->guardExistingPath($file, $approvedBase);
            $parent = dirname($file);
            $this->applyAccess($parent, self::DIRECTORY_MODE);

            if (! is_writable($parent) || ! @unlink($file)) {
                throw $this->permissionException($file);
            }

            $parents[$parent] = $parent;
        }

        $base = realpath($approvedBase);
        foreach (array_reverse(array_values($parents)) as $parent) {
            $this->removeEmptyParents($parent, $base === false ? $approvedBase : $base);
        }
    }

    /**
     * Normalize an existing managed tree created by older CLI processes.
     *
     * @return array{directories: int, files: int}
     */
    public function repairPermissions(string $directory): array
    {
        if (! is_dir($directory)) {
            return ['directories' => 0, 'files' => 0];
        }

        $counts = ['directories' => 0, 'files' => 0];
        $this->repairDirectory($directory, $counts);

        return $counts;
    }

    private function prepareRemoval(string $directory): void
    {
        $this->applyAccess($directory, self::DIRECTORY_MODE);

        if (! is_writable($directory)) {
            throw $this->permissionException($directory);
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->prepareRemoval($path);
            }
        }
    }

    private function removePreparedDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->removePreparedDirectory($path);

                continue;
            }

            if (! @unlink($path)) {
                throw $this->permissionException($path);
            }
        }

        if (! @rmdir($directory)) {
            throw $this->permissionException($directory);
        }
    }

    /**
     * @param  array{directories: int, files: int}  $counts
     */
    private function repairDirectory(string $directory, array &$counts): void
    {
        $this->applyAccess($directory, self::DIRECTORY_MODE);

        $counts['directories']++;

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->repairDirectory($path, $counts);

                continue;
            }

            $this->applyAccess($path, self::FILE_MODE);

            $counts['files']++;
        }
    }

    private function guardExistingPath(string $path, string $approvedBase): void
    {
        $realPath = realpath($path);
        $realBase = realpath($approvedBase);

        if ($realPath === false || $realBase === false) {
            throw new RuntimeException("Unable to resolve managed asset path [{$path}].");
        }

        $prefix = rtrim($realBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $candidate = rtrim($realPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($candidate, $prefix)) {
            throw new RuntimeException("Refusing to modify assets outside approved path [{$path}].");
        }
    }

    private function removeEmptyParents(string $directory, string $approvedBase): void
    {
        $base = rtrim($approvedBase, DIRECTORY_SEPARATOR);
        $current = rtrim($directory, DIRECTORY_SEPARATOR);

        while ($current !== $base && str_starts_with($current.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR)) {
            if ((scandir($current) ?: []) !== ['.', '..'] || ! @rmdir($current)) {
                return;
            }

            $current = dirname($current);
        }
    }

    private function permissionException(string $path): RuntimeException
    {
        return new RuntimeException(
            "Managed plugin assets are not writable [{$path}]. "
            .'Run [php artisan platform:repair-plugin-assets] once as the deployment user, then retry.',
        );
    }

    private function applyAccess(string $path, int $mode): void
    {
        $group = trim((string) config('platform.asset_group', 'www-data'));

        if ($group !== '') {
            @chgrp($path, $group);
        }

        @chmod($path, $mode);
        clearstatcache(true, $path);

        $permissions = @fileperms($path);
        if ($permissions === false || ($permissions & 0777) !== $mode) {
            throw $this->permissionException($path);
        }
    }
}
