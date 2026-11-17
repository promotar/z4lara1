<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_settings', 'validation_rules')) {
                $table->json('validation_rules')->nullable()->after('is_public');
            }

            if (! Schema::hasColumn('platform_settings', 'description')) {
                $table->text('description')->nullable()->after('validation_rules');
            }

            if (! Schema::hasColumn('platform_settings', 'category')) {
                $table->string('category')->nullable()->after('description');
            }

            if (! Schema::hasColumn('platform_settings', 'module')) {
                $table->string('module')->default('core')->after('category');
            }

            if (! Schema::hasColumn('platform_settings', 'visibility_level')) {
                $table->string('visibility_level', 50)->default('admin')->after('module');
            }

            if (! Schema::hasColumn('platform_settings', 'admin_access_level')) {
                $table->string('admin_access_level')->nullable()->after('visibility_level');
            }

            if (! Schema::hasColumn('platform_settings', 'editable')) {
                $table->boolean('editable')->default(true)->after('admin_access_level');
            }

            if (! Schema::hasColumn('platform_settings', 'required')) {
                $table->boolean('required')->default(false)->after('editable');
            }

            if (! Schema::hasColumn('platform_settings', 'sensitive_flag')) {
                $table->boolean('sensitive_flag')->default(false)->after('required');
            }

            if (! Schema::hasColumn('platform_settings', 'public_exposure_allowed')) {
                $table->boolean('public_exposure_allowed')->default(false)->after('sensitive_flag');
            }

            if (! Schema::hasColumn('platform_settings', 'frontend_available')) {
                $table->boolean('frontend_available')->default(false)->after('public_exposure_allowed');
            }

            if (! Schema::hasColumn('platform_settings', 'cache_enabled')) {
                $table->boolean('cache_enabled')->default(true)->after('frontend_available');
            }

            if (! Schema::hasColumn('platform_settings', 'cache_ttl')) {
                $table->unsignedInteger('cache_ttl')->nullable()->after('cache_enabled');
            }

            if (! Schema::hasColumn('platform_settings', 'ui_component')) {
                $table->string('ui_component', 80)->nullable()->after('cache_ttl');
            }

            if (! Schema::hasColumn('platform_settings', 'ui_label')) {
                $table->string('ui_label')->nullable()->after('ui_component');
            }

            if (! Schema::hasColumn('platform_settings', 'allowed_values')) {
                $table->json('allowed_values')->nullable()->after('ui_label');
            }

            if (! Schema::hasColumn('platform_settings', 'min_value')) {
                $table->string('min_value')->nullable()->after('allowed_values');
            }

            if (! Schema::hasColumn('platform_settings', 'max_value')) {
                $table->string('max_value')->nullable()->after('min_value');
            }

            if (! Schema::hasColumn('platform_settings', 'unit')) {
                $table->string('unit', 50)->nullable()->after('max_value');
            }

            if (! Schema::hasColumn('platform_settings', 'depends_on')) {
                $table->json('depends_on')->nullable()->after('unit');
            }

            if (! Schema::hasColumn('platform_settings', 'restart_required')) {
                $table->boolean('restart_required')->default(false)->after('depends_on');
            }

            if (! Schema::hasColumn('platform_settings', 'approval_required')) {
                $table->boolean('approval_required')->default(false)->after('restart_required');
            }

            if (! Schema::hasColumn('platform_settings', 'status')) {
                $table->string('status', 50)->default('active')->after('approval_required');
            }

            if (! Schema::hasColumn('platform_settings', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            foreach ([
                'validation_rules',
                'description',
                'category',
                'module',
                'visibility_level',
                'admin_access_level',
                'editable',
                'required',
                'sensitive_flag',
                'public_exposure_allowed',
                'frontend_available',
                'cache_enabled',
                'cache_ttl',
                'ui_component',
                'ui_label',
                'allowed_values',
                'min_value',
                'max_value',
                'unit',
                'depends_on',
                'restart_required',
                'approval_required',
                'status',
                'version',
            ] as $column) {
                if (Schema::hasColumn('platform_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
