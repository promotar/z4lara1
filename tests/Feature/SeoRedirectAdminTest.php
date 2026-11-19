<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Modules\Seo\Http\Controllers\Admin\RedirectsController;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoRedirectAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();

        Route::get('/admin/plugins/seo/redirects', fn () => '')->name('admin.plugins.seo.redirects.index');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_redirect_can_be_created_validated_and_updated(): void
    {
        $controller = new RedirectsController();
        $response = $controller->store($this->redirectRequest([
            'from_path' => '/old-page/',
            'to_path' => '/new-page',
            'status_code' => 301,
            'priority' => 0,
            'query_behavior' => 'preserve',
        ]), new SeoSiteResolver());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/old-page',
            'to_path' => '/new-page',
            'status_code' => 301,
        ]);
    }

    public function test_loop_and_unallowlisted_external_redirects_are_rejected(): void
    {
        $controller = new RedirectsController();
        $sites = new SeoSiteResolver();

        $controller->store($this->redirectRequest([
            'from_path' => '/loop-a',
            'to_path' => '/loop-b',
            'status_code' => 301,
        ]), $sites);

        try {
            $controller->store($this->redirectRequest([
                'from_path' => '/loop-b',
                'to_path' => '/loop-a',
                'status_code' => 301,
            ]), $sites);
            $this->fail('Loop redirect was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_path', $exception->errors());
        }

        try {
            $controller->store($this->redirectRequest([
                'from_path' => '/external',
                'to_path' => 'https://evil.example/path',
                'status_code' => 301,
            ]), $sites);
            $this->fail('Unallowlisted external redirect was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_path', $exception->errors());
        }

        $this->assertDatabaseMissing('seo_redirects', ['from_path' => '/loop-b']);
        $this->assertDatabaseMissing('seo_redirects', ['from_path' => '/external']);
    }

    private function redirectRequest(array $overrides = []): Request
    {
        return Request::create('/admin/plugins/seo/redirects', 'POST', [
            'from_path' => '/old',
            'to_path' => '/new',
            'status_code' => 301,
            'priority' => 0,
            'query_behavior' => 'preserve',
            ...$overrides,
        ]);
    }
}
