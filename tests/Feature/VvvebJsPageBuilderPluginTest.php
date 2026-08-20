<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\RequiredCorePluginSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\PageBuilder\VvvebDocument;
use Tests\TestCase;

class VvvebJsPageBuilderPluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_declares_the_single_vvvebjs_builder_runtime(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('modules/page-builder/module.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $plugin = new Plugin(['slug' => $manifest['slug'], 'manifest' => $manifest]);

        $this->assertSame('page-builder', $manifest['slug']);
        $this->assertSame('3.4.1', $manifest['version']);
        $this->assertSame('Modules\\PageBuilder\\VvvebJsServiceProvider', $manifest['provider']);
        $this->assertSame('src/VvvebJsServiceProvider.php', $manifest['provider_file']);
        $this->assertSame('filter', $manifest['hooks']['editor.extensions']['type']);
        $this->assertSame('filter', $manifest['hooks']['frontend.html']['type']);
        $this->assertTrue($plugin->isCore());
        $this->assertDirectoryDoesNotExist(base_path('modules/front-builder'));
        $this->assertFileDoesNotExist(base_path('modules/front-builder.zip'));
        $this->assertFileDoesNotExist(base_path('modules/page-builder/src/PageBuilderServiceProvider.php'));
        $this->assertFileExists(base_path('modules/page-builder/src/VvvebJsServiceProvider.php'));
        $this->assertFileExists(base_path('modules/page-builder/resources/vvvebjs/LICENSE'));
        $controller = (string) file_get_contents(
            base_path('modules/page-builder/src/Http/Controllers/Admin/PageController.php'),
        );
        $this->assertStringContainsString(
            'plugin.page-builder.editor.extensions',
            $controller,
        );
        $this->assertStringContainsString('PAGE BUILDER EXTENSION POINT', $controller);
    }

    public function test_vvvebjs_document_normalizes_untrusted_builder_markup(): void
    {
        require_once base_path('modules/page-builder/src/VvvebDocument.php');

        $document = (new VvvebDocument)->normalize(
            '<html><head><style>@import url("https://example.test/a.css"); .safe { color: red; }</style></head>'
            .'<body><script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Link</a></body></html>',
        );

        $this->assertStringContainsString('<base href="/page-builder-assets/demo/landing/">', $document);
        $this->assertStringContainsString('.safe { color: red; }', $document);
        $this->assertStringNotContainsString('<script', $document);
        $this->assertStringNotContainsString('@import', $document);
        $this->assertStringNotContainsString('onclick=', $document);
        $this->assertStringNotContainsString('javascript:', $document);
    }

    public function test_editor_uploads_through_the_platform_media_contract(): void
    {
        $integration = (string) file_get_contents(
            base_path('modules/page-builder/resources/vvvebjs/integration/js/vvveb-integration.js'),
        );

        $this->assertStringContainsString("body.append('media', file)", $integration);
        $this->assertStringNotContainsString("body.append('file', file)", $integration);
        $this->assertStringContainsString('media.media_url || media.url', $integration);
        $this->assertStringContainsString("new URL(String(media.media_url || media.url || ''), window.location.origin)", $integration);
        $this->assertStringContainsString("mediaUrl.pathname.startsWith('/storage/')", $integration);
        $this->assertStringContainsString('path: platformMediaPath.slice(1)', $integration);

        $routes = (string) file_get_contents(base_path('modules/page-builder/routes/web.php'));
        $controller = (string) file_get_contents(
            base_path('modules/page-builder/src/Http/Controllers/Admin/PageController.php'),
        );

        $this->assertStringContainsString("'/page-builder-assets/v6/{path}'", $routes);
        $this->assertStringContainsString("url('/page-builder-assets/v6')", $controller);
    }

    public function test_loaded_builder_elements_do_not_seed_legacy_media_urls(): void
    {
        $runtimeSources = [
            'modules/page-builder/resources/vvvebjs/libs/builder/components-html.js',
            'modules/page-builder/resources/vvvebjs/libs/builder/components-elements.js',
            'modules/page-builder/resources/vvvebjs/libs/builder/section.js',
            'modules/page-builder/resources/vvvebjs/demo/landing/sections/sections.js',
            'modules/page-builder/resources/vvvebjs/libs/builder/sections-bootstrap4.js',
        ];

        foreach ($runtimeSources as $source) {
            $contents = (string) file_get_contents(base_path($source));

            $this->assertDoesNotMatchRegularExpression(
                '~(?:src|poster)=["\'](?:\.\./\.\./|/)media/~',
                $contents,
                $source.' still seeds a legacy media URL.',
            );
        }
    }

    public function test_required_page_builder_is_reactivated_when_registry_state_is_tampered_with(): void
    {
        app(RequiredCorePluginSynchronizer::class)->synchronize();

        $plugin = Plugin::query()->where('slug', 'page-builder')->firstOrFail();
        $plugin->update(['status' => Plugin::STATUS_DISABLED]);

        DB::table('platform_plugin_registry_entries')->updateOrInsert(
            ['registry_type' => 'runtime', 'plugin_slug' => 'page-builder'],
            [
                'payload' => json_encode(['runtime_enabled' => false]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        app(RequiredCorePluginSynchronizer::class)->synchronize();

        $this->assertSame(Plugin::STATUS_ACTIVE, $plugin->fresh()->status);
        $this->assertTrue($plugin->fresh()->isCore());
        $this->assertTrue((bool) data_get(
            json_decode((string) DB::table('platform_plugin_registry_entries')
                ->where('registry_type', 'runtime')
                ->where('plugin_slug', 'page-builder')
                ->value('payload'), true),
            'runtime_enabled',
        ));
    }
}
