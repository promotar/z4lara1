<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_inventory_items')
            && Schema::hasTable('store_inventory_movements')
            && Schema::hasTable('store_inventory_reservations')) {
            return;
        }

        Schema::create('store_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('variant_id')->unique()->constrained('store_product_variants')->restrictOnDelete();
            $table->enum('tracking_mode', ['tracked', 'untracked'])->default('tracked');
            $table->bigInteger('on_hand')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->bigInteger('reorder_point')->nullable();
            $table->unsignedBigInteger('safety_stock')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tracking_mode', 'on_hand', 'reserved'], 'store_inventory_items_availability_idx');
        });

        Schema::create('store_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('inventory_item_id')->constrained('store_inventory_items')->restrictOnDelete();
            $table->enum('type', [
                'opening_balance',
                'receipt',
                'manual_adjustment',
                'sale_commit',
                'return',
                'damage',
                'correction',
            ]);
            $table->bigInteger('quantity_delta');
            $table->bigInteger('balance_after');
            $table->string('reference_type', 64)->nullable();
            $table->char('reference_public_id', 26)->nullable();
            $table->string('reason_code', 128);
            $table->text('note')->nullable();
            $table->string('idempotency_key', 191);
            $table->enum('actor_type', ['user', 'system', 'integration']);
            $table->string('actor_id', 191)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['inventory_item_id', 'idempotency_key'],
                'store_inventory_movements_item_idempotency_unique',
            );
            $table->index(['inventory_item_id', 'occurred_at', 'id'], 'store_inventory_movements_history_idx');
            $table->index(['reference_type', 'reference_public_id'], 'store_inventory_movements_reference_idx');
        });

        Schema::create('store_inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('inventory_item_id')->constrained('store_inventory_items')->restrictOnDelete();
            $table->unsignedBigInteger('quantity');
            $table->enum('status', ['active', 'committed', 'released', 'expired', 'cancelled'])->default('active');
            $table->string('reference_type', 64);
            $table->char('reference_public_id', 26);
            $table->string('idempotency_key', 191);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->unique(
                ['inventory_item_id', 'idempotency_key'],
                'store_inventory_reservations_item_idempotency_unique',
            );
            $table->index(['status', 'expires_at'], 'store_inventory_reservations_expiry_idx');
            $table->index(['reference_type', 'reference_public_id'], 'store_inventory_reservations_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_reservations');
        Schema::dropIfExists('store_inventory_movements');
        Schema::dropIfExists('store_inventory_items');
    }
};
