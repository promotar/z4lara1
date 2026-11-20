<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_virtual_environments', function (Blueprint $table): void {
            $table->boolean('artist_placement_open')->default(true)->after('active_version_id');
            $table->unsignedBigInteger('placement_version')->default(0)->after('artist_placement_open');
            $table->timestampTz('placement_lock_at')->nullable()->after('placement_version');
        });

        Schema::create('exhibition_virtual_placements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('version_id');
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('artist_identity_id');
            $table->string('state', 24)->default('active')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampTz('placed_at');
            $table->timestampTz('sold_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['environment_id', 'artist_identity_id', 'state'], 'ex_ve03_placement_artist_state_idx');
            $table->index(['environment_id', 'artwork_id', 'state'], 'ex_ve03_placement_artwork_state_idx');
            $table->foreign('environment_id', 'ex_ve03_placement_environment_fk')->references('id')->on('exhibition_virtual_environments')->restrictOnDelete();
            $table->foreign('version_id', 'ex_ve03_placement_version_fk')->references('id')->on('exhibition_virtual_environment_versions')->restrictOnDelete();
            $table->foreign('slot_id', 'ex_ve03_placement_slot_fk')->references('id')->on('exhibition_virtual_environment_slots')->restrictOnDelete();
            $table->foreign('artwork_id', 'ex_ve03_placement_artwork_fk')->references('id')->on('exhibition_artworks')->restrictOnDelete();
            $table->foreign('artist_identity_id', 'ex_ve03_placement_artist_fk')->references('id')->on('exhibition_artist_identities')->restrictOnDelete();
        });

        Schema::create('exhibition_virtual_slot_occupancies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('version_id');
            $table->unsignedBigInteger('slot_id')->unique();
            $table->unsignedBigInteger('placement_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('artist_identity_id');
            $table->timestampsTz();
            $table->unique(['environment_id', 'artwork_id'], 'ex_ve03_occupancy_artwork_unique');
            $table->foreign('environment_id', 'ex_ve03_occupancy_environment_fk')->references('id')->on('exhibition_virtual_environments')->restrictOnDelete();
            $table->foreign('version_id', 'ex_ve03_occupancy_version_fk')->references('id')->on('exhibition_virtual_environment_versions')->restrictOnDelete();
            $table->foreign('slot_id', 'ex_ve03_occupancy_slot_fk')->references('id')->on('exhibition_virtual_environment_slots')->restrictOnDelete();
            $table->foreign('placement_id', 'ex_ve03_occupancy_placement_fk')->references('id')->on('exhibition_virtual_placements')->restrictOnDelete();
            $table->foreign('artwork_id', 'ex_ve03_occupancy_artwork_fk')->references('id')->on('exhibition_artworks')->restrictOnDelete();
            $table->foreign('artist_identity_id', 'ex_ve03_occupancy_artist_fk')->references('id')->on('exhibition_artist_identities')->restrictOnDelete();
        });

        Schema::create('exhibition_virtual_slot_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('hold_token')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('version_id');
            $table->unsignedBigInteger('slot_id')->unique();
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('artist_identity_id');
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->unique(['environment_id', 'artist_identity_id'], 'ex_ve03_hold_artist_unique');
            $table->foreign('environment_id', 'ex_ve03_hold_environment_fk')->references('id')->on('exhibition_virtual_environments')->cascadeOnDelete();
            $table->foreign('version_id', 'ex_ve03_hold_version_fk')->references('id')->on('exhibition_virtual_environment_versions')->cascadeOnDelete();
            $table->foreign('slot_id', 'ex_ve03_hold_slot_fk')->references('id')->on('exhibition_virtual_environment_slots')->cascadeOnDelete();
            $table->foreign('artwork_id', 'ex_ve03_hold_artwork_fk')->references('id')->on('exhibition_artworks')->cascadeOnDelete();
            $table->foreign('artist_identity_id', 'ex_ve03_hold_artist_fk')->references('id')->on('exhibition_artist_identities')->cascadeOnDelete();
        });

        Schema::create('exhibition_virtual_placement_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('placement_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->string('reason', 500);
            $table->json('context')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('created_at')->nullable();
            $table->foreign('placement_id', 'ex_ve03_event_placement_fk')->references('id')->on('exhibition_virtual_placements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_virtual_placement_events');
        Schema::dropIfExists('exhibition_virtual_slot_holds');
        Schema::dropIfExists('exhibition_virtual_slot_occupancies');
        Schema::dropIfExists('exhibition_virtual_placements');
        Schema::table('exhibition_virtual_environments', function (Blueprint $table): void {
            $table->dropColumn(['artist_placement_open', 'placement_version', 'placement_lock_at']);
        });
    }
};
