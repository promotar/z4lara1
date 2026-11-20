<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_verification_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('application_id')->unique();
            $table->string('state', 32)->default('pending')->index();
            $table->timestampTz('contact_verified_at')->nullable();
            $table->timestampTz('artistic_profile_verified_at')->nullable();
            $table->timestampTz('samples_verified_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('requested_instructions')->nullable();
            $table->timestampTz('response_deadline')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->foreign('application_id')->references('id')->on('exhibition_artist_applications')->cascadeOnDelete();
        });

        Schema::create('exhibition_verification_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('verification_case_id')->index();
            $table->string('type', 40)->index();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('current')->index();
            $table->unsignedBigInteger('replaces_document_id')->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();
            $table->unique(['verification_case_id', 'type', 'version'], 'exhibition_verification_document_versions_unique');
            $table->foreign('verification_case_id')->references('id')->on('exhibition_verification_cases')->cascadeOnDelete();
            $table->foreign('replaces_document_id')->references('id')->on('exhibition_verification_documents')->nullOnDelete();
        });

        Schema::create('exhibition_agreement_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('version', 40)->unique();
            $table->string('title', 180);
            $table->longText('content');
            $table->unsignedSmallInteger('commission_basis_points');
            $table->json('terms');
            $table->unsignedBigInteger('created_by');
            $table->timestampTz('published_at')->index();
            $table->timestampsTz();
        });

        Schema::create('exhibition_artist_agreements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('verification_case_id')->unique();
            $table->unsignedBigInteger('template_id');
            $table->string('accepted_version', 40);
            $table->string('accepted_title', 180);
            $table->longText('accepted_content');
            $table->unsignedSmallInteger('commission_basis_points');
            $table->json('accepted_terms');
            $table->timestampTz('assigned_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->string('acceptance_method', 40)->nullable();
            $table->json('acceptance_evidence')->nullable();
            $table->timestampsTz();
            $table->foreign('verification_case_id')->references('id')->on('exhibition_verification_cases')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('exhibition_agreement_templates')->restrictOnDelete();
        });

        Schema::create('exhibition_payout_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('verification_case_id')->unique();
            $table->string('method', 40);
            $table->longText('encrypted_payload');
            $table->string('masked_identifier', 80);
            $table->unsignedBigInteger('submitted_by');
            $table->timestampTz('submitted_at');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
            $table->foreign('verification_case_id')->references('id')->on('exhibition_verification_cases')->cascadeOnDelete();
        });

        Schema::create('exhibition_artist_account_status_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artist_identity_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('from_state', 32);
            $table->string('to_state', 32)->index();
            $table->text('reason');
            $table->json('capabilities');
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();
            $table->index('artist_identity_id', 'ex_art_account_identity_idx');
            $table->foreign('artist_identity_id', 'ex_art_account_identity_fk')->references('id')->on('exhibition_artist_identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artist_account_status_history');
        Schema::dropIfExists('exhibition_payout_profiles');
        Schema::dropIfExists('exhibition_artist_agreements');
        Schema::dropIfExists('exhibition_agreement_templates');
        Schema::dropIfExists('exhibition_verification_documents');
        Schema::dropIfExists('exhibition_verification_cases');
    }
};
