<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_artwork_media', function (Blueprint $table): void {
            $table->string('ve03_room_path', 1024)->nullable()->unique()->after('thumbnail_path');
            $table->string('ve03_near_path', 1024)->nullable()->unique()->after('ve03_room_path');
            $table->string('ve03_popup_path', 1024)->nullable()->unique()->after('ve03_near_path');
            $table->string('ve03_derivative_profile', 80)->nullable()->after('ve03_popup_path');
            $table->char('ve03_derivative_source_sha256', 64)->nullable()->index()->after('ve03_derivative_profile');
            $table->timestampTz('ve03_derivatives_ready_at')->nullable()->after('ve03_derivative_source_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_artwork_media', function (Blueprint $table): void {
            $table->dropColumn([
                've03_room_path',
                've03_near_path',
                've03_popup_path',
                've03_derivative_profile',
                've03_derivative_source_sha256',
                've03_derivatives_ready_at',
            ]);
        });
    }
};
