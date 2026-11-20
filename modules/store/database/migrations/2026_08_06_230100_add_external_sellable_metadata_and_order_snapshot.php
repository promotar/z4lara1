<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsMetadata = ! Schema::hasColumn('store_external_sellables', 'metadata');
        Schema::table('store_external_sellables', function (Blueprint $table) use ($needsMetadata): void {
            if ($needsMetadata) {
                $table->json('metadata')->nullable()->after('payload_hash');
            }
        });
        $needsSnapshot = ! Schema::hasColumn('store_order_items', 'external_sellable_snapshot');
        Schema::table('store_order_items', function (Blueprint $table) use ($needsSnapshot): void {
            if ($needsSnapshot) {
                $table->json('external_sellable_snapshot')->nullable()->after('inventory_reservation_public_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('store_order_items', 'external_sellable_snapshot')) {
                $table->dropColumn('external_sellable_snapshot');
            }
        });
        Schema::table('store_external_sellables', function (Blueprint $table): void {
            if (Schema::hasColumn('store_external_sellables', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
