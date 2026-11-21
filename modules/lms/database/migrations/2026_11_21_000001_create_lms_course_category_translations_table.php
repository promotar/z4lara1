<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_category_translations')) {
            return;
        }

        Schema::create('lms_course_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 120);
            $table->string('locale', 16);
            $table->string('title', 120);
            $table->timestamps();

            $table->unique(['source', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_category_translations');
    }
};
