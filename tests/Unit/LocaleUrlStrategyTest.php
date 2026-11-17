<?php

namespace Tests\Unit;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\QueryLocaleUrlStrategy;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class LocaleUrlStrategyTest extends TestCase
{
    private function strategy(): QueryLocaleUrlStrategy
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('values')->andReturn([
            'general.site_language' => 'en',
        ]);

        return new QueryLocaleUrlStrategy(new PlatformLocale($settings));
    }

    public function test_canonical_strips_tracking_and_filters_but_keeps_page(): void
    {
        $strategy = $this->strategy();
        $request = Request::create('/courses?utm_source=newsletter&sort=price&page=2');

        $this->assertSame('http://localhost/courses?page=2', $strategy->canonical($request));
    }

    public function test_canonical_for_arabic_keeps_language_but_strips_tracking(): void
    {
        $strategy = $this->strategy();
        $request = Request::create('/courses?utm_source=newsletter&page=2&lang=ar');

        $this->assertSame('http://localhost/courses?page=2&lang=ar', $strategy->canonical($request));
    }

    public function test_alternates_are_clean_and_reciprocal(): void
    {
        $strategy = $this->strategy();
        $request = Request::create('/courses?utm_source=newsletter&page=2&lang=ar');
        $alternates = $strategy->alternates($request);

        $this->assertSame('http://localhost/courses?page=2', $alternates['en']);
        $this->assertSame('http://localhost/courses?page=2&lang=ar', $alternates['ar']);
        $this->assertSame('http://localhost/courses?page=2', $alternates['x-default']);
    }

    public function test_switcher_url_preserves_query_and_removes_default_lang(): void
    {
        $strategy = $this->strategy();
        $request = Request::create('/courses?utm_source=x&page=2&lang=ar');

        $this->assertSame('http://localhost/courses?utm_source=x&page=2', $strategy->urlFor($request, 'en'));
        $this->assertSame('http://localhost/courses?utm_source=x&page=2&lang=ar', $strategy->urlFor($request, 'ar'));
    }

    public function test_with_locale_updates_an_arbitrary_url(): void
    {
        $strategy = $this->strategy();

        $this->assertSame('http://localhost/blog?x=1&lang=ar', $strategy->withLocale('http://localhost/blog?x=1', 'ar'));
        $this->assertSame('http://localhost/blog?x=1', $strategy->withLocale('http://localhost/blog?x=1&lang=ar', 'en'));
    }

    public function test_current_ignores_invalid_language_values(): void
    {
        $strategy = $this->strategy();

        $this->assertSame('ar', $strategy->current(Request::create('/courses?lang=ar')));
        $this->assertSame('en', $strategy->current(Request::create('/courses?lang=fr')));
        $this->assertSame('en', $strategy->current(Request::create('/courses')));
    }
}
