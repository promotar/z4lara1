<?php

namespace Tests\Unit;

use App\Platform\Core\Services\PlatformVersion;
use Tests\TestCase;

class PlatformVersionTest extends TestCase
{
    public function test_release_label_and_compatibility_version_are_declared_in_code(): void
    {
        $configuration = (string) file_get_contents(config_path('platform.php'));

        $this->assertMatchesRegularExpression("/'release' => '(\d+\.\d+\.\d+)'/", $configuration);
        preg_match("/'release' => '(\d+\.\d+\.\d+)'/", $configuration, $matches);
        $this->assertSame('4.5.71', $matches[1]);
        $this->assertSame('4.5.71', config('platform.release'));

        $this->assertMatchesRegularExpression("/'version' => '(\d+\.\d+\.\d+)'/", $configuration);
        $this->assertSame('3.4.4', config('platform.version'));

        $this->assertDoesNotMatchRegularExpression(
            '/^PLATFORM_VERSION=/m',
            (string) file_get_contents(base_path('.env.example')),
        );
    }

    public function test_current_platform_version_satisfies_supported_plugin_contracts(): void
    {
        config()->set('platform.version', '3.4.4');
        $version = new PlatformVersion;

        $this->assertTrue($version->supports('>=3.4.3 <4.0.0'));
        $this->assertTrue($version->supports('^3.4'));
        $this->assertFalse($version->supports('>=4.0.0'));
        $this->assertFalse($version->supports('invalid-constraint'));
    }
}
