<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('fulfillment_id')->unique();
            $table->unsignedBigInteger('artist_user_id');
            $table->string('state', 40)->index();
            $table->char('currency', 3);
            $table->unsignedTinyInteger('scale')->default(3);
            $table->json('rules_snapshot');
            foreach (['sale_price', 'order_discount', 'platform_commission', 'payment_cost', 'packaging_cost', 'shipping_cost', 'insurance', 'tax', 'refund'] as $name) {
                $table->unsignedBigInteger($name.'_minor')->default(0);
            }
            $table->bigInteger('adjustment_minor')->default(0);
            $table->bigInteger('artist_net_minor');
            $table->timestamp('payment_collected_at')->nullable();
            $table->timestamp('return_window_ends_at')->nullable();
            $table->timestamp('finance_approved_at')->nullable();
            $table->unsignedBigInteger('finance_approved_by')->nullable();
            $table->string('payout_method', 80)->nullable();
            $table->string('payout_reference', 191)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->foreign('fulfillment_id')->references('id')->on('exhibition_fulfillments')->restrictOnDelete();
            $table->index(['artist_user_id', 'state'], 'ex_settlement_artist_state_idx');
        });
        Schema::create('exhibition_settlement_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('settlement_id');
            $table->string('entry_type', 50);
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key', 191)->unique();
            $table->string('reference', 191)->nullable();
            $table->text('description');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');
            $table->foreign('settlement_id')->references('id')->on('exhibition_settlements')->cascadeOnDelete();
            $table->index(['settlement_id', 'occurred_at'], 'ex_settlement_ledger_idx');
        });
        Schema::create('exhibition_settlement_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('settlement_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 50);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('settlement_id')->references('id')->on('exhibition_settlements')->cascadeOnDelete();
        });
        Schema::create('exhibition_payout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('settlement_id');
            $table->string('idempotency_key', 191)->unique();
            $table->string('provider', 80);
            $table->string('provider_reference', 191)->nullable()->unique();
            $table->string('state', 30);
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempt_number');
            $table->timestamps();
            $table->foreign('settlement_id')->references('id')->on('exhibition_settlements')->cascadeOnDelete();
        });
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER exhibition_ledger_no_update BEFORE UPDATE ON exhibition_settlement_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement ledger entries are immutable'");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER exhibition_ledger_no_update BEFORE UPDATE ON exhibition_settlement_ledger_entries BEGIN SELECT RAISE(ABORT, 'Settlement ledger entries are immutable'); END");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS exhibition_ledger_no_update');
        }
        Schema::dropIfExists('exhibition_payout_attempts');
        Schema::dropIfExists('exhibition_settlement_events');
        Schema::dropIfExists('exhibition_settlement_ledger_entries');
        Schema::dropIfExists('exhibition_settlements');
    }
};
