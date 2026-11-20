<?php

namespace Modules\Lms\Support;

use Illuminate\Support\Facades\Storage;

trait ResolvesPlatformMedia
{
    protected function platformMediaPathFromUrl(?string $url): ?string
    {
        $path = rawurldecode((string) (parse_url(trim((string) $url), PHP_URL_PATH) ?: ''));
        $path = ltrim($path, '/');

        foreach (['storage/', 'lms/media-file/', 'media/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));

                if ($prefix === 'media/') {
                    $path = 'media/'.$path;
                }

                break;
            }
        }

        if ($path === '' || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Copy a platform Media Studio video into LMS private storage. The public
     * media record remains reusable by administrators, while students receive
     * only the enrollment-authorized streaming route.
     */
    protected function protectPlatformVideo(?string $url): ?string
    {
        $source = $this->platformMediaPathFromUrl($url);
        if (! $source) {
            return null;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['mp4', 'webm', 'mov', 'm4v', 'ogg'], true)) {
            return null;
        }

        $target = 'lms/protected-videos/'.sha1($source.'|'.Storage::disk('public')->size($source)).'.'.$extension;
        if (! Storage::disk('local')->exists($target)) {
            $stream = Storage::disk('public')->readStream($source);
            if (! is_resource($stream)) {
                return null;
            }

            try {
                if (! Storage::disk('local')->writeStream($target, $stream)) {
                    return null;
                }
            } finally {
                fclose($stream);
            }
        }

        return 'private:'.$target;
    }
}
