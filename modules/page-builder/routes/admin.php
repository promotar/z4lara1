<?php

use Illuminate\Support\Facades\Route;
use Modules\PageBuilder\Http\Controllers\Admin\PageController;

Route::middleware('permission:pages.manage')->group(function (): void {
    Route::get('/admin/theme-builder', fn () => redirect()->route('admin.pages.index'))
        ->name('admin.theme-builder.index');
    Route::post('/admin/theme-builder/templates', [PageController::class, 'legacyThemeStore'])
        ->name('admin.theme-builder.templates.store');
    Route::get('/admin/pages', [PageController::class, 'index'])->name('admin.pages.index');
    Route::post('/admin/pages', [PageController::class, 'store'])->name('admin.pages.store');
    Route::delete('/admin/pages/bulk-delete', [PageController::class, 'bulkDestroy'])->name('admin.pages.bulk-destroy');
    Route::get('/admin/pages/vvveb/media', [PageController::class, 'media'])->name('admin.pages.vvveb-media');
    Route::post('/admin/pages/vvveb/reusable', [PageController::class, 'reusable'])->name('admin.pages.vvveb-reusable');
    Route::get('/admin/pages/{page}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
    Route::get('/admin/pages/{page}/vvveb-canvas', [PageController::class, 'canvas'])->name('admin.pages.vvveb-canvas');
    Route::post('/admin/pages/{page}/vvveb-save', [PageController::class, 'save'])->name('admin.pages.vvveb-save');
    Route::get('/admin/pages/{page}/vvveb-revisions', [PageController::class, 'revisions'])->name('admin.pages.vvveb-revisions');
    Route::post('/admin/pages/{page}/vvveb-revisions/{revision}/restore', [PageController::class, 'restoreRevision'])->name('admin.pages.vvveb-revisions.restore');
    Route::get('/admin/pages/{page}/preview', [PageController::class, 'preview'])->name('admin.pages.preview');
    Route::delete('/admin/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.destroy');
    Route::get('/admin/plugins/page-builder', [PageController::class, 'index'])->name('admin.plugins.page-builder.index');
    Route::get('/admin/vvveb-layout', [PageController::class, 'themeBuilder'])->name('admin.vvveb.layout');
    Route::put('/admin/vvveb-layout', [PageController::class, 'updateThemeBuilder'])->name('admin.vvveb.layout.update');
});
