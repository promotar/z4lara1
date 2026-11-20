<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_virtual_environment_versions', function (Blueprint $table): void {
            $table->dropUnique('exhibition_virtual_environment_versions_runtime_sha256_unique');
            $table->index('runtime_sha256', 'ex_ve03_runtime_sha256_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_virtual_environment_versions', function (Blueprint $table): void {
            $table->dropIndex('ex_ve03_runtime_sha256_idx');
            $table->unique('runtime_sha256', 'exhibition_virtual_environment_versions_runtime_sha256_unique');
        });
    }
};
