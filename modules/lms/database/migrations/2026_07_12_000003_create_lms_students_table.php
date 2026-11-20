<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_students')) {
            return;
        }

        Schema::create('lms_students', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('registered_at')->nullable()->index();
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedInteger('enrolled_courses_count')->default(0);
            $table->json('enrolled_course_ids')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('avatar_name')->nullable();
            $table->json('avatar_meta')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_students');
    }
};
