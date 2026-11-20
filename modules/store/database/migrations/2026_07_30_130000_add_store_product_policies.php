<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumns('store_products', ['shipping_policy', 'return_policy'])) {
            return;
        }

        Schema::table('store_products', function (Blueprint $table): void {
            $table->text('shipping_policy')->nullable()->after('artist_name');
            $table->text('return_policy')->nullable()->after('shipping_policy');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table): void {
            $table->dropColumn(['shipping_policy', 'return_policy']);
        });
    }
};
