<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\DefaultSitemapProvider;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Seo\SitemapContributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SeoSitemapProvider;
use Modules\Seo\Services\SitemapGenerator;
use Modules\Seo\Services\SitemapValidator;
use Tests\TestCase;

class SitemapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_11_08_000001_create_seo_tables.php',
            '2026_11_08_000002_create_seo_sitemap_tables.php',
        ] as $migration) {
            $instance = require base_path('modules/seo/database/migrations/'.$migration);
            $instance->up();
        }

        config()->set('seo.sitemap.max_urls', 2);
        config()->set('seo.sitemap.max_bytes', 52428800);

        app(SeoRegistry::class)->addSitemap(new class implements SitemapContributor {
            public function module(): string
            {
                return 'test';
            }

            public function sitemapEntries(): iterable
            {
                for ($i = 1; $i <= 5; $i++) {
                    yield [
                        'loc' => url('/sitemap-test-'.$i),
                        'changefreq' => 'weekly',
                        'priority' => 0.5,
                    ];
                }
            }
        }, 10);
    }

    public function test_sitemap_is_split_into_chunks_and_served_through_index_and_chunks(): void
    {
        $generator = app(SitemapGenerator::class);
        $run = $generator->generate();

        $this->assertSame('completed', $run->status);
        $this->assertSame(6, $run->urls_count);
        $this->assertSame(3, $run->chunks_count);
        $this->assertSame(3, SeoSitemapChunk::query()->where('run_id', $run->id)->count());

        $provider = new SeoSitemapProvider(
            new DefaultSitemapProvider(app(SeoRegistry::class)),
            new SeoSiteResolver(),
            $generator,
        );

        $index = $provider->sitemapIndex();
        $this->assertCount(3, $index);
        $this->assertStringContainsString('/sitemap/0.xml', $index[0]['loc']);

        $chunk = $provider->sitemapChunk(0);
        $this->assertIsString($chunk);
        $this->assertStringContainsString('<urlset', $chunk);
        $this->assertStringContainsString(url('/sitemap-test-1'), $chunk);
    }

    public function test_prefix_mode_adds_prefixed_urls_and_absolute_alternates(): void
    {
        config()->set('seo.locale.mode', 'prefix');
        config()->set('seo.canonical.host', 'http://example.test');

        \Illuminate\Support\Facades\DB::table('platform_settings')->updateOrInsert(
            ['group_key' => 'general', 'setting_key' => 'site_language'],
            [
                'label' => 'Site Language',
                'type' => 'select',
                'value' => json_encode('ar'),
                'default_value' => json_encode('ar'),
                'options' => json_encode(['en' => 'English', 'ar' => 'Arabic']),
                'sort_order' => 100,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $locale = new \App\Platform\Core\Localization\PlatformLocale(
            app(\App\Platform\Core\Services\SettingsRepository::class),
        );

        $generator = new SitemapGenerator(
            app(\App\Platform\Core\Seo\SitemapXmlRenderer::class),
            app(SeoRegistry::class),
            app(SeoSiteResolver::class),
            app(\Modules\Seo\Services\SitemapUrlPolicy::class),
            $locale,
            new \App\Platform\Core\Seo\PrefixLocaleUrlStrategy($locale),
        );

        $run = $generator->generate();
        $xml = SeoSitemapChunk::query()
            ->where('run_id', $run->id)
            ->orderBy('chunk_index')
            ->pluck('content')
            ->implode('');

        $this->assertStringContainsString('http://example.test/en/sitemap-test-1', $xml);
        $this->assertStringContainsString(
            'hreflang="ar" href="http://example.test/sitemap-test-1"',
            $xml,
        );
        $this->assertStringContainsString(
            'hreflang="en" href="http://example.test/en/sitemap-test-1"',
            $xml,
        );
        $this->assertStringContainsString(
            'hreflang="x-default" href="http://example.test/sitemap-test-1"',
            $xml,
        );
    }

    public function test_validator_accepts_generated_chunks_and_flags_broken_xml(): void
    {
        $generator = app(SitemapGenerator::class);
        $run = $generator->generate();
        $validator = app(SitemapValidator::class);

        $result = $validator->validate();
        $this->assertSame(0, $result['errors']);
        $this->assertSame(0, $result['warnings']);
        $this->assertSame(6, $result['urls']);

        SeoSitemapChunk::query()->where('run_id', $run->id)->where('chunk_index', 0)->update([
            'content' => '<broken>',
        ]);

        $result = $validator->validate();
        $this->assertGreaterThan(0, $result['errors']);
    }
}
