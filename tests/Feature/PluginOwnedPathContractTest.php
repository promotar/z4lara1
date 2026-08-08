<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use App\Platform\Core\Services\PluginRuntimeGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class PluginOwnedPathContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_plugin_owned_paths_and_routes_are_unavailable(): void
    {
        Plugin::query()->create([
            'name' => 'LMS',
            'slug' => 'lms',
            'version' => 'test',
            'status' => Plugin::STATUS_DISABLED,
            'path' => base_path('modules/lms'),
            'provider' => 'Modules\\Lms\\LmsServiceProvider',
            'dependencies' => [],
            'manifest' => [
                'routes' => ['web' => ['name' => 'lms.front.']],
                'frontend' => ['owned_paths' => ['/courses/*', '/my-learning/*']],
            ],
        ]);
        Route::get('/contract-course-route')->name('lms.front.contract');
        $gate = Mockery::mock(PluginRuntimeGate::class);
        $gate->shouldReceive('allows')->with('lms')->andReturnFalse();
        $guard = new PluginOwnedPageGuard($gate);

        $this->assertFalse($guard->isNavigationAvailable(null, '/courses'));
        $this->assertFalse($guard->isNavigationAvailable(null, '/courses/example'));
        $this->assertFalse($guard->isNavigationAvailable(null, '/my-learning/courses'));
        $this->assertFalse($guard->isRouteAvailable('lms.front.contract'));
        $this->assertTrue($guard->isNavigationAvailable(null, '/'));
        $this->assertTrue($guard->isNavigationAvailable(null, '/pages/about'));
    }
}