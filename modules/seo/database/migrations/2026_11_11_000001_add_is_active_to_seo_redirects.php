<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_redirects') || Schema::hasColumn('seo_redirects', 'is_active')) {
            return;
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('status_code');
            $table->index('is_active', 'seo_redirects_active_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('seo_redirects') || ! Schema::hasColumn('seo_redirects', 'is_active')) {
            return;
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->dropIndex('seo_redirects_active_index');
            $table->dropColumn('is_active');
        });
    }
};
