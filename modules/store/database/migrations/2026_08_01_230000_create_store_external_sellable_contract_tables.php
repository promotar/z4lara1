<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_external_sellables')
            && Schema::hasTable('store_external_sellable_operations')) {
            return;
        }

        Schema::create('store_external_sellables', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_code', 64);
            $table->string('external_id', 128);
            $table->foreignId('product_id')->unique()->constrained('store_products')->restrictOnDelete();
            $table->foreignId('variant_id')->unique()->constrained('store_product_variants')->restrictOnDelete();
            $table->unsignedInteger('source_revision');
            $table->string('state', 32)->default('published');
            $table->char('payload_hash', 64);
            $table->json('metadata')->nullable();
            $table->text('last_failure')->nullable();
            $table->timestamps();

            $table->unique(['owner_code', 'external_id'], 'store_external_sellables_owner_external_unique');
            $table->index(['owner_code', 'state'], 'store_external_sellables_owner_state_idx');
        });

        Schema::create('store_external_sellable_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_code', 64);
            $table->string('external_id', 128);
            $table->string('idempotency_key', 191);
            $table->string('action', 32);
            $table->json('response');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['owner_code', 'idempotency_key'], 'store_external_sellable_ops_idempotency_unique');
            $table->index(['owner_code', 'external_id', 'created_at'], 'store_external_sellable_ops_external_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_external_sellable_operations');
        Schema::dropIfExists('store_external_sellables');
    }
};
