<?php

namespace Modules\Blog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Category;

final class BlogCategoriesPageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-blog-categories\s*=)[^>]*>[\s\S]*?<\/div>/i';

    /** @param array<int, array<string, mixed>> $extensions */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $categories = $this->categories('popular', 24);
        $extensions[] = [
            'id' => 'blog.categories',
            'config' => [
                'previewHtml' => $this->cardsHtml($categories),
                'hasCategories' => $categories->isNotEmpty(),
            ],
            'scripts' => [url('/platform/plugins/blog/js/vvveb-blog-categories.js?v=20260820-2')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady() || ! str_contains($html, 'data-blog-categories')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->attributes($match[0]);
            $limit = max(1, min(24, (int) ($attributes['data-blog-categories-limit'] ?? 6)));
            $order = (string) ($attributes['data-blog-categories-order'] ?? 'popular');
            $layout = (string) ($attributes['data-blog-categories-layout'] ?? 'grid');
            $layout = in_array($layout, ['grid', 'list'], true) ? $layout : 'grid';
            $instance = $this->instance($attributes);
            $categories = $this->categories($order, $limit);

            if ($categories->isEmpty()) {
                return '';
            }

            return '<section class="blog-categories" data-blog-categories-rendered="'.e($instance).'">'
                .$this->styles($instance, $attributes, $layout)
                .'<div class="blog-categories-items">'.$this->cardsHtml($categories).'</div>'
                .'</section>';
        }, $html) ?? $html;
    }

    /** @return Collection<int, Category> */
    private function categories(string $order, int $limit): Collection
    {
        $query = Category::query()
            ->withCount(['posts as published_posts_count' => fn ($posts) => $posts->visibleToPublic()]);

        match ($order) {
            'name' => $query->orderBy('name'),
            'name-desc' => $query->orderByDesc('name'),
            'newest' => $query->latest('created_at')->latest('id'),
            default => $query->orderByDesc('published_posts_count')->orderBy('name'),
        };

        return $query->limit($limit)->get();
    }

    /** @param Collection<int, Category> $categories */
    private function cardsHtml(Collection $categories): string
    {
        return $categories->map(function (Category $category): string {
            $url = route('blog.category', ['slug' => $category->slug], false);

            return '<article class="blog-category-card">'
                .'<h3><a href="'.e($url).'">'.e((string) $category->name).'</a></h3>'
                .'<p class="blog-category-description">'.e((string) ($category->description ?: 'Browse published posts in this category.')).'</p>'
                .'<a class="blog-category-count" href="'.e($url).'">'.e((string) $category->published_posts_count).' posts</a>'
                .'</article>';
        })->implode('');
    }

    private function styles(string $instance, array $attributes, string $layout): string
    {
        $selector = '[data-blog-categories-rendered="'.e($instance).'"]';
        $desktop = max(1, min(6, (int) ($attributes['data-blog-categories-columns-desktop'] ?? 3)));
        $tablet = max(1, min(4, (int) ($attributes['data-blog-categories-columns-tablet'] ?? 2)));
        $mobile = max(1, min(2, (int) ($attributes['data-blog-categories-columns-mobile'] ?? 1)));
        $gap = max(0, min(120, (int) ($attributes['data-blog-categories-gap'] ?? 20)));
        $showDescription = ($attributes['data-blog-categories-show-description'] ?? '1') !== '0';
        $showCount = ($attributes['data-blog-categories-show-count'] ?? '1') !== '0';
        $columns = $layout === 'list' ? 1 : $desktop;

        return '<style data-blog-categories-styles>'
            .$selector.' .blog-categories-items{display:grid;grid-template-columns:repeat('.$columns.',minmax(0,1fr));gap:'.$gap.'px}'
            .$selector.' .blog-category-card{display:grid;gap:10px;padding:20px;border:1px solid rgba(15,23,42,.12);border-radius:12px;background:#fff;color:#0f172a}'
            .$selector.' h3,'.$selector.' p{margin:0}'.$selector.' h3{font:700 1.05rem/1.4 system-ui,sans-serif}'.$selector.' a{color:inherit;text-decoration:none}'
            .$selector.' .blog-category-description{color:#64748b;font:400 14px/1.6 system-ui,sans-serif}'.($showDescription ? '' : $selector.' .blog-category-description{display:none}')
            .$selector.' .blog-category-count{color:#991b1b;font:700 13px/1.4 system-ui,sans-serif}'.($showCount ? '' : $selector.' .blog-category-count{display:none}')
            .($layout === 'list' ? '' : '@media(max-width:991px){'.$selector.' .blog-categories-items{grid-template-columns:repeat('.$tablet.',minmax(0,1fr))}}@media(max-width:575px){'.$selector.' .blog-categories-items{grid-template-columns:repeat('.$mobile.',minmax(0,1fr))}}')
            .'</style>';
    }

    /** @return array<string, string> */
    private function attributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-blog-categories[a-z0-9-]*)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [
            strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    private function instance(array $attributes): string
    {
        $value = strtolower((string) ($attributes['data-blog-categories-instance'] ?? ''));
        $value = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $value), '-');

        return $value !== '' ? substr($value, 0, 80) : 'blog-categories-'.substr(sha1((string) json_encode($attributes)), 0, 12);
    }

    private function isReady(): bool
    {
        return Schema::hasTable('blog_categories')
            && Schema::hasTable('blog_posts')
            && Route::has('blog.category');
    }
}
