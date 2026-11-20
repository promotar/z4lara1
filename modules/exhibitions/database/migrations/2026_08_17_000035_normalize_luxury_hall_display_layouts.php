<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03LuxuryHallSlotLayout;

if (! class_exists(Ve03LuxuryHallSlotLayout::class)) {
    require_once dirname(__DIR__, 2).'/src/Application/VirtualExhibitions/Ve03LuxuryHallSlotLayout.php';
}

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_virtual_environment_slots')) {
            return;
        }

        (new Ve03LuxuryHallSlotLayout)->normalizeImportedVersions();
    }

    public function down(): void
    {
        // Display positions are content corrections. Stable slot IDs and codes
        // are intentionally retained, so rolling application code back does
        // not invalidate artwork placements.
    }
};
