<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Store\Models\StoreProductTemplate;

final class StoreTemplateAssetController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $template = StoreProductTemplate::query()->active()->where('slug', $slug)->firstOrFail();

        return response((string) $template->js_code, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
