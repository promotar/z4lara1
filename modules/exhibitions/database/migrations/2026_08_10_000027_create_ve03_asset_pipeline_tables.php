<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_virtual_environments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('title', 180);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('active_version_id')->nullable()->index();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestampsTz();
        });

        Schema::create('exhibition_virtual_environment_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('ready')->index();
            $table->string('viewer_compatibility_version', 40);
            $table->string('source_original_name', 255);
            $table->string('source_disk', 40)->default('local');
            $table->string('source_path', 500);
            $table->unsignedBigInteger('source_size');
            $table->char('source_sha256', 64);
            $table->string('runtime_disk', 40)->default('local');
            $table->string('runtime_path', 500);
            $table->unsignedBigInteger('runtime_size');
            $table->char('runtime_sha256', 64)->unique();
            $table->string('collision_disk', 40)->default('local');
            $table->string('collision_path', 500);
            $table->unsignedBigInteger('collision_size');
            $table->char('collision_sha256', 64);
            $table->string('license_name', 180);
            $table->string('license_code', 80)->nullable();
            $table->string('license_url', 500);
            $table->string('source_url', 500);
            $table->string('creator_name', 180);
            $table->text('attribution_text');
            $table->boolean('commercial_use_allowed');
            $table->boolean('runtime_derivative_allowed');
            $table->json('validation_report');
            $table->json('stats_report');
            $table->json('optimization_report');
            $table->json('metadata_report');
            $table->json('compatibility_report')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['environment_id', 'version_number'], 'ex_ve03_version_number_unique');
            $table->foreign('environment_id', 'ex_ve03_version_environment_fk')
                ->references('id')->on('exhibition_virtual_environments')->cascadeOnDelete();
        });

        Schema::create('exhibition_virtual_environment_rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('version_id');
            $table->string('room_code', 80);
            $table->string('title', 180);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('bounds');
            $table->json('metadata');
            $table->timestampsTz();
            $table->unique(['version_id', 'room_code'], 'ex_ve03_room_code_unique');
            $table->foreign('version_id', 'ex_ve03_room_version_fk')
                ->references('id')->on('exhibition_virtual_environment_versions')->cascadeOnDelete();
        });

        Schema::create('exhibition_virtual_environment_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('version_id');
            $table->unsignedBigInteger('room_id');
            $table->string('slot_code', 80);
            $table->string('name', 140);
            $table->json('translation');
            $table->json('rotation');
            $table->decimal('max_width_m', 8, 3);
            $table->decimal('max_height_m', 8, 3);
            $table->decimal('min_width_m', 8, 3);
            $table->decimal('min_height_m', 8, 3);
            $table->json('allowed_orientations');
            $table->string('frame_profile', 80)->default('standard');
            $table->boolean('enabled_by_default')->default(true);
            $table->string('source_anchor', 120)->nullable();
            $table->json('metadata');
            $table->timestampsTz();
            $table->unique(['version_id', 'slot_code'], 'ex_ve03_slot_code_unique');
            $table->index(['room_id', 'enabled_by_default'], 'ex_ve03_slot_room_enabled_idx');
            $table->foreign('version_id', 'ex_ve03_slot_version_fk')
                ->references('id')->on('exhibition_virtual_environment_versions')->cascadeOnDelete();
            $table->foreign('room_id', 'ex_ve03_slot_room_fk')
                ->references('id')->on('exhibition_virtual_environment_rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_virtual_environment_slots');
        Schema::dropIfExists('exhibition_virtual_environment_rooms');
        Schema::dropIfExists('exhibition_virtual_environment_versions');
        Schema::dropIfExists('exhibition_virtual_environments');
    }
};
