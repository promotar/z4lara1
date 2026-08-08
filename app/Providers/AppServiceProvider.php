<?php

namespace App\Providers;

use App\Platform\Core\Access\RouteAccessGate;
use App\Platform\Core\Access\RoutePermissionCatalog;
use App\Platform\Core\Access\UnauthorizedRouteElementFilter;
use App\Platform\Core\Contracts\EditorialContentProvider;
use App\Platform\Core\Contracts\LatestContentProvider;
use App\Platform\Core\Hooks\HookLoader;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Rendering\NullEditorialContentProvider;
use App\Platform\Core\Rendering\NullLatestContentProvider;
use App\Platform\Core\Services\ActivePluginStylesheets;
use App\Platform\Core\Services\PlatformVersion;
use App\Platform\Core\Services\PluginAssetRegistry;
use App\Platform\Core\Services\PluginFilesystem;
use App\Platform\Core\Services\PluginRuntimeGate;
use App\Platform\Core\Services\PluginServiceProviderLoader;
use App\Platform\Core\Views\ViewNamespaceRegistrar;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class);
        $this->app->singleton(PluginFilesystem::class);
        $this->app->singleton(PlatformVersion::class);
        $this->app->singleton(PluginRuntimeGate::class);
        $this->app->singleton(PluginAssetRegistry::class);
        $this->app->singleton(ActivePluginStylesheets::class);
        $this->app->singleton(EditorialContentProvider::class, NullEditorialContentProvider::class);
        $this->app->singleton(LatestContentProvider::class, NullLatestContentProvider::class);
        $this->app->singleton(RoutePermissionCatalog::class);
        $this->app->scoped(RouteAccessGate::class);
        $this->app->scoped(UnauthorizedRouteElementFilter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('routeAllowed', function (?string $routeName): bool {
            return app(RouteAccessGate::class)->allowsRouteName(auth()->user(), $routeName);
        });

        $this->app->make(PluginServiceProviderLoader::class)
            ->registerActiveProviders($this->app);

        $this->app->make(ViewNamespaceRegistrar::class)->register();

        $this->app->make(HookLoader::class)->load();
    }
}
