<?php

namespace Modules\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
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
                'Cache-Control' => 'public, max-age=3600',
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

        $renderedTemplate = $this->renderTemplate('archive', $posts->getCollection(), 'الأخبار والمقالات');

        return view('blog::frontend.index', compact('posts', 'featuredPost', 'categories', 'renderedTemplate'));
    }

    public function show(string $slug)
    {
        $post = Post::query()
            ->visibleToPublic()
            ->with(['category', 'categories', 'tags', 'author', 'featuredImage'])
            ->where('slug', $slug)
            ->firstOrFail();

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
        ]);
    }

    public function categories()
    {
        $categories = Category::query()
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->visibleToPublic()])
            ->orderByDesc('published_posts_count')
            ->orderBy('name')
            ->get();

        return view('blog::frontend.categories', compact('categories'));
    }

    public function category(string $slug)
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
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
        $archiveTitle = $term === '' ? 'البحث في المقالات' : 'نتائج البحث عن: '.$term;
        $renderedTemplate = $this->renderTemplate('search', $posts->getCollection(), $archiveTitle);

        return view('blog::frontend.search', compact('posts', 'term', 'archiveTitle', 'renderedTemplate'));
    }

    private function renderTemplate(string $context, iterable $posts, string $archiveTitle): ?string
    {
        $template = $this->templateSettings->templateFor($context);

        return $template
            ? $this->templateRenderer->render($template, $posts, ['archive_title' => $archiveTitle])
            : null;
    }
}
