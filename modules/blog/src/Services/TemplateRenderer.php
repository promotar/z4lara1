<?php

namespace Modules\Blog\Services;

use Illuminate\Support\Enumerable;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Template;

class TemplateRenderer
{
    /**
     * Render a saved template with real Blog posts. Collection templates repeat
     * markup placed between {{#posts}} and {{/posts}} for every supplied post.
     *
     * @param  iterable<int, Post>  $posts
     * @param  array<string, scalar|null>  $globals
     * @param  array{wrap_posts?: bool}  $options
     */
    public function render(Template $template, iterable $posts, array $globals = [], array $options = []): string
    {
        $items = $posts instanceof Enumerable ? $posts->values() : collect($posts)->values();
        $markup = (string) $template->html_code;

        $markup = preg_replace_callback(
            '/\{\{#posts\}\}([\s\S]*?)\{\{\/posts\}\}/i',
            function (array $match) use ($items, $options): string {
                $rendered = $items
                    ->map(fn (Post $post): string => $this->renderPostMarkup($match[1], $post))
                    ->implode('');

                return ($options['wrap_posts'] ?? false)
                    ? '<div data-blog-template-items>'.$rendered.'</div>'
                    : $rendered;
            },
            $markup,
        ) ?? $markup;

        if ($items->count() === 1) {
            $markup = $this->renderPostMarkup($markup, $items->first());
        }

        $markup = $this->replaceTokens($markup, array_merge([
            'site_name' => (string) config('app.name', 'Art Z'),
            'archive_title' => 'All Posts',
            'results_count' => (string) $items->count(),
        ], $globals));

        $slug = e($template->slug);
        $output = '<div data-blog-template="'.$slug.'">'
            .'<style data-blog-template-style="'.$slug.'">'.(string) $template->css_code.'</style>'
            .$markup
            .'</div>';

        if (trim((string) $template->js_code) !== '') {
            $output .= '<script src="'.e(route('blog.template-script', ['slug' => $template->slug], false)).'" defer data-blog-template-script="'.$slug.'"></script>';
        }

        return $output;
    }

    /** @return array<string, string> */
    private function postValues(Post $post): array
    {
        $post->loadMissing(['category', 'categories', 'tags', 'author', 'creator', 'featuredImage']);
        $categories = collect([$post->category])->merge($post->categories)->filter()->unique('id')->values();
        $contentText = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $post->content)) ?? '');

        return [
            'id' => (string) $post->id,
            'slug' => e((string) $post->slug),
            'title' => e((string) $post->title),
            'excerpt' => e((string) ($post->excerpt ?: str($contentText)->limit(180))),
            'content' => (string) $post->content,
            'content_text' => e($contentText),
            'url' => e(route('blog.show', $post->slug, false)),
            'featured_image' => e((string) ($post->featuredImageUrl() ?: '')),
            'featured_image_alt' => e((string) $post->featured_image_alt),
            'category' => e((string) ($post->category?->name ?: 'Uncategorized')),
            'category_slug' => e((string) $post->category?->slug),
            'category_url' => $post->category ? e(route('blog.category', $post->category->slug, false)) : '',
            'categories' => e($categories->pluck('name')->implode(', ')),
            'tags' => e($post->tags->pluck('name')->implode(', ')),
            'author' => e((string) ($post->author?->name ?: $post->creator?->name ?: config('app.name', 'Art Z'))),
            'author_id' => (string) ($post->author_id ?: $post->created_by ?: ''),
            'published_at' => e((string) ($post->published_at?->format('F j, Y') ?: '')),
            'published_at_iso' => e((string) ($post->published_at?->toIso8601String() ?: '')),
            'created_at' => e((string) ($post->created_at?->format('F j, Y') ?: '')),
            'updated_at' => e((string) ($post->updated_at?->format('F j, Y') ?: '')),
            'status' => e((string) $post->status),
            'visibility' => e((string) $post->visibility),
            'template' => e((string) ($post->template ?: $post->layout_template)),
            'layout' => e((string) $post->layout),
            'seo_title' => e((string) ($post->seo_title ?: $post->title)),
            'seo_description' => e((string) $post->seo_description),
            'focus_keyword' => e((string) $post->focus_keyword),
            'canonical_url' => e((string) $post->canonical_url),
            'robots_index' => $post->robots_index ? 'index' : 'noindex',
            'robots_follow' => $post->robots_follow ? 'follow' : 'nofollow',
            'schema_type' => e((string) $post->schema_type),
            'seo_score' => (string) ((int) $post->seo_score),
            'seo_social_title' => e((string) $post->seo_social_title),
            'seo_social_description' => e((string) $post->seo_social_description),
        ];
    }

    private function renderPostMarkup(string $markup, Post $post): string
    {
        $post->loadMissing(['category', 'categories', 'tags', 'author', 'creator', 'featuredImage']);
        $categories = collect([$post->category])->merge($post->categories)->filter()->unique('id')->values();

        $markup = preg_replace_callback(
            '/\{\{#tags\}\}([\s\S]*?)\{\{\/tags\}\}/i',
            fn (array $match): string => $post->tags->map(fn ($tag): string => $this->replaceTokens($match[1], [
                'name' => e((string) $tag->name),
                'slug' => e((string) $tag->slug),
                'url' => e(route('blog.tag', $tag->slug, false)),
            ]))->implode(''),
            $markup,
        ) ?? $markup;

        $markup = preg_replace_callback(
            '/\{\{#categories\}\}([\s\S]*?)\{\{\/categories\}\}/i',
            fn (array $match): string => $categories->map(fn ($category): string => $this->replaceTokens($match[1], [
                'name' => e((string) $category->name),
                'slug' => e((string) $category->slug),
                'url' => e(route('blog.category', $category->slug, false)),
            ]))->implode(''),
            $markup,
        ) ?? $markup;

        return $this->replaceTokens($markup, $this->postValues($post));
    }

    /** @param array<string, scalar|null> $values */
    private function replaceTokens(string $markup, array $values): string
    {
        $markup = preg_replace_callback('/\{\{\{\s*content\s*\}\}\}/i', fn (): string => (string) ($values['content'] ?? ''), $markup) ?? $markup;

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $match): string => array_key_exists($match[1], $values) ? (string) $values[$match[1]] : $match[0],
            $markup,
        ) ?? $markup;
    }
}
