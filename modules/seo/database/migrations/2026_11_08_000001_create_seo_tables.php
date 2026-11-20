<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 191)->unique('seo_sites_domain_unique');
            $table->string('default_locale', 12)->default('en');
            $table->boolean('is_primary')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('module_slug', 120)->default('');
            $table->string('group_key', 80);
            $table->string('setting_key', 120);
            $table->longText('value')->nullable();
            $table->string('type', 32)->default('string');
            $table->string('locale', 12)->default('');
            $table->timestamps();

            $table->unique(
                ['site_id', 'module_slug', 'group_key', 'setting_key', 'locale'],
                'seo_settings_identity_unique',
            );
            $table->index(['module_slug', 'group_key'], 'seo_settings_module_group_index');
        });

        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('module_slug', 120)->default('');
            $table->string('seoable_type', 191);
            $table->unsignedBigInteger('seoable_id');
            $table->string('locale', 12)->default('');
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('focus_keyword', 255)->nullable();
            $table->string('canonical', 2048)->nullable();
            $table->string('robots', 255)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->string('twitter_card', 32)->nullable();
            $table->string('og_type', 32)->nullable();
            $table->json('schema_json')->nullable();
            $table->timestamp('orphaned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['site_id', 'module_slug', 'seoable_type', 'seoable_id', 'locale'],
                'seo_meta_identity_unique',
            );
            $table->index(['seoable_type', 'seoable_id'], 'seo_meta_seoable_index');
            $table->index('module_slug', 'seo_meta_module_index');
            $table->index('locale', 'seo_meta_locale_index');
            $table->index('orphaned_at', 'seo_meta_orphaned_index');
        });

        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('from_path', 191);
            $table->text('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->string('module_slug', 120)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_external')->default(false);
            $table->string('query_behavior', 20)->default('preserve');
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'from_path'], 'seo_redirects_path_unique');
            $table->index('module_slug', 'seo_redirects_module_index');
            $table->index('status_code', 'seo_redirects_status_index');
        });

        Schema::create('seo_module_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('module_slug', 120);
            $table->string('version', 50)->nullable();
            $table->string('status', 20)->default('inactive');
            $table->string('settings_hash', 64)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('purge_after')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'module_slug'], 'seo_module_states_identity_unique');
            $table->index('status', 'seo_module_states_status_index');
        });

        Schema::create('seo_audit_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('module_slug', 120)->nullable();
            $table->string('content_type', 80)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('admin_url', 2048)->nullable();
            $table->string('url_hash', 64)->nullable();
            $table->string('url', 2048);
            $table->string('type', 80);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 20)->default('open');
            $table->text('message');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['module_slug', 'status', 'severity'], 'seo_audit_module_status_index');
            $table->index('url_hash', 'seo_audit_url_hash_index');
        });

        Schema::create('seo_lifecycle_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('module_slug', 120);
            $table->string('action', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();

            $table->index('module_slug', 'seo_lifecycle_module_index');
            $table->index('created_at', 'seo_lifecycle_created_index');
        });

        $domain = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

        DB::table('seo_sites')->updateOrInsert(
            ['domain' => $domain],
            [
                'default_locale' => (string) config('app.locale', 'en'),
                'is_primary' => true,
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_lifecycle_log');
        Schema::dropIfExists('seo_audit_issues');
        Schema::dropIfExists('seo_module_states');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('seo_settings');
        Schema::dropIfExists('seo_sites');
    }
};
