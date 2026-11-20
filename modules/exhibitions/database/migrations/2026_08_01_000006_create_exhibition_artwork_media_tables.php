<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artwork_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('replaces_media_id')->nullable();
            $table->string('role', 40)->index();
            $table->string('state', 24)->default('processing')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('disk', 32)->default('local');
            $table->string('original_path', 1024)->nullable()->unique();
            $table->string('display_path', 1024)->nullable()->unique();
            $table->string('thumbnail_path', 1024)->nullable()->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('replaced_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->index('artwork_id', 'ex_art_media_artwork_idx');
            $table->unique(['artwork_id', 'sort_order'], 'ex_art_media_order_unique');
            $table->foreign('artwork_id', 'ex_art_media_artwork_fk')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->foreign('replaces_media_id', 'ex_art_media_replaces_fk')->references('id')->on('exhibition_artwork_media')->nullOnDelete();
        });

        Schema::create('exhibition_artwork_media_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->uuid('media_public_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('action', 40)->index();
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();
            $table->index('artwork_id', 'ex_art_media_history_artwork_idx');
            $table->foreign('artwork_id', 'ex_art_media_history_artwork_fk')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_media_history');
        Schema::dropIfExists('exhibition_artwork_media');
    }
};
