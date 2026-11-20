<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_publications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id')->unique();
            $table->string('state', 40)->default('pending')->index();
            $table->string('store_product_public_id', 64)->nullable()->unique();
            $table->string('store_variant_public_id', 64)->nullable()->unique();
            $table->string('store_inventory_public_id', 64)->nullable()->unique();
            $table->unsignedInteger('last_synced_revision')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });

        Schema::create('exhibition_artwork_publication_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('publication_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 40);
            $table->string('state', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('reason');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('publication_id')->references('id')->on('exhibition_artwork_publications')->cascadeOnDelete();
            $table->index(['publication_id', 'occurred_at'], 'ex_art_publication_events_idx');
        });

        Schema::create('exhibition_artwork_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('customer_user_id');
            $table->text('message');
            $table->string('state', 32)->default('open')->index();
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->index(['artwork_id', 'created_at'], 'ex_art_inquiries_artwork_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_inquiries');
        Schema::dropIfExists('exhibition_artwork_publication_events');
        Schema::dropIfExists('exhibition_artwork_publications');
    }
};
