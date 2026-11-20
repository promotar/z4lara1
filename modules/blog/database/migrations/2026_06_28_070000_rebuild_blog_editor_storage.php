<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('blog_posts', 'password')) {
                    $table->string('password')->nullable()->after('visibility');
                }

                if (! Schema::hasColumn('blog_posts', 'scheduled_at')) {
                    $table->timestamp('scheduled_at')->nullable()->after('published_at');
                }

                if (! Schema::hasColumn('blog_posts', 'author_id')) {
                    $table->foreignId('author_id')->nullable()->after('scheduled_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('blog_posts', 'featured_image_id')) {
                    $table->unsignedBigInteger('featured_image_id')->nullable()->after('author_id');
                }

                if (! Schema::hasColumn('blog_posts', 'template')) {
                    $table->string('template', 80)->default('default')->after('layout_template');
                }

                if (! Schema::hasColumn('blog_posts', 'layout')) {
                    $table->string('layout', 80)->default('default')->after('template');
                }

                if (! Schema::hasColumn('blog_posts', 'seo_title')) {
                    $table->string('seo_title')->nullable()->after('layout');
                }

                if (! Schema::hasColumn('blog_posts', 'seo_description')) {
                    $table->text('seo_description')->nullable()->after('seo_title');
                }

                if (! Schema::hasColumn('blog_posts', 'focus_keyword')) {
                    $table->string('focus_keyword')->nullable()->after('seo_description');
                }

                if (! Schema::hasColumn('blog_posts', 'schema_type')) {
                    $table->string('schema_type', 80)->nullable()->after('focus_keyword');
                }

                if (! Schema::hasColumn('blog_posts', 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            DB::table('blog_posts')
                ->whereNull('author_id')
                ->whereNotNull('created_by')
                ->update(['author_id' => DB::raw('created_by')]);

            DB::table('blog_posts')
                ->whereNull('seo_title')
                ->whereNotNull('meta_title')
                ->update(['seo_title' => DB::raw('meta_title')]);

            DB::table('blog_posts')
                ->whereNull('seo_description')
                ->whereNotNull('meta_description')
                ->update(['seo_description' => DB::raw('meta_description')]);

            DB::table('blog_posts')
                ->whereNull('focus_keyword')
                ->whereNotNull('seo_focus_keyword')
                ->update(['focus_keyword' => DB::raw('seo_focus_keyword')]);

            DB::table('blog_posts')
                ->whereNull('schema_type')
                ->whereNotNull('seo_schema_type')
                ->update(['schema_type' => DB::raw('seo_schema_type')]);

            DB::table('blog_posts')
                ->whereNull('template')
                ->orWhere('template', '')
                ->update(['template' => DB::raw("COALESCE(NULLIF(layout_template, ''), 'default')")]);
        }

        if (! Schema::hasTable('blog_media')) {
            Schema::create('blog_media', function (Blueprint $table): void {
                $table->id();
                $table->string('disk', 40)->default('public');
                $table->string('path');
                $table->string('url');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('alt_text')->nullable();
                $table->string('title')->nullable();
                $table->text('caption')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['mime_type', 'created_at']);
            });
        }

        if (Schema::hasTable('blog_posts') && Schema::hasTable('blog_media') && ! $this->foreignKeyExists('blog_posts', 'blog_posts_featured_image_id_foreign')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->foreign('featured_image_id')->references('id')->on('blog_media')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('blog_post_revisions')) {
            Schema::create('blog_post_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revision_type', 30)->default('manual');
                $table->string('title')->nullable();
                $table->string('slug')->nullable();
                $table->longText('content')->nullable();
                $table->text('excerpt')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['post_id', 'revision_type', 'created_at']);
            });
        }

        if (! Schema::hasTable('blog_post_meta')) {
            Schema::create('blog_post_meta', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
                $table->string('meta_key');
                $table->longText('meta_value')->nullable();
                $table->timestamps();

                $table->unique(['post_id', 'meta_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_meta');
        Schema::dropIfExists('blog_post_revisions');

        if (Schema::hasTable('blog_posts') && $this->foreignKeyExists('blog_posts', 'blog_posts_featured_image_id_foreign')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->dropForeign('blog_posts_featured_image_id_foreign');
            });
        }

        Schema::dropIfExists('blog_media');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};
