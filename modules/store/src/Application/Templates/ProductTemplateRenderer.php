<?php

namespace Modules\Store\Application\Templates;

use Illuminate\Support\Collection;
use Modules\Store\Models\StoreProductTemplate;

final class ProductTemplateRenderer
{
    /**
     * @param  iterable<int, array<string, mixed>>  $products
     * @param  array<string, scalar|null>  $globals
     * @param  array{wrap_products?: bool, include_script?: bool}  $options
     */
    public function render(StoreProductTemplate $template, iterable $products, array $globals = [], array $options = []): string
    {
        $items = $products instanceof Collection ? $products->values() : collect($products)->values();
        $markup = (string) $template->html_code;

        $markup = preg_replace_callback(
            '/\{\{#products\}\}([\s\S]*?)\{\{\/products\}\}/i',
            function (array $match) use ($items, $options): string {
                $rendered = $items
                    ->map(fn (array $product): string => $this->renderProductMarkup($match[1], $product))
                    ->implode('');

                return ($options['wrap_products'] ?? false)
                    ? '<div data-store-template-items>'.$rendered.'</div>'
                    : $rendered;
            },
            $markup,
        ) ?? $markup;

        if ($items->count() === 1) {
            $markup = $this->renderProductMarkup($markup, $items->first());
        }

        $markup = $this->replaceTokens($markup, array_merge([
            'site_name' => (string) config('app.name', 'Art Z'),
            'catalog_title' => 'Products',
            'results_count' => (string) $items->count(),
        ], $globals));

        $slug = e((string) $template->slug);
        $output = '<div data-store-template="'.$slug.'">'
            .'<style data-store-template-style="'.$slug.'">'.(string) $template->css_code.'</style>'
            .$markup
            .'</div>';

        if (($options['include_script'] ?? true) && trim((string) $template->js_code) !== '') {
            $output .= '<script src="'.e(route('store.template-script', ['slug' => $template->slug], false)).'" defer data-store-template-script="'.$slug.'"></script>';
        }

        return $output;
    }

    /** @param array<string, mixed> $product */
    private function renderProductMarkup(string $markup, array $product): string
    {
        $categories = collect($product['categories'] ?? [])->values();
        $tags = collect($product['tags'] ?? [])->values();

        $markup = $this->renderCollection($markup, 'categories', $categories, function (mixed $category): array {
            $item = is_array($category) ? $category : [];
            $slug = (string) ($item['slug'] ?? '');

            return [
                'name' => e((string) ($item['name'] ?? '')),
                'slug' => e($slug),
                'url' => $slug === '' ? '' : e(route('store.categories.show', ['slug' => $slug], false)),
            ];
        });

        $markup = $this->renderCollection($markup, 'tags', $tags, function (mixed $tag): array {
            $value = is_array($tag) ? (string) ($tag['name'] ?? $tag['slug'] ?? '') : (string) $tag;

            return [
                'name' => e($value),
                'slug' => e($value),
                'url' => e(route('store.index', ['tag' => $value], false)),
            ];
        });

        $values = $this->productValues($product);
        foreach (['has_image', 'on_sale', 'is_featured', 'available', 'is_artwork'] as $condition) {
            $markup = $this->renderCondition($markup, $condition, ($values[$condition] ?? '0') === '1');
        }

        return $this->replaceTokens($markup, $values);
    }

    /**
     * @param Collection<int, mixed> $items
     * @param callable(mixed): array<string, scalar|null> $values
     */
    private function renderCollection(string $markup, string $name, Collection $items, callable $values): string
    {
        return preg_replace_callback(
            '/\{\{#'.preg_quote($name, '/').'\}\}([\s\S]*?)\{\{\/'.$name.'\}\}/i',
            fn (array $match): string => $items->map(fn (mixed $item): string => $this->replaceTokens($match[1], $values($item)))->implode(''),
            $markup,
        ) ?? $markup;
    }

    private function renderCondition(string $markup, string $name, bool $value): string
    {
        $markup = preg_replace_callback(
            '/\{\{#'.preg_quote($name, '/').'\}\}([\s\S]*?)\{\{\/'.$name.'\}\}/i',
            fn (array $match): string => $value ? $match[1] : '',
            $markup,
        ) ?? $markup;

        return preg_replace_callback(
            '/\{\{\^'.preg_quote($name, '/').'\}\}([\s\S]*?)\{\{\/'.$name.'\}\}/i',
            fn (array $match): string => $value ? '' : $match[1],
            $markup,
        ) ?? $markup;
    }

