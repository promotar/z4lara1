<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Seo\Services\ContentSeoContributor;
use Modules\Seo\Services\ContentSeoService;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapUrlPolicy;
use Tests\TestCase;

class ContentSeoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();

        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table): void {
                $table->id();
                $table->string('title')->default('');
                $table->string('slug')->nullable();
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable();
                $table->string('status')->default('published');
                $table->string('visibility')->default('public');
                $table->string('seo_title')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('focus_keyword')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('robots_index')->nullable();
                $table->boolean('robots_follow')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('plugins')) {
            Plugin::query()->create([
                'name' => 'Blog',
                'slug' => 'blog',
                'version' => '1.0.0',
                'status' => Plugin::STATUS_ACTIVE,
                'path' => base_path('modules/blog'),
                'provider' => 'Modules\\Blog\\BlogServiceProvider',
                'dependencies' => [],
                'manifest' => [],
            ]);
        }

        DB::table('blog_posts')->insert([
            'id' => 1,
            'title' => 'Content title',
            'slug' => 'content-title',
            'excerpt' => 'Content excerpt',
            'content' => 'Content body',
            'status' => 'published',
            'visibility' => 'public',
            'updated_at' => now(),
        ]);
    }

    public function test_custom_values_are_stored_without_generated_defaults(): void
    {
        $request = Request::create('/', 'POST', [
            'seo_title' => 'Custom SEO title',
            'seo_description' => 'Custom SEO description.',
            'focus_keyword' => 'custom keyword',
            'canonical_url' => 'https://example.com/custom',
            'seo_robots' => 'noindex',
        ]);

        $service = app(ContentSeoService::class);
        $service->save($request, 'blog_post', 1);

        $this->assertDatabaseHas('seo_meta', [
            'seoable_type' => 'blog_post',
            'seoable_id' => 1,
            'title' => 'Custom SEO title',
            'robots' => 'noindex',
        ]);
        $this->assertDatabaseHas('blog_posts', [
            'id' => 1,
            'seo_title' => 'Custom SEO title',
            'robots_index' => 0,
        ]);
        $this->assertSame('Custom SEO title', $service->custom('blog_post', 1)['title']);

        $empty = Request::create('/', 'POST', [
            'seo_title' => '',
            'seo_description' => '',
            'focus_keyword' => '',
            'canonical_url' => '',
            'seo_robots' => '',
        ]);
        $service->save($empty, 'blog_post', 1);

        $this->assertDatabaseMissing('seo_meta', ['seoable_type' => 'blog_post', 'seoable_id' => 1]);
        $this->assertDatabaseHas('blog_posts', [
            'id' => 1,
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
            'robots_index' => null,
        ]);
    }

    public function test_overrides_are_stored_per_language(): void
    {
        $service = app(ContentSeoService::class);

        $service->save(Request::create('/', 'POST', [
            'seo_title' => 'English SEO title',
            'seo_description' => 'English description.',
            'seo_robots' => 'index',
        ]), 'blog_post', 1, 'en');

        $service->save(Request::create('/', 'POST', [
            'seo_title' => 'عنوان SEO العربي',
            'seo_description' => 'وصف SEO العربي.',
            'seo_robots' => 'noindex',
        ]), 'blog_post', 1, 'ar');

        $this->assertSame('English SEO title', $service->custom('blog_post', 1, 'en')['title']);
        $this->assertSame('عنوان SEO العربي', $service->custom('blog_post', 1, 'ar')['title']);
        $this->assertSame('', $service->custom('blog_post', 1, 'ar')['canonical']);
        $this->assertTrue($service->isNoindex('blog_post', 1, 'ar'));
        $this->assertFalse($service->isNoindex('blog_post', 1, 'en'));

        $service->save(Request::create('/', 'POST', [
            'seo_title' => '',
            'seo_description' => '',
            'seo_robots' => '',
        ]), 'blog_post', 1, 'ar');

        $this->assertSame('English SEO title', $service->custom('blog_post', 1, 'en')['title']);
        $this->assertSame('', $service->custom('blog_post', 1, 'ar')['title']);
        $this->assertDatabaseCount('seo_meta', 1);
    }

    public function test_content_contributor_applies_overrides_after_global_defaults(): void
    {
        $request = Request::create('/', 'POST', [
            'seo_title' => 'Override title',
            'seo_description' => 'Override description.',
            'canonical_url' => 'https://example.com/override',
            'seo_robots' => 'noindex',
        ]);

        app(ContentSeoService::class)->save($request, 'blog_post', 1);

        $seo = new SeoData();
        $seo->title = 'Generated title';
        $seo->description = 'Generated description';
        $seo->ogTitle = 'Generated OG title';
        $seo->ogDescription = 'Generated OG description';
        $seo->canonical = 'https://example.com/generated';
        $seo->extra['blog_post_id'] = 1;

        app(ContentSeoContributor::class)->contribute($seo, $request);

        $this->assertSame('Override title', $seo->title);
        $this->assertSame('Override description.', $seo->description);
        $this->assertSame('Override title', $seo->ogTitle);
        $this->assertSame('Override description.', $seo->ogDescription);
        $this->assertSame('https://example.com/override', $seo->canonical);
        $this->assertFalse($seo->robots->index);
    }

    public function test_sitemap_policy_excludes_custom_noindex_content(): void
    {
        $request = Request::create('/', 'POST', [
            'seo_title' => 'Noindex title',
            'seo_robots' => 'noindex',
        ]);

        app(ContentSeoService::class)->save($request, 'blog_post', 1);

        $siteId = app(SeoSiteResolver::class)->id();
        $url = url('/blog/content-title');

        $this->assertFalse(app(SitemapUrlPolicy::class)->allows($url, $siteId));
    }
}
