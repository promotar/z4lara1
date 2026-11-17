<?php

namespace App\Http\Controllers;

use App\Platform\Core\Seo\RobotsProvider;
use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    public function __invoke(RobotsProvider $provider): Response
    {
        return response(implode("\n", $provider->lines())."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