    /** @param array<string, mixed> $product @return array<string, string> */
    private function productValues(array $product): array
    {
        $image = is_array($product['image'] ?? null) ? $product['image'] : [];
        $categories = collect($product['categories'] ?? [])->pluck('name')->filter()->implode(', ');
        $tags = collect($product['tags'] ?? [])->map(fn (mixed $tag): string => is_array($tag) ? (string) ($tag['name'] ?? $tag['slug'] ?? '') : (string) $tag)->filter()->implode(', ');
        $artwork = is_array($product['artwork'] ?? null) ? $product['artwork'] : [];
        $slug = (string) ($product['slug'] ?? '');
        $brandSlug = (string) ($product['brand_slug'] ?? '');
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($product['description'] ?? ''))) ?? '');
        $shortDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($product['short_description'] ?? ''))) ?? '');
        $available = (bool) ($product['available'] ?? false);
        $onSale = (bool) ($product['on_sale'] ?? false);
        $featured = (bool) ($product['is_featured'] ?? false);
        $isArtwork = (string) ($product['type_code'] ?? '') === 'artwork';

        return [
            'public_id' => e((string) ($product['public_id'] ?? '')),
            'name' => e((string) ($product['name'] ?? '')),
            'slug' => e($slug),
            'url' => $slug === '' ? '' : e(route('store.products.show', ['slug' => $slug], false)),
            'quick_view_url' => $slug === '' ? '' : e(route('store.products.quick-view', ['slug' => $slug], false)),
            'type_code' => e((string) ($product['type_code'] ?? 'simple')),
            'short_description' => e($shortDescription),
            'description' => e($description),
            'artist_name' => e((string) ($product['artist_name'] ?? '')),
            'brand' => e((string) ($product['brand_name'] ?? '')),
            'brand_slug' => e($brandSlug),
            'brand_url' => $brandSlug === '' ? '' : e(route('store.brands.show', ['slug' => $brandSlug], false)),
            'sku' => e((string) ($product['sku'] ?? '')),
            'variant_id' => e((string) ($product['variant_public_id'] ?? '')),
            'price' => e((string) ($product['price_formatted'] ?? '')),
            'price_minor' => (string) ((int) ($product['price_minor'] ?? 0)),
            'regular_price' => e((string) ($product['regular_price_formatted'] ?? '')),
            'regular_price_minor' => (string) ((int) ($product['regular_price_minor'] ?? 0)),
            'currency' => e((string) ($product['currency'] ?? config('store.default_currency', 'JOD'))),
            'image' => e((string) ($image['url'] ?? '')),
            'image_alt' => e((string) ($image['alt'] ?? $product['name'] ?? '')),
            'categories' => e($categories),
            'tags' => e($tags),
            'availability' => $available ? 'in-stock' : 'out-of-stock',
            'availability_label' => $available ? 'In stock' : 'Out of stock',
            'stock_quantity' => e((string) ($product['available_quantity'] ?? '')),
            'badge' => $onSale ? 'Sale' : ($featured ? 'Featured' : ''),
            'published_at' => e((string) ($product['published_at'] ?? '')),
            'completion_year' => e((string) ($artwork['completion_year'] ?? '')),
            'work_type' => e((string) ($artwork['work_type'] ?? '')),
            'edition_type' => e((string) ($artwork['edition_type'] ?? '')),
            'artwork_category' => e((string) ($artwork['category'] ?? '')),
            'artistic_style' => e((string) ($artwork['artistic_style'] ?? '')),
            'technique' => e((string) ($artwork['technique'] ?? '')),
            'has_image' => trim((string) ($image['url'] ?? '')) === '' ? '0' : '1',
            'on_sale' => $onSale ? '1' : '0',
            'is_featured' => $featured ? '1' : '0',
            'available' => $available ? '1' : '0',
            'is_artwork' => $isArtwork ? '1' : '0',
        ];
    }

    /** @param array<string, scalar|null> $values */
    private function replaceTokens(string $markup, array $values): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $match): string => array_key_exists(strtolower($match[1]), $values)
                ? (string) $values[strtolower($match[1])]
                : $match[0],
            $markup,
        ) ?? $markup;
    }
}
