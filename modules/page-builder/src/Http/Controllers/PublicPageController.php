<?php

namespace Modules\PageBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function __construct(private readonly PluginOwnedPageGuard $pluginPages) {}

    public function show(string $slug): View|Response
    {
        $page = DB::table('platform_pages')
            ->where('slug', $slug)
            ->where('content_type', 'page')
            ->where('status', 'published')
            ->first();

        abort_unless($page, 404);
        abort_unless($this->pluginPages->isPageAvailable($page), 404);

        $data = [
            'page' => $page,
            'isPreview' => false,
        ];

        if (in_array($slug, ['about', 'contact', 'home', 'news'], true)) {
            return response()
                ->view('page-builder::public.show', $data)
                ->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
                    'Pragma' => 'no-cache',
                    'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                    'X-Art-INPA-Version' => 'responsive-20260721-13',
                ]);
        }

        return view('page-builder::public.show', $data);
    }
}
