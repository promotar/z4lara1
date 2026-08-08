<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_categories')) {
            return;
        }

        Schema::table('blog_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('blog_categories', 'image')) {
                $table->string('image')->nullable()->after('description');
            }
            if (! Schema::hasColumn('blog_categories', 'image_alt')) {
                $table->string('image_alt')->nullable()->after('image');
            }
            if (! Schema::hasColumn('blog_categories', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('image_alt');
            }
            if (! Schema::hasColumn('blog_categories', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('blog_categories')) {
            return;
        }

        foreach (['seo_description', 'seo_title', 'image_alt', 'image'] as $column) {
            if (Schema::hasColumn('blog_categories', $column)) {
                Schema::table('blog_categories', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
