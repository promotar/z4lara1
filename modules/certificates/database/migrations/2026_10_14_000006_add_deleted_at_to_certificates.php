<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificates') && ! Schema::hasColumn('certificates', 'deleted_at')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificates') && Schema::hasColumn('certificates', 'deleted_at')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
