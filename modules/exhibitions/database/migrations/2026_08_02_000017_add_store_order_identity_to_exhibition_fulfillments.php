<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_fulfillments', function (Blueprint $table): void {
            $table->string('store_order_public_id', 64)->nullable()->after('store_order_reference');
            $table->string('store_order_number', 64)->nullable()->after('store_order_public_id');
            $table->string('store_order_item_public_id', 64)->nullable()->after('store_order_number');
            $table->unsignedInteger('store_unit_number')->nullable()->after('store_order_item_public_id');
            $table->unsignedBigInteger('customer_user_id')->nullable()->after('artist_user_id');
            $table->unique(['store_order_item_public_id', 'store_unit_number'], 'ex_fulfillment_order_item_unit_uq');
            $table->index(['store_order_public_id', 'customer_user_id'], 'ex_fulfillment_order_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_fulfillments', function (Blueprint $table): void {
            $table->dropUnique('ex_fulfillment_order_item_unit_uq');
            $table->dropIndex('ex_fulfillment_order_customer_idx');
            $table->dropColumn([
                'store_order_public_id',
                'store_order_number',
                'store_order_item_public_id',
                'store_unit_number',
                'customer_user_id',
            ]);
        });
    }
};
