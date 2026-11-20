<?php

namespace Modules\Blog\Http\Controllers\Frontend;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Models\PlatformMediaMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Blog\Http\Controllers\Admin\PostController;
use Modules\Blog\Models\Author;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\Blog\Support\BlogLocale;
use Modules\Blog\Models\Revision;
use Modules\Blog\Services\AuthorAccessService;
use Throwable;

class WriterController extends PostController
{
    public function __construct(private readonly AuthorAccessService $access) {}

    public function index(Request $request): View
    {
        $this->access->activeAuthor($request->user());

        $posts = Post::query()
            ->where('author_id', $request->user()->getKey())
            ->with(['category', 'tags'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('blog::frontend.writer.index', ['posts' => $posts, 'trashed' => false]);
    }

    public function create(): View
    {
        $request = request();
        $author = $this->access->activeAuthor($request->user());

        return $this->writerForm(new Post([
            'status' => 'draft',
            'review_status' => 'none',
            'visibility' => 'public',
            'robots_index' => true,
            'robots_follow' => true,
            'schema_type' => 'Article',
            'seo_schema_type' => 'Article',
            'template' => 'default',
            'layout' => 'default',
            'layout_template' => 'default',
        ]), $author);
    }

    public function store(Request $request): RedirectResponse
    {
        $author = $this->access->activeAuthor($request->user());
        $attributes = $this->applyPublishingPolicy($this->attributes($request), $request, $author);
        $attributes['author_id'] = $request->user()->getKey();
        $attributes['created_by'] = $request->user()->getKey();
        $attributes['updated_by'] = $request->user()->getKey();

        $post = DB::transaction(function () use ($attributes, $request): Post {
            $post = Post::query()->create($attributes);
            $this->syncTags($post, (string) $request->input('tags', ''));
            $this->createRevision($post, $request, 'manual');

            return $post;
        });


        if ($request->input('intent') === 'preview') {
            return $this->writerRedirect(route('blog.writer.posts.preview', $post, false));
        }

        $path = route('blog.writer.posts.edit', $post, false);
        $switchLocale = (string) $request->input('switch_locale', '');

        if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
            $path .= '?locale='.urlencode($switchLocale);
        }

        return $this->writerRedirect($path, $this->writerStatusMessage($post, true));
    }

    public function edit(Request $request, Post $post): View
    {
        $author = $this->access->activeAuthor($request->user());
        $this->access->ensureOwnsPost($request->user(), $post);
        $post->load(['tags', 'featuredImage', 'revisions.user', 'translations']);

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = BlogLocale::default();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;
        $locale = (string) $request->query('locale', $defaultLocale);

        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }

        $translation = null;
        $display = $post;

        if (! BlogLocale::isDefault($locale)) {
            $translation = PostTranslation::resolve($post, $locale);
            $display = clone $post;
            $display->title = $translation?->title ?? $post->title;
            $display->slug = $translation?->slug ?? $post->slug;
            $display->excerpt = $translation?->excerpt ?? $post->excerpt;
            $display->content = $translation?->content ?? $post->content;
            $display->seo_title = $translation?->seo_title ?? $post->seo_title;
            $display->meta_title = $translation?->seo_title ?? $post->meta_title;
            $display->seo_description = $translation?->seo_description ?? $post->seo_description;
            $display->meta_description = $translation?->seo_description ?? $post->meta_description;
            $display->focus_keyword = $translation?->focus_keyword ?? $post->focus_keyword;
            $display->seo_focus_keyword = $translation?->focus_keyword ?? $post->seo_focus_keyword;
            $display->canonical_url = $translation?->canonical_url ?? $post->canonical_url;
        }

        return $this->writerForm($display, $author, [
            'locale' => $locale,
            'locales' => $locales,
            'translation' => $translation,
            'basePost' => $post,
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $author = $this->access->activeAuthor($request->user());
        $this->access->ensureOwnsPost($request->user(), $post);

        $locales = app(PlatformLocale::class)->languages();
        $locale = (string) $request->input('locale', BlogLocale::default());

        if (! BlogLocale::isDefault($locale) && array_key_exists($locale, $locales)) {
            return $this->updateWriterTranslation($request, $post, $locale);
        }

        $attributes = $this->applyPublishingPolicy($this->attributes($request, $post), $request, $author, $post);
        $attributes['author_id'] = $request->user()->getKey();
        $attributes['created_by'] = $post->created_by ?? $request->user()->getKey();
        $attributes['updated_by'] = $request->user()->getKey();

        DB::transaction(function () use ($post, $request, $attributes): void {
            $this->createRevision($post, $request, 'manual');
            $post->update($attributes);
            $this->syncTags($post, (string) $request->input('tags', ''));
        });


        if ($request->input('intent') === 'preview') {
            return $this->writerRedirect(route('blog.writer.posts.preview', $post, false));
        }

        return $this->writerRedirect(
            route('blog.writer.posts.edit', $post, false),
            $this->writerStatusMessage($post->refresh(), false),
        );
    }

    public function preview(Post $post): View
    {
        $request = request();
        $this->access->activeAuthor($request->user());
        $this->access->ensureOwnsPost($request->user(), $post);
        $post->load(['category', 'tags', 'author', 'featuredImage']);

        return view('blog::frontend.show', ['post' => $post, 'preview' => true]);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $request = request();
        $this->access->activeAuthor($request->user());
        $this->access->ensureOwnsPost($request->user(), $post);
        $post->delete();

        return $this->writerRedirect(route('blog.writer.index', [], false), 'Post moved to trash.');
    }

    public function trash(Request $request): View
    {
        $this->access->activeAuthor($request->user());

        $posts = Post::onlyTrashed()
            ->where('author_id', $request->user()->getKey())
            ->with(['category', 'tags'])
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('blog::frontend.writer.index', ['posts' => $posts, 'trashed' => true]);
    }

    public function restore(Request $request, string $post): RedirectResponse
    {
        $this->access->activeAuthor($request->user());
        $model = Post::onlyTrashed()->findOrFail($post);
        $this->access->ensureOwnsPost($request->user(), $model);
        $model->restore();

        return $this->writerRedirect(route('blog.writer.posts.trash', [], false), 'Post restored.');
    }

    public function forceDestroy(Request $request, string $post): RedirectResponse
    {
        $this->access->activeAuthor($request->user());
        $model = Post::onlyTrashed()->findOrFail($post);
        $this->access->ensureOwnsPost($request->user(), $model);
        $model->forceDelete();

        return $this->writerRedirect(route('blog.writer.posts.trash', [], false), 'Post permanently deleted.');
    }

    public function autosave(Request $request): JsonResponse
    {
        $this->access->activeAuthor($request->user());
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
        $this->access->ensureOwnsPost($request->user(), $post);

        Revision::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->getKey(),
            'revision_type' => 'autosave',
            'title' => $validated['title'] ?? $post->title,
            'slug' => $validated['slug'] ?? $post->slug,
            'content' => $this->sanitizeContent((string) ($validated['content'] ?? $post->content), $request),
            'excerpt' => $validated['excerpt'] ?? $post->excerpt,
            'payload' => $validated,
        ]);
        $this->pruneRevisions($post);
        $post->forceFill(['updated_by' => $request->user()->getKey()])->save();

        return response()->json([
            'ok' => true,
            'post_id' => $post->id,
            'edit_url' => route('blog.writer.posts.edit', $post, false),
            'message' => 'Autosaved.',
        ]);
    }

