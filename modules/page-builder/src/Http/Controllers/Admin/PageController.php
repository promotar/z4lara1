<?php

namespace Modules\PageBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Rendering\PlatformContentRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\PageBuilder\VvvebDocument;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $pages = DB::table('platform_pages');

        if ($search !== '') {
            $pages->where(fn ($query) => $query
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')
                ->orWhere('content_type', 'like', '%'.$search.'%'));
        }

        return view('page-builder::pages.index', [
            'pages' => $pages->latest('updated_at')->latest('id')->get(),
            'search' => $search,
        ]);
    }

    public function store(Request $request, OperationLogger $operations): RedirectResponse
    {
        $type = $this->contentType((string) $request->input('content_type', 'page'));
        $title = 'Untitled '.ucfirst($type).' '.now()->format('Y-m-d H:i');
        $slug = $this->uniqueSlug($title);
        $now = now();
        $id = DB::table('platform_pages')->insertGetId([
            'title' => $title,
            'slug' => $slug,
            'content_type' => $type,
            'block_key' => $type === 'block' ? $slug : null,
            'parent_id' => null,
            'category' => null,
            'menu_label' => null,
            'show_in_menu' => false,
            'content' => null,
            'vvvebjs_html' => null,
            'html' => null,
            'css' => null,
            'status' => 'draft',
            'sort_order' => 0,
            'seo_title' => null,
            'meta_description' => null,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operation = $operations->start('admin.vvvebjs.create', 'vvvebjs-page', (string) $id, ['type' => $type], $request->user()?->id);
        $operations->success($operation, 'VvvebJs draft created.');

        return redirect()->route('admin.pages.edit', $id);
    }

    public function legacyThemeStore(
        Request $request,
        VvvebDocument $documents,
        OperationLogger $operations,
    ): RedirectResponse {
        $data = $request->validate([
            'template_type' => ['required', Rule::in([
                'header', 'footer', 'single_post', 'single_page', 'archive', 'search_results', 'error_404',
            ])],
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'template_file' => ['nullable', 'file', 'max:20480', 'mimes:json,html,htm,txt'],
        ]);

        $type = match ($data['template_type']) {
            'header' => 'header',
            'footer' => 'footer',
            default => 'page',
        };
        $title = trim((string) ($data['name'] ?? '')) ?: 'Untitled '.ucfirst($type).' '.now()->format('Y-m-d H:i');
        $slug = $this->uniqueSlug($title);
        $status = (string) ($data['status'] ?? 'draft');
        $source = $request->file('template_file')?->get();
        $html = is_string($source) ? $source : '';
        $css = '';

        if ($html !== '' && str_ends_with(strtolower((string) $request->file('template_file')?->getClientOriginalName()), '.json')) {
            $payload = json_decode($html, true);
            if (is_array($payload)) {
                $html = (string) ($payload['html'] ?? data_get($payload, 'template.html', ''));
                $css = (string) ($payload['css'] ?? data_get($payload, 'template.css', ''));
            }
        }

        $document = $documents->normalize($documents->blank($title, $html, $css), $title);
        $now = now();
        $id = DB::table('platform_pages')->insertGetId([
            'title' => $title,
            'slug' => $slug,
            'content_type' => $type,
            'block_key' => null,
            'parent_id' => null,
            'category' => 'migrated-theme-builder-request',
            'menu_label' => null,
            'show_in_menu' => false,
            'content' => $documents->body($document),
            'vvvebjs_html' => $document,
            'html' => $documents->body($document),
            'css' => $documents->css($document),
            'status' => $status,
            'sort_order' => 0,
            'seo_title' => null,
            'meta_description' => $request->string('description')->trim()->value() ?: null,
            'published_at' => $status === 'published' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operation = $operations->start('admin.vvvebjs.legacy-theme-create', 'vvvebjs-page', (string) $id, [
            'legacy_template_type' => $data['template_type'],
            'content_type' => $type,
        ], $request->user()?->id);
        $operations->success($operation, 'Legacy Theme Builder request migrated to VvvebJs.');

        return redirect()->route('admin.pages.edit', $id);
    }

    public function edit(int $page, PlatformContentRenderer $renderer, HookManager $hooks): Response
    {
        $record = $this->findPage($page);
        $template = dirname(__DIR__, 4).'/resources/vvvebjs/editor.html';
        abort_unless(is_file($template), 503, 'VvvebJs assets are not installed.');
        $assetBase = url('/page-builder-assets/v5');

        $config = [
            'csrfToken' => csrf_token(),
            'page' => [
                'id' => $record->id,
                'title' => $record->title,
                'slug' => $record->slug,
                'contentType' => $record->content_type ?? 'page',
                'status' => $record->status,
                'seoTitle' => $record->seo_title,
                'metaDescription' => $record->meta_description,
                'blockKey' => $record->block_key ?? null,
                'sortOrder' => (int) ($record->sort_order ?? 0),
                'documentVersion' => $this->documentVersion($record),
            ],
            'pages' => [
                'current' => [
                    'name' => 'current',
                    'filename' => $record->slug.'.html',
                    'file' => $record->slug.'.html',
                    'url' => route('admin.pages.vvveb-canvas', $record->id),
                    'title' => $record->title,
                    'folder' => null,
                ],
            ],
            'saveUrl' => route('admin.pages.vvveb-save', $record->id),
            'previewUrl' => route('admin.pages.preview', $record->id),
            'indexUrl' => route('admin.pages.index'),
            'assetBase' => $assetBase,
            'mediaUrl' => route('admin.pages.vvveb-media'),
            'mediaUploadUrl' => route('admin.media.store'),
            'revisionsUrl' => route('admin.pages.vvveb-revisions', $record->id),
            'reusableUrl' => route('admin.pages.vvveb-reusable'),
            'frontendMenus' => $renderer->menuTraitOptions(),
            'frontendMenuItems' => $renderer->menuPreviewItems(request()->user()),
            'reusables' => DB::table('platform_pages')->where('content_type', 'block')->orderBy('title')->get(['id', 'title', 'html', 'category'])->map(fn (object $block): array => [
                'id' => $block->id,
                'name' => $block->title,
                'html' => (string) $block->html,
                'type' => $block->category === 'vvvebjs-reusable-section' ? 'section' : 'block',
            ])->all(),
        ];

        /*
         * PAGE BUILDER EXTENSION POINT
         * ----------------------------
         * Feature plugins must not patch VvvebJs or this controller to add elements. An active
         * plugin registers the `plugin.page-builder.editor.extensions` filter and appends a descriptor:
         *
         * ['id' => 'vendor.feature', 'config' => [...], 'styles' => [...], 'scripts' => [...]]
         *
         * The descriptor is normalized below, its config is exposed at
         * window.ArtInpaVvvebConfig.extensions[id], and its assets are loaded only while that
         * plugin's hooks are active. See modules/blog/hooks.php for a complete implementation.
         */
        $extensions = $this->editorExtensions(
            $hooks->applyFilters('plugin.page-builder.editor.extensions', [], $record),
        );
        $config['extensions'] = collect($extensions)
            ->mapWithKeys(fn (array $extension): array => [
                $extension['id'] => $extension['config'],
            ])
            ->all();

        $html = (string) file_get_contents($template);
        $encodedConfig = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $html = str_replace('<base href="">', '<base href="'.$assetBase.'/">', $html);
        $extensionStyles = collect($extensions)
            ->flatMap(fn (array $extension): array => $extension['styles'])
            ->map(fn (string $url): string => '<link rel="stylesheet" href="'.e($url).'" data-page-builder-extension>')
            ->implode('');
        $extensionScripts = collect($extensions)
            ->flatMap(fn (array $extension): array => $extension['scripts'])
            ->map(fn (string $url): string => '<script src="'.e($url).'" data-page-builder-extension></script>')
            ->implode('');

        $html = str_replace('</head>', '<script>window.ArtInpaVvvebConfig='.$encodedConfig.';</script><link rel="stylesheet" href="'.$assetBase.'/integration/css/vvveb-integration.css">'.$extensionStyles.'</head>', $html);
        $html = str_replace('data-vvveb-url="save.php"', 'data-vvveb-url="'.e($config['saveUrl']).'"', $html);
        $html = str_replace("window.mediaPath = '../../media';", "window.mediaPath = '/';", $html);
        $html = str_replace("Vvveb.themeBaseUrl = 'demo/landing/';", "Vvveb.themeBaseUrl = '".$assetBase."/demo/landing/';", $html);
        $html = str_replace("let saveReusableUrl = 'save.php?action=saveReusable';", "let saveReusableUrl = '".$config['reusableUrl']."';", $html);
        $html = str_replace('let pages = defaultPages;', 'let pages = window.ArtInpaVvvebConfig.pages;', $html);
        // Plugin elements load first so the standard integration pass can apply global VvvebJs
        // properties (for example z-index) to core and plugin-owned elements uniformly.
        $html = str_replace('</body>', $extensionScripts.'<script src="'.$assetBase.'/integration/js/vvveb-integration.js"></script></body>', $html);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'no-store');
    }

    /**
     * Normalize the public Page Builder extension contract and reject malformed or unsafe assets.
     *
     * @return list<array{id:string, config:array<string, mixed>, styles:list<string>, scripts:list<string>}>
     */
    private function editorExtensions(mixed $extensions): array
    {
        if (! is_array($extensions)) {
            return [];
        }

        return collect($extensions)
            ->filter(fn (mixed $extension): bool => is_array($extension))
            ->map(function (array $extension): array {
                $id = trim((string) ($extension['id'] ?? ''));

                return [
                    'id' => $id,
                    'config' => is_array($extension['config'] ?? null) ? $extension['config'] : [],
                    'styles' => $this->extensionAssetUrls($extension['styles'] ?? [], 'css'),
                    'scripts' => $this->extensionAssetUrls($extension['scripts'] ?? [], 'js'),
                ];
            })
            ->filter(fn (array $extension): bool => preg_match('/^[a-z0-9][a-z0-9._-]*$/', $extension['id']) === 1)
            ->unique('id')
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function extensionAssetUrls(mixed $urls, string $extension): array
    {
        if (is_string($urls)) {
            $urls = [$urls];
        }

        if (! is_array($urls)) {
            return [];
        }

        return collect($urls)
            ->filter(fn (mixed $url): bool => is_string($url))
            ->map(fn (string $url): string => trim($url))
            ->filter(function (string $url) use ($extension): bool {
                $path = (string) parse_url($url, PHP_URL_PATH);
                $isLocal = str_starts_with($url, '/') || str_starts_with($url, url('/'));

                return $isLocal && str_ends_with(strtolower($path), '.'.$extension);
            })
            ->unique()
            ->values()
            ->all();
    }

    public function canvas(int $page, VvvebDocument $documents): Response
    {
        return response($documents->fromPage($this->findPage($page)))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, int $page, VvvebDocument $documents, OperationLogger $operations): JsonResponse
    {
        $record = $this->findPage($page);
        $data = $request->validate([
            'html' => ['required', 'string', 'max:16000000'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content_type' => ['nullable', Rule::in(['page', 'header', 'footer', 'block'])],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'block_key' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:-100000,100000'],
            'autosave' => ['nullable', 'boolean'],
            'document_version' => ['nullable', 'string', 'size:64'],
        ]);

        if (! is_string($data['document_version'] ?? null)
            || ! hash_equals($this->documentVersion($record), $data['document_version'])) {
            return response()->json([
                'success' => false,
                'stale' => true,
                'message' => 'This page changed after the editor was opened. Reload before saving to avoid overwriting newer content.',
            ], 409);
        }

        $document = $documents->normalize($data['html'], (string) ($data['title'] ?? $record->title));
        $title = trim((string) ($data['title'] ?? $record->title)) ?: (string) $record->title;
        $slug = $this->uniqueSlug((string) ($data['slug'] ?? $record->slug), $page);
        $type = $this->contentType((string) ($data['content_type'] ?? $record->content_type ?? 'page'));
        $status = (string) ($data['status'] ?? $record->status ?? 'draft');
        $now = now();

        if (! $request->boolean('autosave')) {
            DB::table('vvvebjs_page_revisions')->insert([
                'page_id' => $page,
                'title' => $record->title,
                'vvvebjs_html' => $record->vvvebjs_html,
                'created_by' => $request->user()?->id,
                'created_at' => $now,
            ]);
        }

        DB::table('platform_pages')->where('id', $page)->update([
            'title' => $title,
            'slug' => $slug,
            'content_type' => $type,
            'block_key' => $type === 'block' ? ($data['block_key'] ?? $record->block_key ?? $slug) : null,
            'vvvebjs_html' => $document,
            'html' => $documents->body($document),
            'content' => $documents->body($document),
            'css' => $documents->css($document),
            'status' => $status,
            'sort_order' => (int) ($data['sort_order'] ?? $record->sort_order ?? 0),
            'seo_title' => $data['seo_title'] ?? $record->seo_title,
            'meta_description' => $data['meta_description'] ?? $record->meta_description,
            'published_at' => $status === 'published' ? ($record->published_at ?: $now) : null,
            'updated_at' => $now,
        ]);

        if (! $request->boolean('autosave')) {
            $operation = $operations->start('admin.vvvebjs.save', 'vvvebjs-page', (string) $page, ['status' => $status, 'type' => $type], $request->user()?->id);
            $operations->success($operation, 'VvvebJs document saved.');
        }

        return response()->json([
            'success' => true,
            'message' => $request->boolean('autosave') ? 'Autosaved' : 'Page saved',
            'updated_at' => $now->toIso8601String(),
            'document_version' => hash('sha256', $page.'|'.$now->toDateTimeString().'|'.$document),
        ]);
    }

    public function preview(int $page): View
    {
        return view('page-builder::public.show', ['page' => $this->findPage($page), 'isPreview' => true]);
    }

    public function revisions(int $page): JsonResponse
    {
        $this->findPage($page);

        return response()->json([
            'revisions' => DB::table('vvvebjs_page_revisions')->where('page_id', $page)->latest('id')->limit(20)->get()->map(fn (object $revision): array => [
                'id' => $revision->id,
                'title' => $revision->title,
                'created_at' => $revision->created_at,
                'restore_url' => route('admin.pages.vvveb-revisions.restore', [$page, $revision->id]),
            ])->all(),
        ]);
    }

    public function restoreRevision(Request $request, int $page, int $revision, VvvebDocument $documents): JsonResponse
    {
        $this->findPage($page);
        $snapshot = DB::table('vvvebjs_page_revisions')->where('page_id', $page)->where('id', $revision)->first();
        abort_unless($snapshot, 404);
        $document = $documents->normalize((string) $snapshot->vvvebjs_html, (string) $snapshot->title);
        DB::table('platform_pages')->where('id', $page)->update([
            'vvvebjs_html' => $document,
            'html' => $documents->body($document),
            'content' => $documents->body($document),
            'css' => $documents->css($document),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Revision restored.']);
    }

    public function reusable(Request $request, VvvebDocument $documents): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['section', 'block'])],
            'name' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string', 'max:4000000'],
        ]);
        $slug = $this->uniqueSlug($data['name']);
        $html = $documents->sanitizeFragment($data['html']);
        $now = now();
        $id = DB::table('platform_pages')->insertGetId([
            'title' => $data['name'], 'slug' => $slug, 'content_type' => 'block', 'block_key' => $slug,
            'parent_id' => null, 'category' => 'vvvebjs-reusable-'.$data['type'], 'menu_label' => null, 'show_in_menu' => false,
            'content' => $html, 'vvvebjs_html' => $documents->blank($data['name'], $html), 'html' => $html, 'css' => null,
            'status' => 'published', 'sort_order' => 0, 'seo_title' => null, 'meta_description' => null, 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return response()->json(['success' => true, 'message' => 'Reusable '.$data['type'].' saved.', 'id' => $id]);
    }

    public function media(): JsonResponse
    {
        $files = collect(Storage::disk('public')->allFiles())->map(fn (string $path): array => [
            'name' => basename($path),
            'type' => 'file',
            'path' => 'storage/'.$path,
            'size' => Storage::disk('public')->size($path),
        ])->values()->all();

        return response()->json(['name' => 'storage', 'type' => 'folder', 'path' => 'storage', 'items' => $files]);
    }

    public function themeBuilder(): View
    {
        $sections = Schema::hasTable('vvvebjs_layout_sections')
            ? DB::table('vvvebjs_layout_sections')->orderBy('sort_order')->orderBy('id')->get()->groupBy('placement')
            : collect();

        return view('page-builder::pages.layout', [
            'designs' => $this->publishedDesigns(),
            'selectedHeaders' => $sections->get('header', collect())->pluck('page_id')->map(fn ($id): int => (int) $id)->values()->all(),
            'selectedFooters' => $sections->get('footer', collect())->pluck('page_id')->map(fn ($id): int => (int) $id)->values()->all(),
        ]);
    }

    public function updateThemeBuilder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headers' => ['nullable', 'array', 'max:50'],
            'headers.*' => ['required', 'integer', 'distinct', Rule::exists('platform_pages', 'id')->where(fn ($query) => $query->where('status', 'published')->where('content_type', '!=', 'block'))],
            'footers' => ['nullable', 'array', 'max:50'],
            'footers.*' => ['required', 'integer', 'distinct', Rule::exists('platform_pages', 'id')->where(fn ($query) => $query->where('status', 'published')->where('content_type', '!=', 'block'))],
        ]);

        DB::transaction(function () use ($data): void {
            DB::table('vvvebjs_layout_sections')->delete();

            foreach (['header' => $data['headers'] ?? [], 'footer' => $data['footers'] ?? []] as $placement => $pageIds) {
                foreach (array_values($pageIds) as $sortOrder => $pageId) {
                    DB::table('vvvebjs_layout_sections')->insert([
                        'placement' => $placement,
                        'page_id' => $pageId,
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', 'Theme Layout saved in the selected order.');
    }

    public function destroy(int $page): RedirectResponse
    {
        DB::table('platform_pages')->where('id', $page)->delete();

        return redirect()->route('admin.pages.index')->with('status', 'VvvebJs page deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['pages' => ['required', 'array'], 'pages.*' => ['integer']])['pages'];
        DB::table('platform_pages')->whereIn('id', $ids)->delete();

        return back()->with('status', count($ids).' page(s) deleted.');
    }

    private function findPage(int $id): object
    {
        $page = DB::table('platform_pages')->where('id', $id)->first();
        abort_unless($page, 404);

        return $page;
    }

    private function documentVersion(object $page): string
    {
        return hash('sha256', $page->id.'|'.$page->updated_at.'|'.(string) ($page->vvvebjs_html ?? ''));
    }

    private function contentType(string $value): string
    {
        return in_array($value, ['page', 'header', 'footer', 'block'], true) ? $value : 'page';
    }

    private function uniqueSlug(string $value, ?int $except = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $index = 2;

        while (DB::table('platform_pages')->where('slug', $slug)->when($except, fn ($query) => $query->where('id', '!=', $except))->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function publishedDesigns(): Collection
    {
        return DB::table('platform_pages')
            ->where('status', 'published')
            ->where('content_type', '!=', 'block')
            ->orderBy('title')
            ->get();
    }
}
