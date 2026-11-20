<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_courses') || Schema::hasColumn('lms_courses', 'curriculum_units')) {
            return;
        }

        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->json('curriculum_units')->nullable()->after('course_progress');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_courses') || ! Schema::hasColumn('lms_courses', 'curriculum_units')) {
            return;
        }

        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->dropColumn('curriculum_units');
        });
    }
};
