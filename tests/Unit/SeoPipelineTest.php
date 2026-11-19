<?php

namespace Tests\Unit;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use App\Platform\Core\Seo\SeoManager;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class SeoPipelineTest extends TestCase
{
    public function test_core_defaults_are_used_when_no_contributor_matches(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('values')->once()->andReturn([
            'general.site_title' => 'ART INPA',
            'general.tagline' => 'Art, culture, education',
            'seo.seo_title' => 'ART INPA SEO',
            'seo.seo_description' => 'Default description',
            'seo.robots_index' => true,
            'seo.robots_follow' => true,
            'seo.open_graph_title' => 'OG title',
            'seo.open_graph_description' => 'OG description',
        ]);

        $registry = new SeoRegistry();
        $manager = new SeoManager($settings, $registry);
        $seo = $manager->forRequest(Request::create('/courses'));

        $this->assertSame('ART INPA SEO', $seo->title);
        $this->assertSame('Default description', $seo->description);
        $this->assertSame('OG title', $seo->ogTitle);
        $this->assertTrue($seo->robots->index);
        $this->assertStringStartsWith('http://localhost/courses', $seo->canonical);
    }

    public function test_module_contributor_can_override_core_values(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('values')->once()->andReturn([
            'general.site_title' => 'ART INPA',
            'seo.seo_title' => 'ART INPA SEO',
            'seo.robots_index' => true,
            'seo.robots_follow' => true,
        ]);

        $registry = new SeoRegistry();
        $registry->add(new class implements SeoContributor {
            public function module(): string
            {
                return 'test-module';
            }

            public function supports(Request $request): bool
            {
                return $request->is('courses*');
            }

            public function contribute(SeoData $seo, Request $request): void
            {
                $seo->title = 'Course title';
                $seo->description = 'Course description';
                $seo->robots = $seo->robots->merge(new Robots(index: false));
                $seo->schema[] = ['@context' => 'https://schema.org', '@type' => 'Course'];
            }
        }, priority: 10);

        $manager = new SeoManager($settings, $registry);
        $seo = $manager->forRequest(Request::create('/courses/demo'));

        $this->assertSame('Course title', $seo->title);
        $this->assertSame('Course description', $seo->description);
        $this->assertFalse($seo->robots->index);
        $this->assertTrue($seo->robots->follow);
        $this->assertCount(1, $seo->schema);
    }

    public function test_higher_priority_contributor_wins(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('values')->once()->andReturn([
            'general.site_title' => 'ART INPA',
            'seo.seo_title' => 'ART INPA SEO',
        ]);

        $registry = new SeoRegistry();
        $registry->add(new class implements SeoContributor {
            public function module(): string { return 'module'; }
            public function supports(Request $request): bool { return true; }
            public function contribute(SeoData $seo, Request $request): void { $seo->title = 'Module title'; }
        }, priority: 10);

        $registry->add(new class implements SeoContributor {
            public function module(): string { return 'seo-plugin'; }
            public function supports(Request $request): bool { return true; }
            public function contribute(SeoData $seo, Request $request): void { $seo->title = 'Plugin title'; }
        }, priority: 100);

        $manager = new SeoManager($settings, $registry);
        $seo = $manager->forRequest(Request::create('/'));

        $this->assertSame('Plugin title', $seo->title);
    }
}
