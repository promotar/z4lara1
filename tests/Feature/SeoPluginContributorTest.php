<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoPluginContributor;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoPluginContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_open_graph_can_be_disabled_without_removing_seo_metadata(): void
    {
        $siteId = (new SeoSiteResolver())->id();
        SeoSetting::putValue($siteId, 'seo', 'social', 'og_enabled', false, 'boolean');

        $seo = new SeoData(new Robots());
        $seo->title = 'Course';
        $seo->description = 'Description';

        (new SeoPluginContributor(new SeoSiteResolver()))->contribute($seo, Request::create('/course'));

        $this->assertFalse($seo->ogEnabled);
        $this->assertStringNotContainsString('og:title', app(\App\Platform\Core\Seo\HeadRenderer::class)->render($seo));
        $this->assertStringContainsString('<title>Course', app(\App\Platform\Core\Seo\HeadRenderer::class)->render($seo));
    }

    public function test_it_applies_plugin_settings_at_priority_100(): void
    {
        $siteId = (new SeoSiteResolver())->id();
        SeoSetting::putValue($siteId, 'seo', 'general', 'site_name', 'ART INPA', 'string');
        SeoSetting::putValue($siteId, 'seo', 'general', 'title_suffix', ' | ART INPA', 'string');
        SeoSetting::putValue($siteId, 'seo', 'general', 'default_description', 'Plugin description', 'string');
        SeoSetting::putValue($siteId, 'seo', 'robots', 'index', false, 'boolean');
        SeoSetting::putValue($siteId, 'seo', 'robots', 'follow', true, 'boolean');
        SeoSetting::putValue($siteId, 'seo', 'verification', 'google', 'google-token', 'string');
        SeoSetting::putValue($siteId, 'seo', 'verification', 'bing', 'bing-token', 'string');

        $seo = new SeoData(new Robots());
        $seo->title = 'Course';

        (new SeoPluginContributor(new SeoSiteResolver()))->contribute($seo, Request::create('/course'));

        $this->assertSame('Course | ART INPA', $seo->title);
        $this->assertSame('Plugin description', $seo->description);
        $this->assertFalse($seo->robots->index);
        $this->assertTrue($seo->robots->follow);
        $this->assertSame('google-token', $seo->meta['google-site-verification']);
        $this->assertSame('bing-token', $seo->meta['msvalidate.01']);
        $this->assertNotEmpty($seo->schema);
        $this->assertSame('Organization', $seo->schema[0]['@type']);
    }
}
