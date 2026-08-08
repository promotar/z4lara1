<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Models\User;
use App\Platform\Core\Models\PlatformMediaMetadata;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Revision;
use Modules\Blog\Models\Tag;
use Modules\Blog\Services\SeoScoreCalculator;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $view = in_array($request->string('view')->toString(), ['published', 'scheduled', 'trash'], true)
            ? $request->string('view')->toString()
            : 'all';
        $posts = Post::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->when($view === 'published', fn ($query) => $query->where('status', 'published'))
            ->when($view === 'scheduled', fn ($query) => $query->where('status', 'scheduled'))
            ->with(['category', 'tags', 'author', 'featuredImage'])
            ->when($request->filled('category_id'), function ($query) use ($request): void {
                $categoryId = $request->integer('category_id');
                $query->where(function ($query) use ($categoryId): void {
                    $query->where('category_id', $categoryId)
                        ->orWhereHas('categories', fn ($categories) => $categories->where('blog_categories.id', $categoryId));
                });
            })
            ->when($request->filled('tag_id'), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->where('blog_tags.id', $request->integer('tag_id'))))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhere('focus_keyword', 'like', $search)
                        ->orWhere('seo_focus_keyword', 'like', $search);
                });
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $authors = User::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $tags = Tag::query()->orderBy('name')->get(['id', 'name']);
        $tabCounts = [
            'all' => Post::query()->count(),
            'published' => Post::query()->where('status', 'published')->count(),
            'scheduled' => Post::query()->where('status', 'scheduled')->count(),
            'trash' => Post::onlyTrashed()->count(),
        ];

        return view('blog::admin.posts.index', compact('posts', 'authors', 'categories', 'tags', 'view', 'tabCounts'));
    }

    public function create(): View
    {
        return $this->form(new Post([
            'status' => 'draft',
            'visibility' => 'public',
            'robots_index' => true,
            'robots_follow' => true,
            'schema_type' => 'Article',
            'seo_schema_type' => 'Article',
            'template' => 'default',
            'layout' => 'default',
            'layout_template' => 'default',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->attributes($request);
        $attributes['author_id'] = $request->user()?->id;
        $attributes['created_by'] = $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;

        $post = Post::query()->create($attributes);
        $this->syncTags($post, (string) $request->input('tags', ''));
        $this->createRevision($post, $request, 'manual');

        if ($request->input('intent') === 'preview') {
            return $this->relativeRedirect($this->previewPath($post));
        }

        return $this->relativeRedirect($this->adminPostPath($post, 'edit'), $this->statusMessage($post, true));
    }

    public function edit(Post $post): View
    {
        $post->load(['tags', 'featuredImage', 'revisions.user']);

        return $this->form($post);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->createRevision($post, $request, 'manual');

        $attributes = $this->attributes($request, $post);
        $attributes['author_id'] = $post->author_id ?? $request->user()?->id;
        $attributes['created_by'] = $post->created_by ?? $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;

        $post->update($attributes);
        $this->syncTags($post, (string) $request->input('tags', ''));

        if ($request->input('intent') === 'preview') {
            return $this->relativeRedirect($this->previewPath($post));
        }

        return $this->relativeRedirect($this->adminPostPath($post, 'edit'), $this->statusMessage($post, false));
    }

    public function quickUpdate(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($post->id)],
            'status' => ['required', 'in:draft,published,scheduled,private'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $publishedAt = filled($validated['published_at'] ?? null) ? Carbon::parse($validated['published_at']) : null;
        $scheduledAt = filled($validated['scheduled_at'] ?? null) ? Carbon::parse($validated['scheduled_at']) : null;

        if ($validated['status'] === 'published' && $publishedAt === null) {
            $publishedAt = $post->published_at ?? now();
        }

        if ($validated['status'] === 'scheduled') {
            $scheduledAt ??= $post->scheduled_at ?? now()->addHour();
            $publishedAt = $scheduledAt;
        }

        $quickAttributes = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'status' => $validated['status'],
            'published_at' => $publishedAt,
            'scheduled_at' => $scheduledAt,
            'author_id' => $validated['author_id'] ?? null,
            'updated_by' => $request->user()?->id,
        ];
        $post->loadMissing('tags');
        $quickAttributes['seo_score'] = app(SeoScoreCalculator::class)->calculate(
            array_merge($post->getAttributes(), $quickAttributes),
            $post->tags->pluck('name')->implode(','),
        );
        $post->update($quickAttributes);

        return $this->relativeRedirect('/admin/plugins/blog/posts#post-'.$post->id, 'Post details updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return $this->relativeRedirect('/admin/plugins/blog/posts', 'Post deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'bulk_action' => ['required', 'in:trash,restore,force_delete'],
        ]);
        $posts = Post::withTrashed()->whereKey($validated['ids'])->get();

        match ($validated['bulk_action']) {
            'trash' => $posts->each(fn (Post $post) => $post->trashed() ?: $post->delete()),
            'restore' => $posts->each(fn (Post $post) => $post->trashed() ? $post->restore() : null),
            'force_delete' => $posts->each(fn (Post $post) => $post->trashed() ? $post->forceDelete() : null),
        };

        $path = $validated['bulk_action'] === 'trash' ? '/admin/plugins/blog/posts' : '/admin/plugins/blog/posts?view=trash';

        return $this->relativeRedirect($path, 'Bulk action completed.');
    }

    public function emptyTrash(): RedirectResponse
    {
        Post::onlyTrashed()->get()->each->forceDelete();

        return $this->relativeRedirect('/admin/plugins/blog/posts?view=trash', 'Post trash emptied.');
    }

    public function preview(Post $post): View|RedirectResponse
    {
        if ($this->isPubliclyViewable($post)) {
            return $this->relativeRedirect($this->publicPostPath($post));
        }

        $post->load(['category', 'tags', 'author', 'featuredImage']);

        return view('blog::frontend.show', [
            'post' => $post,
            'preview' => true,
        ]);
    }

    public function autosave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => ['required', 'integer', 'exists:blog_posts,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $post = Post::query()->findOrFail($validated['post_id']);

        Revision::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()?->id,
            'revision_type' => 'autosave',
            'title' => $validated['title'] ?? $post->title,
            'slug' => $validated['slug'] ?? $post->slug,
            'content' => $this->sanitizeContent((string) ($validated['content'] ?? $post->content), $request),
            'excerpt' => $validated['excerpt'] ?? $post->excerpt,
            'payload' => $validated,
        ]);
        $this->pruneRevisions($post);

        $post->forceFill(['updated_by' => $request->user()?->id])->save();

        return response()->json([
            'ok' => true,
            'post_id' => $post->id,
            'edit_url' => $this->adminPostPath($post, 'edit'),
            'message' => 'Autosaved.',
        ]);
    }

    public function restoreRevision(Request $request, Post $post, Revision $revision): RedirectResponse
    {
        abort_unless($revision->post_id === $post->id, 404);

        $this->createRevision($post, $request, 'pre_restore');

        $payload = $revision->payload ?: [];
        $post->update([
            'title' => $revision->title ?: $post->title,
            'slug' => $revision->slug ?: $post->slug,
            'content' => $this->sanitizeContent((string) ($revision->content ?? $post->content), $request),
            'excerpt' => $revision->excerpt,
            'seo_title' => $payload['seo_title'] ?? $post->seo_title,
            'seo_description' => $payload['seo_description'] ?? $post->seo_description,
            'focus_keyword' => $payload['focus_keyword'] ?? $post->focus_keyword,
            'updated_by' => $request->user()?->id,
        ]);

        return $this->relativeRedirect($this->adminPostPath($post, 'edit'), 'Revision restored.');
    }

    public function destroyRevision(Post $post, Revision $revision): RedirectResponse
    {
        abort_unless($revision->post_id === $post->id, 404);

        $revision->delete();

        return $this->relativeRedirect($this->adminPostPath($post, 'edit'), 'Revision deleted.');
    }

    public function mediaLibrary(Request $request): JsonResponse
    {
        $media = Media::query()
            ->latest()
            ->take(60)
            ->get()
            ->map(fn (Media $item): array => $this->mediaPayload($item))
            ->values();

        return response()->json(['items' => $media]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $path = $file->store('blog/media', 'public');
        $url = Storage::disk('public')->url($path);
        $dimensions = $this->imageDimensions($file->getRealPath());

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'url' => $url,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'alt_text' => $validated['alt_text'] ?? '',
            'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'caption' => $validated['caption'] ?? '',
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json(['ok' => true, 'media' => $this->mediaPayload($media)]);
    }

    public function updateMedia(Request $request, Media $media): JsonResponse
    {
        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $media->update($validated);

        return response()->json(['ok' => true, 'media' => $this->mediaPayload($media)]);
    }

    public function destroyMedia(Media $media): JsonResponse
    {
        $media->delete();

        return response()->json(['ok' => true]);
    }

    public function slug(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'post_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'slug' => $this->uniqueSlug($validated['title'], $validated['post_id'] ?? null),
        ]);
    }

    private function form(Post $post): View
    {
        return view('blog::admin.posts.form', [
            'post' => $post,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'availableTags' => Tag::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'mediaItems' => Media::query()->latest()->take(24)->get(),
            'mediaLibrary' => [],
            'templates' => $this->templates(),
            'schemaTypes' => [
                'BlogPosting' => 'BlogPosting',
                'Article' => 'Article',
                'NewsArticle' => 'NewsArticle',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, ?Post $post = null): array
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,scheduled,private'],
            'visibility' => ['nullable', 'in:public,private,password'],
            'password' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'featured_image_id' => ['nullable', 'integer'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string', Rule::in(array_keys($this->templates()))],
            'layout' => ['nullable', 'string', 'max:80'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'robots_index' => ['nullable', 'boolean'],
            'robots_follow' => ['nullable', 'boolean'],
            'schema_type' => ['nullable', 'in:BlogPosting,Article,NewsArticle'],
        ]);

        $intent = (string) $request->input('intent', 'save');
        $status = $validated['status'] ?? $post?->status ?? 'draft';

        if ($intent === 'draft') {
            $status = 'draft';
        } elseif ($intent === 'publish') {
            $status = 'published';
        } elseif ($intent === 'schedule') {
            $status = 'scheduled';
        } elseif ($intent === 'preview') {
            $status = $status ?: 'draft';
        }

        $visibility = $validated['visibility'] ?? 'public';
        if ($status === 'private') {
            $visibility = 'private';
        }

        $publishedAt = ! empty($validated['published_at']) ? Carbon::parse($validated['published_at']) : null;
        $scheduledAt = ! empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = $post?->published_at ?? now();
        }

        if ($status === 'scheduled') {
            $scheduledAt = $scheduledAt ?: ($publishedAt && $publishedAt->isFuture() ? $publishedAt : now()->addHour());
            $publishedAt = $scheduledAt;
        }

        $slug = $validated['slug'] ?: $validated['title'];
        $template = $validated['template'] ?? $post?->template ?? $post?->layout_template ?? 'default';
        $content = $this->sanitizeContent((string) ($validated['content'] ?? ''), $request);
        $focusKeyword = $validated['focus_keyword'] ?? '';
        $seoTitle = $validated['seo_title'] ?? '';
        $seoDescription = $validated['seo_description'] ?? '';
        $schemaType = $validated['schema_type'] ?? 'BlogPosting';

        $attributes = [
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($slug, $post?->id),
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $content,
            'status' => $status,
            'visibility' => $visibility,
            'password' => $visibility === 'password' ? ($validated['password'] ?? $post?->password) : null,
            'published_at' => $publishedAt,
            'scheduled_at' => $scheduledAt,
            'featured_image_id' => $validated['featured_image_id'] ?? null,
            'featured_image' => $validated['featured_image'] ?? null,
            'featured_image_alt' => $validated['featured_image_alt'] ?? null,
            'template' => $template,
            'layout' => $validated['layout'] ?? $template,
            'layout_template' => $template,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'meta_title' => $seoTitle,
            'meta_description' => $seoDescription,
            'focus_keyword' => $focusKeyword,
            'seo_focus_keyword' => $focusKeyword,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'robots_index' => $request->boolean('robots_index', true),
            'robots_follow' => $request->boolean('robots_follow', true),
            'schema_type' => $schemaType,
            'seo_schema_type' => $schemaType,
        ];

        $attributes['seo_score'] = app(SeoScoreCalculator::class)->calculate($attributes, (string) $request->input('tags', ''));

        if (! empty($attributes['featured_image_id'])) {
            $media = Media::query()->find($attributes['featured_image_id']);
            $attributes['featured_image'] = $media?->url ?: $attributes['featured_image'];
            $attributes['featured_image_alt'] = $attributes['featured_image_alt'] ?: $media?->alt_text;
        }

        return $attributes;
    }

    private function syncTags(Post $post, string $tags): void
    {
        $ids = collect(explode(',', $tags))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(function (string $name): int {
                $existing = Tag::query()
                    ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                    ->first();

                return ($existing ?? Tag::query()->create([
                    'name' => $name,
                    'slug' => $this->uniqueTagSlug($name),
                ]))->id;
            })
            ->all();

        $post->tags()->sync($ids);
    }

    private function createRevision(Post $post, Request $request, string $type): void
    {
        if (! $post->exists) {
            return;
        }

        Revision::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()?->id,
            'revision_type' => $type,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'payload' => $post->only([
                'seo_title',
                'seo_description',
                'focus_keyword',
                'canonical_url',
                'robots_index',
                'robots_follow',
                'schema_type',
                'template',
                'layout',
            ]),
        ]);
        $this->pruneRevisions($post);
    }

    private function pruneRevisions(Post $post): void
    {
        $idsToDelete = Revision::query()
            ->where('post_id', $post->id)
            ->latest()
            ->pluck('id')
            ->skip(4)
            ->values();

        if ($idsToDelete->isNotEmpty()) {
            Revision::query()->whereIn('id', $idsToDelete)->delete();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function globalMediaLibrary(): array
    {
        $metadata = Schema::hasTable('platform_media_metadata')
            ? PlatformMediaMetadata::query()
                ->get(['url', 'alt_text', 'title', 'caption', 'description'])
                ->mapWithKeys(fn (PlatformMediaMetadata $item): array => [
                    $item->url => [
                        'alt_text' => $item->alt_text,
                        'title' => $item->title,
                        'caption' => $item->caption ?? '',
                        'description' => $item->description ?? '',
                    ],
                ])
                ->all()
            : [];

        return $this->publicMediaFilePaths()
            ->filter(fn (string $path): bool => preg_match('/\.(png|jpe?g|webp|ico|gif|svg)$/i', $path) === 1)
            ->map(function (string $path) use ($metadata): array {
                $url = '/storage/'.$path;

                return [
                    'url' => $url,
                    'path' => $path,
                    'name' => basename($path),
                    'alt_text' => $metadata[$url]['alt_text'] ?? '',
                    'title' => $metadata[$url]['title'] ?? '',
                    'caption' => $metadata[$url]['caption'] ?? '',
                    'description' => $metadata[$url]['description'] ?? '',
                    'modified_at' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($path)),
                ];
            })
            ->sortByDesc('modified_at')
            ->take(60)
            ->values()
            ->all();
    }

    private function publicMediaFilePaths(): Collection
    {
        return collect(Storage::disk('public')->allFiles())->unique()->values();
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'post';
        $slug = $base;
        $index = 2;

        while (Post::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueTagSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'tag';
        $slug = $base;
        $index = 2;

        while (Tag::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function sanitizeContent(string $html, Request $request): string
    {
        if ($this->scriptsAllowed($request)) {
            return $html;
        }

        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '$1="#"', $html) ?? '';

        return $html;
    }

    private function scriptsAllowed(Request $request): bool
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('super-admin')) {
            return false;
        }

        if (! Schema::hasTable('platform_settings') || ! Schema::hasColumn('platform_settings', 'setting_key') || ! Schema::hasColumn('platform_settings', 'value')) {
            return false;
        }

        return DB::table('platform_settings')
            ->where('setting_key', 'blog.allow_super_admin_scripts')
            ->whereIn('value', ['1', 'true', 'yes', 'enabled'])
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function templates(): array
    {
        return [
            'default' => 'Default',
            'feature_article' => 'Feature Article',
            'news_brief' => 'News Brief',
            'artist_profile' => 'Artist Profile',
        ];
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function imageDimensions(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($path);

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'mime_type' => $media->mime_type,
            'alt_text' => $media->alt_text,
            'title' => $media->title,
            'caption' => $media->caption,
            'width' => $media->width,
            'height' => $media->height,
            'is_image' => Str::startsWith((string) $media->mime_type, 'image/'),
        ];
    }

    private function statusMessage(Post $post, bool $created): string
    {
        if ($post->status === 'published') {
            return $created ? 'Post published.' : 'Post updated and published.';
        }

        if ($post->status === 'scheduled') {
            return 'Post scheduled.';
        }

        return $created ? 'Draft created.' : 'Post updated.';
    }

    private function adminPostPath(Post $post, string $action): string
    {
        return match ($action) {
            'edit' => '/admin/plugins/blog/posts/'.$post->getKey().'/edit',
            'preview' => '/admin/plugins/blog/posts/'.$post->getKey().'/preview',
            default => '/admin/plugins/blog/posts',
        };
    }

    private function publicPostPath(Post $post): string
    {
        return '/blog/'.$post->slug;
    }

    private function previewPath(Post $post): string
    {
        return $this->isPubliclyViewable($post)
            ? $this->publicPostPath($post)
            : $this->adminPostPath($post, 'preview');
    }

    private function isPubliclyViewable(Post $post): bool
    {
        if ($post->status !== 'published' || $post->visibility !== 'public' || blank($post->slug)) {
            return false;
        }

        if ($post->published_at && Carbon::parse($post->published_at)->isFuture()) {
            return false;
        }

        if ($post->scheduled_at && Carbon::parse($post->scheduled_at)->isFuture()) {
            return false;
        }

        return true;
    }

    private function relativeRedirect(string $path, ?string $status = null): RedirectResponse
    {
        $response = redirect()->to($path);

        if ($status !== null) {
            $response->with('status', $status);
        }

        $response->headers->set('Location', $path);

        return $response;
    }
}
