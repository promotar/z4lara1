<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoHeadTest extends TestCase
{
    public function test_home_canonical_ignores_tracking_parameters(): void
    {
        $response = $this->get('/?utm_source=newsletter');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="http://localhost"', false);
    }

    public function test_arabic_query_locale_has_self_canonical_and_x_default(): void
    {
        $response = $this->get('/?lang=ar');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="http://localhost?lang=ar"', false);
        $response->assertSee('hreflang="ar"', false);
        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_login_is_noindex(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, follow"', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_not_found_has_no_canonical_and_is_noindex(): void
    {
        $response = $this->get('/missing-seo-test-page');

        $response->assertNotFound();
        $response->assertDontSee('rel="canonical"', false);
        $response->assertSee('noindex', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_health_endpoint_is_noindex(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    }
}
