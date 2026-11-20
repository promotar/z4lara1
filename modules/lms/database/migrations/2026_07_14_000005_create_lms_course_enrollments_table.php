<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        Schema::create('lms_course_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_code_id')->nullable()->constrained('lms_course_codes')->nullOnDelete();
            $table->string('source', 32)->default('course_code')->index();
            $table->timestamp('enrolled_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_enrollments');
    }
};
