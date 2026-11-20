<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\SitemapSectionsController;

/**
 * Public sitemap endpoints owned by the SEO plugin.
 *
 * Registered through the platform PluginRouteLoader (module.json routes.web),
 * so they are prefixed/named here without the "seo." prefix and carry
 * EnsurePluginIsActive:seo. Core keeps /sitemap.xml.
 */
Route::get('/sitemap_index.xml', [SitemapSectionsController::class, 'index'])
    ->name('sitemap.sections.index');

Route::get('/sitemap.xsl', [SitemapSectionsController::class, 'stylesheet'])
    ->name('sitemap.sections.stylesheet');

Route::get('/{file}-sitemap.xml', [SitemapSectionsController::class, 'section'])
    ->where('file', '[a-z0-9_\-]+')
    ->name('sitemap.sections.section');

Route::get('/{file}-sitemap{page}.xml', [SitemapSectionsController::class, 'section'])
    ->where(['file' => '[a-z0-9_\-]+', 'page' => '[0-9]+'])
    ->name('sitemap.sections.section.page');
