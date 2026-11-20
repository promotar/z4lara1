<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_codes')) {
            return;
        }

        Schema::create('lms_course_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('batch_id', 64)->nullable()->index();
            $table->string('status', 32)->default('available')->index();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['course_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_codes');
    }
};
