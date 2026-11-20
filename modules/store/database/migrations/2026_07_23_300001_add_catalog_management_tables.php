<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_brands')
            && Schema::hasColumns('store_products', ['brand_id', 'tags', 'catalog_position'])
            && Schema::hasColumn('store_categories', 'image_path')) {
            return;
        }

        Schema::create('store_brands', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->enum('status', ['active', 'hidden', 'archived'])->default('active');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['status', 'position'], 'store_brands_status_position_idx');
        });

        Schema::table('store_products', function (Blueprint $table): void {
            $table->foreignId('brand_id')->nullable()->after('internal_code')
                ->constrained('store_brands')->nullOnDelete();
            $table->json('tags')->nullable()->after('is_featured');
            $table->unsignedInteger('catalog_position')->default(0)->after('tags');

            $table->index(['brand_id', 'status'], 'store_products_brand_status_idx');
            $table->index(['catalog_position', 'updated_at'], 'store_products_catalog_position_idx');
        });

        Schema::table('store_categories', function (Blueprint $table): void {
            $table->string('image_path', 512)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('store_categories', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });

        Schema::table('store_products', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
            $table->dropIndex('store_products_brand_status_idx');
            $table->dropIndex('store_products_catalog_position_idx');
            $table->dropColumn(['brand_id', 'tags', 'catalog_position']);
        });

        Schema::dropIfExists('store_brands');
    }
};
