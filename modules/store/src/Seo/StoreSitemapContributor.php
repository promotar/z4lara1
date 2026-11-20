<?php

namespace Modules\Store\Seo;

use App\Platform\Core\Seo\SitemapContributor;
use Illuminate\Support\Facades\DB;

/**
 * Adds public Store product URLs to the sitemap while Store is active.
 */
final class StoreSitemapContributor implements SitemapContributor
{
    public function module(): string
    {
        return 'store';
    }

    public function sitemapEntries(): iterable
    {
        $products = DB::table('store_products as product')
            ->where('product.status', 'published')
            ->where('product.visibility', 'public')
            ->where('product.type_code', '!=', 'artwork')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('store_external_sellables as external')
                    ->whereColumn('external.product_id', 'product.id');
            })
            ->orderBy('product.id')
            ->cursor();

        foreach ($products as $product) {
            $translations = DB::table('store_product_translations')
                ->where('product_id', $product->id)
                ->get(['locale', 'slug']);

            $default = $translations->firstWhere('locale', (string) $product->default_locale)
                ?? $translations->first();

            if ($default === null || trim((string) $default->slug) === '') {
                continue;
            }

            $alternates = [];
            foreach ($translations as $translation) {
                if (trim((string) $translation->slug) === '') {
                    continue;
                }

                $alternates[(string) $translation->locale] = route(
                    'store.products.show',
                    ['slug' => $translation->slug],
                    false,
                ).'?lang='.urlencode((string) $translation->locale);
            }

            $defaultUrl = route('store.products.show', ['slug' => $default->slug], false);
            $alternates['x-default'] = $defaultUrl;

            yield [
                'loc' => route('store.products.show', ['slug' => $default->slug]),
                'lastmod' => $product->updated_at ? (string) $product->updated_at : null,
                'changefreq' => 'weekly',
                'priority' => 0.7,
                'alternates' => $alternates,
            ];
        }
    }
}
