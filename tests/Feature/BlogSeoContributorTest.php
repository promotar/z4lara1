<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Blog\Models\Post;
use Modules\Blog\Seo\BlogSeoContributor;
use Modules\Blog\Seo\BlogSitemapContributor;
use Tests\TestCase;

class BlogSeoContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Schema::create('blog_posts', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('public');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_schema_type')->nullable();
            $table->string('schema_type')->nullable();
            $table->string('seo_social_title')->nullable();
            $table->text('seo_social_description')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('featured_image', 2048)->nullable();
            $table->unsignedBigInteger('featured_image_id')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        \Illuminate\Support\Facades\Schema::create('blog_categories', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        \Illuminate\Support\Facades\Schema::create('blog_tags', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();
        });

        \Illuminate\Support\Facades\Schema::create('blog_category_post', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('category_id');
        });

        \Illuminate\Support\Facades\Schema::create('blog_post_tag', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('tag_id');
        });

        \Illuminate\Support\Facades\Schema::create('blog_media', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('url', 2048)->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\Schema::create('blog_post_translations', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('locale', 12);
            $table->string('slug');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Route::get('/blog', fn () => '')->name('blog.index');
        Route::get('/blog/category', fn () => '')->name('blog.categories');
        Route::get('/blog/category/{slug}', fn () => '')->name('blog.category');
        Route::get('/blog/tag/{slug}', fn () => '')->name('blog.tag');
        Route::get('/blog/search', fn () => '')->name('blog.search');
        Route::get('/blog/{slug}', fn () => '')->name('blog.show');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_post_page_uses_editorial_seo_fields_and_article_schema(): void
    {
        $post = Post::query()->create([
            'title' => 'Designing With Light',
            'slug' => 'designing-with-light',
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
            'excerpt' => 'A practical guide to designing with natural light.',
            'seo_title' => 'Designing With Light — ART INPA',
            'meta_description' => 'How to design interiors with natural light.',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $request = $this->requestFor('blog.show', '/blog/'.$post->slug, ['slug' => $post->slug]);
        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.test/blog/'.$post->slug;
        $seo->siteName = 'ART INPA';

        (new BlogSeoContributor())->contribute($seo, $request);

        $this->assertSame('Designing With Light — ART INPA', $seo->title);
        $this->assertSame('How to design interiors with natural light.', $seo->description);
        $this->assertTrue($seo->robots->index);
        $this->assertSame('article', $seo->ogType);

        $types = array_column($seo->schema, '@type');
        $this->assertContains('BlogPosting', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_search_page_is_noindex_and_sitemap_contains_published_posts(): void
    {
        $post = Post::query()->create([
            'title' => 'Published',
            'slug' => 'published',
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
            'excerpt' => 'Published excerpt.',
        ]);

        Post::query()->create([
            'title' => 'Draft',
            'slug' => 'draft',
            'status' => 'draft',
            'visibility' => 'public',
            'excerpt' => 'Draft excerpt.',
        ]);

        $request = $this->requestFor('blog.search', '/blog/search?q=light', []);
        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.test/blog/search?q=light';

        (new BlogSeoContributor())->contribute($seo, $request);

        $this->assertFalse($seo->robots->index);
        $this->assertStringContainsString('Search', (string) $seo->title);

        $entries = iterator_to_array((new BlogSitemapContributor())->sitemapEntries());
        $locations = array_column($entries, 'loc');

        $this->assertContains(route('blog.show', $post->slug), $locations);
        $this->assertNotContains(route('blog.show', 'draft'), $locations);
    }

    private function requestFor(string $name, string $uri, array $parameters): Request
    {
        $request = Request::create($uri);
        $route = new \Illuminate\Routing\Route('GET', $uri, []);
        $route->name($name);
        $route->parameters = $parameters;
        $request->setRouteResolver(static fn () => $route);

        return $request;
    }
}
