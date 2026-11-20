<?php

namespace Modules\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Platform\Core\Localization\PlatformLocale;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\CategoryTranslation;
use Modules\Blog\Models\PostTranslation;
use Modules\Blog\Support\BlogLocale;
use Modules\Blog\Models\Tag;
use Modules\Blog\Models\Template;
use Modules\Blog\Services\TemplateRenderer;
use Modules\Blog\Services\TemplateSettings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BlogController extends Controller
{
    public function __construct(
        private readonly TemplateRenderer $templateRenderer,
        private readonly TemplateSettings $templateSettings,
    ) {}

    public function styles(): BinaryFileResponse
    {
        return response()->file(
            dirname(__DIR__, 3).'/resources/css/blog.css',
            [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function templateScript(string $slug): \Illuminate\Http\Response
    {
        $template = Template::query()->active()->where('slug', $slug)->firstOrFail();

        return response((string) $template->js_code, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index()
    {
        $featuredPost = Post::query()
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->latest('published_at')
            ->latest('id')
            ->first();

        $posts = Post::query()
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        $categories = Category::query()
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->visibleToPublic()])
            ->orderByDesc('published_posts_count')
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->localizeCategories($categories);

        $this->localizePost($featuredPost);
        $this->localizeCollection($posts->getCollection());

        // Templates render the collection, so translation must happen first.
        $renderedTemplate = $this->renderTemplate(
            'archive',
            $posts->getCollection(),
            __('blog::ui.news-and-articles'),
        );

        return view('blog::frontend.index', compact('posts', 'featuredPost', 'categories', 'renderedTemplate'));
    }

    private function localizePost(?Post $post): void
    {
        if (! $post) {
            return;
        }

        $translation = PostTranslation::resolve($post, app()->getLocale());

        $translation?->applyTo($post);
    }

    private function localizeCollection(iterable $posts): void
    {
        $locale = app()->getLocale();

        // The base rows hold the site language; translations apply elsewhere.
        if (BlogLocale::isDefault($locale)) {
            return;
        }

        $map = PostTranslation::mapForPosts($posts, $locale);

        foreach ($posts as $post) {
            if (isset($map[$post->id])) {
                $map[$post->id]->applyTo($post);
            }
        }

        $this->localizeCategories(collect($posts)->pluck('category')->filter()->unique('id')->values());
    }

    /**
     * Localise category names attached to posts or listed on their own.
     */
    private function localizeCategories(iterable $categories): void
    {
        $locale = app()->getLocale();

        if (BlogLocale::isDefault($locale)) {
            return;
        }

        $map = CategoryTranslation::mapForCategories($categories, $locale);

        foreach ($categories as $category) {
            if (isset($map[$category->id])) {
                $map[$category->id]->applyTo($category);
            }
        }
    }

    public function show(string $slug)
    {
        $locale = app()->getLocale();
        $translation = null;
        $post = null;

        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if ($translation) {
            $post = Post::query()
                ->visibleToPublic()
                ->with(['category', 'categories', 'tags', 'author', 'featuredImage'])
                ->find($translation->post_id);
        }

        if (! $post) {
            $post = Post::query()
                ->visibleToPublic()
                ->with(['category', 'categories', 'tags', 'author', 'featuredImage'])
                ->where('slug', $slug)
                ->firstOrFail();
        }

        $baseSlug = $post->slug;
        $translation?->applyTo($post);

        if ($post->relationLoaded('category') ? $post->category : null) {
            $this->localizeCategories([$post->category]);
        }

        $seoAlternates = [];
        $translationSlugs = PostTranslation::query()
            ->where('post_id', $post->id)
            ->pluck('slug', 'locale');

        $seoAlternates[BlogLocale::default()] = route('blog.show', $baseSlug, false);
        foreach ($translationSlugs as $code => $translationSlug) {
            $seoAlternates[$code] = route('blog.show', $translationSlug, false).'?lang='.urlencode($code);
        }

        $canonicalOverride = ! BlogLocale::isDefault($locale) && $translation
            ? route('blog.show', $translation->slug).'?lang='.urlencode($locale)
            : null;

        $categoryIds = collect([$post->category_id])
            ->merge($post->categories->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        $relatedPosts = Post::query()
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->whereKeyNot($post->id)
            ->when(
                $categoryIds->isNotEmpty(),
                fn ($query) => $query->where(function ($query) use ($categoryIds): void {
                    $query
                        ->whereIn('category_id', $categoryIds)
                        ->orWhereHas('categories', fn ($categories) => $categories->whereIn('blog_categories.id', $categoryIds));
                })
            )
            ->latest('published_at')
            ->latest('id')
            ->limit(3)
            ->get();

        return view('blog::frontend.show', [
            'post' => $post,
            'preview' => false,
            'relatedPosts' => $relatedPosts,
            'renderedTemplate' => $this->renderTemplate('single', [$post], $post->title),
            'seoAlternates' => $seoAlternates,
            'canonicalOverride' => $canonicalOverride,
        ]);
    }

    public function categories()
    {
        $categories = Category::query()
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->visibleToPublic()])
            ->orderByDesc('published_posts_count')
            ->orderBy('name')
            ->get();

        $this->localizeCategories($categories);

        return view('blog::frontend.categories', compact('categories'));
    }

    public function category(string $slug)
    {
        $locale = app()->getLocale();
        $category = null;

        if (! BlogLocale::isDefault($locale)) {
            $translation = CategoryTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->first();

            if ($translation) {
                $category = Category::query()->find($translation->category_id);
            }
        }

        $category ??= Category::query()->where('slug', $slug)->firstOrFail();

        if (! BlogLocale::isDefault($locale)) {
            CategoryTranslation::resolve($category, $locale)?->applyTo($category);
        }
        $posts = Post::query()
            ->where(function ($query) use ($category): void {
                $query
                    ->where('category_id', $category->id)
                    ->orWhereHas('categories', fn ($categories) => $categories->where('blog_categories.id', $category->id));
            })
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        $this->localizeCollection($posts->getCollection());

        $renderedTemplate = $this->renderTemplate('category', $posts->getCollection(), $category->name);

        return view('blog::frontend.category', compact('category', 'posts', 'renderedTemplate'));
    }

    public function tag(string $slug)
    {
        $tag = Tag::query()->where('slug', $slug)->firstOrFail();
        $posts = $tag->posts()
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        $this->localizeCollection($posts->getCollection());

        $renderedTemplate = $this->renderTemplate('archive', $posts->getCollection(), '#'.$tag->name);

        return view('blog::frontend.tag', compact('tag', 'posts', 'renderedTemplate'));
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $posts = Post::query()
            ->visibleToPublic()
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term): void {
                $query->where('title', 'like', '%'.$term.'%')
                    ->orWhere('excerpt', 'like', '%'.$term.'%')
                    ->orWhere('content', 'like', '%'.$term.'%');
            }))
            ->latest('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();
        $this->localizeCollection($posts->getCollection());
        $archiveTitle = $term === ''
            ? __('blog::ui.search-in-articles')
            : __('blog::ui.search-results-for').': '.$term;
        $renderedTemplate = $this->renderTemplate('search', $posts->getCollection(), $archiveTitle);

        return view('blog::frontend.search', compact('posts', 'term', 'archiveTitle', 'renderedTemplate'));
    }

    private function renderTemplate(string $context, iterable $posts, string $archiveTitle): ?string
    {
        $items = $posts instanceof \Illuminate\Support\Enumerable ? $posts : collect($posts);

        // With no published posts a saved template would render an empty grid;
        // fall back to the default view so visitors get a proper empty state.
        if ($items->isEmpty()) { // no published posts
            return null;
        }

        $template = $this->templateSettings->templateFor($context);

        if (! $template) {
            return null;
        }

        return \Modules\Blog\Support\ContentEmbeds::render(
            $this->templateRenderer->render($template, $items, ['archive_title' => $archiveTitle]),
        );
    }
}
