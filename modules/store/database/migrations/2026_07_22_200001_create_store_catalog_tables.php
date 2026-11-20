<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_products')
            && Schema::hasTable('store_product_translations')
            && Schema::hasTable('store_product_variants')) {
            return;
        }

        Schema::create('store_products', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('type_code', 64);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'hidden'])->default('hidden');
            $table->string('internal_code', 128)->nullable()->unique();
            $table->string('default_locale', 16);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'visibility', 'published_at'], 'store_products_catalog_idx');
            $table->index(['type_code', 'status'], 'store_products_type_status_idx');
        });

        Schema::create('store_product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('slug');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale'], 'store_product_translations_product_locale_unique');
            $table->unique(['locale', 'slug'], 'store_product_translations_locale_slug_unique');
            $table->index(['locale', 'name'], 'store_product_translations_locale_name_idx');
        });

        Schema::create('store_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('product_id')->constrained('store_products')->restrictOnDelete();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('inactive');
            $table->string('sku', 64)->nullable()->unique();
            $table->string('barcode', 128)->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->string('combination_key', 512);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('weight_minor')->nullable();
            $table->string('weight_unit', 16)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->unique(['product_id', 'combination_key'], 'store_product_variants_combination_unique');
            $table->index(['product_id', 'status', 'position'], 'store_product_variants_product_status_idx');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE store_product_variants
                ADD COLUMN default_product_guard BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN is_default = 1 AND archived_at IS NULL THEN product_id
                            ELSE NULL
                        END
                    ) STORED,
                ADD UNIQUE INDEX store_product_variants_one_default_unique (default_product_guard)
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX store_product_variants_one_default_unique
                ON store_product_variants (product_id)
                WHERE is_default = 1 AND archived_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_variants');
        Schema::dropIfExists('store_product_translations');
        Schema::dropIfExists('store_products');
    }
};
