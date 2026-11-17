<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Seo\Http\Controllers\Admin\SettingsController;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoDefaults;
use Modules\Seo\Services\SeoRedirectResolver;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoRestructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_settings_update_saves_all_four_groups(): void
    {
        $sites = new SeoSiteResolver();
        $request = Request::create('/admin/plugins/seo/settings', 'PATCH', [
            'site_name' => 'ART INPA',
            'default_description' => 'Default description',
            'default_og_image' => '/storage/seo/default.jpg',
            'title_template' => '{title} | {site_name}',
            'description_template' => '{excerpt}',
            'robots_behavior' => 'index_follow',
            'canonical_behavior' => 'auto',
            'og_enabled' => '1',
            'social_title' => 'Social title',
            'social_description' => 'Social description',
            'social_image' => '/storage/seo/social.jpg',
            'organization_name' => 'ART INPA',
            'organization_logo' => '/storage/seo/logo.png',
            'organization_url' => 'https://artinpa.test',
            'organization_email' => 'hello@artinpa.test',
            'organization_phone' => '+962 7 0000 0000',
        ]);

        (new SettingsController())->update($request, $sites);

        $defaults = app(SeoDefaults::class)->forSite($sites->id());

        $this->assertSame('ART INPA', $defaults['site_name']);
        $this->assertSame('Default description', $defaults['default_description']);
        $this->assertSame('{title} | {site_name}', $defaults['title_template']);
        $this->assertSame('index_follow', $defaults['robots_behavior']);
        $this->assertTrue($defaults['og_enabled']);
        $this->assertSame('Social title', $defaults['social_title']);
        $this->assertSame('ART INPA', $defaults['organization_name']);
        $this->assertSame('https://artinpa.test', $defaults['organization_url']);
    }

    public function test_inactive_redirect_is_not_resolved(): void
    {
        $sites = new SeoSiteResolver();
        SeoRedirect::query()->create([
            'site_id' => $sites->id(),
            'from_path' => '/disabled',
            'to_path' => '/target',
            'status_code' => 301,
            'is_active' => false,
        ]);
        SeoRedirectResolver::flush($sites->id());

        $this->assertSame([], (new SeoRedirectResolver())->redirects($sites->id()));
    }
}
