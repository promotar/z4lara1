<?php

namespace Modules\Blog\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Modules\Blog\Models\Template;

class PageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-blog-template-slug\s*=)[^>]*>[\s\S]*?<\/div>/i';

    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * Register the Blog-owned VvvebJs element through Page Builder's public extension contract.
     *
     * @param  array<int, array<string, mixed>>  $extensions
     * @return array<int, array<string, mixed>>
     */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->schemaIsReady()) {
            return $extensions;
        }

        $previewPosts = $this->postsQuery()->limit(6)->get();
        $templates = Template::query()
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function (Template $template) use ($previewPosts): array {
                $posts = $template->category === 'single' ? $previewPosts->take(1) : $previewPosts;

                return [
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'category' => $template->category,
                    'previewHtml' => $this->renderer->render($template, $posts, [
                        'archive_title' => 'Template preview',
                    ]),
                ];
            })
            ->values()
            ->all();

        $extensions[] = [
            'id' => 'blog.templates',
            'config' => [
                'templates' => $templates,
                'categories' => Category::query()->orderBy('name')->get(['name', 'slug'])->toArray(),
                'tags' => Tag::query()->orderBy('name')->get(['name', 'slug'])->toArray(),
            ],
            'scripts' => [url('/platform/plugins/blog/js/vvveb-blog-template.js')],
            'styles' => [],
        ];

        return $extensions;
    }

    /**
     * Replace Blog template slots after Page Builder has rendered its regular dynamic content.
     * Slots contain no domain HTML, so they remain invisible if Blog is not active.
     */
    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->schemaIsReady() || ! str_contains($html, 'data-blog-template-slug')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->slotAttributes($match[0]);
            $slug = $attributes['data-blog-template-slug'] ?? '';
            $template = Template::query()->active()->where('slug', $slug)->first();

            if (! $template) {
                return '';
            }

            [$posts, $globals, $pagination] = $this->slotPosts($attributes, $template);
            $instance = $this->instanceKey($attributes, $template);
            $layout = $this->layoutSettings($attributes);

            return '<div data-blog-template-rendered="'.e($instance).'" data-blog-template-layout="'.e($layout['mode']).'">'
                .$this->layoutStyles($instance, $layout)
                .'<div data-blog-template-results>'
                .$this->renderer->render($template, $posts, $globals, ['wrap_posts' => $layout['mode'] !== 'template'])
                .'</div>'
                .$pagination
                .'</div>';
        }, $html) ?? $html;
    }

    /** @return array{0:iterable<int, Post>, 1:array<string, scalar|null>, 2:string} */
    private function slotPosts(array $attributes, Template $template): array
    {
        $source = $attributes['data-blog-template-source'] ?? 'latest';
        $value = trim((string) ($attributes['data-blog-template-value'] ?? ''));
        if ($source === 'category') {
            $value = trim((string) ($attributes['data-blog-template-category'] ?? $value));
        } elseif ($source === 'tag') {
            $value = trim((string) ($attributes['data-blog-template-tag'] ?? $value));
        }
        $limit = max(1, min(24, (int) ($attributes['data-blog-template-limit'] ?? 6)));
        $query = $this->postsQuery();
        $title = 'Latest posts';

        if ($source === 'single') {
            $limit = 1;
            $query->when($value !== '', fn (Builder $query) => $query->where('slug', $value));
            $title = 'Post';
        } elseif ($source === 'category') {
            $query->where(function (Builder $query) use ($value): void {
                $query
                    ->whereHas('category', fn (Builder $category) => $category->where('slug', $value))
                    ->orWhereHas('categories', fn (Builder $categories) => $categories->where('blog_categories.slug', $value));
            });
            $title = Category::query()->where('slug', $value)->value('name') ?: 'Category';
        } elseif ($source === 'tag') {
            $query->whereHas('tags', fn (Builder $tags) => $tags->where('blog_tags.slug', $value));
            $title = Tag::query()->where('slug', $value)->value('name') ?: 'Tag';
        } elseif ($source === 'search') {
            $query->where(function (Builder $query) use ($value): void {
                $query->where('title', 'like', '%'.$value.'%')->orWhere('content', 'like', '%'.$value.'%');
            });
            $title = $value === '' ? 'Search results' : 'Search results for '.$value;
        }

        if ($template->category === 'single') {
            $limit = 1;
        }

        $paginationEnabled = ($attributes['data-blog-template-pagination'] ?? '0') === '1'
            && $source !== 'single'
            && $template->category !== 'single';

        if ($paginationEnabled) {
            $instance = $this->instanceKey($attributes, $template);
            $pageName = 'blog_page_'.str_replace('-', '_', $instance);
            $currentPage = max(1, (int) request()->query($pageName, 1));
            $paginator = $query
                ->paginate($limit, ['*'], $pageName, $currentPage)
                ->withQueryString();
            $style = (string) ($attributes['data-blog-template-pagination-style'] ?? 'numbers');

            return [
                $paginator->getCollection(),
                ['archive_title' => $title],
                $this->paginationHtml($paginator, $style, $instance),
            ];
        }

        return [$query->limit($limit)->get(), ['archive_title' => $title], ''];
    }

    private function paginationHtml(LengthAwarePaginator $paginator, string $style, string $instance): string
    {
        if ($paginator->lastPage() <= 1) {
            return '';
        }

        $style = in_array($style, ['numbers', 'simple', 'load-more'], true) ? $style : 'numbers';
        $links = '';

        if ($style === 'load-more') {
            if (! $paginator->hasMorePages()) {
                return '';
            }

            $links = $this->pageLink(
                (string) $paginator->nextPageUrl(),
                'Load more',
                'blog-template-pagination__load-more',
                ['data-blog-pagination-load-more' => '1'],
            );
        } else {
            $links .= $paginator->onFirstPage()
                ? $this->disabledPageLink('‹', 'Previous page')
                : $this->pageLink((string) $paginator->previousPageUrl(), '‹', '', ['aria-label' => 'Previous page']);

            if ($style === 'numbers') {
                $first = max(1, $paginator->currentPage() - 2);
                $last = min($paginator->lastPage(), $paginator->currentPage() + 2);

                if ($first > 1) {
                    $links .= $this->pageLink($paginator->url(1), '1');
                    if ($first > 2) {
                        $links .= '<span class="blog-template-pagination__ellipsis" aria-hidden="true">…</span>';
                    }
                }

                foreach ($paginator->getUrlRange($first, $last) as $page => $url) {
                    $links .= $page === $paginator->currentPage()
                        ? '<span class="is-current" aria-current="page">'.e((string) $page).'</span>'
                        : $this->pageLink($url, (string) $page);
                }

                if ($last < $paginator->lastPage()) {
                    if ($last < $paginator->lastPage() - 1) {
                        $links .= '<span class="blog-template-pagination__ellipsis" aria-hidden="true">…</span>';
                    }
                    $links .= $this->pageLink($paginator->url($paginator->lastPage()), (string) $paginator->lastPage());
                }
            } else {
                $links .= '<span class="blog-template-pagination__status">Page '
                    .e((string) $paginator->currentPage()).' of '.e((string) $paginator->lastPage())
                    .'</span>';
            }

            $links .= $paginator->hasMorePages()
                ? $this->pageLink((string) $paginator->nextPageUrl(), '›', '', ['aria-label' => 'Next page'])
                : $this->disabledPageLink('›', 'Next page');
        }

        return '<nav class="blog-template-pagination blog-template-pagination--'.e($style).'" '
            .'data-blog-template-pagination="'.e($instance).'" aria-label="Blog posts pagination">'
            .$this->paginationStyles()
            .$links
            .'</nav>';
    }

    /** @param array<string, string> $attributes */
    private function pageLink(string $url, string $label, string $class = '', array $attributes = []): string
    {
        $attributeHtml = collect($attributes)
            ->map(fn (string $value, string $name): string => ' '.e($name).'="'.e($value).'"')
            ->implode('');

        return '<a href="'.e($url).'"'.($class !== '' ? ' class="'.e($class).'"' : '').$attributeHtml.'>'
            .e($label)
            .'</a>';
    }

    private function disabledPageLink(string $label, string $ariaLabel): string
    {
        return '<span class="is-disabled" aria-disabled="true" aria-label="'.e($ariaLabel).'">'.e($label).'</span>';
    }

    private function paginationStyles(): string
    {
        return <<<'HTML'
<style data-blog-template-pagination-styles>
.blog-template-pagination{display:flex;align-items:center;justify-content:center;gap:.4rem;margin:1.5rem 0;flex-wrap:wrap}
.blog-template-pagination a,.blog-template-pagination>span{display:inline-flex;min-width:2.25rem;height:2.25rem;align-items:center;justify-content:center;padding:0 .65rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;color:#374151;font:600 .875rem/1 system-ui,sans-serif;text-decoration:none}
.blog-template-pagination a:hover{border-color:#991b1b;color:#991b1b}.blog-template-pagination .is-current{border-color:#991b1b;background:#991b1b;color:#fff}.blog-template-pagination .is-disabled{opacity:.42}.blog-template-pagination .blog-template-pagination__ellipsis,.blog-template-pagination .blog-template-pagination__status{border-color:transparent;background:transparent}.blog-template-pagination__load-more{min-width:8rem!important}.blog-template-pagination__load-more.is-loading{opacity:.6;pointer-events:none}
</style>
HTML;
    }

    /** @return array{mode:string, desktop:int, tablet:int, mobile:int, gap:int} */
    private function layoutSettings(array $attributes): array
    {
        $mode = (string) ($attributes['data-blog-template-layout'] ?? 'template');

        return [
            'mode' => in_array($mode, ['template', 'grid', 'cards', 'slider'], true) ? $mode : 'template',
            'desktop' => max(1, min(6, (int) ($attributes['data-blog-template-columns-desktop'] ?? 3))),
            'tablet' => max(1, min(4, (int) ($attributes['data-blog-template-columns-tablet'] ?? 2))),
            'mobile' => max(1, min(2, (int) ($attributes['data-blog-template-columns-mobile'] ?? 1))),
            'gap' => max(0, min(120, (int) ($attributes['data-blog-template-gap'] ?? 24))),
        ];
    }

    /** @param array{mode:string, desktop:int, tablet:int, mobile:int, gap:int} $layout */
    private function layoutStyles(string $instance, array $layout): string
    {
        $selector = '[data-blog-template-rendered="'.e($instance).'"]';
        $gap = $layout['gap'];
        $desktop = $layout['desktop'];
        $tablet = $layout['tablet'];
        $mobile = $layout['mobile'];

        if ($layout['mode'] === 'template') {
            return '';
        }

        if ($layout['mode'] === 'slider') {
            $base = "display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - ".(($desktop - 1) * $gap)."px)/{$desktop});grid-template-columns:none;overflow-x:auto;overscroll-behavior-inline:contain;scroll-behavior:smooth;scroll-snap-type:x mandatory;scrollbar-width:thin";
            $tabletRule = "grid-auto-columns:calc((100% - ".(($tablet - 1) * $gap)."px)/{$tablet})";
            $mobileRule = "grid-auto-columns:calc((100% - ".(($mobile - 1) * $gap)."px)/{$mobile})";
        } else {
            $base = "display:grid;grid-template-columns:repeat({$desktop},minmax(0,1fr))";
            $tabletRule = "grid-template-columns:repeat({$tablet},minmax(0,1fr))";
            $mobileRule = "grid-template-columns:repeat({$mobile},minmax(0,1fr))";
        }

        $cards = $layout['mode'] === 'cards'
            ? "{$selector} [data-blog-template-items]>*{overflow:hidden;border:1px solid #eadada;border-radius:12px;background:#fff;box-shadow:0 8px 24px rgba(45,20,20,.08)}"
            : '';

        return '<style data-blog-template-layout-styles>'
            ."{$selector} [data-blog-template-items]{{$base};position:relative;width:100%;grid-column:1/-1;flex:0 0 100%;gap:{$gap}px}"
            ."{$selector} [data-blog-template-items]>*{min-width:0;scroll-snap-align:start}"
            .$cards
            ."{$selector} .blog-template-slider-controls{display:flex;align-items:center;justify-content:flex-end;gap:.45rem;margin:.75rem 0}"
            ."{$selector} .blog-template-slider-controls button{display:inline-grid;width:2.35rem;height:2.35rem;place-items:center;border:1px solid #e3d2d2;border-radius:50%;background:#fff;color:#541515;font:700 1rem/1 system-ui;cursor:pointer}"
            ."@media(max-width:991px){{$selector} [data-blog-template-items]{{$tabletRule}}}"
            ."@media(max-width:575px){{$selector} [data-blog-template-items]{{$mobileRule}}}"
            .'</style>';
    }

    private function instanceKey(array $attributes, Template $template): string
    {
        $candidate = strtolower((string) ($attributes['data-blog-template-instance'] ?? ''));
        $candidate = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $candidate), '-');

        if ($candidate !== '') {
            return substr($candidate, 0, 80);
        }

        return 'bt-'.substr(sha1($template->slug.'|'.json_encode($attributes)), 0, 12);
    }

    private function postsQuery(): Builder
    {
        return Post::query()
            ->visibleToPublic()
            ->with(['category', 'categories', 'tags', 'author', 'creator', 'featuredImage'])
            ->latest('published_at')
            ->latest('id');
    }

    /** @return array<string, string> */
    private function slotAttributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all(
            '/\b(data-blog-[a-z0-9-]+)\s*=\s*(["\'])(.*?)\2/is',
            (string) ($opening[1] ?? ''),
            $matches,
            PREG_SET_ORDER,
        );

        return collect($matches)->mapWithKeys(fn (array $match): array => [
            strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('blog_templates')
            && Schema::hasTable('blog_posts')
            && Schema::hasTable('blog_categories')
            && Schema::hasTable('blog_tags');
    }
}
