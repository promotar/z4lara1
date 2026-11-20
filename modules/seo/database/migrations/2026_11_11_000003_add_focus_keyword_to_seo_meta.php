<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_meta') && ! Schema::hasColumn('seo_meta', 'focus_keyword')) {
            Schema::table('seo_meta', function (Blueprint $table): void {
                $table->string('focus_keyword', 255)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_meta') && Schema::hasColumn('seo_meta', 'focus_keyword')) {
            Schema::table('seo_meta', function (Blueprint $table): void {
                $table->dropColumn('focus_keyword');
            });
        }
    }
};
