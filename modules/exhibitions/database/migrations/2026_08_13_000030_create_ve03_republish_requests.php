<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_virtual_republish_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('placement_id');
            $table->unsignedBigInteger('artist_identity_id');
            $table->string('state', 24)->default('pending')->index();
            $table->text('artist_message');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->index(['placement_id', 'state'], 'ex_ve03_republish_placement_state_idx');
            $table->index(['artist_identity_id', 'state'], 'ex_ve03_republish_artist_state_idx');
            $table->foreign('placement_id', 'ex_ve03_republish_placement_fk')->references('id')->on('exhibition_virtual_placements')->cascadeOnDelete();
            $table->foreign('artist_identity_id', 'ex_ve03_republish_artist_fk')->references('id')->on('exhibition_artist_identities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_virtual_republish_requests');
    }
};
