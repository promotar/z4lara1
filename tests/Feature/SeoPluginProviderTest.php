<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\RobotsProvider;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Seo\SitemapProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Modules\Seo\SeoServiceProvider;
use Modules\Seo\Services\SeoModuleSyncManager;
use Tests\TestCase;

class SeoPluginProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_provider_registers_services_and_global_contributor(): void
    {
        $provider = new SeoServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue($this->app->bound(SeoModuleSyncManager::class));
        $this->assertTrue($this->app->bound(SitemapProvider::class));
        $this->assertTrue($this->app->bound(RobotsProvider::class));
        $this->assertNotEmpty($this->app->make(SeoRegistry::class)->allContributors());
    }

    public function test_admin_route_file_registers_expected_names(): void
    {
        Route::middleware('web')
            ->prefix('admin/plugins/seo')
            ->name('admin.plugins.seo.')
            ->group(base_path('modules/seo/routes/admin.php'));

        $names = collect(Route::getRoutes())
            ->map(static fn ($route): ?string => $route->getName())
            ->filter(static fn (?string $name): bool => is_string($name) && str_contains($name, 'seo'))
            ->values()
            ->all();

        $this->assertContains('admin.plugins.seo.dashboard', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.settings.edit', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.settings.update', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.sitemap.index', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.redirects.index', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.audit.index', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.modules.index', $names, json_encode($names));
        $this->assertContains('admin.plugins.seo.modules.update', $names, json_encode($names));
    }

    public function test_admin_views_compile_without_php_syntax_errors(): void
    {
        foreach (glob(base_path('modules/seo/resources/views/admin/*.blade.php')) ?: [] as $file) {
            $compiled = Blade::compileString((string) file_get_contents($file));

            try {
                token_get_all($compiled, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                $this->fail(basename($file).': '.$exception->getMessage());
            }

            $this->assertNotSame('', trim($compiled), basename($file));
        }
    }
}
