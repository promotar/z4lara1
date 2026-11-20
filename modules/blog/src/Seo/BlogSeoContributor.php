<?php

namespace Modules\Blog\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\LocaleUrlStrategy;
use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\Blog\Models\Tag;
use Throwable;

/**
 * SEO adapter for the Blog module.
 *
 * Only registered while Blog is active. It reads existing editorial SEO
 * fields and never writes to Blog content or Core tables.
 */
final class BlogSeoContributor implements SeoContributor
{
    public function module(): string
    {
        return 'blog';
    }

    public function supports(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'blog.index',
            'blog.show',
            'blog.categories',
            'blog.category',
            'blog.tag',
            'blog.search',
        ], true);
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            match ($request->route()?->getName()) {
                'blog.show' => $this->post($seo, $request),
                'blog.index' => $this->archive($seo, 'Blog', 'Latest articles, stories and insights from ART INPA.'),
                'blog.categories' => $this->archive($seo, 'Blog Categories', 'Browse every category on the ART INPA journal.'),
                'blog.category' => $this->category($seo, $request),
                'blog.tag' => $this->tag($seo, $request),
                'blog.search' => $this->search($seo, $request),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function post(SeoData $seo, Request $request): void
    {
        $slug = (string) ($request->route('slug') ?: $request->segment(2));
        $locale = app()->getLocale();
        $defaultLocale = app(PlatformLocale::class)->defaultCode();
        $translation = null;
        $post = null;

        if ($locale !== $defaultLocale) {
            $translation = PostTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->first();

            if ($translation !== null) {
                $post = Post::query()
                    ->visibleToPublic()
                    ->with(['category', 'categories', 'tags', 'author', 'featuredImage'])
                    ->find($translation->post_id);
            }
        }

        if ($post === null) {
            $post = Post::query()
                ->visibleToPublic()
                ->with(['category', 'categories', 'tags', 'author', 'featuredImage'])
                ->where('slug', $slug)
                ->first();
        }

        if ($post === null) {
            return;
        }

        $baseSlug = (string) $post->slug;
        $translation?->applyTo($post);

        $localeUrls = app(LocaleUrlStrategy::class);
        $canonical = $post->canonical_url
            ?: $localeUrls->withLocale(route('blog.show', $baseSlug, false), $locale);
        if ($translation !== null) {
            $canonical = $localeUrls->withLocale(route('blog.show', $translation->slug, false), $locale);
        }

        $seo->title = trim((string) ($post->seo_title ?: $post->meta_title ?: $post->title)) ?: null;
        $seo->description = $this->text($post->seo_description ?: $post->meta_description ?: $post->excerpt, 160);
        $seo->canonical = $this->absolute($canonical);
        if ($post->robots_index !== null || $post->robots_follow !== null) {
            $seo->robots = $seo->robots->merge(new Robots(
                index: (bool) ($post->robots_index ?? true),
                follow: (bool) ($post->robots_follow ?? true),
            ));
        }
        $seo->ogType = 'article';
        $seo->ogTitle = trim((string) ($post->seo_social_title ?: $seo->title)) ?: null;
        $seo->ogDescription = $this->text($post->seo_social_description ?: $seo->description, 200);
        $seo->twitterTitle = $seo->ogTitle;
        $seo->twitterDescription = $seo->ogDescription;

        $image = $post->featuredImageUrl();
        if (filled($image)) {
            $seo->image = $this->absolute($image);
            $seo->imageAlt = $post->featured_image_alt ?: $post->featuredImage?->alt_text;
            $seo->twitterImage = $seo->image;
            $seo->twitterCard = 'summary_large_image';
        }

        $seo->extra['blog_post_id'] = (int) $post->id;
        $seo->extra['blog_post_slug'] = $baseSlug;
        $seo->extra['content_title'] = strip_tags((string) $post->title);
        $seo->extra['content_description'] = strip_tags((string) ($post->excerpt ?: Str::limit((string) $post->content, 180)));
        $seo->extra['content_text'] = implode(' ', array_filter([
            strip_tags((string) $post->title),
            strip_tags((string) $post->excerpt),
            strip_tags((string) $post->content),
        ]));
        $seo->extra['replace_title'] = true;

        $seo->alternates = $this->alternates($post, $baseSlug);
        $seo->schema[] = $this->articleSchema($post, $seo, $canonical);
        $seo->schema[] = $this->breadcrumbSchema($post, $canonical);
    }

    private function archive(SeoData $seo, string $title, string $description): void
    {
        $seo->title = $title;
        $seo->description = $description;
        $seo->ogTitle = $title;
        $seo->ogDescription = $description;
        $seo->extra['replace_title'] = true;
        $seo->schema[] = $this->collectionSchema($seo, $title);
    }

    private function category(SeoData $seo, Request $request): void
    {
        $slug = (string) ($request->route('slug') ?: $request->segment(2));
        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null) {
            return;
        }

        $title = trim((string) $category->name) ?: 'Blog Category';
        $description = $this->text($category->description ?? 'Articles filed under '.$title.'.', 160);

        $this->archive($seo, $title, $description);
        $seo->schema[] = $this->breadcrumbSchemaFor(
            [
                ['name' => 'Home', 'item' => url('/')],
                ['name' => 'Blog', 'item' => route('blog.index')],
                ['name' => $title, 'item' => $seo->canonical],
            ],
        );
    }

    private function tag(SeoData $seo, Request $request): void
    {
        $slug = (string) ($request->route('slug') ?: $request->segment(2));
        $tag = Tag::query()->where('slug', $slug)->first();

        if ($tag === null) {
            return;
        }

        $title = '#'.trim((string) $tag->name);
        $description = $this->text('Articles tagged '.$title.' on the ART INPA journal.', 160);

        $this->archive($seo, $title, $description);
        $seo->schema[] = $this->breadcrumbSchemaFor(
            [
                ['name' => 'Home', 'item' => url('/')],
                ['name' => 'Blog', 'item' => route('blog.index')],
                ['name' => $title, 'item' => $seo->canonical],
            ],
        );
    }

    private function search(SeoData $seo, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));

        $seo->title = $term !== '' ? 'Search: '.$term : 'Search';
        $seo->description = 'Search results from the ART INPA journal.';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $seo->description;
        $seo->extra['replace_title'] = true;
        $seo->robots = $seo->robots->merge(Robots::noindex());
    }

    /**
     * @return array<string, string>
     */
    private function alternates(Post $post, string $baseSlug): array
    {
        $localeUrls = app(LocaleUrlStrategy::class);
        $defaultLocale = app(PlatformLocale::class)->defaultCode();
        $baseUrl = route('blog.show', $baseSlug, false);
        $alternates = [
            $defaultLocale => $localeUrls->withLocale($baseUrl, $defaultLocale),
        ];

        foreach (PostTranslation::query()->where('post_id', $post->id)->pluck('slug', 'locale') as $locale => $slug) {
            $alternates[(string) $locale] = $localeUrls->withLocale(
                route('blog.show', $slug, false),
                (string) $locale,
            );
        }

        foreach (array_keys(app(PlatformLocale::class)->languages()) as $code) {
            $code = (string) $code;
            $alternates[$code] ??= $localeUrls->withLocale($baseUrl, $code);
        }

        $default = $alternates[$defaultLocale] ?? reset($alternates);
        if (is_string($default) && $default !== '') {
            $alternates['x-default'] = $default;
        }

        return $alternates;
    }

    /**
     * @return array<string, mixed>
     */
    private function articleSchema(Post $post, SeoData $seo, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => in_array($post->schema_type, ['BlogPosting', 'Article', 'NewsArticle'], true)
                ? $post->schema_type
                : 'BlogPosting',
            'headline' => $post->title,
            'description' => $seo->description,
            'url' => $canonical,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'image' => $seo->image ? ['@type' => 'ImageObject', 'url' => $seo->image] : null,
            'author' => $post->author?->name
                ? ['@type' => 'Person', 'name' => $post->author->name]
                : ['@type' => 'Organization', 'name' => $seo->siteName ?: config('app.name', 'ART INPA')],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $seo->siteName ?: config('app.name', 'ART INPA'),
                'url' => url('/'),
            ],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'articleSection' => $post->category?->name,
            'keywords' => $post->tags->pluck('name')->filter()->values()->all() ?: null,
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ];

        return array_filter($schema, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function breadcrumbSchema(Post $post, string $canonical): array
    {
        return $this->breadcrumbSchemaFor([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => 'Blog', 'item' => route('blog.index')],
            ['name' => $post->title, 'item' => $canonical],
        ]);
    }

    /**
     * @param  array<int, array{name: string, item: ?string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbSchemaFor(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if (filled($item['item'])) {
                $entry['item'] = $this->absolute((string) $item['item']);
            }

            $elements[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
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

    private function text(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit);
    }

    private function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
