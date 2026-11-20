<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_instructors') || Schema::hasColumn('lms_instructors', 'can_publish_directly')) {
            return;
        }

        Schema::table('lms_instructors', function (Blueprint $table): void {
            $table->boolean('can_publish_directly')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_instructors') || ! Schema::hasColumn('lms_instructors', 'can_publish_directly')) {
            return;
        }

        Schema::table('lms_instructors', function (Blueprint $table): void {
            $table->dropColumn('can_publish_directly');
        });
    }
};
