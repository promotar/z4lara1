<?php

namespace App\Platform\Core\Assets;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class AssetPublisher
{
    public function __construct(private readonly ManagedAssetFilesystem $files) {}

    /**
     * @return array<string, mixed>
     */
    public function publish(AssetManifest $manifest): array
    {
        $source = $this->realDirectory($manifest->sourcePath);

        if ($source === null) {
            return ['published' => false, 'copied_files' => []];
        }

        $destination = $this->approvedDestination($manifest->destinationPath, $manifest->type);
        $copied = [];
        $created = [];

        $this->copyDirectory($source, $destination, $source, $copied, $created);

        return [
            'published' => $copied !== [],
            'source' => $source,
            'destination' => $destination,
            'copied_files' => $copied,
            'created_files' => $created,
        ];
    }

    private function copyDirectory(
        string $source,
        string $destination,
        string $root,
        array &$copied,
        array &$created,
    ): void {
        $this->files->ensureDirectory($destination);

        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source.DIRECTORY_SEPARATOR.$item;
            $realFrom = realpath($from);

            if ($realFrom === false || ! str_starts_with($realFrom, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException("Refusing unsafe asset source [{$from}].");
            }

            $to = $destination.DIRECTORY_SEPARATOR.$item;

            if (is_dir($realFrom)) {
                $this->copyDirectory($realFrom, $to, $root, $copied, $created);

                continue;
            }

            $existed = is_file($to);
            $this->files->replaceFile($realFrom, $to);
            $copied[] = $to;

            if (! $existed) {
                $created[] = $to;
            }

            Log::info('Published platform asset file.', [
                'from' => $realFrom,
                'to' => $to,
            ]);
        }
    }

    private function realDirectory(string $path): ?string
    {
        $real = realpath($path);

        return $real !== false && is_dir($real) ? $real : null;
    }

    private function approvedDestination(string $path, string $type): string
    {
        $base = public_path('platform/'.$type);

        if (! in_array($type, ['plugins', 'themes'], true)) {
            throw new RuntimeException("Unsupported asset type [{$type}].");
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $baseNormalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with(rtrim($normalized, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, $baseNormalized)) {
            throw new RuntimeException("Refusing asset destination outside approved path [{$path}].");
        }

        return $path;
    }
}
