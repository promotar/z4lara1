<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_instructors') || ! Schema::hasColumn('lms_instructors', 'status')) {
            return;
        }

        Schema::table('lms_instructors', function (Blueprint $table): void {
            $table->string('status', 32)->default('pending')->change();
        });

        DB::table('lms_instructors')
            ->whereNull('user_id')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_instructors') || ! Schema::hasColumn('lms_instructors', 'status')) {
            return;
        }

        Schema::table('lms_instructors', function (Blueprint $table): void {
            $table->string('status', 32)->default('active')->change();
        });
    }
};
