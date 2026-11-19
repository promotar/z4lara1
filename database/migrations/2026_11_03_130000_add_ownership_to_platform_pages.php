<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_pages', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('id');
                $table->index('created_by');
            }

            if (! Schema::hasColumn('platform_pages', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_pages', 'updated_by')) {
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('platform_pages', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
