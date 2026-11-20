<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artist_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('core_user_id')->unique();
            $table->string('state', 32)->default('draft')->index();
            $table->string('legal_name', 160)->nullable();
            $table->string('legal_identifier', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('artistic_name', 160)->nullable();
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->text('biography')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('previous_exhibitions')->nullable();
            $table->text('awards_certificates')->nullable();
            $table->json('previous_work_links')->nullable();
            $table->json('social_accounts')->nullable();
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->timestampTz('ownership_declared_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->text('current_reason')->nullable();
            $table->text('next_action')->nullable();
            $table->timestampTz('response_deadline')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('exhibition_application_samples', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('application_id')->index();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->timestampsTz();
            $table->foreign('application_id')->references('id')->on('exhibition_artist_applications')->cascadeOnDelete();
        });

        Schema::create('exhibition_application_status_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('application_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->index();
            $table->text('reason')->nullable();
            $table->text('next_action')->nullable();
            $table->timestampTz('response_deadline')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();
            $table->foreign('application_id')->references('id')->on('exhibition_artist_applications')->cascadeOnDelete();
        });

        Schema::create('exhibition_application_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('application_id')->index();
            $table->unsignedBigInteger('core_user_id')->index();
            $table->string('type', 80);
            $table->string('state', 32);
            $table->string('idempotency_key', 190)->unique();
            $table->timestampTz('delivered_at')->nullable();
            $table->string('failure_class', 255)->nullable();
            $table->timestampsTz();
            $table->foreign('application_id')->references('id')->on('exhibition_artist_applications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_application_notifications');
        Schema::dropIfExists('exhibition_application_status_history');
        Schema::dropIfExists('exhibition_application_samples');
        Schema::dropIfExists('exhibition_artist_applications');
    }
};
