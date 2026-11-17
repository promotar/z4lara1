<?php

namespace App\Platform\Core\Assets;

class AssetCacheBuster
{
    public function versionedUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $url;
        }

        $file = public_path(ltrim($path, '/'));

        if (! is_file($file)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.filemtime($file);
    }
}
