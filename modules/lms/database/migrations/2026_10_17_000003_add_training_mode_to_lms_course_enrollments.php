<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        Schema::table('lms_course_enrollments', function (Blueprint $table): void {
            if (! Schema::hasColumn('lms_course_enrollments', 'training_mode')) {
                $table->string('training_mode', 20)->nullable()->after('course_code_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        Schema::table('lms_course_enrollments', function (Blueprint $table): void {
            if (Schema::hasColumn('lms_course_enrollments', 'training_mode')) {
                $table->dropColumn('training_mode');
            }
        });
    }
};
