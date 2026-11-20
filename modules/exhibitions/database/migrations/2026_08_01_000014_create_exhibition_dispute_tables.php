<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_dispute_cases', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('source', 30);
            $t->string('source_reference', 191)->unique();
            $t->string('type', 50)->index();
            $t->string('priority', 20)->index();
            $t->string('state', 30)->index();
            $t->unsignedBigInteger('fulfillment_id')->nullable();
            $t->unsignedBigInteger('settlement_id')->nullable();
            $t->unsignedBigInteger('artwork_id');
            $t->unsignedBigInteger('artist_user_id');
            $t->unsignedBigInteger('customer_user_id')->nullable();
            $t->string('customer_reference', 191)->nullable();
            $t->unsignedBigInteger('assigned_to')->nullable();
            $t->timestamp('response_due_at')->nullable();
            $t->timestamp('resolution_due_at')->nullable();
            $t->string('subject', 191);
            $t->text('summary');
            $t->text('final_decision')->nullable();
            $t->text('decision_reason')->nullable();
            $t->json('financial_impact')->nullable();
            $t->json('inventory_impact')->nullable();
            $t->json('fulfillment_impact')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->unsignedInteger('reopen_count')->default(0);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
            $t->foreign('fulfillment_id')->references('id')->on('exhibition_fulfillments')->restrictOnDelete();
            $t->foreign('settlement_id')->references('id')->on('exhibition_settlements')->restrictOnDelete();
            $t->foreign('artwork_id')->references('id')->on('exhibition_artworks')->restrictOnDelete();
            $t->index(['artist_user_id', 'state'], 'ex_dispute_artist_state_idx');
        });
        Schema::create('exhibition_dispute_messages', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('case_id');
            $t->unsignedBigInteger('sender_user_id')->nullable();
            $t->string('sender_role', 30);
            $t->string('audience', 30);
            $t->text('body');
            $t->string('idempotency_key', 191)->unique();
            $t->timestamp('created_at');
            $t->foreign('case_id')->references('id')->on('exhibition_dispute_cases')->cascadeOnDelete();
            $t->index(['case_id', 'created_at'], 'ex_dispute_messages_idx');
        });
        Schema::create('exhibition_dispute_evidence', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('case_id');
            $t->unsignedBigInteger('uploaded_by');
            $t->string('uploader_role', 30);
            $t->string('audience', 30);
            $t->string('role', 40);
            $t->string('disk', 30)->default('local');
            $t->string('path', 500);
            $t->string('original_name', 255);
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->char('checksum_sha256', 64);
            $t->timestamp('created_at');
            $t->foreign('case_id')->references('id')->on('exhibition_dispute_cases')->cascadeOnDelete();
        });
        Schema::create('exhibition_dispute_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('case_id');
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->string('actor_role', 30);
            $t->string('action', 50);
            $t->string('from_state', 30)->nullable();
            $t->string('to_state', 30);
            $t->string('idempotency_key', 191)->unique();
            $t->text('reason');
            $t->json('metadata')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->foreign('case_id')->references('id')->on('exhibition_dispute_cases')->cascadeOnDelete();
            $t->index(['case_id', 'occurred_at'], 'ex_dispute_events_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_dispute_events');
        Schema::dropIfExists('exhibition_dispute_evidence');
        Schema::dropIfExists('exhibition_dispute_messages');
        Schema::dropIfExists('exhibition_dispute_cases');
    }
};
