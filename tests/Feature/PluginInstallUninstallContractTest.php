<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PluginController;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginManager;
use App\Platform\Core\Services\PluginManifestReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PluginInstallUninstallContractTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'permission-contract-test';

    private string $modulePath;

    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = base_path('modules/'.self::SLUG);
        $this->packagePath = base_path('modules/Permission Contract Test.zip');
        File::deleteDirectory($this->modulePath);
        File::delete($this->packagePath);
        File::copyDirectory(
            base_path('tests/Fixtures/plugins/'.self::SLUG),
            $this->modulePath,
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulePath);
        File::delete($this->packagePath);
        File::deleteDirectory(public_path('platform/plugins/'.self::SLUG));
        Storage::disk('local')->deleteDirectory(self::SLUG);

        foreach (glob(storage_path('app/plugin_uninstalls/removed_modules/'.self::SLUG.'-*')) ?: [] as $archive) {
            File::deleteDirectory($archive);
        }

        foreach ([
            storage_path('app/platform/backup-checkpoints/*'.self::SLUG.'*'),
            storage_path('app/platform/plugin-install-checkpoints/*'.self::SLUG.'*'),
            storage_path('app/platform/plugin-uninstall-checkpoints/*'.self::SLUG.'*'),
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $checkpoint) {
                is_dir($checkpoint) ? File::deleteDirectory($checkpoint) : File::delete($checkpoint);
            }
        }

        parent::tearDown();
    }

    public function test_plugin_purge_removes_owned_data_metadata_files_and_keeps_only_final_audit_log(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->install($this->modulePath);
        $asset = public_path('platform/plugins/'.self::SLUG.'/css/permission-contract-test.css');
        File::put($this->packagePath, 'disposable package');
        Storage::disk('local')->put(self::SLUG.'/owned.txt', 'must be purged');

        DB::table('permission_contract_test_records')->insert([
            'value' => 'must be purged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('activity_logs')->insert([
            'user_id' => null,
            'action' => 'permission-contract-test.record',
            'subject_type' => null,
            'subject_id' => null,
            'description' => 'must be purged',
            'properties_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('platform_plugin_registry_entries')->updateOrInsert(
            ['registry_type' => 'test-extra', 'plugin_slug' => self::SLUG],
            ['payload' => '{}', 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('platform_settings')->insert([
            'group_key' => self::SLUG,
            'setting_key' => 'enabled',
            'label' => 'Enabled',
            'type' => 'boolean',
            'value' => 'true',
            'default_value' => 'false',
            'options' => null,
            'help_text' => null,
            'sort_order' => 0,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('plugin_updates')->insert([
            'plugin_slug' => self::SLUG,
            'plugin_id' => $plugin->id,
            'version' => '1.0.1',
            'current_version' => '1.0.0',
            'available_version' => '1.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Permission::findOrCreate('route.admin.plugins.permission-contract-test.index', 'web');
        DB::table('operation_logs')->insert([
            'operation_type' => 'fixture.history',
            'target_type' => 'plugin',
            'target_slug' => self::SLUG.'.child',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(Plugin::STATUS_INSTALLED, $plugin->status);
        $this->assertFileExists($asset);
        $this->assertSame(0664, fileperms($asset) & 0777);
        $this->assertTrue(Schema::hasColumn('users', 'permission_contract_test_note'));
        $this->assertDatabaseHas('menus', ['key' => self::SLUG.'.admin']);
        $this->assertGreaterThan(
            0,
            DB::table('backup_checkpoints')
                ->where('target_type', 'plugin')
                ->where('target_slug', self::SLUG)
                ->count(),
        );
        $this->assertNotEmpty(
            glob(storage_path('app/platform/plugin-install-checkpoints/*'.self::SLUG.'*')) ?: [],
        );

        $plugin = $manager->activate($plugin);
        $this->assertSame(Plugin::STATUS_ACTIVE, $plugin->status);

        $plugin = $manager->deactivate($plugin);
        $this->assertSame(Plugin::STATUS_DISABLED, $plugin->status);

        $result = $manager->purge($plugin);

        $this->assertTrue($result['success'], (string) ($result['message'] ?? ''));
        $this->assertSame('purge', $result['data_policy']);
        $this->assertFalse(Schema::hasTable('permission_contract_test_records'));
        $this->assertFalse(Schema::hasColumn('users', 'permission_contract_test_note'));
        $this->assertDatabaseMissing('migrations', [
            'migration' => '2026_07_24_000001_create_permission_contract_test_records',
        ]);
        $this->assertNull(Permission::query()->where('name', self::SLUG.'.manage')->first());
        $this->assertNull(
            Permission::query()
                ->where('name', 'route.admin.plugins.permission-contract-test.index')
                ->first(),
        );
        $this->assertDatabaseMissing('platform_plugin_registry_entries', [
            'plugin_slug' => self::SLUG,
        ]);
        $this->assertDatabaseMissing('platform_settings', [
            'group_key' => self::SLUG,
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'permission-contract-test.record',
        ]);
        $this->assertDatabaseMissing('operation_logs', [
            'target_slug' => self::SLUG.'.child',
        ]);
        $this->assertDatabaseMissing('plugin_updates', [
            'plugin_slug' => self::SLUG,
        ]);
        $this->assertDatabaseMissing('menus', [
            'key' => self::SLUG.'.admin',
        ]);
        $this->assertDatabaseMissing('backup_checkpoints', [
            'target_type' => 'plugin',
            'target_slug' => self::SLUG,
        ]);
        $this->assertSame(
            [],
            glob(storage_path('app/platform/plugin-install-checkpoints/*'.self::SLUG.'*')) ?: [],
        );
        $this->assertFileDoesNotExist($asset);
        $this->assertFileExists($this->packagePath);
        $this->assertNotContains(
            realpath($this->packagePath),
            $result['removed_resources']['package_files'],
        );
        $this->assertFalse(Storage::disk('local')->exists(self::SLUG));
        $this->assertDirectoryDoesNotExist($this->modulePath);
        $this->assertNull(Plugin::query()->where('slug', self::SLUG)->first());
        $this->assertSame(1, DB::table('operation_logs')->where('target_slug', self::SLUG)->count());
        $this->assertDatabaseHas('operation_logs', [
            'operation_type' => 'plugin.purge',
            'target_slug' => self::SLUG,
            'status' => 'success',
        ]);

        $staleModelRetry = $manager->purge($plugin);
        $slugRetry = $manager->purge(self::SLUG);

        $this->assertTrue($staleModelRetry['success']);
        $this->assertTrue($staleModelRetry['already_absent']);
        $this->assertSame(['already_absent'], $staleModelRetry['completed_steps']);
        $this->assertTrue($slugRetry['success']);
        $this->assertTrue($slugRetry['already_absent']);
        $this->assertSame(1, DB::table('operation_logs')->where('target_slug', self::SLUG)->count());
        $this->assertFileExists($this->packagePath);
    }

    public function test_first_upload_replaces_a_matching_unregistered_bundled_module_safely(): void
    {
        $uploadedRoot = storage_path('framework/testing/permission-contract-upload');
        File::deleteDirectory($uploadedRoot);
        File::copyDirectory(base_path('tests/Fixtures/plugins/'.self::SLUG), $uploadedRoot);
        File::put($this->modulePath.'/bundled-only.txt', 'old bundled copy');
        File::put($uploadedRoot.'/uploaded-only.txt', 'uploaded package');

        try {
            $manager = app(PluginManager::class);
            $uploadedManifest = $manager->validatePackage($uploadedRoot);
            $method = new ReflectionMethod(PluginController::class, 'installOverUnregisteredDirectory');
            $plugin = $method->invoke(
                app(PluginController::class),
                Request::create('/admin/plugins/install', 'POST'),
                $manager,
                $uploadedManifest,
                $uploadedRoot,
                $this->modulePath,
            );

            $this->assertSame(Plugin::STATUS_INSTALLED, $plugin->status);
            $this->assertDatabaseHas('plugins', ['slug' => self::SLUG]);
            $this->assertFileExists($this->modulePath.'/uploaded-only.txt');
            $this->assertFileDoesNotExist($this->modulePath.'/bundled-only.txt');
        } finally {
            File::deleteDirectory($uploadedRoot);
        }
    }

    public function test_first_upload_preserves_an_unregistered_directory_with_a_different_identity(): void
    {
        $uploadedRoot = storage_path('framework/testing/permission-contract-mismatch');
        File::deleteDirectory($uploadedRoot);
        File::copyDirectory(base_path('tests/Fixtures/plugins/'.self::SLUG), $uploadedRoot);
        File::put($this->modulePath.'/preserve-me.txt', 'must remain');

        $manifestPath = $this->modulePath.'/module.json';
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['author'] = 'Different Owner';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $manager = app(PluginManager::class);
            $uploadedManifest = $manager->validatePackage($uploadedRoot);
            $method = new ReflectionMethod(PluginController::class, 'installOverUnregisteredDirectory');

            try {
                $method->invoke(
                    app(PluginController::class),
                    Request::create('/admin/plugins/install', 'POST'),
                    $manager,
                    $uploadedManifest,
                    $uploadedRoot,
                    $this->modulePath,
                );
                $this->fail('A mismatched unregistered module directory must not be replaced.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('different owner/author', $exception->getMessage());
            }

            $this->assertDatabaseMissing('plugins', ['slug' => self::SLUG]);
            $this->assertFileExists($this->modulePath.'/preserve-me.txt');
        } finally {
            File::deleteDirectory($uploadedRoot);
        }
    }

    public function test_manifest_accepts_explicit_empty_ownership_but_rejects_a_missing_category(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/plugins/'.self::SLUG.'/module.json')),
            true,
        );

        foreach (['tables', 'settings', 'storage_paths', 'records', 'columns', 'operation_target_prefixes'] as $key) {
            $manifest['uninstall'][$key] = [];
        }

        $validated = app(PluginManifestReader::class)->validate($manifest);
        $this->assertSame([], data_get($validated, 'uninstall.records'));

        unset($manifest['uninstall']['records']);

        $this->expectException(ValidationException::class);
        app(PluginManifestReader::class)->validate($manifest);
    }
}
