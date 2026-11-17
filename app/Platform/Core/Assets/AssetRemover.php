<?php

namespace App\Platform\Core\Assets;

use RuntimeException;

class AssetRemover
{
    public function __construct(private readonly ManagedAssetFilesystem $files) {}

    public function remove(string $type, string $slug): bool
    {
        $directory = public_path('platform/'.$type.'/'.$slug);
        $this->guard($directory, $type);

        if (! is_dir($directory)) {
            return false;
        }

        $this->files->removeDirectory($directory, public_path('platform/'.$type));

        return true;
    }

    /**
     * @param  list<string>  $files
     */
    public function removePublishedFiles(array $files, string $type): void
    {
        if (! in_array($type, ['plugins', 'themes'], true)) {
            throw new RuntimeException("Unsupported asset type [{$type}].");
        }

        $this->files->removeFiles($files, public_path('platform/'.$type));
    }

    private function guard(string $directory, string $type): void
    {
        if (! in_array($type, ['plugins', 'themes'], true)) {
            throw new RuntimeException("Unsupported asset type [{$type}].");
        }

        $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, public_path('platform/'.$type)), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($normalized, $base)) {
            throw new RuntimeException("Refusing to remove assets outside approved path [{$directory}].");
        }
    }
}
