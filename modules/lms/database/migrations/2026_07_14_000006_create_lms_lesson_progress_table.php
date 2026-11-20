<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_lesson_progress')) {
            return;
        }

        Schema::create('lms_lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lms_lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('completed')->default(false)->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'lesson_id', 'user_id'], 'lms_lesson_progress_unique');
            $table->index(['course_id', 'user_id', 'completed'], 'lms_lesson_progress_course_user_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_lesson_progress');
    }
};
