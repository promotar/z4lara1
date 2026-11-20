<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\Admin\AuditController;
use Modules\Seo\Http\Controllers\Admin\DashboardController;
use Modules\Seo\Http\Controllers\Admin\MediaPickerController;
use Modules\Seo\Http\Controllers\Admin\ModulesController;
use Modules\Seo\Http\Controllers\Admin\RedirectsController;
use Modules\Seo\Http\Controllers\Admin\SettingsController;
use Modules\Seo\Http\Controllers\Admin\SitemapController;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/settings', [SettingsController::class, 'edit'])
    ->middleware('permission:seo.settings')
    ->name('settings.edit');
Route::patch('/settings', [SettingsController::class, 'update'])
    ->middleware('permission:seo.settings')
    ->name('settings.update');

Route::get('/modules', [ModulesController::class, 'index'])
    ->middleware('permission:seo.settings')
    ->name('modules.index');
Route::put('/modules', [ModulesController::class, 'update'])
    ->middleware('permission:seo.settings')
    ->name('modules.update');

Route::get('/media', [MediaPickerController::class, 'index'])
    ->middleware('permission:seo.settings')
    ->name('media.index');
Route::post('/media', [MediaPickerController::class, 'store'])
    ->middleware('permission:seo.settings')
    ->name('media.store');

Route::get('/sitemap', [SitemapController::class, 'index'])
    ->middleware('permission:seo.sitemap')
    ->name('sitemap.index');
Route::patch('/sitemap', [SitemapController::class, 'update'])
    ->middleware('permission:seo.sitemap')
    ->name('sitemap.update');
Route::post('/sitemap/generate', [SitemapController::class, 'generate'])
    ->middleware('permission:seo.sitemap')
    ->name('sitemap.generate');
Route::post('/sitemap/validate', [SitemapController::class, 'validate'])
    ->middleware('permission:seo.sitemap')
    ->name('sitemap.validate');

Route::get('/redirects', [RedirectsController::class, 'index'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.index');
Route::get('/redirects/create', [RedirectsController::class, 'create'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.create');
Route::post('/redirects', [RedirectsController::class, 'store'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.store');
Route::get('/redirects/{redirect}/edit', [RedirectsController::class, 'edit'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.edit');
Route::patch('/redirects/{redirect}', [RedirectsController::class, 'update'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.update');
Route::patch('/redirects/{redirect}/toggle', [RedirectsController::class, 'toggle'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.toggle');
Route::delete('/redirects/{redirect}', [RedirectsController::class, 'destroy'])
    ->middleware('permission:seo.redirects')
    ->name('redirects.destroy');

Route::get('/audit', [AuditController::class, 'index'])
    ->middleware('permission:seo.audit')
    ->name('audit.index');
Route::post('/audit/run', [AuditController::class, 'run'])
    ->middleware('permission:seo.audit')
    ->name('audit.run');
Route::patch('/audit/{issue}/resolve', [AuditController::class, 'resolve'])
    ->middleware('permission:seo.audit')
    ->name('audit.resolve');
