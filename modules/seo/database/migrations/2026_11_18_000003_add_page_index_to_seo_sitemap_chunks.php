<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_sitemap_chunks') || Schema::hasColumn('seo_sitemap_chunks', 'page_index')) {
            return;
        }

        Schema::table('seo_sitemap_chunks', function (Blueprint $table): void {
            $table->unsignedInteger('page_index')->nullable()->after('content_type');
            $table->index(['content_type', 'page_index'], 'seo_sitemap_chunks_type_page_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('seo_sitemap_chunks') || ! Schema::hasColumn('seo_sitemap_chunks', 'page_index')) {
            return;
        }

        Schema::table('seo_sitemap_chunks', function (Blueprint $table): void {
            $table->dropIndex('seo_sitemap_chunks_type_page_index');
            $table->dropColumn('page_index');
        });
    }
};
