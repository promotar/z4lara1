<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Seo\Http\Middleware\SeoRedirectMiddleware;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\SeoRedirectResolver;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoRedirectMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
        Cache::flush();
    }

    public function test_exact_redirect_preserves_query_and_records_hit(): void
    {
        $redirect = $this->redirect('/old', '/new');

        $response = $this->middleware()->handle(
            Request::create('/old?utm_source=test'),
            fn () => response('next'),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/new?utm_source=test', (string) $response->headers->get('Location'));
        $this->assertSame(1, (int) $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_query_can_be_ignored(): void
    {
        $this->redirect('/old', '/new', ['query_behavior' => 'ignore']);

        $response = $this->middleware()->handle(
            Request::create('/old?utm_source=test'),
            fn () => response('next'),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/new', (string) $response->headers->get('Location'));
    }

    public function test_gone_redirect_returns_410(): void
    {
        $this->redirect('/gone', '', ['status_code' => 410]);

        $response = $this->middleware()->handle(
            Request::create('/gone'),
            fn () => response('next'),
        );

        $this->assertSame(410, $response->getStatusCode());
    }

    public function test_external_redirect_requires_allowlist(): void
    {
        config()->set('seo.redirects.external_allowlist', []);
        $this->redirect('/external', 'https://example.com/landing', ['is_external' => true]);

        $response = $this->middleware()->handle(
            Request::create('/external'),
            fn () => response('next'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next', $response->getContent());
    }

    public function test_allowlisted_external_redirect_is_allowed(): void
    {
        config()->set('seo.redirects.external_allowlist', ['example.com']);
        $this->redirect('/external', 'https://example.com/landing', ['is_external' => true]);

        $response = $this->middleware()->handle(
            Request::create('/external'),
            fn () => response('next'),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://example.com/landing', $response->headers->get('Location'));
    }

    public function test_redirect_chains_are_flattened_and_all_hits_are_recorded(): void
    {
        $a = $this->redirect('/a', '/b');
        $b = $this->redirect('/b', '/c');
        $c = $this->redirect('/c', '/final');
        SeoRedirectResolver::flush((new SeoSiteResolver())->id());

        $response = $this->middleware()->handle(
            Request::create('/a'),
            fn () => response('next'),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/final', (string) $response->headers->get('Location'));
        $this->assertSame(1, (int) $a->fresh()->hits);
        $this->assertSame(1, (int) $b->fresh()->hits);
        $this->assertSame(1, (int) $c->fresh()->hits);
    }

    public function test_loop_is_not_emitted(): void
    {
        $this->redirect('/a', '/b');
        $this->redirect('/b', '/a');
        SeoRedirectResolver::flush((new SeoSiteResolver())->id());

        $response = $this->middleware()->handle(
            Request::create('/a'),
            fn () => response('next'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next', $response->getContent());
    }

    public function test_wildcard_redirect_is_supported(): void
    {
        $this->redirect('/legacy/*', '/modern', ['priority' => 5]);

        $response = $this->middleware()->handle(
            Request::create('/legacy/path/to/page'),
            fn () => response('next'),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/modern', (string) $response->headers->get('Location'));
    }

    private function middleware(): SeoRedirectMiddleware
    {
        return new SeoRedirectMiddleware(new SeoSiteResolver(), new SeoRedirectResolver());
    }

    private function redirect(string $from, string $to, array $overrides = []): SeoRedirect
    {
        return SeoRedirect::query()->create([
            'site_id' => (new SeoSiteResolver())->id(),
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => 301,
            'priority' => 0,
            'is_external' => false,
            'query_behavior' => 'preserve',
            ...$overrides,
        ]);
    }
}
