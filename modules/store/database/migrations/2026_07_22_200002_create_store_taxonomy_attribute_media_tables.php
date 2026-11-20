<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $ownedTables = [
            'store_categories',
            'store_category_translations',
            'store_product_categories',
            'store_attributes',
            'store_attribute_translations',
            'store_attribute_options',
            'store_attribute_option_translations',
            'store_product_attribute_assignments',
            'store_product_attribute_values',
            'store_variant_option_values',
            'store_media_assets',
            'store_product_media',
            'store_product_media_translations',
        ];

        if (collect($ownedTables)->every(fn (string $table): bool => Schema::hasTable($table))) {
            return;
        }

        Schema::create('store_categories', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->restrictOnDelete();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->string('code', 128)->nullable()->unique();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('depth')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['parent_id', 'status', 'position'], 'store_categories_tree_idx');
        });

        Schema::create('store_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('store_categories')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'locale'], 'store_category_translations_category_locale_unique');
            $table->unique(['locale', 'slug'], 'store_category_translations_locale_slug_unique');
        });

        Schema::create('store_product_categories', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('store_products')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('store_categories')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['product_id', 'category_id']);
            $table->index(['category_id', 'position'], 'store_product_categories_category_idx');
        });

        Schema::create('store_attributes', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('code', 128)->unique();
            $table->enum('data_type', ['text', 'integer', 'decimal', 'boolean', 'select']);
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('supports_variant_axis')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('validation_schema')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('store_attribute_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('store_attributes')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->text('help_text')->nullable();
            $table->timestamps();

            $table->unique(['attribute_id', 'locale'], 'store_attribute_translations_attribute_locale_unique');
        });

        Schema::create('store_attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('attribute_id')->constrained('store_attributes')->restrictOnDelete();
            $table->string('code', 128);
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->unsignedInteger('position')->default(0);
            $table->string('swatch_type', 32)->nullable();
            $table->string('swatch_value')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->unique(['attribute_id', 'code'], 'store_attribute_options_attribute_code_unique');
        });

        Schema::create('store_attribute_option_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_option_id')->constrained('store_attribute_options')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->timestamps();

            $table->unique(['attribute_option_id', 'locale'], 'store_attribute_option_translations_option_locale_unique');
        });

        Schema::create('store_product_attribute_assignments', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('store_products')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('store_attributes')->restrictOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variant_axis')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->primary(['product_id', 'attribute_id'], 'store_product_attribute_assignments_primary');
            $table->index(['attribute_id', 'is_filterable'], 'store_product_attribute_assignments_filter_idx');
        });

        Schema::create('store_product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('store_attributes')->restrictOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('store_attribute_options')->restrictOnDelete();
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 24, 8)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id'], 'store_product_attribute_values_product_attribute_unique');
            $table->index(['attribute_id', 'option_id'], 'store_product_attribute_values_option_filter_idx');
            $table->index(['attribute_id', 'value_integer'], 'store_product_attribute_values_integer_filter_idx');
            $table->index(['attribute_id', 'value_decimal'], 'store_product_attribute_values_decimal_filter_idx');
        });

        Schema::create('store_variant_option_values', function (Blueprint $table): void {
            $table->foreignId('variant_id')->constrained('store_product_variants')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('store_attributes')->restrictOnDelete();
            $table->foreignId('attribute_option_id')->constrained('store_attribute_options')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['variant_id', 'attribute_id'], 'store_variant_option_values_variant_attribute_primary');
            $table->unique(['variant_id', 'attribute_option_id'], 'store_variant_option_values_variant_option_unique');
            $table->index(['attribute_id', 'attribute_option_id'], 'store_variant_option_values_filter_idx');
        });

        Schema::create('store_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('disk', 64);
            // Keep the composite (disk, path) unique key below the utf8mb4
            // index-byte limit used by supported MariaDB/MySQL versions.
            $table->string('path', 512);
            $table->string('original_filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->enum('status', ['processing', 'ready', 'failed', 'archived'])->default('processing');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path'], 'store_media_assets_disk_path_unique');
            $table->index(['checksum', 'size_bytes'], 'store_media_assets_checksum_idx');
        });

        Schema::create('store_product_media', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('product_id')->constrained('store_products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('store_product_variants')->restrictOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('store_media_assets')->restrictOnDelete();
            $table->string('external_source', 64)->nullable();
            $table->string('external_key', 255)->nullable();
            $table->enum('role', ['cover', 'gallery', 'thumbnail', 'swatch']);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->enum('visibility', ['public', 'hidden'])->default('public');
            $table->timestamps();

            $table->index(['product_id', 'variant_id', 'role', 'position'], 'store_product_media_context_idx');
            $table->index(['external_source', 'external_key'], 'store_product_media_external_idx');
        });

        Schema::create('store_product_media_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_media_id')->constrained('store_product_media')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->text('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['product_media_id', 'locale'], 'store_product_media_translations_media_locale_unique');
        });

        $this->addConditionalUniqueness();
    }

    private function addConditionalUniqueness(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE store_product_categories
                ADD COLUMN primary_product_guard BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN product_id ELSE NULL END) STORED,
                ADD UNIQUE INDEX store_product_categories_one_primary_unique (primary_product_guard)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE store_product_media
                ADD COLUMN primary_cover_guard VARCHAR(191)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN is_primary = 1 AND role = 'cover'
                                THEN CONCAT(product_id, ':', IFNULL(variant_id, 0))
                            ELSE NULL
                        END
                    ) STORED,
                ADD UNIQUE INDEX store_product_media_one_primary_cover_unique (primary_cover_guard)
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX store_product_categories_one_primary_unique
                ON store_product_categories (product_id)
                WHERE is_primary = 1
            SQL);

            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX store_product_media_one_primary_cover_unique
                ON store_product_media (product_id, COALESCE(variant_id, 0))
                WHERE is_primary = 1 AND role = 'cover'
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_media_translations');
        Schema::dropIfExists('store_product_media');
        Schema::dropIfExists('store_media_assets');
        Schema::dropIfExists('store_variant_option_values');
        Schema::dropIfExists('store_product_attribute_values');
        Schema::dropIfExists('store_product_attribute_assignments');
        Schema::dropIfExists('store_attribute_option_translations');
        Schema::dropIfExists('store_attribute_options');
        Schema::dropIfExists('store_attribute_translations');
        Schema::dropIfExists('store_attributes');
        Schema::dropIfExists('store_product_categories');
        Schema::dropIfExists('store_category_translations');
        Schema::dropIfExists('store_categories');
    }
};
