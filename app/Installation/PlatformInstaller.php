<?php

namespace App\Installation;

use App\Http\Controllers\Admin\MenuSettingsController;
use App\Models\User;
use App\Platform\Core\Services\PermissionManager;
use App\Platform\Core\Services\RequiredCorePluginBootstrapper;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PlatformInstaller
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly RequiredCorePluginBootstrapper $requiredCorePlugins,
    ) {}

    /** @param array<string, string> $database */
    public function testDatabase(array $database): void
    {
        $this->configureDatabase($database);
        try {
            DB::connection('installer')->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException('Database connection failed: '.$exception->getMessage(), 0, $exception);
        } finally {
            DB::purge('installer');
        }
    }

    /**
     * @param  array<string, string>  $platform
     * @param  array<string, string>  $database
     * @param  array<string, string>  $owner
     */
    public function install(array $platform, array $database, array $owner, ?UploadedFile $logo): void
    {
        $this->testDatabase($database);
        // A fresh installation always gets a new permanent key. The key used
        // while rendering the installer is temporary and must never be reused
        // from a packaged environment file.
        $appKey = 'base64:'.base64_encode(random_bytes(32));

        $this->state->write([
            'APP_NAME' => $platform['name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($platform['domain'], '/'),
            'TRUSTED_PROXIES' => $platform['trusted_proxies'] ?? '127.0.0.1,::1',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'INSTALLATION_COMPLETE' => '0',
            'INSTAAL_IS_ACTIVE' => '0',
            'INSTAAL_IS_ATIVE' => '0',
        ]);

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', $this->connection($database));
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->requiredCorePlugins->bootstrap();

        User::query()->delete();
        $user = User::query()->forceCreate([
            'name' => Str::before($owner['email'], '@'),
            'email' => $owner['email'],
            'email_verified_at' => now(),
            'password' => $owner['password'],
        ]);
        app(PermissionManager::class)->assignSuperAdmin($user);
        app(MenuSettingsController::class)->initializeDefaults();

        $settings = app(SettingsRepository::class);
        $settings->values();
        $files = $logo ? ['general' => ['site_logo' => $logo]] : [];
        $settings->update([
            'general' => [
                'site_title' => $platform['name'],
                'wordpress_address_url' => rtrim($platform['domain'], '/'),
                'site_address_url' => rtrim($platform['domain'], '/'),
                'admin_email' => $owner['email'],
            ],
        ], $files, [], [], $user->id, 'platform.installer');

        $this->state->setInstalled(true);
        Artisan::call('optimize:clear');
    }

    /**
     * @param  array<string, string>  $database
     * @param  array<string, string>  $runtime
     */
    public function update(array $database, array $runtime = []): void
    {
        $this->testDatabase($database);

        $environment = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
        ];
        if (filter_var($runtime['app_url'] ?? null, FILTER_VALIDATE_URL)) {
            $environment['APP_URL'] = rtrim($runtime['app_url'], '/');
        }
        if (trim($runtime['trusted_proxies'] ?? '') !== '') {
            $environment['TRUSTED_PROXIES'] = trim($runtime['trusted_proxies']);
        }
        $this->state->write($environment);

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', $this->connection($database));
        DB::purge('mysql');

        // Updates are intentionally non-destructive: only pending migrations
        // run, and existing platform records are never truncated or reseeded.
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

        $this->state->setInstalled(true);
        Artisan::call('optimize:clear');
    }

    /** @param array<string, string> $database */
    private function configureDatabase(array $database): void
    {
        Config::set('database.connections.installer', $this->connection($database));
        DB::purge('installer');
    }

    /** @param array<string, string> $database */
    private function connection(array $database): array
    {
        return [
            'driver' => 'mysql',
            'host' => $database['host'],
            'port' => $database['port'],
            'database' => $database['database'],
            'username' => $database['username'],
            'password' => $database['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }
}
