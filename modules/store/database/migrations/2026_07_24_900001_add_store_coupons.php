<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_coupon_usages')
            && Schema::hasColumns('store_promotions', ['code_normalized', 'minimum_subtotal_minor'])
            && Schema::hasColumns('store_orders', [
                'coupon_code',
                'coupon_discount_type',
                'coupon_fixed_amount_minor',
                'coupon_percentage_basis_points',
                'coupon_eligible_items',
            ])) {
            return;
        }

        Schema::table('store_promotions', function (Blueprint $table): void {
            $table->string('code_normalized', 128)->nullable()->after('code');
            $table->unsignedBigInteger('minimum_subtotal_minor')->default(0)->after('per_customer_limit');
        });

        $normalizedCodes = [];
        DB::table('store_promotions')
            ->whereNotNull('code')
            ->eachById(function (object $promotion) use (&$normalizedCodes): void {
                $normalized = strtoupper(trim((string) $promotion->code));
                if ($normalized === '' || isset($normalizedCodes[$normalized])) {
                    return;
                }
                $normalizedCodes[$normalized] = true;
                DB::table('store_promotions')->where('id', $promotion->id)->update([
                    'code_normalized' => $normalized,
                ]);
            });

        Schema::table('store_promotions', function (Blueprint $table): void {
            $table->unique('code_normalized', 'store_promotions_code_normalized_unique');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('coupon_code', 128)->nullable()->after('discount_minor');
            $table->string('coupon_discount_type', 32)->nullable()->after('coupon_code');
            $table->unsignedBigInteger('coupon_fixed_amount_minor')->nullable()->after('coupon_discount_type');
            $table->unsignedInteger('coupon_percentage_basis_points')->nullable()->after('coupon_fixed_amount_minor');
            $table->json('coupon_eligible_items')->nullable()->after('coupon_percentage_basis_points');
        });

        Schema::create('store_coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('promotion_id')->constrained('store_promotions')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('guest_identity_hash', 64)->nullable();
            $table->enum('status', ['counted', 'released'])->default('counted');
            $table->timestamp('counted_at')->useCurrent();
            $table->timestamp('released_at')->nullable();

            $table->unique(['promotion_id', 'order_id'], 'store_coupon_usage_promotion_order_unique');
            $table->index(['promotion_id', 'status'], 'store_coupon_usage_total_idx');
            $table->index(['promotion_id', 'user_id', 'status'], 'store_coupon_usage_user_idx');
            $table->index(['promotion_id', 'guest_identity_hash', 'status'], 'store_coupon_usage_guest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_coupon_usages');

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'coupon_code',
                'coupon_discount_type',
                'coupon_fixed_amount_minor',
                'coupon_percentage_basis_points',
                'coupon_eligible_items',
            ]);
        });

        Schema::table('store_promotions', function (Blueprint $table): void {
            $table->dropUnique('store_promotions_code_normalized_unique');
            $table->dropColumn(['code_normalized', 'minimum_subtotal_minor']);
        });
    }
};
