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
        $this->assertFileExists(base_path('modules/blog/resources/assets/js/vvveb-blog-template.js'));
        $this->assertFileExists(base_path('modules/blog/resources/assets/js/blog-template-pagination.js'));
        $this->assertStringContainsString(
            'plugin.page-builder.editor.extensions',
            (string) file_get_contents(base_path('modules/blog/hooks.php')),
        );
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
