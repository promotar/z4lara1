<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_setting_audits')
            && Schema::hasColumns('store_orders', ['tax_minor', 'tax_name', 'prices_include_tax'])) {
            return;
        }

        Schema::create('store_setting_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 128)->index();
            $table->json('old_value')->nullable();
            $table->json('new_value');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_minor')->default(0)->after('discount_minor');
            $table->string('tax_name', 64)->nullable()->after('tax_minor');
            $table->boolean('prices_include_tax')->default(false)->after('tax_name');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn(['tax_minor', 'tax_name', 'prices_include_tax']);
        });
        Schema::dropIfExists('store_setting_audits');
    }
};
