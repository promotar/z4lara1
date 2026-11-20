<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_store_lifecycle_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('store_sequence')->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->uuid('artwork_public_id');
            $table->string('store_order_public_id', 64);
            $table->string('store_order_item_public_id', 64);
            $table->string('action', 32);
            $table->unsignedInteger('quantity');
            $table->string('state', 24)->index();
            $table->char('payload_sha256', 64);
            $table->text('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['store_order_public_id', 'action'], 'ex_store_receipt_order_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_store_lifecycle_receipts');
    }
};
