<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoModuleSettings;
use Tests\TestCase;

class SeoModuleLocaleSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_templates_are_saved_per_locale(): void
    {
        $settings = $this->app->make(SeoModuleSettings::class);

        $english = SeoModuleSettings::DEFAULT_TEMPLATES;
        $english['seo_title'] = 'EN {seo_title}';
        $english['image_seo_title'] = 'EN IMAGE {content_title}';
        $settings->saveModule('blog', $english, 'en');

        $arabic = SeoModuleSettings::DEFAULT_TEMPLATES;
        $arabic['seo_title'] = 'AR {seo_title}';
        $arabic['image_seo_title'] = 'AR IMAGE {content_title}';
        $settings->saveModule('blog', $arabic, 'ar');

        $this->assertSame('EN {seo_title}', $settings->forModule('blog', 'en')['seo_title']);
        $this->assertSame('AR {seo_title}', $settings->forModule('blog', 'ar')['seo_title']);
        $this->assertSame('EN IMAGE {content_title}', $settings->forModule('blog', 'en')['image_seo_title']);
        $this->assertSame('AR IMAGE {content_title}', $settings->forModule('blog', 'ar')['image_seo_title']);

        $stored = SeoSetting::query()
            ->where('module_slug', 'seo')
            ->where('group_key', 'modules')
            ->where('setting_key', 'blog')
            ->firstOrFail();

        $value = json_decode((string) $stored->value, true);

        $this->assertSame('EN {seo_title}', $value['en']['seo_title']);
        $this->assertSame('AR {seo_title}', $value['ar']['seo_title']);
    }

    public function test_legacy_flat_templates_are_shared_until_saved_per_language(): void
    {
        $settings = $this->app->make(SeoModuleSettings::class);

        SeoSetting::putValue(
            $this->app->make(\Modules\Seo\Services\SeoSiteResolver::class)->id(),
            'seo',
            'modules',
            'blog',
            ['seo_title' => 'Legacy {seo_title}'],
            'json',
        );

        $this->assertSame('Legacy {seo_title}', $settings->forModule('blog', 'en')['seo_title']);
        $this->assertSame('Legacy {seo_title}', $settings->forModule('blog', 'ar')['seo_title']);

        $arabic = SeoModuleSettings::DEFAULT_TEMPLATES;
        $arabic['seo_title'] = 'AR {seo_title}';
        $settings->saveModule('blog', $arabic, 'ar');

        $this->assertSame('Legacy {seo_title}', $settings->forModule('blog', 'en')['seo_title']);
        $this->assertSame('AR {seo_title}', $settings->forModule('blog', 'ar')['seo_title']);
    }
}
