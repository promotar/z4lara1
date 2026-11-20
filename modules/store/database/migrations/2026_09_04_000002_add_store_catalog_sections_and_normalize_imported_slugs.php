<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_categories')) {
            return;
        }

        if (! Schema::hasColumn('store_categories', 'catalog_section')) {
            Schema::table('store_categories', function (Blueprint $table): void {
                $table->string('catalog_section', 32)->nullable()->after('code')->index('store_categories_catalog_section_idx');
            });
        }

        if (Schema::hasTable('store_category_translations')) {
            DB::transaction(function (): void {
                $translations = DB::table('store_category_translations')
                    ->orderBy('id')
                    ->get(['id', 'category_id', 'locale', 'name', 'slug']);

                foreach ($translations as $translation) {
                    $slug = (string) $translation->slug;
                    if (preg_match('/^(.*)-[a-f0-9]{8}$/i', $slug, $matches) !== 1) {
                        continue;
                    }

                    $canonical = rtrim((string) $matches[1], '-');
                    if ($canonical === '' || DB::table('store_category_translations')
                        ->where('locale', $translation->locale)
                        ->where('slug', $canonical)
                        ->where('id', '!=', $translation->id)
                        ->exists()) {
                        continue;
                    }

                    DB::table('store_category_translations')
                        ->where('id', $translation->id)
                        ->update(['slug' => $canonical, 'updated_at' => now()]);
                }

                $clothingNames = [
                    'apparel', 'clothes', 'clothing', 'fashion', 'textiles',
                    'ملابس', 'منسوجات', 'أزياء', 'ازياء',
                ];
                $rootCategoryIds = DB::table('store_categories as category')
                    ->join('store_category_translations as translation', 'translation.category_id', '=', 'category.id')
                    ->whereNull('category.parent_id')
                    ->whereIn(DB::raw('LOWER(TRIM(translation.name))'), $clothingNames)
                    ->pluck('category.id');

                if ($rootCategoryIds->isNotEmpty()) {
                    DB::table('store_categories')
                        ->whereIn('id', $rootCategoryIds)
                        ->whereNull('catalog_section')
                        ->update(['catalog_section' => 'clothing', 'updated_at' => now()]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_categories') && Schema::hasColumn('store_categories', 'catalog_section')) {
            Schema::table('store_categories', function (Blueprint $table): void {
                $table->dropIndex('store_categories_catalog_section_idx');
                $table->dropColumn('catalog_section');
            });
        }
    }
};
