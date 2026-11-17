<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Seo\Models\SeoSchemaTemplate;
use Modules\Seo\Services\SchemaTemplateContributor;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SchemaTemplateContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_11_08_000001_create_seo_tables.php',
            '2026_11_08_000003_create_seo_schema_templates.php',
        ] as $migration) {
            $instance = require base_path('modules/seo/database/migrations/'.$migration);
            $instance->up();
        }
    }

    public function test_active_template_is_appended_for_matching_route(): void
    {
        $siteId = (new SeoSiteResolver())->id();
        SeoSchemaTemplate::query()->create([
            'site_id' => $siteId,
            'name' => 'Blog Article',
            'key' => 'blog-article',
            'type' => 'WebPage',
            'route_pattern' => 'blog.show',
            'schema_json' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Blog Article',
                'url' => 'https://example.test/blog/article',
            ]),
            'is_active' => true,
            'priority' => 10,
        ]);

        $request = Request::create('/blog/article');
        $route = new \Illuminate\Routing\Route('GET', '/blog/{slug}', []);
        $route->name('blog.show');
        $request->setRouteResolver(static fn () => $route);

        $seo = new SeoData(new Robots());
        (new SchemaTemplateContributor(new SeoSiteResolver()))->contribute($seo, $request);

        $this->assertCount(1, $seo->schema);
        $this->assertSame('WebPage', $seo->schema[0]['@type']);
    }

    public function test_inactive_or_non_matching_template_is_ignored(): void
    {
        $siteId = (new SeoSiteResolver())->id();
        SeoSchemaTemplate::query()->create([
            'site_id' => $siteId,
            'name' => 'Ignored',
            'key' => 'ignored',
            'route_pattern' => 'store.*',
            'schema_json' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Ignored',
                'url' => 'https://example.test/store',
            ]),
            'is_active' => true,
            'priority' => 10,
        ]);

        $request = Request::create('/blog/article');
        $route = new \Illuminate\Routing\Route('GET', '/blog/{slug}', []);
        $route->name('blog.show');
        $request->setRouteResolver(static fn () => $route);

        $seo = new SeoData(new Robots());
        (new SchemaTemplateContributor(new SeoSiteResolver()))->contribute($seo, $request);

        $this->assertSame([], $seo->schema);
    }
}
