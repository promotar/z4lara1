<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_orders', 'idempotency_key')) {
            return;
        }

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->unique('store_orders_idempotency_key_unique')->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropUnique('store_orders_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
