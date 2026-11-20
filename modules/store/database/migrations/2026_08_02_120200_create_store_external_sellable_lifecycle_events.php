<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_external_sellable_lifecycle_events')) {
            return;
        }

        Schema::create('store_external_sellable_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_code', 64);
            $table->string('external_id', 128);
            $table->string('action', 32);
            $table->unsignedInteger('quantity');
            $table->char('order_public_id', 26);
            $table->string('order_number', 32);
            $table->char('order_item_public_id', 26);
            $table->unsignedBigInteger('customer_user_id')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->string('payment_status', 32);
            $table->string('idempotency_key', 191)->unique('store_ext_lifecycle_idempotency_unique');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_code', 'id'], 'store_ext_lifecycle_owner_sequence_idx');
            $table->index(['owner_code', 'external_id', 'id'], 'store_ext_lifecycle_external_sequence_idx');
            $table->index(
                ['owner_code', 'order_public_id', 'customer_user_id'],
                'store_ext_lifecycle_owner_order_customer_idx',
            );
            $table->index(
                ['owner_code', 'order_number', 'customer_user_id'],
                'store_ext_lifecycle_owner_number_customer_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_external_sellable_lifecycle_events');
    }
};