    public function slug(Request $request): JsonResponse
    {
        $this->access->activeAuthor($request->user());
        if ($request->filled('post_id')) {
            $post = Post::query()->findOrFail($request->integer('post_id'));
            $this->access->ensureOwnsPost($request->user(), $post);
        }

        return parent::slug($request);
    }

    public function mediaIndex(Request $request): JsonResponse
    {
        $this->access->activeAuthor($request->user());
        $items = Media::query()
            ->where('uploaded_by', $request->user()->getKey())
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (Media $media): array => $this->mediaPayload($media))
            ->values();

        return response()->json(['items' => $items]);
    }

    public function mediaStore(Request $request): JsonResponse
    {
        $this->access->activeAuthor($request->user());
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);
        $file = $request->file('image');
        $dimensions = $this->imageDimensions($file?->getRealPath());
        $path = $file?->store('media/'.now()->format('Y/m'), 'public');

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'The media file could not be saved.'], 422);
        }

        $url = Storage::disk('public')->url($path);
        $title = trim((string) ($validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));
        try {
            $media = DB::transaction(function () use ($request, $validated, $file, $dimensions, $path, $url, $title): Media {
                $media = Media::query()->create([
                    'disk' => 'public',
                    'path' => $path,
                    'url' => $url,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize() ?: 0,
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'alt_text' => $validated['alt_text'] ?? '',
                    'title' => $title,
                    'caption' => $validated['caption'] ?? '',
                    'uploaded_by' => $request->user()->getKey(),
                ]);

                if (Schema::hasTable('platform_media_metadata')) {
                    PlatformMediaMetadata::query()->updateOrCreate(
                        ['url' => $url],
                        [
                            'alt_text' => $media->alt_text ?: '',
                            'title' => $title,
                            'caption' => $media->caption ?: '',
                            'description' => '',
                        ],
                    );
                }

                return $media;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return response()->json(['ok' => true, 'media' => $this->mediaPayload($media)]);
    }

    private function writerForm(Post $post, Author $author, array $overrides = []): View
    {
        $isEdit = $post->exists;

        return $this->form($post, array_merge([
            'writerMode' => true,
            'canPublishDirectly' => $author->canPublishDirectly(),
            'formAction' => $isEdit
                ? route('blog.writer.posts.update', $post, false)
                : route('blog.writer.posts.store', [], false),
            'deleteAction' => $isEdit ? route('blog.writer.posts.destroy', $post, false) : null,
            'editorRoutes' => [
                'autosave' => route('blog.writer.posts.autosave', [], false),
                'slug' => route('blog.writer.posts.slug', [], false),
                'mediaIndex' => route('blog.writer.media.index', [], false),
                'mediaStore' => route('blog.writer.media.store', [], false),
                'categoryQuick' => null,
            ],
        ], $overrides));
    }

    private function updateWriterTranslation(Request $request, Post $post, string $locale): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
        ]);

        $translation = PostTranslation::query()->firstOrNew([
            'post_id' => $post->id,
            'locale' => $locale,
        ]);

        $slugSource = trim((string) ($validated['slug'] ?? '')) ?: $validated['title'];
        $slug = PostTranslation::uniqueSlug($slugSource, $locale, $translation->id);

        $translation->fill([
            'title' => $validated['title'],
            'slug' => $slug,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $this->sanitizeContent((string) ($validated['content'] ?? ''), $request),
            'featured_image' => $validated['featured_image'] ?? null,
            'featured_image_alt' => $validated['featured_image_alt'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'focus_keyword' => $validated['focus_keyword'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'updated_by' => $request->user()?->getKey(),
        ]);

        if (! $translation->exists) {
            $translation->created_by = $request->user()?->getKey();
        }

        $translation->save();

        return $this->writerRedirect(
            route('blog.writer.posts.edit', $post, false).'?locale='.urlencode($locale),
            __('core.translate.saved'),
        );
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function applyPublishingPolicy(array $attributes, Request $request, Author $author, ?Post $post = null): array
    {
        $intent = (string) $request->input('intent', 'draft');

        if (! $author->canPublishDirectly()) {
            $attributes['status'] = 'draft';
            $attributes['published_at'] = null;
            $attributes['scheduled_at'] = null;
            $attributes['review_status'] = $intent === 'publish'
                ? 'pending'
                : ($intent === 'preview' ? ($post?->review_status ?? 'none') : 'none');

            return $attributes;
        }

        $attributes['review_status'] = in_array($attributes['status'], ['published', 'scheduled', 'private'], true)
            ? 'approved'
            : 'none';

        return $attributes;
    }

    private function writerStatusMessage(Post $post, bool $created): string
    {
        if ($post->review_status === 'pending') {
            return 'Post submitted for editorial review.';
        }

        if ($post->status === 'published') {
            return $created ? 'Post published.' : 'Post updated and published.';
        }

        if ($post->status === 'scheduled') {
            return 'Post scheduled.';
        }

        return $created ? 'Draft created.' : 'Draft updated.';
    }

    private function writerRedirect(string $path, ?string $status = null): RedirectResponse
    {
        $response = redirect()->to($path);
        if ($status !== null) {
            $response->with('status', $status);
        }
        $response->headers->set('Location', $path);

        return $response;
    }
}
