<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Certificates\Seo\CertificateSeoContributor;
use Tests\TestCase;

class CertificateSeoContributorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('certificates', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('name_normalized', 191)->nullable();
            $table->dateTime('date');
            $table->string('slug', 190)->unique();
            $table->string('image');
            $table->softDeletes();
        });

        Route::get('/certificate-search', fn () => '')->name('certificates.search');
        Route::get('/{certificateSlug}', fn () => '')->name('certificates.show');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_certificate_verification_pages_are_noindex_nofollow(): void
    {
        \Modules\Certificates\Models\Certificate::query()->create([
            'name' => 'Jane Artist',
            'date' => now(),
            'slug' => 'CERT-001',
            'image' => 'certificates/images/test.jpg',
        ]);

        $request = $this->requestFor('certificates.show', '/CERT-001', ['certificateSlug' => 'CERT-001']);
        $seo = new SeoData(new Robots());

        (new CertificateSeoContributor())->contribute($seo, $request);

        $this->assertFalse($seo->robots->index);
        $this->assertFalse($seo->robots->follow);
        $this->assertContains('noarchive', $seo->robots->directives);
        $this->assertSame('Jane Artist', $seo->title);
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
