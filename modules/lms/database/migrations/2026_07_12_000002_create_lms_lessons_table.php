<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_lessons')) {
            return;
        }

        Schema::create('lms_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->string('module_title')->nullable();
            $table->unsignedInteger('lesson_order')->default(1);
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_name')->nullable();
            $table->json('featured_image_meta')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('publish_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('visibility')->default('enrolled');
            $table->unsignedSmallInteger('duration_hours')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('availability')->default('immediate');
            $table->string('access')->default('enrolled');
            $table->string('completion_requirement')->default('manual');
            $table->string('lesson_type')->default('video');
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable();
            $table->boolean('free_preview')->default(false);
            $table->json('attachments')->nullable();
            $table->json('resources')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'lesson_order']);
            $table->index(['lesson_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_lessons');
    }
};
