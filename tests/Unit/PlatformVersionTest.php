<?php

namespace Tests\Unit;

use App\Platform\Core\Services\PlatformVersion;
use Tests\TestCase;

class PlatformVersionTest extends TestCase
{
    public function test_release_version_is_declared_by_the_environment_contract(): void
    {
        $environment = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^PLATFORM_VERSION=(\d+\.\d+\.\d+)$/m',
            $environment,
        );
        preg_match('/^PLATFORM_VERSION=(\d+\.\d+\.\d+)$/m', $environment, $matches);
        $declaredVersion = $matches[1];

        $this->assertSame('2.5.2', $declaredVersion);
        $this->assertSame($declaredVersion, config('platform.version'));
        $this->assertStringContainsString(
            "env('PLATFORM_VERSION', '{$declaredVersion}')",
            (string) file_get_contents(config_path('platform.php')),
        );
    }

    public function test_current_platform_version_satisfies_supported_plugin_contracts(): void
    {
        config()->set('platform.version', '2.0.0');
        $version = new PlatformVersion;

        $this->assertTrue($version->supports('>=2.0.0 <3.0.0'));
        $this->assertTrue($version->supports('^2.0'));
        $this->assertFalse($version->supports('>=3.0.0'));
        $this->assertFalse($version->supports('invalid-constraint'));
    }
}
