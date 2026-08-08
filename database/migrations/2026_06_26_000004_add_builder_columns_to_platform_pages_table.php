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
            if (! Schema::hasColumn('platform_pages', 'page_builder_json')) {
                $table->longText('page_builder_json')->nullable()->after('content');
            }

            if (! Schema::hasColumn('platform_pages', 'html')) {
                $table->longText('html')->nullable()->after('page_builder_json');
            }

            if (! Schema::hasColumn('platform_pages', 'css')) {
                $table->longText('css')->nullable()->after('html');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        Schema::table('platform_pages', function (Blueprint $table): void {
            foreach (['page_builder_json', 'html', 'css'] as $column) {
                if (Schema::hasColumn('platform_pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
