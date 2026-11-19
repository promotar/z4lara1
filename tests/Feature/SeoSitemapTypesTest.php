<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Seo\SitemapContributor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapGenerator;
use Tests\TestCase;

class SeoSitemapTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_11_08_000001_create_seo_tables.php',
            '2026_11_08_000002_create_seo_sitemap_tables.php',
        ] as $file) {
            $migration = require base_path('modules/seo/database/migrations/'.$file);
            $migration->up();
        }

        if (! Schema::hasTable('platform_pages')) {
            Schema::create('platform_pages', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('content_type')->default('page');
                $table->longText('content')->nullable();
                $table->string('status')->default('draft');
                $table->timestamps();
            });
        }

        Plugin::query()->create([
            'name' => 'Page Builder',
            'slug' => 'page-builder',
            'version' => '1.0.0',
            'status' => Plugin::STATUS_ACTIVE,
            'path' => base_path('modules/page-builder'),
            'provider' => 'Modules\\PageBuilder\\PageBuilderServiceProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);

        app(SeoRegistry::class)->addSitemap(new class implements SitemapContributor {
            public function module(): string
            {
                return 'test';
            }

            public function sitemapEntries(): iterable
            {
                yield ['loc' => url('/pages/hidden')];
                yield ['loc' => url('/sitemap-generic')];
            }
        });
    }

    public function test_disabled_content_type_is_excluded_from_the_generated_sitemap(): void
    {
        $siteId = (new SeoSiteResolver())->id();
        SeoSetting::putValue($siteId, 'seo', 'sitemap', 'types', ['page' => false], 'json');

        $run = app(SitemapGenerator::class)->generate($siteId);
        $xml = SeoSitemapChunk::query()->where('run_id', $run->id)->pluck('content')->implode('');

        $this->assertStringContainsString(url('/sitemap-generic'), $xml);
        $this->assertStringNotContainsString(url('/pages/hidden'), $xml);
    }
}
