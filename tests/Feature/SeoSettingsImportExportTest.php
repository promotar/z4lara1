<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Seo\Http\Controllers\Admin\SettingsController;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoSettingsImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_export_and_import_round_trip(): void
    {
        $sites = new SeoSiteResolver();
        SeoSetting::putValue($sites->id(), 'seo', 'general', 'title_suffix', ' | TEST', 'string');
        SeoSetting::putValue($sites->id(), 'seo', 'robots', 'index', false, 'boolean');

        $export = (new SettingsController())->export($sites)->getData(true);
        $this->assertSame('artinpa-seo-settings', $export['format']);
        $this->assertNotEmpty($export['settings']);

        $request = Request::create('/admin/plugins/seo/settings/import', 'POST', [
            'payload' => json_encode($export),
        ]);

        (new SettingsController())->import($request, $sites);

        $this->assertSame(' | TEST', SeoSetting::valueFor($sites->id(), 'seo', 'general', 'title_suffix', '', ''));
        $this->assertFalse((bool) SeoSetting::valueFor($sites->id(), 'seo', 'robots', 'index', '', true));
    }
}
