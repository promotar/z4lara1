<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_courses')) {
            return;
        }

        Schema::table('lms_courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('lms_courses', 'meta_title')) {
                $table->string('meta_title', 255)->nullable()->after('title');
            }

            if (! Schema::hasColumn('lms_courses', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }

            if (! Schema::hasColumn('lms_courses', 'canonical_url')) {
                $table->string('canonical_url', 2048)->nullable()->after('meta_description');
            }

            if (! Schema::hasColumn('lms_courses', 'robots_index')) {
                $table->boolean('robots_index')->default(true)->after('canonical_url');
            }

            if (! Schema::hasColumn('lms_courses', 'robots_follow')) {
                $table->boolean('robots_follow')->default(true)->after('robots_index');
            }

            if (! Schema::hasColumn('lms_courses', 'schema_type')) {
                $table->string('schema_type', 32)->nullable()->after('robots_follow');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_courses')) {
            return;
        }

        Schema::table('lms_courses', function (Blueprint $table): void {
            foreach (['schema_type', 'robots_follow', 'robots_index', 'canonical_url', 'meta_description', 'meta_title'] as $column) {
                if (Schema::hasColumn('lms_courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
