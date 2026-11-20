<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_authenticity', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id')->unique();
            $table->boolean('owns_artwork')->default(false);
            $table->boolean('may_display_and_sell')->default(false);
            $table->text('inspiration_disclosure')->nullable();
            $table->text('digital_tools_disclosure')->nullable();
            $table->string('current_physical_location', 500)->nullable();
            $table->string('state', 48)->default('draft');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('declared_at')->nullable();
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });
        Schema::create('exhibition_artwork_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->string('role', 64);
            $table->string('state', 32)->default('ready');
            $table->string('disk', 32)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 500);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->uuid('exact_match_public_id')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->index(['artwork_id', 'state']);
        });
        Schema::create('exhibition_artwork_provenance', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->date('event_date');
            $table->string('event_type', 64);
            $table->string('owner_name', 255)->nullable();
            $table->string('location', 500)->nullable();
            $table->text('description');
            $table->string('document_reference', 500)->nullable();
            $table->unsignedBigInteger('entered_by');
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->index(['artwork_id', 'event_date']);
        });
        Schema::create('exhibition_artwork_authenticity_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('reviewer_user_id');
            $table->string('previous_state', 48);
            $table->string('decision', 64);
            $table->text('reason');
            $table->text('review_notes')->nullable();
            $table->text('request_instructions')->nullable();
            $table->json('evidence_references')->nullable();
            $table->unsignedInteger('review_version');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->index(['artwork_id', 'occurred_at'], 'ex_art_auth_review_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_authenticity_reviews');
        Schema::dropIfExists('exhibition_artwork_provenance');
        Schema::dropIfExists('exhibition_artwork_evidence');
        Schema::dropIfExists('exhibition_artwork_authenticity');
    }
};
