<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'artist_name')) {
            Schema::table('store_products', function (Blueprint $table): void {
                $table->string('artist_name')->nullable()->after('internal_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_products', 'artist_name')) {
            Schema::table('store_products', function (Blueprint $table): void {
                $table->dropColumn('artist_name');
            });
        }
    }
};
