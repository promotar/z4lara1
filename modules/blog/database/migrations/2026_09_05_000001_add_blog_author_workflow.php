<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_authors')) {
            Schema::create('blog_authors', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('status', 24)->default('active')->index();
                $table->boolean('can_publish_directly')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('blog_posts') && ! Schema::hasColumn('blog_posts', 'review_status')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->string('review_status', 24)->default('none')->after('status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'review_status')) {
            if (Schema::hasIndex('blog_posts', ['review_status'])) {
                Schema::table('blog_posts', function (Blueprint $table): void {
                    $table->dropIndex(['review_status']);
                });
            }

            Schema::table('blog_posts', function (Blueprint $table): void {
                $table->dropColumn('review_status');
            });
        }

        Schema::dropIfExists('blog_authors');
    }
};
