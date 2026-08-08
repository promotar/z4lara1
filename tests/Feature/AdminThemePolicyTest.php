<?php

namespace Tests\Feature;

use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\Licensing\LicenseManager;
use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\BackupCheckpoint;
use App\Platform\Core\Models\OperationLog;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\AdminThemeManager;
use App\Platform\Core\Services\PluginActivator;
use App\Platform\Core\Services\PluginCacheCleaner;
use App\Platform\Core\Services\PluginDeactivator;
use App\Platform\Core\Services\PluginDependencyChecker;
use App\Platform\Core\Services\PluginFilesystem;
use App\Platform\Core\Services\PluginMenuRegistry;
use App\Platform\Core\Services\PluginPackageValidator;
use App\Platform\Core\Services\PluginRuntimeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminThemePolicyTest extends TestCase
{
    use RefreshDatabase;

    private string $customThemePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customThemePath = storage_path('framework/testing/custom-admin-theme');
        File::deleteDirectory($this->customThemePath);
        File::ensureDirectoryExists($this->customThemePath);
        File::put($this->customThemePath.'/module.json', json_encode([
            'name' => 'Custom Admin Theme',
            'slug' => 'custom-admin-theme',
            'version' => '1.0.0',
            'type' => 'theme',
            'theme' => ['scope' => 'admin'],
            'platform_version' => '>=2.5.0 <3.0.0',
            'description' => 'Custom admin theme.',
            'author' => 'Tests',
            'provider' => 'Modules\\CustomAdminTheme\\CustomAdminThemeServiceProvider',
            'provider_file' => 'src/CustomAdminThemeServiceProvider.php',
            'dependencies' => [],
            'uninstall' => [
                'tables' => [],
                'settings' => [],
                'storage_paths' => [],
                'records' => [],
                'columns' => [],
                'operation_target_prefixes' => [],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::ensureDirectoryExists($this->customThemePath.'/src');
        File::put($this->customThemePath.'/src/CustomAdminThemeServiceProvider.php', "<?php\n\nnamespace Modules\\CustomAdminTheme;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass CustomAdminThemeServiceProvider extends ServiceProvider {}\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->customThemePath);

        parent::tearDown();
    }

    public function test_activating_custom_admin_theme_disables_default_and_deactivation_restores_default(): void
    {
        $this->createPlugin('Admin Theme', 'admin-theme', Plugin::STATUS_ACTIVE, [
            'type' => 'theme',
            'theme' => ['scope' => 'admin', 'default' => true],
        ]);
        $this->createPlugin('Custom Admin Theme', 'custom-admin-theme', Plugin::STATUS_DISABLED, [
            'type' => 'theme',
            'theme' => ['scope' => 'admin'],
        ], $this->customThemePath);

        $repository = new PluginRepository;
        $menus = Mockery::mock(PluginMenuRegistry::class);
        $menus->shouldReceive('show')->with('custom-admin-theme')->once();
        $menus->shouldReceive('hide')->with('admin-theme')->once();
        $menus->shouldReceive('hide')->with('custom-admin-theme')->once();
        $menus->shouldReceive('show')->with('admin-theme')->once();
        $runtime = Mockery::mock(PluginRuntimeRegistry::class);
        $runtime->shouldReceive('enable')->with('custom-admin-theme')->once();
        $runtime->shouldReceive('disable')->with('admin-theme')->once();
        $runtime->shouldReceive('disable')->with('custom-admin-theme')->once();
        $runtime->shouldReceive('enable')->with('admin-theme')->once();
        $cache = Mockery::mock(PluginCacheCleaner::class);
        $cache->shouldReceive('clear')->times(3);
        $steps = Mockery::mock(StepBackupper::class);
        $steps->shouldReceive('afterStep')->andReturn(new BackupCheckpoint);

        $adminThemes = new AdminThemeManager($repository, $menus, $runtime, $cache);
        $dependencies = Mockery::mock(PluginDependencyChecker::class);
        $dependencies->shouldReceive('missingDependencies')->once()->andReturn([]);
        $licenses = Mockery::mock(LicenseManager::class);
        $licenses->shouldReceive('canActivatePlugin')->once()->with('custom-admin-theme')->andReturnTrue();

        $activator = new PluginActivator(
            $repository,
            $dependencies,
            $menus,
            $licenses,
            $runtime,
            $cache,
            $steps,
            app(PluginPackageValidator::class),
            app(PluginFilesystem::class),
            $adminThemes,
        );

        $activator->activate('custom-admin-theme');

        $this->assertSame(Plugin::STATUS_DISABLED, Plugin::query()->where('slug', 'admin-theme')->value('status'));
        $this->assertSame(Plugin::STATUS_ACTIVE, Plugin::query()->where('slug', 'custom-admin-theme')->value('status'));

        $operation = new OperationLog;
        $operations = Mockery::mock(OperationLogger::class);
        $operations->shouldReceive('start')->once()->andReturn($operation);
        $operations->shouldReceive('success')->once()->with($operation, 'Plugin disabled.')->andReturn($operation);
        $failed = Mockery::mock(FailedOperationLogger::class);
        $failed->shouldNotReceive('log');

        $deactivator = new PluginDeactivator($repository, $menus, $runtime, $cache, $operations, $failed, $steps, $adminThemes);
        $deactivator->deactivate('custom-admin-theme');

        $this->assertSame(Plugin::STATUS_ACTIVE, Plugin::query()->where('slug', 'admin-theme')->value('status'));
        $this->assertSame(Plugin::STATUS_DISABLED, Plugin::query()->where('slug', 'custom-admin-theme')->value('status'));
    }

    public function test_default_admin_theme_cannot_be_directly_deactivated_without_replacement(): void
    {
        $this->createPlugin('Admin Theme', 'admin-theme', Plugin::STATUS_ACTIVE, [
            'type' => 'theme',
            'theme' => ['scope' => 'admin', 'default' => true],
        ]);

        $adminThemes = new AdminThemeManager(
            new PluginRepository,
            Mockery::mock(PluginMenuRegistry::class),
            Mockery::mock(PluginRuntimeRegistry::class),
            Mockery::mock(PluginCacheCleaner::class),
        );
        $deactivator = new PluginDeactivator(
            new PluginRepository,
            Mockery::mock(PluginMenuRegistry::class),
            Mockery::mock(PluginRuntimeRegistry::class),
            Mockery::mock(PluginCacheCleaner::class),
            Mockery::mock(OperationLogger::class),
            Mockery::mock(FailedOperationLogger::class),
            Mockery::mock(StepBackupper::class),
            $adminThemes,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The default admin theme cannot be deactivated while no other admin theme is active.');

        $deactivator->deactivate('admin-theme');
    }

    private function createPlugin(string $name, string $slug, string $status, array $manifest, ?string $path = null): Plugin
    {
        return Plugin::query()->create([
            'name' => $name,
            'slug' => $slug,
            'version' => '1.0.0',
            'status' => $status,
            'path' => $path ?? base_path('modules/admin-theme'),
            'provider' => 'Modules\\Theme\\ThemeServiceProvider',
            'dependencies' => [],
            'manifest' => array_replace([
                'name' => $name,
                'slug' => $slug,
                'version' => '1.0.0',
                'provider' => 'Modules\\Theme\\ThemeServiceProvider',
                'dependencies' => [],
            ], $manifest),
        ]);
    }
}
