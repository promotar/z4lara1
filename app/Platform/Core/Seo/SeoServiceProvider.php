<?php

namespace App\Platform\Core\Seo;

use Illuminate\Support\ServiceProvider;

final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeoRegistry::class);
        $this->app->singleton(SeoIndexability::class);
        $this->app->singleton(SeoHeadInjector::class);
        $strategy = strtolower((string) config('seo.locale.mode', 'query')) === 'prefix'
            ? PrefixLocaleUrlStrategy::class
            : QueryLocaleUrlStrategy::class;

        $this->app->singleton(LocaleUrlStrategy::class, $strategy);
        $this->app->singleton(SeoManager::class);
        $this->app->singleton(HeadRenderer::class);
        $this->app->bind(SitemapProvider::class, DefaultSitemapProvider::class);
        $this->app->bind(RobotsProvider::class, DefaultRobotsProvider::class);
    }
}
