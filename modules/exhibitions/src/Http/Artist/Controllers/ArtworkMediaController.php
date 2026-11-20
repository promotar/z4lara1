<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Media\ArtworkMediaService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkMediaController extends Controller
{
    public function __construct(private readonly ArtworkService $artworks, private readonly ArtworkMediaService $media, private readonly ArtistDashboardService $dashboard) {}

    public function index(Request $request, string $artwork): View
    {
        $row = $this->artworks->owned((int) $request->user()->id, $artwork);

        return view('exhibitions::artist.artworks.media', [
            'artwork' => $row, 'media' => DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->whereNotIn('state', ['deleted', 'replaced'])->orderBy('sort_order')->get(),
            'roles' => $this->media->roles(), 'history' => DB::table('exhibition_artwork_media_history')->where('artwork_id', $row->id)->orderByDesc('occurred_at')->limit(30)->get(),
        ] + $this->navigation((int) $request->user()->id));
    }

    public function upload(Request $request, string $artwork): RedirectResponse|JsonResponse
    {
        if ($request->has('items')) {
            $errorKey = 'items';
            $validated = $request->validate([
                'items' => ['required', 'array', 'min:1', 'max:8'],
                'items.*.role' => ['required', Rule::in($this->media->roles())],
                'items.*.file' => ['required', 'file', 'max:20480'],
            ]);
            $items = array_values(array_map(
                static fn (array $item): array => ['role' => $item['role'], 'file' => $item['file']],
                $validated['items']
            ));
        } else {
            $errorKey = 'files';
            // Backwards compatibility for clients using one role with a file array.
            $validated = $request->validate([
                'role' => ['required', Rule::in($this->media->roles())],
                'files' => ['required', 'array', 'min:1', 'max:8'],
                'files.*' => ['required', 'file', 'max:20480'],
            ]);
            $items = array_map(
                static fn ($file): array => ['role' => $validated['role'], 'file' => $file],
                $validated['files']
            );
        }

        try {
            $created = $this->media->uploadItems((int) $request->user()->id, $artwork, $items);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([$errorKey => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => count($created).' image(s) uploaded and processed safely.']);
        }

        return back()->with('status', 'Artwork image processing completed.');
    }

    public function replace(Request $request, string $artwork, string $media): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $existing = $this->media->ownedMedia((int) $request->user()->id, $artwork, $media);
        try {
            $this->media->upload((int) $request->user()->id, $artwork, $validated['file'], $existing->role, $existing->public_id);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return back()->with('status', 'Artwork image replaced with retained review history.');
    }

    public function reorder(Request $request, string $artwork): RedirectResponse
    {
        $validated = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['required', 'uuid', 'distinct']]);
        try {
            $this->media->reorder((int) $request->user()->id, $artwork, $validated['order']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['order' => $exception->getMessage()]);
        }

        return back()->with('status', 'Image order saved.');
    }

    public function primary(Request $request, string $artwork, string $media): RedirectResponse
    {
        try {
            $this->media->makePrimary((int) $request->user()->id, $artwork, $media);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['media' => $exception->getMessage()]);
        }

        return back()->with('status', 'Primary artwork image updated.');
    }

    public function delete(Request $request, string $artwork, string $media): RedirectResponse
    {
        try {
            $this->media->delete((int) $request->user()->id, $artwork, $media);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['media' => $exception->getMessage()]);
        }

        return back()->with('status', 'Eligible artwork image removed.');
    }

    public function display(Request $request, string $artwork, string $media): StreamedResponse
    {
        $row = $this->media->ownedMedia((int) $request->user()->id, $artwork, $media, true);
        abort_unless($row->state === 'ready' && $row->display_path, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->display_path), 404);

        return Storage::disk($row->disk)->response($row->display_path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300']);
    }

    public function original(Request $request, string $artwork, string $media): StreamedResponse
    {
        $row = $this->media->ownedMedia((int) $request->user()->id, $artwork, $media, true);
        abort_unless($row->original_path && Storage::disk($row->disk)->exists($row->original_path), 404);

        return Storage::disk($row->disk)->download($row->original_path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    /** @return array<string,mixed> */
    private function navigation(int $coreUserId): array
    {
        return ['context' => $this->dashboard->overview($coreUserId)['context'], 'navCounts' => $this->dashboard->navigationCounts($coreUserId), 'activeTab' => 'artworks'];
    }
}
