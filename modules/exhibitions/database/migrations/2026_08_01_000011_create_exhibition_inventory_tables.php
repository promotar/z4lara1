<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->string('inventory_key', 64);
            $table->unsignedInteger('edition_number')->nullable();
            $table->string('certificate_reference', 191)->nullable();
            $table->string('state', 32)->default('available')->index();
            $table->string('hold_token', 191)->nullable()->unique();
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->string('reservation_reference', 191)->nullable()->index();
            $table->string('sale_reference', 191)->nullable()->index();
            $table->string('store_sync_state', 32)->default('pending');
            $table->text('store_sync_failure')->nullable();
            $table->timestamp('last_store_sync_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->unique(['artwork_id', 'inventory_key'], 'ex_art_inventory_key_unique');
            $table->unique(['artwork_id', 'edition_number'], 'ex_art_edition_number_unique');
        });

        Schema::create('exhibition_artwork_inventory_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 40);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('idempotency_key', 191)->unique();
            $table->string('external_reference', 191)->nullable();
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('inventory_item_id')->references('id')->on('exhibition_artwork_inventory_items')->cascadeOnDelete();
            $table->index(['inventory_item_id', 'occurred_at'], 'ex_art_inventory_events_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_inventory_events');
        Schema::dropIfExists('exhibition_artwork_inventory_items');
    }
};
