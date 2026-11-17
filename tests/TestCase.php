<?php

namespace Tests;

use App\Installation\InstallationState;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private string $defaultTestInstallationDirectory;

    private string $testEnvironmentDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTestInstallationDirectory = storage_path('framework/testing/default-installation-state');
        File::ensureDirectoryExists($this->defaultTestInstallationDirectory);
        File::put($this->defaultTestInstallationDirectory.'/installation.complete', "installed\n");
        $this->app->instance(InstallationState::class, new InstallationState(
            $this->defaultTestInstallationDirectory.'/installation.env',
            $this->defaultTestInstallationDirectory.'/.env',
            $this->defaultTestInstallationDirectory.'/installation.complete',
        ));

        if (Schema::hasTable('platform_settings')) {
            $this->artisan('platform:sync-settings', ['--no-interaction' => true]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->defaultTestInstallationDirectory)) {
            File::deleteDirectory($this->defaultTestInstallationDirectory);
        }
        if (isset($this->testEnvironmentDirectory)) {
            File::deleteDirectory($this->testEnvironmentDirectory);
        }

        parent::tearDown();
    }

    public function createApplication(): Application
    {
        $basePath = dirname(__DIR__);
        $this->registerMountedPluginNamespaces($basePath);
        $environment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => $basePath.'/bootstrap/cache/testing-config.php',
            'APP_EVENTS_CACHE' => $basePath.'/bootstrap/cache/testing-events.php',
            'APP_ROUTES_CACHE' => $basePath.'/bootstrap/cache/testing-routes.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->testEnvironmentDirectory = $basePath.'/storage/framework/testing/environment';
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->testEnvironmentDirectory);
        $files->put($this->testEnvironmentDirectory.'/.env', '');

        $app = require $basePath.'/bootstrap/app.php';
        $app->useEnvironmentPath($this->testEnvironmentDirectory);
        $app->make(Kernel::class)->bootstrap();

        if (
            $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException('Tests refused to start outside isolated SQLite memory storage.');
        }

        return $app;
    }

    private function registerMountedPluginNamespaces(string $basePath): void
    {
        foreach (glob($basePath.'/modules/*/module.json') ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $provider = is_array($manifest) ? ($manifest['provider'] ?? null) : null;

            if (! is_string($provider) || ! str_contains($provider, '\\')) {
                continue;
            }

            $namespace = substr($provider, 0, (int) strrpos($provider, '\\') + 1);
            $modulePath = dirname($manifestPath);
            $sourcePath = is_dir($modulePath.'/src') ? $modulePath.'/src' : $modulePath;

            foreach (ClassLoader::getRegisteredLoaders() as $loader) {
                $loader->addPsr4($namespace, $sourcePath, true);
            }
        }
    }
}
