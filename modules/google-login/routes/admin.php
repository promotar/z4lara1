<?php

use Illuminate\Support\Facades\Route;
use Modules\GoogleLogin\Http\Controllers\Admin\GoogleLoginSettingsController;

Route::get('/settings', [GoogleLoginSettingsController::class, 'edit'])->name('settings.edit');
Route::match(['put', 'patch'], '/settings', [GoogleLoginSettingsController::class, 'update'])->name('settings.update');
