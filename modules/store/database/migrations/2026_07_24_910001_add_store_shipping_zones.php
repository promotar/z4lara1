<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_shipping_zones')
            && Schema::hasTable('store_shipping_zone_locations')
            && Schema::hasColumns('store_orders', [
                'shipping_method_code',
                'shipping_zone_name',
                'shipping_free_reason',
                'shipping_estimate',
            ])) {
            return;
        }

        Schema::create('store_shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name');
            $table->string('method_code', 64)->default('delivery');
            $table->unsignedBigInteger('delivery_cost_minor')->default(0);
            $table->unsignedBigInteger('free_shipping_threshold_minor')->nullable();
            $table->string('estimated_delivery', 255)->nullable();
            $table->text('customer_message')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('priority')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'priority'], 'store_shipping_zones_resolution_idx');
        });

        Schema::create('store_shipping_zone_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('store_shipping_zones')->cascadeOnDelete();
            $table->enum('match_type', ['country', 'governorate', 'city', 'area', 'postal_code']);
            $table->string('match_value', 255);
            $table->string('match_value_normalized', 255);
            $table->timestamps();
            $table->unique(
                ['shipping_zone_id', 'match_type', 'match_value_normalized'],
                'store_shipping_zone_location_unique',
            );
            $table->index(
                ['match_type', 'match_value_normalized'],
                'store_shipping_zone_location_match_idx',
            );
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('shipping_method_code', 64)->default('delivery')->after('shipping_minor');
            $table->string('shipping_zone_name')->nullable()->after('shipping_method_code');
            $table->string('shipping_free_reason')->nullable()->after('shipping_zone_name');
            $table->string('shipping_estimate')->nullable()->after('shipping_free_reason');
        });

        $now = now();
        $zoneId = (int) DB::table('store_shipping_zones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'Default Delivery Zone',
            'method_code' => 'delivery',
            'delivery_cost_minor' => max(0, (int) config('store.orders.shipping_minor', 0)),
            'free_shipping_threshold_minor' => null,
            'estimated_delivery' => null,
            'customer_message' => 'Delivery rules can be refined by the store administration.',
            'status' => 'active',
            'priority' => -1000,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $country = strtoupper(trim((string) config('store.default_country', 'JO')));
        DB::table('store_shipping_zone_locations')->insert([
            'shipping_zone_id' => $zoneId,
            'match_type' => 'country',
            'match_value' => $country,
            'match_value_normalized' => strtolower($country),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'shipping_method_code',
                'shipping_zone_name',
                'shipping_free_reason',
                'shipping_estimate',
            ]);
        });
        Schema::dropIfExists('store_shipping_zone_locations');
        Schema::dropIfExists('store_shipping_zones');
    }
};
