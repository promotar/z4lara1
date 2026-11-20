<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_product_reviews')) {
            return;
        }

        Schema::create('store_product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('reviewer_name');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->enum('status', ['published', 'hidden'])->default('published');
            $table->boolean('verified_purchase')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'order_item_id'], 'store_reviews_user_item_unique');
            $table->index(['product_id', 'status', 'created_at'], 'store_reviews_product_status_idx');
            $table->index(['order_id', 'user_id'], 'store_reviews_order_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_reviews');
    }
};
