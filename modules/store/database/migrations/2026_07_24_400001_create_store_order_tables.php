<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_orders') && Schema::hasTable('store_order_items')) {
            return;
        }

        Schema::create('store_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('order_number', 32)->unique();
            $table->enum('status', ['new', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'])->default('new');
            $table->string('payment_method', 64)->default('cash_on_delivery');
            $table->enum('payment_status', ['cod_pending', 'paid', 'failed', 'cancelled'])->default('cod_pending');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 64);
            $table->char('country_code', 2)->default('JO');
            $table->string('city', 128);
            $table->string('area', 128)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('locale', 16)->default('ar');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['status', 'placed_at'], 'store_orders_status_placed_idx');
            $table->index(['customer_phone', 'placed_at'], 'store_orders_phone_placed_idx');
        });

        Schema::create('store_order_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->char('product_public_id', 26);
            $table->char('variant_public_id', 26);
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->char('inventory_reservation_public_id', 26)->nullable();
            $table->json('external_sellable_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'id'], 'store_order_items_order_idx');
            $table->index('variant_public_id', 'store_order_items_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
    }
};
