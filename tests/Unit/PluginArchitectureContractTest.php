<?php

namespace Tests\Unit;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Services\PluginOwnershipValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class PluginArchitectureContractTest extends TestCase
{
    public function test_project_root_contains_no_legacy_runtime_patch_scripts(): void
    {
        $scripts = glob(dirname(__DIR__, 2).'/*.php') ?: [];

        self::assertSame(
            [],
            array_values($scripts),
            'Temporary PHP scripts must not execute beside the application entry points.',
        );
    }

    public function test_every_runtime_plugin_declares_the_unified_platform_contract(): void
    {
        $manifests = glob(dirname(__DIR__, 2).'/modules/*/module.json') ?: [];

        self::assertNotEmpty($manifests);

        foreach ($manifests as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            self::assertIsArray($manifest, $manifestPath.' must contain valid JSON.');
            self::assertContains($manifest['type'] ?? null, ['feature', 'theme', 'service'], $manifestPath);
            self::assertMatchesRegularExpression(
                '/^>=2\.\d+\.\d+ <3\.0\.0$/',
                (string) ($manifest['platform_version'] ?? ''),
                $manifestPath,
            );
            self::assertNotEmpty($manifest['provider'] ?? null, $manifestPath);
            self::assertIsArray($manifest['uninstall'] ?? null, $manifestPath);
            self::assertArrayNotHasKey('script', $manifest['uninstall'], $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.tables'), $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.settings'), $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.storage_paths'), $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.records'), $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.columns'), $manifestPath);
            self::assertIsArray(data_get($manifest, 'uninstall.operation_target_prefixes'), $manifestPath);

            (new PluginOwnershipValidator)->validate(
                dirname($manifestPath),
                PluginManifest::fromArray($manifest, $manifestPath),
            );
        }
    }

    public function test_install_contract_rejects_an_undeclared_migration_table(): void
    {
        $root = dirname(__DIR__, 2).'/tests/Fixtures/plugins/permission-contract-test';
        $manifest = json_decode((string) file_get_contents($root.'/module.json'), true);
        $manifest['uninstall']['tables'] = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('permission_contract_test_records');

        (new PluginOwnershipValidator)->validate(
            $root,
            PluginManifest::fromArray($manifest, $root.'/module.json'),
        );
    }

    public function test_every_distributable_plugin_zip_declares_complete_purge_ownership(): void
    {
        $root = dirname(__DIR__, 2);
        $archives = glob($root.'/modules/*.zip') ?: [];

        self::assertNotEmpty($archives);

        foreach ($archives as $archive) {
            $temporary = sys_get_temp_dir().'/ainpa-plugin-contract-'.bin2hex(random_bytes(8));
            mkdir($temporary, 0777, true);

            try {
                $zip = new ZipArchive;
                self::assertTrue($zip->open($archive) === true, $archive);
                self::assertTrue($zip->extractTo($temporary), $archive);
                $zip->close();

                $manifests = [];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS),
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getFilename() === 'module.json') {
                        $manifests[] = $file->getPathname();
                    }
                }

                self::assertCount(1, $manifests, $archive);

                $manifest = json_decode((string) file_get_contents($manifests[0]), true);
                self::assertIsArray($manifest, $archive);

                foreach (['tables', 'settings', 'storage_paths', 'records', 'columns', 'operation_target_prefixes'] as $key) {
                    self::assertIsArray(data_get($manifest, 'uninstall.'.$key), $archive.': '.$key);
                }
                self::assertArrayNotHasKey('script', $manifest['uninstall'], $archive);

                (new PluginOwnershipValidator)->validate(
                    dirname($manifests[0]),
                    PluginManifest::fromArray($manifest, $manifests[0]),
                );
            } finally {
                $this->removeDirectory($temporary);
            }
        }
    }

    public function test_plugin_providers_do_not_register_routes_outside_the_core_loader(): void
    {
        foreach ($this->providerFiles() as $providerFile) {
            $source = (string) file_get_contents($providerFile);

            self::assertStringNotContainsString('Route::', $source, $providerFile);
            self::assertStringNotContainsString('loadRoutesFrom(', $source, $providerFile);
        }
    }

    public function test_core_does_not_import_plugin_namespaces(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/^use\s+Modules\\\\/m',
                (string) file_get_contents($file->getPathname()),
                $file->getPathname(),
            );
        }
    }

    public function test_composer_autoload_does_not_preload_plugins(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $namespaces = array_merge(
            array_keys(data_get($composer, 'autoload.psr-4', [])),
            array_keys(data_get($composer, 'autoload-dev.psr-4', [])),
        );

        foreach ($namespaces as $namespace) {
            self::assertFalse(str_starts_with($namespace, 'Modules\\'), $namespace);
        }
    }

    public function test_uninstall_contract_purges_runtime_state_but_retains_distributable_archives(): void
    {
        $root = dirname(__DIR__, 2);
        $flow = (string) file_get_contents($root.'/app/Platform/Core/Plugins/Uninstall/PluginUninstallFlow.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/PluginController.php');
        $packageRemover = (string) file_get_contents(
            $root.'/app/Platform/Core/Plugins/Uninstall/PluginPackageRemover.php',
        );

        self::assertStringContainsString("'data_policy' => 'purge'", $flow);
        self::assertStringContainsString('$plugins->purge($slug)', $controller);
        self::assertStringNotContainsString('data_retained', $flow);
        self::assertStringNotContainsString('archiveModuleFiles', $flow);
        self::assertStringNotContainsString("getExtension()) === 'zip'", $packageRemover);
        self::assertStringNotContainsString('modules/*.zip', $packageRemover);
        self::assertFileDoesNotExist($root.'/app/Platform/Core/Plugins/Uninstall/PluginUninstallBackup.php');
    }

    public function test_plugin_archives_use_a_private_managed_upload_workspace(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/PluginController.php');
        $filesystems = (string) file_get_contents($root.'/config/filesystems.php');

        self::assertStringContainsString('PluginUploadWorkspace', $controller);
        self::assertStringNotContainsString("store('plugin_uploads/tmp')", $controller);
        self::assertStringNotContainsString('Storage::path(', $controller);
        self::assertStringContainsString("'plugin_uploads' => [", $filesystems);
        self::assertStringContainsString("'root' => storage_path('app/plugin_uploads')", $filesystems);
        self::assertStringContainsString("'visibility' => 'private'", $filesystems);
    }

    public function test_core_does_not_query_plugin_owned_tables(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/(?:DB::table|Schema::hasTable|Schema::getColumnListing)\(["\x27](?:blog_|lms_|store_|ai_core_|ai_assistant_|professional_programmer_)/',
                (string) file_get_contents($file->getPathname()),
                $file->getPathname(),
            );
        }
    }

    /** @return list<string> */
    private function providerFiles(): array
    {
        $files = [];

        foreach (glob(dirname(__DIR__, 2).'/modules/*/module.json') ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $root = dirname($manifestPath);
            $configured = is_array($manifest) ? ($manifest['provider_file'] ?? null) : null;
            $candidates = array_filter([
                is_string($configured) ? $root.'/'.$configured : null,
                $root.'/src/'.basename(str_replace('\\', '/', (string) ($manifest['provider'] ?? ''))).'.php',
                $root.'/ServiceProvider.php',
            ]);

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $files[] = $candidate;
                    break;
                }
            }
        }

        return $files;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
