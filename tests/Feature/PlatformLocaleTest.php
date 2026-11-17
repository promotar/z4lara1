<?php

namespace Tests\Feature;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Models\PlatformSetting;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_installations_default_to_english(): void
    {
        $setting = PlatformSetting::query()
            ->where('group_key', 'general')
            ->where('setting_key', 'site_language')
            ->firstOrFail();

        $this->assertSame('en', $setting->default_value);
        $this->assertSame('en', app(PlatformLocale::class)->current());
    }

    public function test_saved_platform_language_is_applied_to_web_responses(): void
    {
        app(SettingsRepository::class)->update([
            'general' => ['site_language' => 'ar'],
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_invalid_saved_language_falls_back_to_application_locale(): void
    {
        PlatformSetting::query()
            ->where('group_key', 'general')
            ->where('setting_key', 'site_language')
            ->update(['value' => '../invalid']);
        app(SettingsRepository::class)->forgetMemoizedValues();

        config()->set('app.locale', 'en');

        $this->assertSame('en', app(PlatformLocale::class)->current());
    }
}
