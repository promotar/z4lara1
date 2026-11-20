<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_notification_templates', function (Blueprint $t): void {
            $t->id();
            $t->string('event_key', 100);
            $t->string('channel', 20);
            $t->string('locale', 12);
            $t->string('subject', 255);
            $t->text('body');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('updated_by');
            $t->timestamps();
            $t->unique(['event_key', 'channel', 'locale'], 'ex_notify_template_unique');
        });
        Schema::create('exhibition_notification_template_history', function (Blueprint $t): void {
            $t->id();
            $t->string('event_key', 100);
            $t->string('channel', 20);
            $t->string('locale', 12);
            $t->unsignedInteger('version');
            $t->string('subject', 255);
            $t->text('body');
            $t->unsignedBigInteger('actor_user_id');
            $t->text('reason');
            $t->timestamp('created_at');
            $t->unique(['event_key', 'channel', 'locale', 'version'], 'ex_notify_template_history_unique');
        });
        Schema::create('exhibition_notification_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('event_key', 100)->index();
            $t->string('channel', 20)->index();
            $t->unsignedBigInteger('recipient_user_id')->index();
            $t->string('locale', 12);
            $t->string('subject', 255);
            $t->text('body');
            $t->string('link', 500)->nullable();
            $t->string('state', 30)->index();
            $t->string('idempotency_key', 191)->unique();
            $t->unsignedInteger('template_version')->default(0);
            $t->unsignedInteger('attempt_count')->default(0);
            $t->unsignedInteger('max_attempts')->default(3);
            $t->timestamp('next_attempt_at')->nullable();
            $t->string('failure_class', 120)->nullable();
            $t->text('failure_message')->nullable();
            $t->json('safe_context')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('exhibition_notification_attempts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('delivery_id');
            $t->unsignedInteger('attempt_number');
            $t->string('state', 30);
            $t->string('failure_class', 120)->nullable();
            $t->text('failure_message')->nullable();
            $t->timestamp('attempted_at');
            $t->foreign('delivery_id')->references('id')->on('exhibition_notification_deliveries')->cascadeOnDelete();
            $t->unique(['delivery_id', 'attempt_number'], 'ex_notify_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_notification_attempts');
        Schema::dropIfExists('exhibition_notification_deliveries');
        Schema::dropIfExists('exhibition_notification_template_history');
        Schema::dropIfExists('exhibition_notification_templates');
    }
};
