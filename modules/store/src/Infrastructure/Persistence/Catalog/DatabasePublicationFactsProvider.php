<?php

namespace Modules\Store\Infrastructure\Persistence\Catalog;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Catalog\ProductRecord;
use Modules\Store\Domain\Catalog\PublicationFacts;
use Modules\Store\Domain\Catalog\PublicationFactsProvider;

final class DatabasePublicationFactsProvider implements PublicationFactsProvider
{
    public function forProduct(ProductRecord $product, string $currency, DateTimeImmutable $at): PublicationFacts
    {
        $variants = DB::table('store_product_variants')
            ->where('product_id', $product->id)
            ->where('status', '!=', 'archived')
            ->get(['id', 'status', 'sku', 'is_default']);
        $active = $variants->where('status', 'active')->values();
        $activeIds = $active->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        $missingPrices = $activeIds === [] ? 0 : DB::table('store_product_variants as variants')
            ->whereIn('variants.id', $activeIds)
            ->whereNotExists(function ($query) use ($currency, $at): void {
                $query->selectRaw('1')
                    ->from('store_prices as prices')
                    ->whereColumn('prices.variant_id', 'variants.id')
                    ->where('prices.type', 'regular')
                    ->where('prices.status', 'active')
                    ->where('prices.currency', strtoupper($currency))
                    ->where(function ($window) use ($at): void {
                        $window->whereNull('prices.starts_at')->orWhere('prices.starts_at', '<=', $at);
                    })
                    ->where(function ($window) use ($at): void {
                        $window->whereNull('prices.ends_at')->orWhere('prices.ends_at', '>', $at);
                    });
            })
            ->count();

        $missingInventory = $activeIds === [] ? 0 : DB::table('store_product_variants as variants')
            ->whereIn('variants.id', $activeIds)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('store_inventory_items as inventory')
                    ->whereColumn('inventory.variant_id', 'variants.id');
            })
            ->count();

        $missingAttributes = DB::table('store_product_attribute_assignments as assignments')
            ->where('assignments.product_id', $product->id)
            ->where('assignments.is_required', true)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('store_product_attribute_values as attribute_values')
                    ->whereColumn('attribute_values.product_id', 'assignments.product_id')
                    ->whereColumn('attribute_values.attribute_id', 'assignments.attribute_id');
            })
            ->count();

        $axisCount = DB::table('store_product_attribute_assignments')
            ->where('product_id', $product->id)
            ->where('is_variant_axis', true)
            ->count();
        $invalidAxes = 0;
        if ($axisCount > 0) {
            foreach ($activeIds as $variantId) {
                if (DB::table('store_variant_option_values')->where('variant_id', $variantId)->count() !== $axisCount) {
                    $invalidAxes++;
                }
            }
        }

        $hasPrimaryMedia = DB::table('store_product_media as product_media')
            ->leftJoin('store_media_assets as media_assets', 'media_assets.id', '=', 'product_media.media_asset_id')
            ->where('product_media.product_id', $product->id)
            ->whereNull('product_media.variant_id')
            ->where('product_media.role', 'cover')
            ->where('product_media.is_primary', true)
            ->where('product_media.visibility', 'public')
            ->where(function ($query): void {
                $query->whereNotNull('product_media.external_source')
                    ->orWhere('media_assets.status', 'ready');
            })
            ->exists();

        return new PublicationFacts(
            hasDefaultTranslation: DB::table('store_product_translations')
                ->where('product_id', $product->id)
                ->where('locale', $product->defaultLocale)
                ->exists(),
            totalVariantCount: $variants->count(),
            activeVariantCount: $active->count(),
            activeDefaultVariantCount: $active->where('is_default', true)->count(),
            activeVariantsMissingSku: $active->filter(static fn (object $row): bool => trim((string) $row->sku) === '')->count(),
            activeVariantsMissingRegularPrice: $missingPrices,
            activeVariantsMissingInventory: $missingInventory,
            missingRequiredAttributeValues: $missingAttributes,
            invalidVariantAxisCount: $invalidAxes,
            hasPrimaryMedia: $hasPrimaryMedia,
        );
    }
}
