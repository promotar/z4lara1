<?php

namespace Tests\Feature;

use App\Platform\Core\Seo\PrefixLocaleUrlStrategy;
use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PrefixLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('platform_settings')->updateOrInsert(
            ['group_key' => 'general', 'setting_key' => 'site_language'],
            [
                'label' => 'Site Language',
                'type' => 'select',
                'value' => json_encode('ar'),
                'default_value' => json_encode('ar'),
                'options' => json_encode(['en' => 'English', 'ar' => 'Arabic']),
                'sort_order' => 100,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function test_prefixed_public_path_is_rewritten_before_routing(): void
    {
        config()->set('app.locale', 'ar');
        config()->set('seo.locale.mode', 'prefix');

        Route::middleware(\App\Http\Middleware\ApplyPlatformLocale::class)->get('/prefix-test', fn () => response(
            request()->path().'|'.app()->getLocale().'|'
            .app(\App\Platform\Core\Localization\PlatformLocale::class)->current()
        ));

        $this->get('/en/prefix-test?x=1')
            ->assertOk()
            ->assertSee('prefix-test|en');
    }

    public function test_language_query_redirects_to_prefix_and_links_are_prefixed(): void
    {
        config()->set('app.locale', 'ar');
        config()->set('seo.locale.mode', 'prefix');

        Route::middleware(\App\Http\Middleware\ApplyPlatformLocale::class)
            ->get('/prefix-links', fn () => response('<a href="/blog">Blog</a>'));

        $this->get('/prefix-links?lang=en')
            ->assertRedirect('/en/prefix-links');

        $this->get('/en/prefix-links')
            ->assertOk()
            ->assertSee('href="/en/blog"', false);
    }

    public function test_canonical_and_alternates_use_prefixes(): void
    {
        config()->set('app.locale', 'ar');

        $request = Request::create('/en/blog');
        $request->attributes->set('seo_locale', 'en');
        $request->attributes->set('seo_original_path', '/blog');

        $strategy = new PrefixLocaleUrlStrategy(app(PlatformLocale::class));

        $this->assertStringEndsWith('/en/blog', $strategy->canonical($request));
        $this->assertStringEndsWith('/blog', $strategy->alternates($request)['ar']);
        $this->assertStringEndsWith('/en/blog', $strategy->alternates($request)['en']);
        $this->assertStringEndsWith('/blog', $strategy->alternates($request)['x-default']);
    }
}
