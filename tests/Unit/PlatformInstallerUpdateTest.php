<?php

namespace Tests\Unit;

use App\Installation\InstallationState;
use App\Installation\PlatformInstaller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PlatformInstallerUpdateTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/platform-installer-update');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_update_runs_pending_migrations_without_erasing_or_seeding_existing_data(): void
    {
        $state = new InstallationState(
            $this->directory.'/installation.env',
            $this->directory.'/.env',
            $this->directory.'/installation.complete',
        );
        File::put($this->directory.'/.env', "APP_NAME=\"Existing platform\"\n");

        $connection = new class
        {
            public function getPdo(): object
            {
                return new \stdClass;
            }
        };

        Config::shouldReceive('set')->andReturnNull();
        DB::shouldReceive('connection')->with('installer')->once()->andReturn($connection);
        DB::shouldReceive('purge')->with('installer')->twice();
        DB::shouldReceive('purge')->with('mysql')->once();
        Artisan::shouldReceive('call')
            ->with('migrate', ['--force' => true, '--no-interaction' => true])
            ->once()
            ->andReturn(0);
        Artisan::shouldReceive('call')->with('migrate:fresh', \Mockery::any())->never();
        Artisan::shouldReceive('call')->with('db:wipe', \Mockery::any())->never();
        Artisan::shouldReceive('call')->with('optimize:clear')->once()->andReturn(0);

        (new PlatformInstaller($state))->update([
            'host' => 'database.internal',
            'port' => '3306',
            'database' => 'existing_platform',
            'username' => 'platform_user',
            'password' => 'secret-not-logged',
        ]);

        $this->assertTrue($state->installed());
        $this->assertFileExists($this->directory.'/installation.complete');
        $this->assertStringContainsString('APP_NAME="Existing platform"', File::get($this->directory.'/.env'));
        $this->assertStringContainsString('DB_DATABASE="existing_platform"', File::get($this->directory.'/.env'));
    }
}
