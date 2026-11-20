<?php

namespace Modules\Store\Seo;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Store\Application\Reviews\ProductReviewService;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Throwable;

/**
 * SEO adapter for Store product and catalog pages.
 */
final class StoreSeoContributor implements SeoContributor
{
    public function __construct(
        private readonly StorefrontCatalogService $catalog,
        private readonly ProductReviewService $reviews,
    ) {
    }

    public function module(): string
    {
        return 'store';
    }

    public function supports(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'store.index',
            'store.products.show',
            'store.artworks.index',
            'store.search',
        ], true);
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            match ($request->route()?->getName()) {
                'store.products.show' => $this->product($seo, $request),
                'store.index' => $this->catalogPage($seo, 'Store', 'Shop original artworks, products and creative pieces from ART INPA.'),
                'store.artworks.index' => $this->catalogPage($seo, 'Artworks', 'Browse original artworks available through ART INPA.'),
                'store.search' => $this->search($seo, $request),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function product(SeoData $seo, Request $request): void
    {
        $slug = (string) ($request->route('slug') ?: $request->segment(2));
        $product = $this->catalog->product($slug);

        if ($product === null || ($product['source_owner'] ?? null) === 'exhibitions') {
            return;
        }

        $description = $this->text($product['meta_description'] ?? $product['short_description'] ?? $product['description'] ?? null, 160);
        $canonical = route('store.products.show', ['slug' => $product['slug']]);

        $seo->title = trim((string) ($product['meta_title'] ?: $product['name'])) ?: null;
        $seo->description = $description;
        $seo->canonical = $canonical;
        $seo->ogType = 'product';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $description;
        $seo->twitterTitle = $seo->title;
        $seo->twitterDescription = $description;

        $image = $this->image($product);
        if ($image !== null) {
            $seo->image = $image;
            $seo->imageAlt = $product['name'];
            $seo->twitterImage = $image;
            $seo->twitterCard = 'summary_large_image';
        }

        $seo->extra['store_product_id'] = (int) $product['id'];
        $seo->extra['store_product_slug'] = (string) $product['slug'];
        $seo->extra['content_title'] = strip_tags((string) ($product['name'] ?? ''));
        $seo->extra['content_description'] = strip_tags((string) ($product['short_description'] ?? $product['description'] ?? ''));
        $seo->extra['content_text'] = implode(' ', array_filter([
            strip_tags((string) ($product['name'] ?? '')),
            strip_tags((string) ($product['short_description'] ?? '')),
            strip_tags((string) ($product['description'] ?? '')),
        ]));
        $seo->extra['replace_title'] = true;

        $seo->schema[] = $this->productSchema($product, $seo, $canonical, $description, $image);
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => 'Store', 'item' => route('store.index')],
            ['name' => $product['name'], 'item' => $canonical],
        ]);
    }

    private function catalogPage(SeoData $seo, string $title, string $description): void
    {
        $seo->title = $title;
        $seo->description = $description;
        $seo->ogTitle = $title;
        $seo->ogDescription = $description;
        $seo->extra['replace_title'] = true;
        $seo->schema[] = $this->collectionSchema($seo, $title);
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => $title, 'item' => $seo->canonical],
        ]);
    }

    private function search(SeoData $seo, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));

        $seo->title = $term !== '' ? 'Store search: '.$term : 'Store search';
        $seo->description = 'Search the ART INPA store.';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $seo->description;
        $seo->extra['replace_title'] = true;
        $seo->robots = $seo->robots->merge(Robots::noindex());
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function productSchema(array $product, SeoData $seo, string $canonical, ?string $description, ?string $image): array
    {
        $price = $product['price']['minor'] ?? null;
        $currency = (string) ($product['price']['currency'] ?? 'JOD');
        $available = (bool) ($product['available'] ?? false);

        $offers = $price === null ? null : [
            '@type' => 'Offer',
            'url' => $canonical,
            'price' => number_format(((int) $price) / 100, 2, '.', ''),
            'priceCurrency' => $currency,
            'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ];

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $description,
            'sku' => $product['sku'] ?? null,
            'image' => $image,
            'url' => $canonical,
            'brand' => filled($product['brand_name'] ?? null)
                ? ['@type' => 'Brand', 'name' => $product['brand_name']]
                : null,
            'category' => $product['categories']->first()['name'] ?? null,
            'offers' => $offers,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        $rating = $this->reviews->forProduct((int) $product['id']);
        if ((int) ($rating['count'] ?? 0) > 0 && (float) ($rating['average'] ?? 0) > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $rating['average'], 1, '.', ''),
                'reviewCount' => (int) $rating['count'],
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function image(array $product): ?string
    {
        $media = $product['media'] ?? null;

        if ($media instanceof \Illuminate\Support\Collection) {
            $first = $media->first();
        } elseif (is_array($media)) {
            $first = reset($media);
        } else {
            $first = null;
        }

        $url = is_array($first)
            ? ($first['url'] ?? $first['src'] ?? null)
            : (is_string($first) ? $first : null);

        if (! filled($url)) {
            return null;
        }

        $url = (string) $url;

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionSchema(SeoData $seo, string $name): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $seo->description,
            'url' => $seo->canonical,
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<int, array{name: string, item: ?string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if (filled($item['item'])) {
                $entry['item'] = $item['item'];
            }

            $elements[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    private function text(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit);
    }
}
