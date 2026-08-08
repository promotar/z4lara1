<?php

use Illuminate\Support\Facades\Route;
use Modules\ArtInpaAdminProTheme\Http\Controllers\AdminThemeSettingsController;

Route::get('/admin/plugins/admin-theme/settings', [AdminThemeSettingsController::class, 'index'])
    ->name('admin.plugins.admin-theme.settings');
Route::patch('/admin/plugins/admin-theme/settings', [AdminThemeSettingsController::class, 'update'])
    ->name('admin.plugins.admin-theme.settings.update');
Route::delete('/admin/plugins/admin-theme/settings', [AdminThemeSettingsController::class, 'reset'])
    ->name('admin.plugins.admin-theme.settings.reset');
