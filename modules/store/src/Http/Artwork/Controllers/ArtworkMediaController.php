<?php

namespace Modules\Store\Http\Artwork\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Store\Application\Artwork\ArtworkMediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkMediaController extends Controller
{
    public function sellerIndex(Request $request, string $artwork, ArtworkMediaService $media): View
    {
        $this->assertSeller($request);

        return $this->view($media, (int) $request->user()->id, $artwork, false);
    }

    public function adminIndex(Request $request, string $artwork, ArtworkMediaService $media): View
    {
        $this->assertAdmin($request);

        return $this->view($media, (int) $request->user()->id, $artwork, true);
    }

    public function sellerUpload(Request $request, string $artwork, ArtworkMediaService $media): RedirectResponse|JsonResponse
    {
        $this->assertSeller($request);

        return $this->upload($request, $artwork, $media, false);
    }

    public function adminUpload(Request $request, string $artwork, ArtworkMediaService $media): RedirectResponse|JsonResponse
    {
        $this->assertAdmin($request);

        return $this->upload($request, $artwork, $media, true);
    }

    public function sellerReorder(Request $request, string $artwork, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertSeller($request);

        return $this->reorder($request, $artwork, $media, false);
    }

    public function adminReorder(Request $request, string $artwork, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertAdmin($request);

        return $this->reorder($request, $artwork, $media, true);
    }

    public function sellerPrimary(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertSeller($request);
        $media->makePrimary((int) $request->user()->id, $artwork, $image);

        return back()->with('status', 'Primary front image updated.');
    }

    public function adminPrimary(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertAdmin($request);
        $media->makePrimary((int) $request->user()->id, $artwork, $image, true);

        return back()->with('success', 'Primary front image updated.');
    }

    public function sellerReplace(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertSeller($request);

        return $this->replace($request, $artwork, $image, $media, false);
    }

    public function adminReplace(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertAdmin($request);

        return $this->replace($request, $artwork, $image, $media, true);
    }

    public function sellerDelete(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertSeller($request);
        $media->delete((int) $request->user()->id, $artwork, $image);

        return back()->with('status', 'Artwork image removed.');
    }

    public function adminDelete(Request $request, string $artwork, string $image, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertAdmin($request);
        $media->delete((int) $request->user()->id, $artwork, $image, true);

        return back()->with('success', 'Artwork image removed.');
    }

    public function sellerOriginal(Request $request, string $artwork, string $image, ArtworkMediaService $media): StreamedResponse
    {
        $this->assertSeller($request);

        return $this->download($media->originalFor((int) $request->user()->id, $artwork, $image), true);
    }

    public function adminOriginal(Request $request, string $artwork, string $image, ArtworkMediaService $media): StreamedResponse
    {
        $this->assertAdmin($request);

        return $this->download($media->originalFor((int) $request->user()->id, $artwork, $image, true), true);
    }

    public function sellerDisplay(Request $request, string $artwork, string $image, ArtworkMediaService $media): StreamedResponse
    {
        $this->assertSeller($request);

        return $this->download($media->originalFor((int) $request->user()->id, $artwork, $image), false);
    }

    public function adminDisplay(Request $request, string $artwork, string $image, ArtworkMediaService $media): StreamedResponse
    {
        $this->assertAdmin($request);

        return $this->download($media->originalFor((int) $request->user()->id, $artwork, $image, true), false);
    }

    private function view(ArtworkMediaService $media, int $actorId, string $artwork, bool $admin): View
    {
        return view('store::artworks.media', $media->workspace($actorId, $artwork, $admin) + [
            'roles' => $media->roles(),
            'admin' => $admin,
        ]);
    }

    private function upload(Request $request, string $artwork, ArtworkMediaService $media, bool $admin): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'between:1,8'],
            'items.*.role' => ['required', Rule::in($media->roles())],
            'items.*.file' => ['required', 'file', 'max:20480'],
        ]);
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = ['role' => $item['role'], 'file' => $item['file']];
        }
        try {
            $media->uploadItems((int) $request->user()->id, (string) $request->user()->name, $artwork, $items, $admin);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
            throw ValidationException::withMessages(['images' => $exception->getMessage()]);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Artwork images uploaded safely.']);
        }

        return back()->with($admin ? 'success' : 'status', 'Artwork images uploaded safely.');
    }

    private function reorder(Request $request, string $artwork, ArtworkMediaService $media, bool $admin): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['required', 'string', 'size:26', 'distinct']]);
        $media->reorder((int) $request->user()->id, $artwork, $data['order'], $admin);

        return back()->with($admin ? 'success' : 'status', 'Artwork image order saved.');
    }

    private function replace(Request $request, string $artwork, string $image, ArtworkMediaService $media, bool $admin): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $media->replace((int) $request->user()->id, (string) $request->user()->name, $artwork, $image, $data['file'], $admin);

        return back()->with($admin ? 'success' : 'status', 'Artwork image replaced safely.');
    }

    private function download(object $media, bool $original): StreamedResponse
    {
        $disk = $original ? $media->original_disk : $media->display_disk;
        $path = $original ? $media->original_path : $media->display_path;
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return $original
            ? Storage::disk($disk)->download($path, $media->original_name, ['Cache-Control' => 'private, no-store'])
            : Storage::disk($disk)->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    private function assertSeller(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ((method_exists($user, 'hasRole') && $user->hasRole('artist')) || $user->can('store.artworks.sell')), 403);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless($request->user()?->can('store.products.create') || $request->user()?->can('store.products.update'), 403);
    }
}
