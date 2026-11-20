<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('artist_user_id');
            $table->string('store_order_reference', 191)->unique();
            $table->string('state', 40)->index();
            $table->timestamp('preparation_deadline')->nullable();
            $table->text('packaging_instructions');
            $table->timestamp('artist_notified_at')->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('carrier_reference', 191)->nullable();
            $table->string('custody_holder', 80)->default('artist');
            $table->text('condition_summary')->nullable();
            $table->boolean('certificate_included')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->foreign('inventory_item_id')->references('id')->on('exhibition_artwork_inventory_items')->restrictOnDelete();
            $table->index(['artist_user_id', 'state'], 'ex_fulfillment_artist_state_idx');
        });
        Schema::create('exhibition_fulfillment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('fulfillment_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_role', 32);
            $table->string('action', 50);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->text('notes');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('fulfillment_id')->references('id')->on('exhibition_fulfillments')->cascadeOnDelete();
            $table->index(['fulfillment_id', 'occurred_at'], 'ex_fulfillment_events_idx');
        });
        Schema::create('exhibition_fulfillment_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('fulfillment_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('role', 40);
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamp('created_at');
            $table->foreign('fulfillment_id')->references('id')->on('exhibition_fulfillments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_fulfillment_evidence');
        Schema::dropIfExists('exhibition_fulfillment_events');
        Schema::dropIfExists('exhibition_fulfillments');
    }
};
