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
use App\Platform\Core\Services\PluginActivator;
use App\Platform\Core\Services\PluginCacheCleaner;
use App\Platform\Core\Services\PluginDeactivator;
use App\Platform\Core\Services\PluginDependencyChecker;
use App\Platform\Core\Services\PluginFilesystem;
use App\Platform\Core\Services\PluginMenuRegistry;
use App\Platform\Core\Services\PluginPackageValidator;
use App\Platform\Core\Services\PluginRuntimeRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PluginLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivate_and_reactivate_preserve_plugin_data_and_refresh_runtime_state(): void
    {
        Schema::create('lms_contract_data', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
        DB::table('lms_contract_data')->insert(['value' => 'preserved-course-data']);
        $before = DB::table('lms_contract_data')->orderBy('id')->get()->toJson();
        Plugin::query()->create([
            'name' => 'LMS',
            'slug' => 'lms',
            'version' => 'test',
            'status' => Plugin::STATUS_ACTIVE,
            'path' => base_path('tests/Fixtures/plugins/permission-contract-test'),
            'provider' => 'Modules\\Lms\\LmsServiceProvider',
            'dependencies' => [],
            'manifest' => ['dependencies' => []],
        ]);
        $this->assertDatabaseHas('plugins', [
            'slug' => 'lms',
            'status' => Plugin::STATUS_ACTIVE,
        ]);

        $repository = new PluginRepository;
        $menus = Mockery::mock(PluginMenuRegistry::class);
        $menus->shouldReceive('hide')->once()->with('lms');
        $menus->shouldReceive('show')->once()->with('lms');
        $runtime = Mockery::mock(PluginRuntimeRegistry::class);
        $runtime->shouldReceive('disable')->once()->with('lms');
        $runtime->shouldReceive('enable')->once()->with('lms');
        $cache = Mockery::mock(PluginCacheCleaner::class);
        $cache->shouldReceive('clear')->twice();
        $operation = new OperationLog;
        $operations = Mockery::mock(OperationLogger::class);
        $operations->shouldReceive('start')->once()->andReturn($operation);
        $operations->shouldReceive('success')->once()->with($operation, 'Plugin disabled.')->andReturn($operation);
        $failed = Mockery::mock(FailedOperationLogger::class);
        $failed->shouldNotReceive('log');
        $steps = Mockery::mock(StepBackupper::class);
        $steps->shouldReceive('afterStep')->times(8)->andReturn(new BackupCheckpoint);

        $deactivator = new PluginDeactivator($repository, $menus, $runtime, $cache, $operations, $failed, $steps);
        $disabled = $deactivator->deactivate('lms');
        $this->assertSame(Plugin::STATUS_DISABLED, $disabled->status);
        $this->assertSame(Plugin::STATUS_DISABLED, Plugin::query()->where('slug', 'lms')->value('status'));
        $this->assertSame($before, DB::table('lms_contract_data')->orderBy('id')->get()->toJson());

        $dependencies = Mockery::mock(PluginDependencyChecker::class);
        $dependencies->shouldReceive('missingDependencies')->once()->andReturn([]);
        $licenses = Mockery::mock(LicenseManager::class);
        $licenses->shouldReceive('canActivatePlugin')->once()->with('lms')->andReturnTrue();
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
        );
        $active = $activator->activate('lms');

        $this->assertSame(Plugin::STATUS_ACTIVE, $active->status);
        $this->assertSame(Plugin::STATUS_ACTIVE, Plugin::query()->where('slug', 'lms')->value('status'));
        $this->assertSame($before, DB::table('lms_contract_data')->orderBy('id')->get()->toJson());
    }

    public function test_core_plugin_cannot_be_deactivated(): void
    {
        Plugin::query()->create([
            'name' => 'Platform Core',
            'slug' => 'platform-core',
            'version' => 'test',
            'status' => Plugin::STATUS_ACTIVE,
            'path' => base_path(),
            'provider' => 'App\\Providers\\AppServiceProvider',
            'dependencies' => [],
            'manifest' => ['type' => 'core'],
        ]);

        $deactivator = new PluginDeactivator(
            new PluginRepository,
            Mockery::mock(PluginMenuRegistry::class),
            Mockery::mock(PluginRuntimeRegistry::class),
            Mockery::mock(PluginCacheCleaner::class),
            Mockery::mock(OperationLogger::class),
            Mockery::mock(FailedOperationLogger::class),
            Mockery::mock(StepBackupper::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Core plugin [platform-core] cannot be deactivated.');

        $deactivator->deactivate('platform-core');
    }
}
