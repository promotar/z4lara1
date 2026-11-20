<?php

namespace Modules\Seo;

use App\Platform\Core\Seo\RobotsProvider;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Seo\SitemapProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Modules\Seo\Console\GenerateSitemap;
use Modules\Seo\Console\PurgeModule;
use Modules\Seo\Console\RunAudit;
use Modules\Seo\Console\SyncModules;
use Modules\Seo\Console\ValidateSitemap;
use Modules\Seo\Http\Middleware\SeoRedirectMiddleware;
use Modules\Seo\Http\Middleware\SitemapOverrideMiddleware;
use Modules\Seo\Services\SeoAuditEngine;
use Modules\Seo\Services\ContentSeoContributor;
use Modules\Seo\Services\ContentSeoService;
use Modules\Seo\Services\SeoContentTypeRegistry;
use Modules\Seo\Services\SeoDefaults;
use Modules\Seo\Services\SeoKeywordExtractor;
use Modules\Seo\Services\SeoModuleSettings;
use Modules\Seo\Services\SeoModuleSyncManager;
use Modules\Seo\Services\SeoRedirectResolver;
use Modules\Seo\Services\SitemapGenerator;
use Modules\Seo\Services\SitemapUrlPolicy;
use Modules\Seo\Services\SitemapValidator;
use Modules\Seo\Services\SeoPluginContributor;
use Modules\Seo\Services\SeoRobotsProvider;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SeoSitemapProvider;

final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeoSiteResolver::class);
        $this->app->singleton(SeoRedirectResolver::class);
        $this->app->singleton(SeoDefaults::class);
        $this->app->singleton(SeoContentTypeRegistry::class);
        $this->app->singleton(ContentSeoService::class);
        $this->app->singleton(ContentSeoContributor::class);
        $this->app->singleton(SitemapUrlPolicy::class);
        $this->app->singleton(SitemapGenerator::class);
        $this->app->singleton(SitemapValidator::class);
        $this->app->singleton(SeoAuditEngine::class);
        $this->app->singleton(SeoModuleSyncManager::class);
        $this->app->singleton(SeoModuleSettings::class);
        $this->app->singleton(SeoKeywordExtractor::class);
        $this->app->singleton(SeoPluginContributor::class);
        $this->app->singleton(SeoSitemapProvider::class);
        $this->app->singleton(SeoRobotsProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'seo');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'seo');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncModules::class,
                PurgeModule::class,
                GenerateSitemap::class,
                ValidateSitemap::class,
                RunAudit::class,
            ]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('seo:sitemap:generate')
                    ->dailyAt('03:20')
                    ->withoutOverlapping();
                $schedule->command('seo:audit --limit=100')
                    ->dailyAt('03:40')
                    ->withoutOverlapping();
            });
        }

        $this->app->make(SeoRegistry::class)->add(
            $this->app->make(SeoPluginContributor::class),
            priority: 100,
        );

        $this->app->make(SeoRegistry::class)->add(
            $this->app->make(ContentSeoContributor::class),
            priority: 150,
        );

        // SEO Plugin owns the richer providers while it is active. Core falls
        // back to its default providers when this plugin is disabled.
        $this->app->singleton(SitemapProvider::class, SeoSitemapProvider::class);
        $this->app->singleton(RobotsProvider::class, SeoRobotsProvider::class);

        // Redirects must also work for paths that no longer have a route, so
        // they run as global middleware before routing/fallback handling.
        $this->app->make(Kernel::class)->pushMiddleware(SeoRedirectMiddleware::class);
        $this->app->make(Kernel::class)->pushMiddleware(SitemapOverrideMiddleware::class);

        // Public sitemap endpoints live in routes/web.php so the platform
        // PluginRouteLoader registers them with EnsurePluginIsActive.
    }
}
