<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (collect([
            'store_prices',
            'store_promotions',
            'store_promotion_targets',
            'store_promotion_conditions',
            'store_promotion_actions',
        ])->every(fn (string $table): bool => Schema::hasTable($table))) {
            return;
        }

        Schema::create('store_prices', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('variant_id')->constrained('store_product_variants')->restrictOnDelete();
            $table->enum('type', ['regular', 'sale']);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(
                ['variant_id', 'currency', 'status', 'type', 'starts_at', 'ends_at', 'priority'],
                'store_prices_resolution_idx',
            );
        });

        Schema::create('store_promotions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name');
            $table->string('code', 128)->nullable()->unique();
            $table->enum('mode', ['automatic', 'code'])->default('automatic');
            $table->enum('status', ['draft', 'active', 'paused', 'expired', 'archived'])->default('draft');
            $table->integer('priority')->default(0);
            $table->enum('stacking_policy', ['exclusive', 'stackable', 'best_price'])->default('best_price');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('usage_limit')->nullable();
            $table->unsignedBigInteger('per_customer_limit')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->index(['status', 'starts_at', 'ends_at', 'priority'], 'store_promotions_schedule_idx');
        });

        Schema::create('store_promotion_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('store_promotions')->cascadeOnDelete();
            $table->enum('target_type', ['all_catalog', 'category', 'product', 'variant']);
            $table->char('target_public_id', 26)->nullable();
            // Application-generated canonical key; never accepted from a client request.
            $table->string('target_key', 64);
            $table->boolean('include_descendants')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_public_id'], 'store_promotion_targets_lookup_idx');
            $table->unique(['promotion_id', 'target_key'], 'store_promotion_targets_promotion_key_unique');
        });

        Schema::create('store_promotion_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('store_promotions')->cascadeOnDelete();
            $table->string('condition_type', 128);
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('payload');
            $table->timestamps();

            $table->index(['promotion_id', 'condition_type'], 'store_promotion_conditions_type_idx');
        });

        Schema::create('store_promotion_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('store_promotions')->cascadeOnDelete();
            $table->enum('action_type', ['percentage_discount', 'fixed_amount_discount']);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('maximum_discount_minor')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['promotion_id', 'action_type'], 'store_promotion_actions_type_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE store_promotion_targets
                ADD CONSTRAINT store_promotion_targets_shape_check
                CHECK (
                    (target_type = 'all_catalog' AND target_public_id IS NULL)
                    OR (target_type IN ('category', 'product', 'variant') AND target_public_id IS NOT NULL)
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_promotion_actions');
        Schema::dropIfExists('store_promotion_conditions');
        Schema::dropIfExists('store_promotion_targets');
        Schema::dropIfExists('store_promotions');
        Schema::dropIfExists('store_prices');
    }
};
