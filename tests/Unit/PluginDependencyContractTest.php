<?php

namespace Tests\Unit;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Services\PluginDependencyChecker;
use PHPUnit\Framework\TestCase;

class PluginDependencyContractTest extends TestCase
{
    public function test_required_dependencies_must_be_active_before_activation(): void
    {
        $manifest = new PluginManifest(
            name: 'Dependent Plugin',
            slug: 'dependent-plugin',
            version: '1.0.0',
            provider: 'Modules\\Dependent\\ServiceProvider',
            dependencies: ['lms'],
        );
        $checker = new PluginDependencyChecker();

        $this->assertSame(['lms'], $checker->missingDependencies($manifest, ['blog']));
        $this->assertSame([], $checker->missingDependencies($manifest, ['blog', 'lms']));
    }
}