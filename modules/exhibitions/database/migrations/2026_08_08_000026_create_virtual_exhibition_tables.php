<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_virtual_rooms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title', 180);
            $table->string('slug', 190)->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('panorama_disk', 40)->nullable();
            $table->string('panorama_path', 500)->nullable();
            $table->string('panorama_mime', 80)->nullable();
            $table->unsignedBigInteger('panorama_size')->nullable();
            $table->unsignedInteger('panorama_width')->nullable();
            $table->unsignedInteger('panorama_height')->nullable();
            $table->char('panorama_checksum', 64)->nullable();
            $table->string('cover_disk', 40)->nullable();
            $table->string('cover_path', 500)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->decimal('initial_yaw', 8, 3)->default(0);
            $table->decimal('initial_pitch', 8, 3)->default(0);
            $table->decimal('initial_fov', 8, 3)->default(75);
            $table->decimal('minimum_fov', 8, 3)->default(35);
            $table->decimal('maximum_fov', 8, 3)->default(110);
            $table->boolean('auto_rotate_enabled')->default(false);
            $table->decimal('auto_rotate_speed', 8, 3)->default(2);
            $table->unsignedInteger('auto_rotate_delay')->default(4000);
            $table->timestampTz('published_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('exhibition_virtual_room_slots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('room_id');
            $table->string('code', 64);
            $table->string('name', 140);
            $table->decimal('anchor_yaw', 8, 3);
            $table->decimal('anchor_pitch', 8, 3);
            $table->decimal('roll', 8, 3)->default(0);
            $table->decimal('angular_width', 8, 3)->default(24);
            $table->decimal('maximum_angular_height', 8, 3)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('enabled')->default(true)->index();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('active_request_id')->nullable()->unique();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestampsTz();
            $table->unique(['room_id', 'code'], 'ex_virtual_slot_room_code_unique');
            $table->index(['room_id', 'enabled'], 'ex_virtual_slot_room_enabled_idx');
            $table->foreign('room_id', 'ex_virtual_slot_room_fk')->references('id')->on('exhibition_virtual_rooms')->cascadeOnDelete();
        });

        Schema::create('exhibition_virtual_placement_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('artwork_id');
            $table->unsignedBigInteger('artist_identity_id');
            $table->string('status', 32)->default('draft')->index();
            $table->text('artist_note')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->json('artwork_snapshot')->nullable();
            $table->decimal('placement_yaw', 8, 3);
            $table->decimal('placement_pitch', 8, 3);
            $table->decimal('placement_roll', 8, 3)->default(0);
            $table->decimal('placement_width', 8, 3);
            $table->decimal('maximum_height', 8, 3)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('removed_at')->nullable();
            $table->text('removal_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->index(['room_id', 'status'], 'ex_virtual_request_room_state_idx');
            $table->index(['artist_identity_id', 'status'], 'ex_virtual_request_artist_state_idx');
            $table->index(['artwork_id', 'status'], 'ex_virtual_request_artwork_state_idx');
            $table->foreign('room_id', 'ex_virtual_request_room_fk')->references('id')->on('exhibition_virtual_rooms')->cascadeOnDelete();
            $table->foreign('slot_id', 'ex_virtual_request_slot_fk')->references('id')->on('exhibition_virtual_room_slots')->restrictOnDelete();
            $table->foreign('artwork_id', 'ex_virtual_request_artwork_fk')->references('id')->on('exhibition_artworks')->restrictOnDelete();
            $table->foreign('artist_identity_id', 'ex_virtual_request_artist_fk')->references('id')->on('exhibition_artist_identities')->restrictOnDelete();
        });

        Schema::create('exhibition_virtual_placement_transitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('placement_request_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->index();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('created_at');
            $table->index(['placement_request_id', 'occurred_at'], 'ex_virtual_transition_request_idx');
            $table->foreign('placement_request_id', 'ex_virtual_transition_request_fk')
                ->references('id')->on('exhibition_virtual_placement_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_virtual_placement_transitions');
        Schema::dropIfExists('exhibition_virtual_placement_requests');
        Schema::dropIfExists('exhibition_virtual_room_slots');
        Schema::dropIfExists('exhibition_virtual_rooms');
    }
};
