<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_lessons') || Schema::hasColumn('lms_lessons', 'module_order')) {
            return;
        }

        Schema::table('lms_lessons', function (Blueprint $table): void {
            $table->unsignedInteger('module_order')->default(1)->after('module_title');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_lessons') || ! Schema::hasColumn('lms_lessons', 'module_order')) {
            return;
        }

        Schema::table('lms_lessons', function (Blueprint $table): void {
            $table->dropColumn('module_order');
        });
    }
};
