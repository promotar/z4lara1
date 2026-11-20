<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_order_events')
            && Schema::hasColumns('store_orders', [
                'governorate',
                'delivery_phone',
                'shipping_company',
                'payment_collected_minor',
                'cancellation_reason',
                'return_reason',
                'version',
            ])
            && Schema::hasColumn('store_order_items', 'discount_minor')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE store_orders MODIFY status VARCHAR(32) NOT NULL');
            DB::table('store_orders')->where('status', 'fulfilled')->update(['status' => 'delivered']);
            DB::statement(<<<'SQL'
                ALTER TABLE store_orders
                MODIFY status ENUM(
                    'new', 'confirmed', 'processing', 'ready',
                    'shipped', 'delivered', 'cancelled', 'returned'
                ) NOT NULL DEFAULT 'new'
            SQL);
        }

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('governorate', 128)->nullable()->after('country_code');
            $table->string('district', 128)->nullable()->after('area');
            $table->string('street')->nullable()->after('district');
            $table->string('building', 128)->nullable()->after('street');
            $table->string('floor', 64)->nullable()->after('building');
            $table->string('apartment', 64)->nullable()->after('floor');
            $table->string('landmark')->nullable()->after('apartment');
            $table->string('delivery_phone', 64)->nullable()->after('landmark');
            $table->text('delivery_notes')->nullable()->after('delivery_phone');

            $table->string('shipping_company')->nullable()->after('customer_notes');
            $table->string('courier_name')->nullable()->after('shipping_company');
            $table->string('courier_phone', 64)->nullable()->after('courier_name');
            $table->string('tracking_number', 128)->nullable()->after('courier_phone');
            $table->text('shipping_notes')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('shipping_notes');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');

            $table->unsignedBigInteger('payment_collected_minor')->default(0)->after('total_minor');
            $table->timestamp('payment_collected_at')->nullable()->after('payment_collected_minor');
            $table->text('payment_note')->nullable()->after('payment_collected_at');

            $table->string('cancellation_reason_code', 64)->nullable()->after('payment_note');
            $table->text('cancellation_reason')->nullable()->after('cancellation_reason_code');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->text('return_reason')->nullable()->after('cancelled_at');
            $table->string('return_condition', 64)->nullable()->after('return_reason');
            $table->boolean('return_to_stock')->nullable()->after('return_condition');
            $table->timestamp('returned_at')->nullable()->after('return_to_stock');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            $table->unsignedInteger('version')->default(1)->after('updated_by');

            $table->index(['payment_method', 'payment_status'], 'store_orders_payment_idx');
            $table->index(['area', 'placed_at'], 'store_orders_area_placed_idx');
            $table->index(['total_minor', 'placed_at'], 'store_orders_total_placed_idx');
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_minor')->default(0)->after('unit_price_minor');
        });

        Schema::create('store_order_events', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->enum('actor_type', ['user', 'system', 'integration', 'customer'])->default('system');
            $table->string('actor_id', 191)->nullable();
            $table->string('actor_name')->nullable();
            $table->enum('visibility', ['internal', 'customer'])->default('internal');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at', 'id'], 'store_order_events_timeline_idx');
            $table->index(['event_type', 'created_at'], 'store_order_events_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_events');

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropColumn('discount_minor');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropIndex('store_orders_payment_idx');
            $table->dropIndex('store_orders_area_placed_idx');
            $table->dropIndex('store_orders_total_placed_idx');
            $table->dropColumn([
                'governorate', 'district', 'street', 'building', 'floor', 'apartment',
                'landmark', 'delivery_phone', 'delivery_notes', 'shipping_company',
                'courier_name', 'courier_phone', 'tracking_number', 'shipping_notes',
                'shipped_at', 'delivered_at', 'payment_collected_minor',
                'payment_collected_at', 'payment_note', 'cancellation_reason_code',
                'cancellation_reason', 'cancelled_at', 'return_reason',
                'return_condition', 'return_to_stock', 'returned_at', 'updated_by',
                'version',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE store_orders MODIFY status VARCHAR(32) NOT NULL');
            DB::table('store_orders')->where('status', 'new')->update(['status' => 'confirmed']);
            DB::table('store_orders')->whereIn('status', ['ready', 'shipped'])->update(['status' => 'processing']);
            DB::table('store_orders')->whereIn('status', ['delivered', 'returned'])->update(['status' => 'fulfilled']);
            DB::statement(<<<'SQL'
                ALTER TABLE store_orders
                MODIFY status ENUM('confirmed', 'processing', 'fulfilled', 'cancelled')
                NOT NULL DEFAULT 'confirmed'
            SQL);
        }
    }
};
