<?php

namespace Tests\Feature;

use App\Platform\Core\Assets\AssetManager;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginAssetRegistry;
use App\Platform\Core\Services\PluginRuntimeGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginAssetRegistryTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            File::deleteDirectory($path);
        }

        parent::tearDown();
    }

    public function test_active_assets_are_published_ordered_and_removed_when_plugin_is_disabled(): void
    {
        $feature = $this->plugin('test-feature-assets', 'feature', 'feature.css');
        $theme = $this->plugin('test-theme-assets', 'theme', 'theme.css');
        $disabled = $this->plugin('test-disabled-assets', 'theme', 'disabled.css', Plugin::STATUS_DISABLED);

        $registry = app(PluginAssetRegistry::class);
        $assets = $registry->assets('frontend');

        $this->assertSame(
            [$feature->slug, $theme->slug],
            collect($assets['styles'])->pluck('slug')->all(),
        );
        $this->assertSame(
            [$feature->slug, $theme->slug],
            collect($registry->styles('guest'))->pluck('slug')->all(),
        );
        $this->assertFileExists(public_path("platform/plugins/{$feature->slug}/css/feature.css"));
        $this->assertFileExists(public_path("platform/plugins/{$theme->slug}/css/theme.css"));
        $this->assertFileDoesNotExist(public_path("platform/plugins/{$disabled->slug}/css/disabled.css"));
        $this->assertSame(
            0775,
            fileperms(public_path("platform/plugins/{$theme->slug}/css")) & 0777,
        );
        $this->assertSame(
            0664,
            fileperms(public_path("platform/plugins/{$theme->slug}/css/theme.css")) & 0777,
        );

        File::delete(public_path("platform/plugins/{$theme->slug}/css/theme.css"));
        $registry->flush($theme->slug);
        app(PluginRuntimeGate::class)->flush($theme->slug);
        $registry->assets('frontend');
        $this->assertFileExists(public_path("platform/plugins/{$theme->slug}/css/theme.css"));

        $registry->synchronize($theme);
        $theme->refresh();
        $this->assertSame('1.0.0', $theme->version);
        $this->assertSame('theme', data_get($theme->manifest, 'type'));
        $this->assertSame(['css/theme.css'], data_get($theme->manifest, 'assets.frontend.styles'));
        $this->assertSame(
            ['theme-owned-page'],
            data_get($theme->manifest, 'frontend.owned_pages'),
        );
        $this->assertSame(realpath($theme->path), $theme->path);

        $sourceStylesheet = $theme->path.'/resources/assets/css/theme.css';
        File::put($sourceStylesheet, "/* atomically replaced */\n");
        chmod(public_path("platform/plugins/{$theme->slug}/css/theme.css"), 0444);
        $registry->synchronize($theme);

        $this->assertSame(
            "/* atomically replaced */\n",
            File::get(public_path("platform/plugins/{$theme->slug}/css/theme.css")),
        );
        $this->assertSame(
            0664,
            fileperms(public_path("platform/plugins/{$theme->slug}/css/theme.css")) & 0777,
        );

        $theme->update(['status' => Plugin::STATUS_DISABLED]);
        app(PluginRuntimeGate::class)->flush($theme->slug);
        $registry->flush($theme->slug);

        $this->assertNotContains(
            $theme->slug,
            collect($registry->styles('frontend'))->pluck('slug')->all(),
        );

        $this->assertTrue(app(AssetManager::class)->removePluginAssets($theme));
        $this->assertDirectoryDoesNotExist(public_path("platform/plugins/{$theme->slug}"));
    }

    private function plugin(
        string $slug,
        string $type,
        string $stylesheet,
        string $status = Plugin::STATUS_ACTIVE,
    ): Plugin {
        $root = storage_path('framework/testing/'.$slug);
        $public = public_path('platform/plugins/'.$slug);
        $this->temporaryPaths[] = $root;
        $this->temporaryPaths[] = $public;

        File::ensureDirectoryExists($root.'/resources/assets/css');
        File::put($root.'/resources/assets/css/'.$stylesheet, "/* {$slug} */\n");
        File::put($root.'/module.json', json_encode([
            'name' => $slug,
            'slug' => $slug,
            'version' => '1.0.0',
            'provider' => 'Tests\\Fixtures\\UnusedProvider',
            'type' => $type,
            'platform_version' => '>=2.0.0 <3.0.0',
            'assets' => [
                'source' => 'resources/assets',
                'frontend' => [
                    'styles' => ['css/'.$stylesheet],
                    'scripts' => [],
                ],
                'guest' => [
                    'styles' => ['css/'.$stylesheet],
                    'scripts' => [],
                ],
            ],
            'frontend' => [
                'owned_pages' => ['theme-owned-page'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Plugin::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'version' => '0.9.0',
            'status' => $status,
            'path' => $root,
            'provider' => 'Tests\\Fixtures\\UnusedProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);
    }
}
