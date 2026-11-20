<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_artist_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('artist_identity_id');
            $table->string('subject', 180);
            $table->string('category', 40)->index();
            $table->string('state', 32)->default('open')->index();
            $table->timestampTz('last_message_at')->index();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index('artist_identity_id', 'ex_artist_support_identity_idx');
            $table->foreign('artist_identity_id', 'ex_artist_support_identity_fk')->references('id')->on('exhibition_artist_identities')->cascadeOnDelete();
        });

        Schema::create('exhibition_artist_support_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->string('sender_role', 24);
            $table->text('body');
            $table->timestampsTz();
            $table->index('ticket_id', 'ex_artist_support_message_ticket_idx');
            $table->foreign('ticket_id', 'ex_artist_support_message_ticket_fk')->references('id')->on('exhibition_artist_support_tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artist_support_messages');
        Schema::dropIfExists('exhibition_artist_support_tickets');
    }
};
