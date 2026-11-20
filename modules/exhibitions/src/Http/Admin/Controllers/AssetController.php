<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class AssetController extends Controller
{
    public function stylesheet(): BinaryFileResponse
    {
        return response()->file(
            dirname(__DIR__, 4).'/resources/private-assets/css/exhibitions.css',
            [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function flag(string $country): BinaryFileResponse
    {
        $country = strtolower($country);
        abort_unless(preg_match('/^[a-z]{2}$/', $country) === 1, 404);

        $path = dirname(__DIR__, 4).'/resources/private-assets/flags/4x3/'.$country.'.svg';
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function virtualExhibitionScript(): Response
    {
        $path = dirname(__DIR__, 4).'/resources/private-assets/js/virtual-exhibition.js';
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function ve03ArtistPlacementScript(): Response
    {
        $path = dirname(__DIR__, 4).'/resources/private-assets/js/ve03-artist-placement.js';
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
