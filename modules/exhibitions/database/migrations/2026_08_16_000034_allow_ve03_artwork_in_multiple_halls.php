<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->index('artwork_id', 'ex_ve03_occupancy_artwork_idx');
        });

        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->dropUnique('ex_ve03_occupancy_artwork_global_unique');
        });
    }

    public function down(): void
    {
        $hasArtworkInMultipleHalls = DB::table('exhibition_virtual_slot_occupancies')
            ->select('artwork_id')
            ->groupBy('artwork_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasArtworkInMultipleHalls) {
            return;
        }

        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->unique('artwork_id', 'ex_ve03_occupancy_artwork_global_unique');
        });

        Schema::table('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->dropIndex('ex_ve03_occupancy_artwork_idx');
        });
    }
};
