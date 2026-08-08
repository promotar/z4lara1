<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DocumentationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuSettingsController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlatformRegistryController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Ai\AiChatController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\ProfileController;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use App\Platform\Core\Services\PluginRouteLoader;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware('throttle:20,1')->prefix('install')->name('install.')->group(function (): void {
    Route::get('/', [InstallationController::class, 'index'])->name('index');
    Route::get('/platform', [InstallationController::class, 'platform'])->name('platform');
    Route::post('/platform', [InstallationController::class, 'storePlatform'])->name('platform.store');
    Route::get('/database', [InstallationController::class, 'database'])->name('database');
    Route::post('/database', [InstallationController::class, 'storeDatabase'])->name('database.store');
    Route::get('/owner', [InstallationController::class, 'owner'])->name('owner');
    Route::post('/finish', [InstallationController::class, 'finish'])->name('finish');
});

Route::get('/', function (SettingsRepository $settings) {
    $cacheBypassHeaders = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
        'Pragma' => 'no-cache',
        'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        'X-Art-INPA-Version' => 'responsive-20260721-14',
    ];

    $values = $settings->values();

    if (($values['front_page.front_page_mode'] ?? 'default') === 'static') {
        $frontPage = (string) ($values['front_page.front_page'] ?? 'front.home');

        if (
            (str_starts_with($frontPage, 'platform-page:') || str_starts_with($frontPage, 'front-builder:'))
            && Route::has('pages.show')
        ) {
            $slug = str_starts_with($frontPage, 'platform-page:')
                ? substr($frontPage, strlen('platform-page:'))
                : substr($frontPage, strlen('front-builder:'));

            if (
                Schema::hasTable('platform_pages')
                && ($page = DB::table('platform_pages')
                    ->where('slug', $slug)
                    ->where('content_type', 'page')
                    ->where('status', 'published')
                    ->first())
            ) {
                return response()
                    ->view('page-builder::public.show', [
                        'page' => $page,
                        'isPreview' => false,
                    ])
                    ->withHeaders($cacheBypassHeaders);
            }
        }

    }

    if (Schema::hasTable('platform_pages')) {
        $coreHome = DB::table('platform_pages')
            ->where('slug', 'home')
            ->where('content_type', 'page')
            ->where('status', 'published')
            ->first();

        if ($coreHome) {
            return response()
                ->view('page-builder::public.show', [
                    'page' => $coreHome,
                    'isPreview' => false,
                ])
                ->withHeaders($cacheBypassHeaders);
        }
    }

    return response()
        ->view('frontend.home')
        ->withHeaders($cacheBypassHeaders);
})->name('front.home');

Route::post('/ai/message', [AiChatController::class, 'message'])
    ->middleware('throttle:60,1')
    ->name('ai.message');

Route::get('/account', function (PluginOwnedPageGuard $pluginPages) {
    $user = request()->user();

    if ($user?->hasRole('student') && $pluginPages->isRouteAvailable('lms.front.student.overview')) {
        return redirect()->route('lms.front.student.overview');
    }

    return view('frontend.account');
})->middleware(['auth', 'verified'])->name('front.account');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'staff'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('super-admin')->group(function () {
        Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation.index');
        Route::get('/documentation/reports/{report}/view', [DocumentationController::class, 'viewReport'])
            ->name('documentation.reports.view');
        Route::get('/documentation/reports/{report}/download', [DocumentationController::class, 'downloadReport'])
            ->name('documentation.reports.download');
        Route::post('/documentation/tasks', [DocumentationController::class, 'store'])->name('documentation.tasks.store');
        Route::patch('/documentation/tasks/{task}', [DocumentationController::class, 'update'])->name('documentation.tasks.update');
        Route::patch('/documentation/tasks/{task}/toggle', [DocumentationController::class, 'toggle'])->name('documentation.tasks.toggle');
        Route::delete('/documentation/tasks/{task}', [DocumentationController::class, 'destroy'])->name('documentation.tasks.destroy');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/backups/{backup}/location', [BackupController::class, 'showLocation'])->name('backups.location');
        Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    Route::middleware('permission:menus.manage')->group(function () {
        Route::get('/menus', [MenuSettingsController::class, 'index'])->name('menus.index');
        Route::post('/menus/{location}/menus', [MenuSettingsController::class, 'storeMenu'])->name('menus.store');
        Route::patch('/menus/menus/{menu}', [MenuSettingsController::class, 'updateMenu'])->name('menus.update');
        Route::delete('/menus/menus/{menu}', [MenuSettingsController::class, 'destroyMenu'])->name('menus.destroy');
        Route::post('/menus/{location}/items', [MenuSettingsController::class, 'store'])->name('menus.items.store');
        Route::post('/menus/menus/{menu}/items', [MenuSettingsController::class, 'storeForMenu'])->name('menus.items.store-for-menu');
        Route::patch('/menus/items/{item}', [MenuSettingsController::class, 'update'])->name('menus.items.update');
        Route::delete('/menus/items/{item}', [MenuSettingsController::class, 'destroy'])->name('menus.items.destroy');
    });

    Route::get('/plugins', [PluginController::class, 'index'])
        ->middleware('permission:plugins.view')
        ->name('plugins.index');
    Route::get('/plugins/install', [PluginController::class, 'create'])
        ->middleware('permission:plugins.install')
        ->name('plugins.create');
    Route::post('/plugins/install', [PluginController::class, 'store'])
        ->middleware('permission:plugins.install')
        ->name('plugins.store');
    Route::get('/plugins/update/{token}', [PluginController::class, 'reviewUpdate'])
        ->middleware('permission:plugins.install')
        ->name('plugins.update.review');
    Route::post('/plugins/update/{token}', [PluginController::class, 'confirmUpdate'])
        ->middleware('permission:plugins.install')
        ->name('plugins.update.confirm');
    Route::patch('/plugins/{slug}/activate', [PluginController::class, 'activate'])
        ->middleware('permission:plugins.activate')
        ->name('plugins.activate');
    Route::patch('/plugins/{slug}/deactivate', [PluginController::class, 'deactivate'])
        ->middleware('permission:plugins.activate')
        ->name('plugins.deactivate');
    Route::delete('/plugins/{slug}', [PluginController::class, 'destroy'])
        ->middleware('permission:plugins.install')
        ->name('plugins.destroy');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify-email');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:permissions.manage')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::post('/permissions/sync-defaults', [PermissionController::class, 'syncDefaults'])->name('permissions.sync-defaults');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::patch('/settings/media', [SettingsController::class, 'updateMedia'])->name('settings.media.update');
    });

    Route::middleware('permission:media.manage')->group(function () {
        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaController::class, 'store'])->name('media.store');
        Route::patch('/media/metadata', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media', [MediaController::class, 'destroy'])->name('media.destroy');
    });

    Route::middleware('super-admin')->group(function () {
        Route::get('/platform-registry', [PlatformRegistryController::class, 'index'])
            ->name('platform-registry.index');
        Route::get('/platform-registry/live-log', [PlatformRegistryController::class, 'liveLog'])
            ->name('platform-registry.live-log');
    });
});

app(PluginRouteLoader::class)->loadWebRoutes();
app(PluginRouteLoader::class)->loadAdminRoutes();

require __DIR__.'/auth.php';
