<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('lms_courses', 'overview')) {
                $table->text('overview')->nullable();
            }
            if (! Schema::hasColumn('lms_courses', 'partners')) {
                $table->json('partners')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lms_courses', function (Blueprint $table): void {
            foreach (['overview', 'partners'] as $column) {
                if (Schema::hasColumn('lms_courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};