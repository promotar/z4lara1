<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SitemapPing;
use Tests\TestCase;

class SitemapPingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_it_pings_google_and_bing_and_records_the_result(): void
    {
        Http::fake([
            'https://www.google.com/*' => Http::response('', 200),
            'https://www.bing.com/*' => Http::response('', 202),
        ]);

        $result = (new SitemapPing())->ping(1);

        $this->assertSame(200, $result['google']);
        $this->assertSame(202, $result['bing']);
        $this->assertStringEndsWith('/sitemap.xml', $result['sitemap']);

        $last = SeoSetting::valueFor(1, 'seo', 'sitemap', 'last_ping', '', []);
        $this->assertIsArray($last);
        $this->assertSame(200, $last['results']['google']);
    }
}
