<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeoPluginInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_activation_reconciles_modules_that_were_already_active(): void
    {
        Plugin::query()->create([
            'name' => 'Blog',
            'slug' => 'blog',
            'version' => '1.0.0',
            'status' => Plugin::STATUS_ACTIVE,
            'path' => base_path('modules/blog'),
            'provider' => 'Modules\\Blog\\BlogServiceProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);

        $manager = app(PluginManager::class);
        $plugin = $manager->install(base_path('modules/seo'));
        $manager->activate($plugin);

        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'blog',
            'status' => 'active',
        ]);
    }

    public function test_seo_module_installs_activates_and_seeds_its_own_state(): void
    {
        $manager = app(PluginManager::class);

        $plugin = $manager->install(base_path('modules/seo'));

        $this->assertSame('seo', $plugin->slug);
        $this->assertSame(Plugin::STATUS_INSTALLED, $plugin->status);
        $this->assertTrue(Schema::hasTable('seo_settings'));
        $this->assertTrue(Schema::hasTable('seo_module_states'));

        $plugin = $manager->activate($plugin);

        $this->assertSame(Plugin::STATUS_ACTIVE, $plugin->status);
        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'seo',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('seo_settings', [
            'module_slug' => 'seo',
            'group_key' => 'general',
            'setting_key' => 'title_suffix',
            'value' => ' | ART INPA',
        ]);
    }
}
