<?php

namespace Modules\Store\Infrastructure\ReadModel;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Store\Contracts\Catalog\ProductPage;
use Modules\Store\Contracts\Catalog\ProductReadContext;
use Modules\Store\Contracts\Catalog\ProductReadService;
use Modules\Store\Contracts\Catalog\ProductSearchQuery;
use Modules\Store\Contracts\Catalog\ProductView;
use Modules\Store\Contracts\Catalog\VariantView;
use Modules\Store\Domain\Pricing\PriceRecord;
use Modules\Store\Domain\Pricing\PriceResolver;
use Modules\Store\Domain\Pricing\PriceType;
use Modules\Store\Domain\Shared\Exceptions\PriceConflict;
use Modules\Store\Domain\Shared\Money;

final readonly class DatabaseProductReadService implements ProductReadService
{
    public function __construct(private PriceResolver $prices) {}

    public function findByPublicId(string $publicId, ProductReadContext $context): ?ProductView
    {
        $row = $this->products()->where('public_id', $publicId)->first();

        return $row === null ? null : ($this->hydrate([$row], $context)[0] ?? null);
    }

    public function findBySlug(string $slug, ProductReadContext $context): ?ProductView
    {
        $translations = DB::table('store_product_translations')
            ->where('slug', $slug)
            ->whereIn('locale', array_values(array_unique([$context->locale, $context->fallbackLocale])))
            ->get(['product_id', 'locale']);
        $match = $translations->firstWhere('locale', $context->locale)
            ?? $translations->firstWhere('locale', $context->fallbackLocale);
        if ($match === null) {
            return null;
        }
        $row = $this->products()->where('id', (int) $match->product_id)->first();

        return $row === null ? null : ($this->hydrate([$row], $context)[0] ?? null);
    }

    public function search(ProductSearchQuery $query): ProductPage
    {
        $builder = $this->products()
            ->where('status', $query->status)
            ->where('visibility', $query->visibility);
        if ($query->categoryPublicId !== null) {
            $builder->whereExists(function ($category) use ($query): void {
                $category->selectRaw('1')
                    ->from('store_product_categories as product_categories')
                    ->join('store_categories as categories', 'categories.id', '=', 'product_categories.category_id')
                    ->whereColumn('product_categories.product_id', 'store_products.id')
                    ->where('categories.public_id', $query->categoryPublicId);
            });
        }

        $total = (clone $builder)->count();
        $rows = $builder->orderBy('id')
            ->offset(max(0, $query->offset))
            ->limit(max(1, min(100, $query->limit)))
            ->get()
            ->all();

        return new ProductPage(
            items: $this->hydrate($rows, $query->context),
            total: $total,
            limit: max(1, min(100, $query->limit)),
            offset: max(0, $query->offset),
        );
    }

    /** @param list<object> $products @return list<ProductView> */
    private function hydrate(array $products, ProductReadContext $context): array
    {
        if ($products === []) {
            return [];
        }
        $productIds = array_map(static fn (object $row): int => (int) $row->id, $products);
        $locales = [$context->locale, $context->fallbackLocale];
        foreach ($products as $product) {
            $locales[] = (string) $product->default_locale;
        }
        $translations = DB::table('store_product_translations')
            ->whereIn('product_id', $productIds)
            ->whereIn('locale', array_values(array_unique($locales)))
            ->get(['product_id', 'locale', 'name', 'slug'])
            ->groupBy('product_id');
        $variants = DB::table('store_product_variants')
            ->whereIn('product_id', $productIds)
            ->where('status', '!=', 'archived')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'public_id', 'product_id', 'sku', 'status', 'is_default'])
            ->groupBy('product_id');
        $variantIds = $variants->flatten(1)->pluck('id')->all();
        $priceRows = $variantIds === [] ? collect() : DB::table('store_prices')
            ->whereIn('variant_id', $variantIds)
            ->where('currency', strtoupper($context->currency))
            ->get()
            ->groupBy('variant_id');
        $inventoryRows = $variantIds === [] ? collect() : DB::table('store_inventory_items')
            ->whereIn('variant_id', $variantIds)
            ->get(['variant_id', 'tracking_mode', 'on_hand', 'reserved', 'safety_stock'])
            ->keyBy('variant_id');
        $views = [];

        foreach ($products as $product) {
            $translation = $this->translation($translations->get($product->id, collect()), $product, $context);
            if ($translation === null) {
                continue;
            }
            $variantViews = [];
            $amounts = [];
            foreach ($variants->get($product->id, collect()) as $variant) {
                $amount = $this->effectiveAmount($priceRows->get($variant->id, collect()), $context);
                if ($amount !== null) {
                    $amounts[] = $amount;
                }
                $inventory = $inventoryRows->get($variant->id);
                $available = $inventory === null || (string) $inventory->tracking_mode !== 'tracked'
                    ? null
                    : (int) $inventory->on_hand - (int) $inventory->reserved - (int) $inventory->safety_stock;
                $variantViews[] = new VariantView(
                    publicId: (string) $variant->public_id,
                    sku: $variant->sku === null ? null : (string) $variant->sku,
                    status: (string) $variant->status,
                    isDefault: (bool) $variant->is_default,
                    effectivePriceMinor: $amount,
                    currency: strtoupper($context->currency),
                    availableQuantity: $available,
                    resolvedAt: $context->resolvedAt,
                );
            }
            $views[] = new ProductView(
                publicId: (string) $product->public_id,
                typeCode: (string) $product->type_code,
                status: (string) $product->status,
                visibility: (string) $product->visibility,
                locale: (string) $translation->locale,
                usedFallback: (string) $translation->locale !== $context->locale,
                name: (string) $translation->name,
                slug: (string) $translation->slug,
                variants: $variantViews,
                minimumPriceMinor: $amounts === [] ? null : min($amounts),
                maximumPriceMinor: $amounts === [] ? null : max($amounts),
                currency: strtoupper($context->currency),
                resolvedAt: $context->resolvedAt,
            );
        }

        return $views;
    }

    private function translation(Collection $translations, object $product, ProductReadContext $context): ?object
    {
        foreach (array_values(array_unique([$context->locale, $context->fallbackLocale, (string) $product->default_locale])) as $locale) {
            $translation = $translations->firstWhere('locale', $locale);
            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    private function effectiveAmount(Collection $rows, ProductReadContext $context): ?int
    {
        $records = $rows->map(static fn (object $row): PriceRecord => new PriceRecord(
            publicId: (string) $row->public_id,
            type: PriceType::from((string) $row->type),
            money: new Money((int) $row->amount_minor, (string) $row->currency),
            startsAt: $row->starts_at === null ? null : new DateTimeImmutable((string) $row->starts_at),
            endsAt: $row->ends_at === null ? null : new DateTimeImmutable((string) $row->ends_at),
            priority: (int) $row->priority,
            status: (string) $row->status,
        ))->all();

        try {
            return $this->prices->resolve($records, strtoupper($context->currency), $context->resolvedAt)->effective->amountMinor;
        } catch (PriceConflict) {
            return null;
        }
    }

    private function products(): Builder
    {
        return DB::table('store_products')->select([
            'id', 'public_id', 'type_code', 'status', 'visibility', 'default_locale', 'published_at',
        ]);
    }
}
