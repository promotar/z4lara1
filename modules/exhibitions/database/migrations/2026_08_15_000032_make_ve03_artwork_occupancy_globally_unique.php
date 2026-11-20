<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->unique('artwork_id', 'ex_ve03_occupancy_artwork_global_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->dropUnique('ex_ve03_occupancy_artwork_global_unique');
        });
    }
};
