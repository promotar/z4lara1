<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artworks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artist_identity_id');
            $table->uuid('draft_key');
            $table->string('state', 32)->default('draft')->index();
            $table->string('title', 180)->nullable();
            $table->unsignedSmallInteger('completion_year')->nullable();
            $table->string('work_type', 80)->nullable();
            $table->string('collection_name', 160)->nullable();
            $table->string('edition_type', 24)->default('original');
            $table->unsignedInteger('edition_number')->nullable();
            $table->unsignedInteger('edition_total')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('artistic_style', 100)->nullable();
            $table->string('subject', 160)->nullable();
            $table->string('technique', 160)->nullable();
            $table->string('material', 160)->nullable();
            $table->string('surface', 120)->nullable();
            $table->json('primary_colors')->nullable();
            $table->string('orientation', 24)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('depth', 10, 2)->nullable();
            $table->string('dimension_unit', 8)->default('cm');
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 8)->default('kg');
            $table->boolean('is_framed')->default(false);
            $table->string('frame_material', 120)->nullable();
            $table->string('condition', 80)->nullable();
            $table->boolean('ready_to_hang')->default(false);
            $table->text('short_description')->nullable();
            $table->longText('detailed_description')->nullable();
            $table->longText('artwork_story')->nullable();
            $table->longText('inspiration_source')->nullable();
            $table->text('care_instructions')->nullable();
            $table->json('keywords')->nullable();
            $table->string('image_alt_text', 180)->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->decimal('requested_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('JOD');
            $table->decimal('frame_cost', 14, 2)->nullable();
            $table->decimal('packaging_cost', 14, 2)->nullable();
            $table->string('availability', 32)->nullable();
            $table->unsignedSmallInteger('preparation_days')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('approval_review_round_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('reserved_at')->nullable();
            $table->timestampTz('sold_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['artist_identity_id', 'draft_key'], 'ex_artwork_artist_draft_unique');
            $table->index('artist_identity_id', 'ex_artwork_artist_idx');
            $table->index('approved_by', 'ex_artwork_approved_by_idx');
            $table->index('approval_review_round_id', 'ex_artwork_approval_round_idx');
            $table->foreign('artist_identity_id', 'ex_artwork_artist_fk')->references('id')->on('exhibition_artist_identities')->cascadeOnDelete();
        });

        Schema::create('exhibition_artwork_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedInteger('revision_number');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('reason', 120);
            $table->json('snapshot');
            $table->timestampTz('created_at');
            $table->unique(['artwork_id', 'revision_number'], 'ex_artwork_revision_unique');
            $table->index('artwork_id', 'ex_artwork_revision_artwork_idx');
            $table->foreign('artwork_id', 'ex_artwork_revision_artwork_fk')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });

        Schema::create('exhibition_artwork_state_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->index();
            $table->text('reason');
            $table->unsignedBigInteger('review_round_id')->nullable();
            $table->string('event_key', 64)->nullable()->unique();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();
            $table->index('artwork_id', 'ex_artwork_state_artwork_idx');
            $table->foreign('artwork_id', 'ex_artwork_state_artwork_fk')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_state_history');
        Schema::dropIfExists('exhibition_artwork_revisions');
        Schema::dropIfExists('exhibition_artworks');
    }
};
