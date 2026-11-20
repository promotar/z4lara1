<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumns('store_artwork_details', ['draft_key', 'frame_cost_minor', 'packaging_cost_minor'])
            && collect([
                'store_artwork_media',
                'store_artwork_media_history',
                'store_artwork_authenticity',
                'store_artwork_evidence',
                'store_artwork_provenance',
                'store_artwork_authenticity_reviews',
            ])->every(fn (string $table): bool => Schema::hasTable($table))) {
            return;
        }

        Schema::table('store_artwork_details', function (Blueprint $table): void {
            $table->uuid('draft_key')->nullable()->unique()->after('workflow_state');
            $table->unsignedBigInteger('frame_cost_minor')->nullable()->after('requested_price_minor');
            $table->unsignedBigInteger('packaging_cost_minor')->nullable()->after('frame_cost_minor');
        });

        Schema::create('store_artwork_media', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->foreignId('replaces_media_id')->nullable()->constrained('store_artwork_media')->nullOnDelete();
            $table->char('product_media_public_id', 26)->nullable()->index();
            $table->string('role', 40)->index();
            $table->string('state', 24)->default('processing')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('original_disk', 32)->default('local');
            $table->string('display_disk', 32)->default('public');
            $table->string('original_path', 512)->unique();
            $table->string('display_path', 512)->unique();
            $table->string('thumbnail_path', 512)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64)->index();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['artwork_detail_id', 'state', 'sort_order'], 'store_artwork_media_state_order_idx');
        });

        Schema::create('store_artwork_media_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->char('media_public_id', 26);
            $table->unsignedBigInteger('actor_user_id');
            $table->string('action', 40)->index();
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('store_artwork_authenticity', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->unique()->constrained('store_artwork_details')->cascadeOnDelete();
            $table->string('ownership_basis', 24);
            $table->boolean('may_display_and_sell')->default(false);
            $table->text('inspiration_disclosure');
            $table->text('digital_tools_disclosure');
            $table->string('current_physical_location', 500);
            $table->string('state', 48)->default('submitted')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('declared_at');
            $table->timestamps();
        });

        Schema::create('store_artwork_evidence', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->string('role', 64)->index();
            $table->string('state', 32)->default('ready')->index();
            $table->string('disk', 32)->default('local');
            $table->string('path', 512)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->char('exact_match_public_id', 26)->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['artwork_detail_id', 'state'], 'store_artwork_evidence_state_idx');
        });

        Schema::create('store_artwork_provenance', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('event_type', 64);
            $table->string('owner_name', 255)->nullable();
            $table->string('location', 500)->nullable();
            $table->text('description');
            $table->string('document_reference', 500)->nullable();
            $table->unsignedBigInteger('entered_by');
            $table->timestamps();

            $table->index(['artwork_detail_id', 'event_date'], 'store_artwork_provenance_timeline_idx');
        });

        Schema::create('store_artwork_authenticity_reviews', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->unsignedBigInteger('reviewer_user_id');
            $table->string('previous_state', 48);
            $table->string('decision', 64);
            $table->text('reason');
            $table->json('evidence_references')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['artwork_detail_id', 'occurred_at'], 'store_artwork_auth_review_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_artwork_authenticity_reviews');
        Schema::dropIfExists('store_artwork_provenance');
        Schema::dropIfExists('store_artwork_evidence');
        Schema::dropIfExists('store_artwork_authenticity');
        Schema::dropIfExists('store_artwork_media_history');
        Schema::dropIfExists('store_artwork_media');

        Schema::table('store_artwork_details', function (Blueprint $table): void {
            $table->dropUnique(['draft_key']);
            $table->dropColumn(['draft_key', 'frame_cost_minor', 'packaging_cost_minor']);
        });
    }
};
