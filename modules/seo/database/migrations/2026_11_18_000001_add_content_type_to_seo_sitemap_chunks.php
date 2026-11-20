<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_sitemap_chunks') || Schema::hasColumn('seo_sitemap_chunks', 'content_type')) {
            return;
        }

        Schema::table('seo_sitemap_chunks', function (Blueprint $table): void {
            $table->string('content_type', 40)->nullable()->after('chunk_index')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_sitemap_chunks') && Schema::hasColumn('seo_sitemap_chunks', 'content_type')) {
            Schema::table('seo_sitemap_chunks', function (Blueprint $table): void {
                $table->dropColumn('content_type');
            });
        }
    }
};
