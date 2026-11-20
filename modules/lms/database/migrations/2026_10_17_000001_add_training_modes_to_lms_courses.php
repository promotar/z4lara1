<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('lms_courses', 'training_mode')) {
                $table->string('training_mode', 20)->default('online');
            }
            if (! Schema::hasColumn('lms_courses', 'online_price')) {
                $table->decimal('online_price', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('lms_courses', 'in_person_price')) {
                $table->decimal('in_person_price', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lms_courses', function (Blueprint $table): void {
            foreach (['training_mode', 'online_price', 'in_person_price'] as $column) {
                if (Schema::hasColumn('lms_courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
