<?php

use Illuminate\Support\Facades\Route;
use Modules\Certificates\Http\Controllers\AdminCertificateController;
use Modules\Certificates\Http\Controllers\AdminIssuerController;
use Modules\Certificates\Http\Controllers\AdminTemplateController;

Route::get('/', [AdminCertificateController::class, 'index'])->name('index');
Route::get('/create', [AdminCertificateController::class, 'create'])->name('create');
Route::post('/', [AdminCertificateController::class, 'store'])->name('store');
Route::delete('/bulk', [AdminCertificateController::class, 'bulkDestroy'])->name('bulk-destroy');
Route::post('/bulk/restore', [AdminCertificateController::class, 'bulkRestore'])->name('bulk-restore');
Route::delete('/bulk/force', [AdminCertificateController::class, 'bulkForceDestroy'])->name('bulk-force-destroy');
Route::delete('/trash/empty', [AdminCertificateController::class, 'emptyTrash'])->name('trash.empty');
Route::get('/{certificate}/edit', [AdminCertificateController::class, 'edit'])->whereNumber('certificate')->name('edit');
Route::post('/{certificate}/restore', [AdminCertificateController::class, 'restore'])->withTrashed()->whereNumber('certificate')->name('restore');
Route::delete('/{certificate}/force', [AdminCertificateController::class, 'forceDestroy'])->withTrashed()->whereNumber('certificate')->name('force-destroy');
Route::put('/{certificate}', [AdminCertificateController::class, 'update'])->whereNumber('certificate')->name('update');
Route::delete('/{certificate}', [AdminCertificateController::class, 'destroy'])->whereNumber('certificate')->name('destroy');

Route::middleware('permission:certificates.issuers.manage')->prefix('issuers')->name('issuers.')->group(function (): void {
    Route::get('/', [AdminIssuerController::class, 'index'])->name('index');
    Route::get('/users', [AdminIssuerController::class, 'users'])->name('users');
    Route::post('/', [AdminIssuerController::class, 'store'])->name('store');
    Route::put('/{issuer}', [AdminIssuerController::class, 'update'])->name('update');
    Route::delete('/{issuer}', [AdminIssuerController::class, 'destroy'])->name('destroy');
});

Route::middleware('permission:certificates.templates.manage')->prefix('templates')->name('templates.')->group(function (): void {
    Route::get('/', [AdminTemplateController::class, 'index'])->name('index');
    Route::post('/', [AdminTemplateController::class, 'store'])->name('store');
    Route::get('/{template}/edit', [AdminTemplateController::class, 'edit'])->whereNumber('template')->name('edit');
    Route::match(['put', 'patch'], '/{template}', [AdminTemplateController::class, 'update'])->whereNumber('template')->name('update');
    Route::delete('/{template}', [AdminTemplateController::class, 'destroy'])->whereNumber('template')->name('destroy');
    Route::post('/{template}/duplicate', [AdminTemplateController::class, 'duplicate'])->whereNumber('template')->name('duplicate');
    Route::post('/{template}/default', [AdminTemplateController::class, 'setDefault'])->whereNumber('template')->name('default');
});
