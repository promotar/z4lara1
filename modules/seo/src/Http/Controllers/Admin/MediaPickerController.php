<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MediaController as CoreMediaController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reuses the platform Media Studio behind the SEO settings permission.
 */
final class MediaPickerController
{
    public function index(Request $request): View|JsonResponse
    {
        return app(CoreMediaController::class)->index($request);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return app(CoreMediaController::class)->store($request);
    }
}
