<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_pricing', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('artwork_id')->unique();
            $t->string('state', 48)->default('draft');
            $t->decimal('requested_amount', 18, 3)->nullable();
            $t->char('currency', 3)->default('JOD');
            $t->text('artist_explanation')->nullable();
            $t->json('supporting_factors')->nullable();
            $t->decimal('proposed_amount', 18, 3)->nullable();
            $t->decimal('agreed_amount', 18, 3)->nullable();
            $t->decimal('approved_amount_snapshot', 18, 3)->nullable();
            $t->boolean('documented_exception')->default(false);
            $t->unsignedBigInteger('last_actor_user_id')->nullable();
            $t->timestamp('artist_accepted_at')->nullable();
            $t->timestamp('reviewer_approved_at')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
            $t->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });
        Schema::create('exhibition_artwork_pricing_evidence', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('artwork_id');
            $t->string('role', 64);
            $t->string('disk', 32)->default('local');
            $t->string('path', 1000);
            $t->string('original_name', 500);
            $t->string('mime_type', 128);
            $t->unsignedBigInteger('size_bytes');
            $t->char('checksum_sha256', 64);
            $t->unsignedBigInteger('uploaded_by');
            $t->timestamp('uploaded_at');
            $t->timestamps();
            $t->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $t->index(['artwork_id', 'role'], 'ex_art_price_evidence_idx');
        });
        Schema::create('exhibition_artwork_pricing_history', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('artwork_id');
            $t->unsignedBigInteger('actor_user_id');
            $t->string('action', 64);
            $t->string('previous_state', 48)->nullable();
            $t->string('new_state', 48);
            $t->decimal('requested_amount', 18, 3)->nullable();
            $t->decimal('proposed_amount', 18, 3)->nullable();
            $t->decimal('agreed_amount', 18, 3)->nullable();
            $t->decimal('approved_amount_snapshot', 18, 3)->nullable();
            $t->char('currency', 3);
            $t->text('reason');
            $t->json('evidence_references')->nullable();
            $t->json('before_values')->nullable();
            $t->json('after_values')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $t->index(['artwork_id', 'occurred_at'], 'ex_art_price_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_pricing_history');
        Schema::dropIfExists('exhibition_artwork_pricing_evidence');
        Schema::dropIfExists('exhibition_artwork_pricing');
    }
};
