<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_artist_commission_terms')
            || ! Schema::hasColumn('exhibition_artist_commission_terms', 'created_by')
            || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'exhibition_artist_commission_terms')
            ->where('COLUMN_NAME', 'created_by')
            ->where('REFERENCED_TABLE_NAME', 'users')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            $quoted = str_replace('`', '``', (string) $constraint);
            DB::statement("ALTER TABLE `exhibition_artist_commission_terms` DROP FOREIGN KEY `{$quoted}`");
        }
    }

    public function down(): void
    {
        // Deliberately do not recreate a cross-module foreign key.
    }
};
