<?php

use Illuminate\Support\Facades\Route;
use Modules\PageBuilder\Http\Controllers\PublicPageController;
use Modules\PageBuilder\Http\Controllers\VvvebAssetController;

Route::get('/page-builder-assets/v6/{path}', [VvvebAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('page-builder.assets.v6');

Route::get('/page-builder-assets/v5/{path}', [VvvebAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('page-builder.assets.v5');

Route::get('/page-builder-assets/v4/{path}', [VvvebAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('page-builder.assets.v4');

Route::get('/page-builder-assets/v3/{path}', [VvvebAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('page-builder.assets.v3');

Route::get('/page-builder-assets/{path}', [VvvebAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('page-builder.assets');

Route::get('/pages/{slug}', [PublicPageController::class, 'show'])->name('pages.show');
Route::get('/page/{slug}', fn (string $slug) => redirect()->route('pages.show', $slug, 301))
    ->name('pages.legacy-show');
