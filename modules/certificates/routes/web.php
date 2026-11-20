<?php

use Illuminate\Support\Facades\Route;
use Modules\Certificates\Http\Controllers\Frontend\IssuerPortalController;
use Modules\Certificates\Http\Controllers\PublicCertificateController;
use Modules\Certificates\Http\Middleware\LimitCertificateAttempts;
use Modules\Certificates\Support\CertificateRoutes;

Route::get('/certificate-search', [PublicCertificateController::class, 'search'])->name('search');
Route::post('/certificate-search', [PublicCertificateController::class, 'lookup'])
    ->middleware(LimitCertificateAttempts::class)->name('lookup');
Route::get('/certificate-media/{slug}', [PublicCertificateController::class, 'image'])->name('image');
Route::get('/certificate-assets/certificates.css', [PublicCertificateController::class, 'styles'])->name('styles');
Route::get('/certificate-assets/templates/{slug}.js', [PublicCertificateController::class, 'templateScript'])
    ->where('slug', '[A-Za-z0-9._-]+')->name('template-script');
Route::get('/certificate-assets/{asset}', [PublicCertificateController::class, 'asset'])
    ->where('asset', 'cairo-arabic\.woff2|cairo-latin\.woff2|certificate\.svg')->name('asset');

Route::middleware('auth')->prefix('certificates/manage')->name('manage.')->group(function (): void {
    Route::get('/', [IssuerPortalController::class, 'index'])->name('index');
    Route::get('/create', [IssuerPortalController::class, 'create'])->name('create');
    Route::post('/', [IssuerPortalController::class, 'store'])->name('store');
    Route::delete('/bulk', [IssuerPortalController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::post('/bulk/restore', [IssuerPortalController::class, 'bulkRestore'])->name('bulk-restore');
    Route::delete('/bulk/force', [IssuerPortalController::class, 'bulkForceDestroy'])->name('bulk-force-destroy');
    Route::delete('/trash/empty', [IssuerPortalController::class, 'emptyTrash'])->name('trash.empty');
    Route::get('/{certificate}/edit', [IssuerPortalController::class, 'edit'])->whereNumber('certificate')->name('edit');
    Route::post('/{certificate}/restore', [IssuerPortalController::class, 'restore'])->withTrashed()->whereNumber('certificate')->name('restore');
    Route::delete('/{certificate}/force', [IssuerPortalController::class, 'forceDestroy'])->withTrashed()->whereNumber('certificate')->name('force-destroy');
    Route::match(['put', 'patch'], '/{certificate}', [IssuerPortalController::class, 'update'])->whereNumber('certificate')->name('update');
    Route::delete('/{certificate}', [IssuerPortalController::class, 'destroy'])->whereNumber('certificate')->name('destroy');
});

Route::get('/{certificateSlug}', [PublicCertificateController::class, 'show'])
    ->where('certificateSlug', CertificateRoutes::pattern())
    ->middleware(LimitCertificateAttempts::class)->name('show');
