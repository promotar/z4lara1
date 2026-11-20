<?php

namespace Modules\Store\Application\Media;

final class StoreMediaUrl
{
    public function forDisk(string $disk, ?string $path): ?string
    {
        return $disk === 'public' ? $this->publicPath($path) : null;
    }

    public function publicPath(?string $path): ?string
    {
        $path = trim((string) $path);

        return $path === '' ? null : '/storage/'.ltrim($path, '/');
    }

    public function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/')) {
            $query = parse_url($url, PHP_URL_QUERY);

            return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
        }

        return $url;
    }
}
