<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_categories') && ! Schema::hasColumn('blog_categories', 'deleted_at')) {
            Schema::table('blog_categories', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_categories') && Schema::hasColumn('blog_categories', 'deleted_at')) {
            Schema::table('blog_categories', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
