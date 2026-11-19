<?php

namespace Modules\PageBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VvvebAssetController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $root = realpath(dirname(__DIR__, 3).'/resources/vvvebjs');
        $file = $root ? realpath($root.DIRECTORY_SEPARATOR.ltrim($path, '/\\')) : false;

        abort_unless(
            is_string($root)
            && is_string($file)
            && str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            && is_file($file),
            404,
        );

        return response()->file($file, [
            'Content-Type' => $this->contentType($file),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function contentType(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'json', 'map' => 'application/json; charset=UTF-8',
            'html', 'htm' => 'text/html; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'application/octet-stream',
        };
    }
}
