<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_settings')) {
            return;
        }

        $setting = DB::table('exhibition_settings')
            ->where('key', 'exhibitions.commission.default_rate')
            ->first();

        if ($setting === null) {
            return;
        }

        $stored = json_decode((string) $setting->value, true);
        if (! is_numeric($stored)) {
            return;
        }

        $legacyBasisPoints = (float) $stored;
        if ($legacyBasisPoints <= 100 || $legacyBasisPoints > 10_000) {
            return;
        }

        DB::table('exhibition_settings')
            ->where('id', $setting->id)
            ->update([
                'value' => json_encode(number_format($legacyBasisPoints / 100, 3, '.', ''), JSON_THROW_ON_ERROR),
                'type' => 'decimal',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is a one-way repair of an incorrectly retained legacy basis-point value.
    }
};
