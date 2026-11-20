<?php

namespace Modules\Blog\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\SitemapContributor;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\Blog\Models\Tag;

/**
 * Adds published Blog URLs to the sitemap while Blog is active.
 */
final class BlogSitemapContributor implements SitemapContributor
{
    public function module(): string
    {
        return 'blog';
    }

    public function sitemapEntries(): iterable
    {
        foreach (Category::query()->has('posts')->orderBy('id')->cursor() as $category) {
            yield [
                'loc' => route('blog.category', $category->slug),
                'lastmod' => $category->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => 0.5,
            ];
        }

        foreach (Tag::query()->has('posts')->orderBy('id')->cursor() as $tag) {
            yield [
                'loc' => route('blog.tag', $tag->slug),
                'lastmod' => $tag->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => 0.4,
            ];
        }

        foreach (Post::query()->visibleToPublic()->orderBy('id')->cursor() as $post) {
            $alternates = [app(PlatformLocale::class)->defaultCode() => route('blog.show', $post->slug, false)];

            foreach (PostTranslation::query()->where('post_id', $post->id)->pluck('slug', 'locale') as $locale => $slug) {
                $alternates[(string) $locale] = route('blog.show', $slug, false).'?lang='.urlencode((string) $locale);
            }

            $default = $alternates[app(PlatformLocale::class)->defaultCode()] ?? reset($alternates);
            if (is_string($default) && $default !== '') {
                $alternates['x-default'] = $default;
            }

            yield [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => 0.7,
                'alternates' => $alternates,
            ];
        }
    }
}
