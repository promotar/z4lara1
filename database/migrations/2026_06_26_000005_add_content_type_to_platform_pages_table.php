<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        Schema::table('platform_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_pages', 'content_type')) {
                $table->string('content_type', 40)->default('page')->after('slug')->index();
            }

            if (! Schema::hasColumn('platform_pages', 'block_key')) {
                $table->string('block_key')->nullable()->after('content_type')->index();
            }

            if (! Schema::hasColumn('platform_pages', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        Schema::table('platform_pages', function (Blueprint $table): void {
            foreach (['content_type', 'block_key', 'sort_order'] as $column) {
                if (Schema::hasColumn('platform_pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
