<?php

namespace Tests\Feature;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\RequiredCorePluginBootstrapper;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FreshDatabaseSeedTest extends TestCase
{
    public function test_fresh_database_migrations_and_core_seeders_complete(): void
    {
        $this->artisan('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ])->assertSuccessful();

        app(RequiredCorePluginBootstrapper::class)->bootstrap();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('platform_pages'));
        $this->assertTrue(Schema::hasTable('vvvebjs_page_revisions'));
        $this->assertTrue(Schema::hasTable('vvvebjs_layout_sections'));
        $this->assertDatabaseHas('plugins', [
            'slug' => 'page-builder',
            'status' => Plugin::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('plugins', [
            'slug' => 'admin-theme',
            'status' => Plugin::STATUS_ACTIVE,
        ]);
    }
}
