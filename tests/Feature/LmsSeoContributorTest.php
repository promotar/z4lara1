<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Lms\Models\Course;
use Modules\Lms\Seo\LmsSeoContributor;
use Tests\TestCase;

class LmsSeoContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'modules/lms/database/migrations',
            '--force' => true,
        ]);

        Route::get('/courses', fn () => '')->name('lms.front.courses');
        Route::get('/courses/{course}', fn () => '')->name('lms.front.courses.show');
        Route::get('/instructors/{instructor}', fn () => '')->name('lms.front.instructors.show');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_course_page_gets_course_metadata_offer_and_faq_schema(): void
    {
        $course = Course::query()->create([
            'title' => 'Interior Design Principles',
            'slug' => 'interior-design-principles',
            'status' => 'published',
            'visibility' => 'public',
            'short_description' => 'Learn the foundations of interior design.',
            'description' => 'A complete practical course.',
            'regular_price' => 120,
            'currency' => 'JOD',
            'pricing_type' => 'paid',
            'training_mode' => 'online',
            'faqs' => [
                ['question' => 'Who is this for?', 'answer' => 'Beginners and professionals.'],
            ],
        ]);

        $request = $this->requestFor('lms.front.courses.show', '/courses/'.$course->slug, [
            'course' => $course,
        ]);

        $seo = new SeoData(new Robots());
        $seo->canonical = 'https://example.test/courses/'.$course->slug;
        $seo->siteName = 'ART INPA';

        (new LmsSeoContributor())->contribute($seo, $request);

        $this->assertSame('Interior Design Principles', $seo->title);
        $this->assertSame('Learn the foundations of interior design.', $seo->description);
        $this->assertTrue($seo->robots->index);
        $this->assertNotEmpty($seo->schema);

        $types = array_column($seo->schema, '@type');
        $this->assertContains('Course', $types);
        $this->assertContains('Offer', $types);
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);
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
