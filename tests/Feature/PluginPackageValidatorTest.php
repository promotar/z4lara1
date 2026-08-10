<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PluginController;
use App\Platform\Core\Exceptions\PluginPackageValidationException;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginManager;
use App\Platform\Core\Services\PluginPackageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PluginPackageValidatorTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packagePath = storage_path('framework/testing/plugin-package-validator');
        File::deleteDirectory($this->packagePath);
        File::copyDirectory(
            base_path('tests/Fixtures/plugins/permission-contract-test'),
            $this->packagePath,
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->packagePath);

        parent::tearDown();
    }

    public function test_complete_plugin_package_passes_preflight(): void
    {
        $manifest = app(PluginPackageValidator::class)->validate($this->packagePath);

        $this->assertSame('permission-contract-test', $manifest->slug);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertDatabaseMissing('plugins', ['slug' => $manifest->slug]);
    }

    public function test_invalid_package_returns_all_actionable_reasons_before_install(): void
    {
        $manifestPath = $this->packagePath.'/module.json';
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['provider_file'] = 'src/MissingServiceProvider.php';
        $manifest['dependencies'] = ['missing-foundation-plugin'];
        $manifest['routes'] = [
            'admin' => [
                'file' => 'routes/missing.php',
                'prefix' => 'admin/plugins/invalid',
                'name' => 'admin.plugins.invalid.',
                'middleware' => ['web'],
            ],
        ];
        $manifest['assets']['frontend']['styles'][] = 'css/missing.css';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($this->packagePath.'/src/Broken.php', '<?php class Broken {');

        try {
            app(PluginManager::class)->install($this->packagePath);
            $this->fail('Expected package preflight to reject the invalid plugin.');
        } catch (PluginPackageValidationException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('MissingServiceProvider.php', $message);
            $this->assertStringContainsString('routes/missing.php', $message);
            $this->assertStringContainsString('css/missing.css', $message);
            $this->assertStringContainsString('Broken.php', $message);
            $this->assertStringContainsString('missing-foundation-plugin', $message);
        }

        $this->assertNull(Plugin::query()->where('slug', 'permission-contract-test')->first());
    }

    public function test_asset_files_without_a_catalog_are_rejected(): void
    {
        $manifestPath = $this->packagePath.'/module.json';
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['assets'] = ['source' => 'resources/assets'];
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->expectException(PluginPackageValidationException::class);
        $this->expectExceptionMessage('no styles or scripts are declared');

        app(PluginPackageValidator::class)->validate($this->packagePath);
    }

    public function test_corrupted_installed_package_cannot_be_activated(): void
    {
        $plugin = Plugin::query()->create([
            'name' => 'Permission Contract Test',
            'slug' => 'permission-contract-test',
            'version' => '1.0.0',
            'status' => Plugin::STATUS_DISABLED,
            'path' => $this->packagePath,
            'provider' => 'Tests\\Fixtures\\Plugins\\PermissionContractTestServiceProvider',
            'dependencies' => [],
            'manifest' => json_decode(
                (string) File::get($this->packagePath.'/module.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        ]);
        File::put($this->packagePath.'/src/BrokenAfterInstall.php', '<?php class BrokenAfterInstall {');

        try {
            app(PluginManager::class)->activate($plugin);
            $this->fail('Expected activation preflight to reject the corrupted plugin.');
        } catch (PluginPackageValidationException $exception) {
            $this->assertStringContainsString('BrokenAfterInstall.php', $exception->getMessage());
        }

        $this->assertSame(
            Plugin::STATUS_DISABLED,
            $plugin->fresh()->status,
            'A rejected plugin must remain disabled.',
        );
    }

    public function test_current_source_packages_pass_the_same_preflight(): void
    {
        $paths = collect(File::directories(base_path('modules')))
            ->filter(fn (string $path): bool => File::isFile($path.'/module.json'))
            ->values();

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $manifest = app(PluginPackageValidator::class)->validate($path);
            $sourceManifest = json_decode(
                (string) File::get($path.'/module.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $this->assertSame($sourceManifest['slug'], $manifest->slug);
        }
    }

    public function test_uploaded_plugin_archive_is_validated_without_bundling_it_in_core(): void
    {
        $archivePath = storage_path('framework/testing/permission-contract-test.zip');
        $extractPath = storage_path('framework/testing/plugin-zip-'.bin2hex(random_bytes(6)));
        File::delete($archivePath);
        File::deleteDirectory($extractPath);
        File::ensureDirectoryExists($extractPath);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->packagePath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = substr($file->getPathname(), strlen($this->packagePath) + 1);
                $archive->addFile($file->getPathname(), 'permission-contract-test/'.$relativePath);
            }
        }

        $archive->close();

        try {
            $method = new ReflectionMethod(PluginController::class, 'extractAndValidateZip');
            $pluginRoot = $method->invoke(app(PluginController::class), $archivePath, $extractPath);
            $manifest = app(PluginPackageValidator::class)->validate($pluginRoot);

            $this->assertSame('permission-contract-test', $manifest->slug);
            $this->assertSame(0, Plugin::query()->count());
        } finally {
            File::delete($archivePath);
            File::deleteDirectory($extractPath);
        }
    }

    public function test_archive_with_multiple_manifests_is_rejected_before_install(): void
    {
        $archivePath = storage_path('framework/testing/multiple-plugin-manifests.zip');
        $extractPath = storage_path('framework/testing/multiple-plugin-manifests');
        File::delete($archivePath);
        File::deleteDirectory($extractPath);
        File::ensureDirectoryExists($extractPath);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $archive->addFromString('plugin-a/module.json', '{}');
        $archive->addFromString('plugin-b/module.json', '{}');
        $archive->close();

        try {
            $method = new ReflectionMethod(PluginController::class, 'extractAndValidateZip');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('exactly one module.json');

            $method->invoke(app(PluginController::class), $archivePath, $extractPath);
        } finally {
            File::delete($archivePath);
            File::deleteDirectory($extractPath);
        }
    }
}
