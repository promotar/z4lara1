<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoModuleState;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoModuleSyncManager;
use Modules\Seo\Services\SeoSiteResolver;
use Tests\TestCase;

class SeoModuleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('modules/seo/database/migrations/2026_11_08_000001_create_seo_tables.php');
        $migration->up();
    }

    public function test_activation_seeds_defaults_and_does_not_overwrite_existing_values(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());
        $sync->activate('seo');

        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'seo',
            'status' => SeoModuleState::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('seo_settings', [
            'module_slug' => 'seo',
            'group_key' => 'general',
            'setting_key' => 'title_suffix',
            'value' => ' | ART INPA',
        ]);

        $siteId = (new SeoSiteResolver())->id();
        SeoSetting::putValue($siteId, 'seo', 'general', 'title_suffix', 'Custom suffix', 'string');
        $sync->activate('seo');

        $this->assertDatabaseHas('seo_settings', [
            'module_slug' => 'seo',
            'group_key' => 'general',
            'setting_key' => 'title_suffix',
            'value' => 'Custom suffix',
        ]);
    }

    public function test_deactivation_keeps_all_seo_data(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());
        $sync->activate('seo');
        $siteId = (new SeoSiteResolver())->id();

        SeoMeta::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'seo',
            'seoable_type' => 'page',
            'seoable_id' => 10,
            'locale' => '',
            'title' => 'Kept title',
        ]);
        SeoRedirect::query()->create([
            'site_id' => $siteId,
            'from_path' => '/old',
            'to_path' => '/new',
            'status_code' => 301,
            'module_slug' => 'seo',
        ]);

        $sync->deactivate('seo');

        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'seo',
            'status' => SeoModuleState::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('seo_settings', ['module_slug' => 'seo']);
        $this->assertDatabaseHas('seo_meta', ['module_slug' => 'seo', 'title' => 'Kept title']);
        $this->assertDatabaseHas('seo_redirects', ['module_slug' => 'seo', 'from_path' => '/old']);
    }

    public function test_archive_sets_purge_window_without_deleting_data(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());
        $siteId = (new SeoSiteResolver())->id();
        SeoSetting::putValue($siteId, 'blog', 'general', 'title_suffix', 'Blog', 'string');

        $sync->archive('blog');

        $state = SeoModuleState::query()->where('module_slug', 'blog')->firstOrFail();
        $this->assertSame(SeoModuleState::STATUS_ARCHIVED, $state->status);
        $this->assertNotNull($state->purge_after);
        $this->assertDatabaseHas('seo_settings', ['module_slug' => 'blog']);
    }

    public function test_reconcile_creates_state_for_active_module_without_existing_state(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());

        \App\Platform\Core\Models\Plugin::query()->create([
            'name' => 'LMS',
            'slug' => 'lms',
            'version' => '1.0.0',
            'status' => \App\Platform\Core\Models\Plugin::STATUS_ACTIVE,
            'path' => base_path('modules/lms'),
            'provider' => 'Modules\\Lms\\LmsServiceProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);

        $sync->reconcile();

        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'lms',
            'status' => SeoModuleState::STATUS_ACTIVE,
        ]);
    }

    public function test_reconcile_distinguishes_inactive_from_missing_modules(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());
        $siteId = (new SeoSiteResolver())->id();

        \App\Platform\Core\Models\Plugin::query()->create([
            'name' => 'LMS',
            'slug' => 'lms',
            'version' => '1.0.0',
            'status' => \App\Platform\Core\Models\Plugin::STATUS_ACTIVE,
            'path' => base_path('modules/lms'),
            'provider' => 'Modules\\Lms\\LmsServiceProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);
        \App\Platform\Core\Models\Plugin::query()->create([
            'name' => 'Blog',
            'slug' => 'blog',
            'version' => '1.0.0',
            'status' => \App\Platform\Core\Models\Plugin::STATUS_DISABLED,
            'path' => base_path('modules/blog'),
            'provider' => 'Modules\\Blog\\BlogServiceProvider',
            'dependencies' => [],
            'manifest' => [],
        ]);

        SeoModuleState::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'lms',
            'status' => SeoModuleState::STATUS_INACTIVE,
        ]);
        SeoModuleState::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'blog',
            'status' => SeoModuleState::STATUS_ACTIVE,
        ]);
        SeoModuleState::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'store',
            'status' => SeoModuleState::STATUS_ACTIVE,
        ]);

        $result = $sync->reconcile();

        $this->assertSame(SeoModuleState::STATUS_ACTIVE, SeoModuleState::query()->where('module_slug', 'lms')->value('status'));
        $this->assertSame(SeoModuleState::STATUS_INACTIVE, SeoModuleState::query()->where('module_slug', 'blog')->value('status'));
        $this->assertSame(SeoModuleState::STATUS_ORPHANED, SeoModuleState::query()->where('module_slug', 'store')->value('status'));
        $this->assertContains('store', $result['orphaned']);
    }

    public function test_purge_removes_only_the_target_module_data(): void
    {
        $sync = new SeoModuleSyncManager(new SeoSiteResolver());
        $siteId = (new SeoSiteResolver())->id();

        SeoSetting::putValue($siteId, 'blog', 'general', 'title_suffix', 'Blog', 'string');
        SeoSetting::putValue($siteId, 'lms', 'general', 'title_suffix', 'LMS', 'string');
        SeoMeta::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'blog',
            'seoable_type' => 'post',
            'seoable_id' => 1,
            'locale' => '',
            'title' => 'Post',
        ]);
        SeoRedirect::query()->create([
            'site_id' => $siteId,
            'from_path' => '/blog-old',
            'to_path' => '/blog-new',
            'status_code' => 301,
            'module_slug' => 'blog',
        ]);

        $sync->purge('blog');

        $this->assertDatabaseMissing('seo_settings', ['module_slug' => 'blog']);
        $this->assertDatabaseMissing('seo_meta', ['module_slug' => 'blog']);
        $this->assertDatabaseMissing('seo_redirects', ['module_slug' => 'blog']);
        $this->assertDatabaseHas('seo_settings', ['module_slug' => 'lms']);
        $this->assertDatabaseHas('seo_module_states', [
            'module_slug' => 'blog',
            'status' => SeoModuleState::STATUS_PURGED,
        ]);
    }
}
