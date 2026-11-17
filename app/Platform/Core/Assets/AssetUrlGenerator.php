<?php

namespace App\Platform\Core\Assets;

use RuntimeException;

class AssetUrlGenerator
{
    public function url(string $type, string $slug, string $path): string
    {
        if (! in_array($type, ['plugins', 'themes'], true)) {
            throw new RuntimeException("Unsupported asset type [{$type}].");
        }

        $path = trim($path, '/\\');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new RuntimeException("Unsafe asset path [{$path}].");
        }

        return asset('platform/'.$type.'/'.$slug.'/'.$path);
    }
}
