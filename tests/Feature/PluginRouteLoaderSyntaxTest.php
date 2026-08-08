<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginRouteLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class PluginRouteLoaderSyntaxTest extends TestCase
{
    public function test_route_syntax_is_validated_without_an_external_php_process(): void
    {
        Log::spy();

        $directory = storage_path('framework/testing/plugin-route-syntax');
        $validRoute = $directory.'/valid.php';
        $invalidRoute = $directory.'/invalid.php';

        File::ensureDirectoryExists($directory);
        File::put($validRoute, "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/valid', fn () => 'ok');\n");
        File::put($invalidRoute, "<?php\nRoute::get('/invalid', function (\n");

        try {
            $reflection = new ReflectionClass(PluginRouteLoader::class);
            $loader = $reflection->newInstanceWithoutConstructor();
            $validator = $reflection->getMethod('routeFileHasValidSyntax');
            $plugin = new Plugin(['slug' => 'syntax-test']);

            self::assertTrue($validator->invoke($loader, $plugin, $validRoute, 'web'));
            self::assertFalse($validator->invoke($loader, $plugin, $invalidRoute, 'web'));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
