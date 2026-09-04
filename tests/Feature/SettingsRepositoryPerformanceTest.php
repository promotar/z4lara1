<?php

namespace Tests\Feature;

use App\Platform\Core\Models\PlatformSetting;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsRepositoryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_values_are_loaded_once_per_request_without_schema_queries(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $settings = app(SettingsRepository::class);
        $first = $settings->values();
        $second = $settings->values();

        $this->assertSame($first, $second);
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "platform_settings"')));
        $this->assertCount(0, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'sqlite_master') || str_contains($sql, 'pragma table_info')));
    }

    public function test_update_invalidates_only_the_request_local_values(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->values();
        $settings->update(['general' => ['site_title' => 'Updated title']]);

        $this->assertSame('Updated title', $settings->values()['general.site_title']);
    }

    public function test_sync_command_preserves_values_and_unknown_plugin_definitions(): void
    {
        $setting = PlatformSetting::query()->where('group_key', 'general')->where('setting_key', 'site_title')->firstOrFail();
        $setting->forceFill(['value' => 'Kept title'])->save();

        PlatformSetting::query()->create([
            'group_key' => 'plugin-example',
            'setting_key' => 'enabled',
            'label' => 'Plugin example',
            'type' => 'boolean',
            'value' => true,
            'default_value' => false,
            'sort_order' => 10,
        ]);

        $this->assertSame(0, Artisan::call('platform:sync-settings'));

        $this->assertSame('Kept title', PlatformSetting::query()->findOrFail($setting->id)->value);
        $this->assertDatabaseHas('platform_settings', [
            'group_key' => 'plugin-example',
            'setting_key' => 'enabled',
        ]);
    }
}
