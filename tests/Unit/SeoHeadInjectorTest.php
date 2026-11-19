<?php

namespace Tests\Unit;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use App\Platform\Core\Seo\SeoHeadInjector;
use Illuminate\Http\Response;
use Tests\TestCase;

class SeoHeadInjectorTest extends TestCase
{
    public function test_it_adds_missing_canonical_alternates_and_robots(): void
    {
        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.com/courses?page=2';
        $seo->alternates = [
            'en' => 'https://example.com/courses?page=2',
            'ar' => 'https://example.com/courses?page=2&lang=ar',
            'x-default' => 'https://example.com/courses?page=2',
        ];

        $response = new Response('<html><head><title>Legacy</title></head><body></body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        (new SeoHeadInjector())->inject($response, $seo);
        $html = $response->getContent();

        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('hreflang="ar"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
        $this->assertStringContainsString('name="robots"', $html);
    }

    public function test_it_does_not_duplicate_module_declared_alternates(): void
    {
        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.com/blog/post';
        $seo->alternates = [
            'en' => 'https://example.com/blog/post',
            'ar' => 'https://example.com/blog/post?lang=ar',
            'x-default' => 'https://example.com/blog/post',
        ];

        $html = '<html><head><title>Post</title>'
            .'<link rel="canonical" href="https://example.com/blog/post">'
            .'<link rel="alternate" hreflang="en" href="https://example.com/blog/post">'
            .'</head><body></body></html>';

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        (new SeoHeadInjector())->inject($response, $seo);

        $this->assertSame(1, substr_count($response->getContent(), 'rel="canonical"'));
        $this->assertSame(1, substr_count($response->getContent(), 'hreflang="en"'));
        $this->assertSame(1, substr_count($response->getContent(), 'hreflang="ar"'));
        $this->assertSame(1, substr_count($response->getContent(), 'hreflang="x-default"'));
    }

    public function test_it_adds_missing_social_tags_and_schema_for_legacy_pages(): void
    {
        $seo = new SeoData(new Robots());
        $seo->title = 'Course';
        $seo->description = 'Course description';
        $seo->canonical = 'https://example.com/courses/course';
        $seo->ogTitle = 'Course';
        $seo->ogDescription = 'Course description';
        $seo->twitterCard = 'summary';
        $seo->twitterTitle = 'Course';
        $seo->twitterDescription = 'Course description';
        $seo->schema = [[
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => 'Course',
        ]];

        $response = new Response('<html><head><title>Legacy</title></head><body></body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        (new SeoHeadInjector())->inject($response, $seo);
        $html = $response->getContent();

        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:url"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Course"', $html);
    }

    public function test_it_replaces_legacy_title_and_description_for_module_owned_pages(): void
    {
        $seo = new SeoData(new Robots());
        $seo->title = 'Course title';
        $seo->description = 'Course description';
        $seo->extra['replace_title'] = true;

        $html = '<html><head><title>Legacy title</title><meta name="description" content="Legacy description"></head><body></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        (new SeoHeadInjector())->inject($response, $seo);
        $content = $response->getContent();

        $this->assertStringContainsString('<title>Course title</title>', $content);
        $this->assertStringNotContainsString('Legacy title', $content);
        $this->assertStringContainsString('content="Course description"', $content);
        $this->assertStringNotContainsString('Legacy description', $content);
        $this->assertSame(1, substr_count($content, 'name="description"'));
    }

    public function test_it_does_not_duplicate_social_tags_or_schema(): void
    {
        $seo = new SeoData(new Robots());
        $seo->title = 'Post';
        $seo->canonical = 'https://example.com/blog/post';
        $seo->ogTitle = 'Post';
        $seo->schema = [['@type' => 'BlogPosting', 'name' => 'Post']];

        $html = '<html><head><title>Post</title>'
            .'<meta property="og:title" content="Post">'
            .'<meta property="og:url" content="https://example.com/blog/post">'
            .'<script type="application/ld+json">{"@type":"BlogPosting"}</script>'
            .'</head><body></body></html>';

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        (new SeoHeadInjector())->inject($response, $seo);

        $this->assertSame(1, substr_count($response->getContent(), 'property="og:title"'));
        $this->assertSame(1, substr_count($response->getContent(), 'property="og:url"'));
        $this->assertSame(1, substr_count($response->getContent(), 'application/ld+json'));
    }

    public function test_it_replaces_legacy_social_titles_and_descriptions(): void
    {
        $seo = new SeoData(new Robots());
        $seo->title = 'Final title';
        $seo->description = 'Final description';
        $seo->ogTitle = 'Final OG title';
        $seo->ogDescription = 'Final OG description';
        $seo->twitterTitle = 'Final Twitter title';
        $seo->twitterDescription = 'Final Twitter description';

        $html = '<html><head>'
            .'<meta property="og:title" content="Legacy OG title">'
            .'<meta property="og:description" content="Legacy OG description">'
            .'<meta name="twitter:title" content="Legacy Twitter title">'
            .'<meta name="twitter:description" content="Legacy Twitter description">'
            .'</head><body></body></html>';

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        (new SeoHeadInjector())->inject($response, $seo);
        $content = $response->getContent();

        $this->assertStringContainsString('property="og:title" content="Final OG title"', $content);
        $this->assertStringContainsString('property="og:description" content="Final OG description"', $content);
        $this->assertStringContainsString('name="twitter:title" content="Final Twitter title"', $content);
        $this->assertStringContainsString('name="twitter:description" content="Final Twitter description"', $content);
        $this->assertStringNotContainsString('Legacy OG title', $content);
        $this->assertStringNotContainsString('Legacy OG description', $content);
        $this->assertSame(1, substr_count($content, 'property="og:title"'));
        $this->assertSame(1, substr_count($content, 'name="twitter:title"'));
    }

    public function test_it_applies_image_seo_to_all_content_images(): void
    {
        $seo = new SeoData(new Robots());
        $seo->image = 'https://example.com/cover.jpg';
        $seo->imageAlt = 'Image title';
        $seo->extra['image_seo_title'] = 'Image title';
        $seo->extra['image_seo_description'] = 'Image description';

        $html = '<html><head><title>Post</title></head><body>'
            .'<main>'
            .'<img src="/one.jpg">'
            .'<img src="/two.jpg" alt="Existing alt">'
            .'</main>'
            .'<img src="/outside.jpg">'
            .'</body></html>';

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        (new SeoHeadInjector())->inject($response, $seo);
        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'alt="Image title"'));
        $this->assertSame(1, substr_count($content, 'alt="Existing alt"'));
        $this->assertSame(2, substr_count($content, 'title="Image description"'));
        $this->assertStringNotContainsString('outside.jpg" alt=', $content);
        $this->assertStringContainsString('property="og:image:alt" content="Image title"', $content);
        $this->assertStringContainsString('property="og:image:description" content="Image description"', $content);
    }

    public function test_it_skips_image_seo_when_templates_are_empty(): void
    {
        $seo = new SeoData(new Robots());
        $seo->image = 'https://example.com/cover.jpg';

        $html = '<html><head><title>Post</title></head><body><main><img src="/one.jpg"></main></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        (new SeoHeadInjector())->inject($response, $seo);

        $this->assertStringContainsString('<img src="/one.jpg">', $response->getContent());
        $this->assertStringNotContainsString('property="og:image:alt"', $response->getContent());
        $this->assertStringNotContainsString('property="og:image:description"', $response->getContent());
    }

    public function test_it_strips_canonical_and_alternates_on_error(): void
    {
        $seo = new SeoData(new Robots(index: false, follow: true));
        $seo->canonical = 'https://example.com/missing';
        $seo->alternates = ['x-default' => 'https://example.com/missing'];

        $html = '<html><head>'
            .'<link rel="canonical" href="https://example.com/missing">'
            .'<link rel="alternate" hreflang="x-default" href="https://example.com/missing">'
            .'</head><body>404</body></html>';

        $response = new Response($html, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        (new SeoHeadInjector())->inject($response, $seo);

        $this->assertStringNotContainsString('rel="canonical"', $response->getContent());
        $this->assertStringNotContainsString('hreflang=', $response->getContent());
        $this->assertStringContainsString('noindex', $response->getContent());
        $this->assertSame('noindex, follow', $response->headers->get('X-Robots-Tag'));
    }
}
