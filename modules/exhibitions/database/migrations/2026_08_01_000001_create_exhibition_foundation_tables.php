<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artist_identities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('core_user_id')->unique();
            $table->string('state', 32)->default('candidate')->index();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('restricted_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('terminated_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('exhibition_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 120)->index();
            $table->string('target_type', 120)->index();
            $table->string('target_id', 120)->nullable()->index();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->string('request_id', 120)->nullable()->index();
            $table->json('context')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_audit_entries');
        Schema::dropIfExists('exhibition_artist_identities');
    }
};
