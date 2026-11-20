<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_shipping_addresses')
            && Schema::hasColumns('store_orders', ['invoice_number', 'invoice_path', 'invoice_issued_at'])) {
            return;
        }

        Schema::create('store_shipping_addresses', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('label', 64)->default('Home');
            $table->string('recipient_name');
            $table->string('phone', 64);
            $table->char('country_code', 2)->default('JO');
            $table->string('governorate', 128)->nullable();
            $table->string('city', 128);
            $table->string('area', 128)->nullable();
            $table->string('district', 128)->nullable();
            $table->string('street');
            $table->string('building', 128)->nullable();
            $table->string('floor', 64)->nullable();
            $table->string('apartment', 64)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('landmark')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default'], 'store_addresses_user_default_idx');
            $table->index(['user_id', 'created_at'], 'store_addresses_user_created_idx');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('invoice_number', 64)->nullable()->after('return_to_stock');
            $table->string('invoice_path', 1024)->nullable()->after('invoice_number');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_path');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn(['invoice_number', 'invoice_path', 'invoice_issued_at']);
        });

        Schema::dropIfExists('store_shipping_addresses');
    }
};
