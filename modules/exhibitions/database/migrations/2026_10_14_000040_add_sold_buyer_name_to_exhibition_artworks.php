<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exhibition_artworks') && ! Schema::hasColumn('exhibition_artworks', 'sold_buyer_name')) {
            Schema::table('exhibition_artworks', function (Blueprint $table): void {
                $table->string('sold_buyer_name', 180)->nullable()->after('availability');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exhibition_artworks') && Schema::hasColumn('exhibition_artworks', 'sold_buyer_name')) {
            Schema::table('exhibition_artworks', function (Blueprint $table): void {
                $table->dropColumn('sold_buyer_name');
            });
        }
    }
};
