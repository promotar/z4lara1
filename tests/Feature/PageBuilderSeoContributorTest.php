<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\PageBuilder\Seo\PageBuilderSeoContributor;
use Modules\PageBuilder\Seo\PageBuilderSitemapContributor;
use Tests\TestCase;

class PageBuilderSeoContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/pages/{slug}', fn () => '')->name('pages.show');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_published_page_gets_webpage_schema_and_sitemap_entry(): void
    {
        DB::table('platform_pages')->insert([
            'title' => 'About ART INPA',
            'slug' => 'about',
            'content_type' => 'page',
            'status' => 'published',
            'seo_title' => 'About ART INPA',
            'meta_description' => 'Learn about the ART INPA platform.',
            'content' => '<p>About the platform.</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = $this->requestFor('pages.show', '/pages/about', ['slug' => 'about']);
        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.test/pages/about';
        $seo->siteName = 'ART INPA';

        (new PageBuilderSeoContributor())->contribute($seo, $request);

        $this->assertSame('About ART INPA', $seo->title);
        $this->assertSame('Learn about the ART INPA platform.', $seo->description);
        $this->assertContains('WebPage', array_column($seo->schema, '@type'));
        $this->assertContains('BreadcrumbList', array_column($seo->schema, '@type'));

        $entries = iterator_to_array((new PageBuilderSitemapContributor())->sitemapEntries());
        $this->assertContains(route('pages.show', 'about'), array_column($entries, 'loc'));
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
