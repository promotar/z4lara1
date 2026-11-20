<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        if (! Schema::hasColumn('blog_posts', 'visibility')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('visibility', 20)->default('public')->after('status');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'seo_focus_keyword')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('seo_focus_keyword')->nullable()->after('meta_description');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'seo_score')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->unsignedTinyInteger('seo_score')->default(0)->after('seo_focus_keyword');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'seo_schema_type')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('seo_schema_type', 80)->nullable()->after('seo_score');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'seo_social_title')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('seo_social_title')->nullable()->after('seo_schema_type');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'seo_social_description')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->text('seo_social_description')->nullable()->after('seo_social_title');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'canonical_url')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('canonical_url')->nullable()->after('seo_social_description');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'robots_index')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->boolean('robots_index')->default(true)->after('canonical_url');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'robots_follow')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->boolean('robots_follow')->default(true)->after('robots_index');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'featured_image_alt')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('featured_image_alt')->nullable()->after('featured_image');
            });
        }

        if (! Schema::hasColumn('blog_posts', 'layout_template')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('layout_template', 80)->nullable()->after('featured_image_alt');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        foreach ([
            'layout_template',
            'featured_image_alt',
            'robots_follow',
            'robots_index',
            'canonical_url',
            'seo_social_description',
            'seo_social_title',
            'seo_schema_type',
            'seo_score',
            'seo_focus_keyword',
            'visibility',
        ] as $column) {
            if (Schema::hasColumn('blog_posts', $column)) {
                Schema::table('blog_posts', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
