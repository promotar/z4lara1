<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_review_rounds', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('artwork_id');
            $t->unsignedInteger('round_number');
            $t->string('state', 48)->default('under_review');
            $t->unsignedBigInteger('opened_by');
            $t->unsignedBigInteger('assigned_to')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamp('opened_at');
            $t->timestamp('resubmitted_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->foreign('artwork_id')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $t->unique(['artwork_id', 'round_number'], 'ex_art_review_round_unique');
            $t->index(['state', 'assigned_to'], 'ex_art_review_queue_idx');
        });
        Schema::create('exhibition_artwork_review_sections', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('round_id');
            $t->string('section', 32);
            $t->string('state', 48)->default('pending');
            $t->unsignedBigInteger('assigned_to')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->text('reason')->nullable();
            $t->text('adjustment_summary')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->foreign('round_id')->references('id')->on('exhibition_artwork_review_rounds')->cascadeOnDelete();
            $t->unique(['round_id', 'section'], 'ex_art_review_section_unique');
        });
        Schema::create('exhibition_artwork_review_comments', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('round_id');
            $t->unsignedBigInteger('section_id');
            $t->unsignedBigInteger('parent_comment_id')->nullable();
            $t->unsignedBigInteger('author_user_id');
            $t->string('author_role', 24);
            $t->string('target_type', 24);
            $t->string('target_reference', 255);
            $t->text('body');
            $t->string('state', 24)->default('open');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->foreign('round_id')->references('id')->on('exhibition_artwork_review_rounds')->cascadeOnDelete();
            $t->foreign('section_id')->references('id')->on('exhibition_artwork_review_sections')->cascadeOnDelete();
            $t->foreign('parent_comment_id')->references('id')->on('exhibition_artwork_review_comments')->nullOnDelete();
            $t->index(['round_id', 'section_id'], 'ex_art_review_comments_idx');
        });
        Schema::create('exhibition_artwork_review_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('round_id');
            $t->string('event_key', 100)->unique();
            $t->string('event_type', 64);
            $t->unsignedBigInteger('actor_user_id');
            $t->json('payload')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->foreign('round_id')->references('id')->on('exhibition_artwork_review_rounds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_review_events');
        Schema::dropIfExists('exhibition_artwork_review_comments');
        Schema::dropIfExists('exhibition_artwork_review_sections');
        Schema::dropIfExists('exhibition_artwork_review_rounds');
    }
};
