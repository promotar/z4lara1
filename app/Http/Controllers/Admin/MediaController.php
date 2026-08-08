<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Models\PlatformMediaMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $mediaLibrary = $this->mediaLibrary($request->string('type')->toString());

        if ($request->expectsJson()) {
            return response()->json([
                'items' => collect($mediaLibrary)
                    ->map(fn (array $item): array => $this->mediaPayload($item))
                    ->values(),
            ]);
        }

        return view('admin.media.index', [
            'mediaLibrary' => $mediaLibrary,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $fileField = $request->hasFile('media') ? 'media' : 'image';
        $validated = $request->validate([
            $fileField => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif,ico,svg,pdf,mp4,webm,mov,m4v,avi,mkv,mp3,wav,ogg,zip,rar,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf', 'max:204800'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file($fileField);
        $directory = 'media/'.now()->format('Y/m');
        Storage::disk('public')->makeDirectory($directory);

        $path = $file?->store($directory, 'public');

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            $message = 'The media file could not be saved to the media library. Please check storage permissions.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'errors' => [$fileField => [$message]],
                ], 422);
            }

            return back()->withErrors([$fileField => $message])->withInput();
        }

        $url = Storage::url($path);

        if ($request->expectsJson()) {
            $this->saveMediaMetadata($url, [
                'alt_text' => $validated['alt_text'] ?? '',
                'title' => $validated['title'] ?? pathinfo($file?->getClientOriginalName() ?: basename($path), PATHINFO_FILENAME),
                'caption' => $validated['caption'] ?? '',
                'description' => $validated['description'] ?? '',
            ]);

            $metadata = $this->mediaMetadata()[$url] ?? [
                'alt_text' => '',
                'title' => '',
                'caption' => '',
                'description' => '',
            ];

            return response()->json([
                'ok' => true,
                'media' => $this->mediaPayload([
                    'url' => $url,
                    'path' => $path,
                    'name' => basename($path),
                    'directory' => dirname($path) === '.' ? '' : dirname($path),
                    'size' => $this->formatBytes((int) Storage::disk('public')->size($path)),
                    'modified_at' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($path)),
                    'metadata' => $metadata,
                ]),
            ]);
        }

        return back()->with('status', 'Media uploaded successfully: '.$url);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media_url' => ['required', 'string'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->publicStoragePathFromUrl($validated['media_url'])) {
            return back()->with('status', 'Media file was not found.');
        }

        $this->saveMediaMetadata($validated['media_url'], [
            'alt_text' => $validated['alt_text'] ?? '',
            'title' => $validated['title'] ?? '',
            'caption' => $validated['caption'] ?? '',
            'description' => $validated['description'] ?? '',
        ]);

        return back()->with('status', 'Image SEO data saved successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media_urls' => ['required', 'array', 'min:1'],
            'media_urls.*' => ['required', 'string'],
        ]);

        $deleted = 0;

        foreach ($validated['media_urls'] as $url) {
            $path = $this->publicStoragePathFromUrl((string) $url);

            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $this->deleteMediaMetadata((string) $url);
                $deleted++;
            }
        }

        return back()->with('status', $deleted.' media file(s) deleted successfully.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mediaLibrary(string $type = ''): array
    {
        $metadata = $this->mediaMetadata();
        $pattern = match ($type) {
            'image' => '/\.(png|jpe?g|webp|ico|gif|svg)$/i',
            'video' => '/\.(mp4|webm|mov|m4v|avi|mkv)$/i',
            'pdf' => '/\.pdf$/i',
            'file' => '/\.(png|jpe?g|webp|ico|gif|svg|pdf|mp4|webm|mov|m4v|avi|mkv|mp3|wav|ogg|zip|rar|docx?|xlsx?|pptx?|txt|csv|rtf)$/i',
            default => '/\.(png|jpe?g|webp|ico|gif|svg|pdf|mp4|webm|mov|m4v|avi|mkv|mp3|wav|ogg|zip|rar|docx?|xlsx?|pptx?|txt|csv|rtf)$/i',
        };

        return $this->publicMediaFilePaths()
            ->filter(fn (string $path): bool => preg_match($pattern, $path) === 1)
            ->map(function (string $path) use ($metadata): array {
                $url = '/storage/'.$path;
                $size = Storage::disk('public')->size($path);

                return [
                    'url' => $url,
                    'path' => $path,
                    'name' => basename($path),
                    'directory' => dirname($path) === '.' ? '' : dirname($path),
                    'size' => $this->formatBytes($size),
                    'modified_at' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($path)),
                    'metadata' => $metadata[$url] ?? [
                        'alt_text' => '',
                        'title' => '',
                        'caption' => '',
                        'description' => '',
                    ],
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    private function publicMediaFilePaths(): \Illuminate\Support\Collection
    {
        return collect(Storage::disk('public')->allFiles())->unique()->values();
    }

    /**
     * @return array<string, array{alt_text: string, title: string, caption: string, description: string}>
     */
    private function mediaMetadata(): array
    {
        $this->importLegacyMediaMetadata();

        if (! Schema::hasTable('platform_media_metadata')) {
            return [];
        }

        return PlatformMediaMetadata::query()
            ->get(['url', 'alt_text', 'title', 'caption', 'description'])
            ->mapWithKeys(fn (PlatformMediaMetadata $metadata): array => [
                $metadata->url => [
                    'alt_text' => $metadata->alt_text,
                    'title' => $metadata->title,
                    'caption' => $metadata->caption ?? '',
                    'description' => $metadata->description ?? '',
                ],
            ])
            ->all();
    }

    /**
     * @param array{alt_text?: string|null, title?: string|null, caption?: string|null, description?: string|null} $metadata
     */
    private function saveMediaMetadata(string $url, array $metadata): void
    {
        if (! Schema::hasTable('platform_media_metadata')) {
            return;
        }

        PlatformMediaMetadata::query()->updateOrCreate(
            ['url' => $url],
            [
                'alt_text' => trim((string) ($metadata['alt_text'] ?? '')),
                'title' => trim((string) ($metadata['title'] ?? '')),
                'caption' => trim((string) ($metadata['caption'] ?? '')),
                'description' => trim((string) ($metadata['description'] ?? '')),
            ],
        );
    }

    private function deleteMediaMetadata(string $url): void
    {
        if (! Schema::hasTable('platform_media_metadata')) {
            return;
        }

        PlatformMediaMetadata::query()
            ->where('url', $url)
            ->delete();
    }

    private function importLegacyMediaMetadata(): void
    {
        if (! Schema::hasTable('platform_media_metadata')) {
            return;
        }

        $legacyPath = storage_path('app/platform/media-metadata.json');

        if (! File::exists($legacyPath) || PlatformMediaMetadata::query()->exists()) {
            return;
        }

        $decoded = json_decode(File::get($legacyPath), true);

        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $url => $metadata) {
            if (! is_string($url) || ! is_array($metadata) || ! $this->publicStoragePathFromUrl($url)) {
                continue;
            }

            $this->saveMediaMetadata($url, $metadata);
        }
    }

    private function publicStoragePathFromUrl(string $url): ?string
    {
        $url = trim($url);

        if (! str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = ltrim(substr($url, strlen('/storage/')), '/');

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function mediaPayload(array $item): array
    {
        $metadata = $item['metadata'] ?? [];
        $path = (string) ($item['path'] ?? '');
        $absolutePath = $path !== '' && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : null;
        $dimensions = $absolutePath && is_file($absolutePath)
            ? @getimagesize($absolutePath)
            : false;
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;
        $mimeType = is_array($dimensions)
            ? (string) ($dimensions['mime'] ?? '')
            : ($path !== '' && Storage::disk('public')->exists($path) ? (string) (Storage::disk('public')->mimeType($path) ?? '') : '');
        $isImage = str_starts_with($mimeType, 'image/');
        $isVideo = str_starts_with($mimeType, 'video/');
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mediaId = sha1((string) ($item['url'] ?? $path));

        return [
            'id' => $mediaId,
            'media_id' => $mediaId,
            'url' => $item['url'],
            'media_url' => $item['url'],
            'image_url' => $isImage ? $item['url'] : null,
            'thumbnail_url' => $isImage ? $item['url'] : null,
            'path' => $item['path'] ?? null,
            'name' => $item['name'] ?? basename((string) $item['url']),
            'extension' => $extension,
            'directory' => $item['directory'] ?? '',
            'size' => $item['size'] ?? '',
            'modified_at' => $item['modified_at'] ?? '',
            'width' => $width,
            'height' => $height,
            'mime_type' => $mimeType,
            'type' => $mimeType,
            'alt_text' => $metadata['alt_text'] ?? '',
            'title' => $metadata['title'] ?? '',
            'caption' => $metadata['caption'] ?? '',
            'description' => $metadata['description'] ?? '',
            'is_image' => $isImage,
            'is_video' => $isVideo,
            'is_pdf' => $extension === 'pdf' || $mimeType === 'application/pdf',
        ];
    }
}
