<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['blog_posts', 'lms_courses'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'robots_index')) {
                    $table->boolean('robots_index')->nullable()->default(null)->change();
                }

                if (Schema::hasColumn($tableName, 'robots_follow')) {
                    $table->boolean('robots_follow')->nullable()->default(null)->change();
                }
            });

            // Existing "true" values are the old schema default, not a reliable
            // custom override. Keep explicit noindex/nofollow values.
            if (Schema::hasColumn($tableName, 'robots_index')) {
                DB::table($tableName)->where('robots_index', 1)->update(['robots_index' => null]);
            }

            if (Schema::hasColumn($tableName, 'robots_follow')) {
                DB::table($tableName)->where('robots_follow', 1)->update(['robots_follow' => null]);
            }
        }
    }

    public function down(): void
    {
        foreach (['blog_posts', 'lms_courses'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'robots_index')) {
                    $table->boolean('robots_index')->nullable(false)->default(true)->change();
                }

                if (Schema::hasColumn($tableName, 'robots_follow')) {
                    $table->boolean('robots_follow')->nullable(false)->default(true)->change();
                }
            });
        }
    }
};
