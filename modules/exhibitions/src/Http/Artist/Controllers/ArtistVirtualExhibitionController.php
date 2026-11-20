<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03ArtworkDimensions;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03ArtistPlacementService;
use Modules\Exhibition\Application\VirtualExhibitions\VirtualExhibitionService;

final class ArtistVirtualExhibitionController extends Controller
{
    public function __construct(
        private readonly Ve03ArtistPlacementService $ve03,
        private readonly VirtualExhibitionService $legacy,
        private readonly ArtistDashboardAccess $access,
        private readonly ArtistDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->access->context((int) $request->user()->id);
        $requestedSection = in_array($request->query('tab'), ['published', 'available', 'removed'], true)
            ? (string) $request->query('tab')
            : null;
        try {
            $workspace = $this->ve03->workspace((int) $request->user()->id);
        } catch (DomainException $exception) {
            $workspace = [
                'artworks' => collect(),
                'invalidDimensionArtworks' => collect(),
                'placements' => collect(),
                'publishedPlacements' => collect(),
                'adminRemovedPlacements' => collect(),
                'environments' => collect(),
                'environment' => null,
                'unavailable' => $exception->getMessage(),
            ];
        }
        $virtualSection = $requestedSection
            ?? ($workspace['publishedPlacements']->isEmpty()
                && ($workspace['artworks']->isNotEmpty() || $workspace['invalidDimensionArtworks']->isNotEmpty())
                ? 'available'
                : 'published');

        $allPublishedPlacements = $workspace['publishedPlacements'];
        $publishedFilters = [
            'q' => Str::limit(trim((string) $request->query('q', '')), 120, ''),
            'hall' => ctype_digit((string) $request->query('hall', '')) ? (int) $request->query('hall') : null,
            'status' => in_array($request->query('status'), ['published', 'sold'], true) ? (string) $request->query('status') : '',
            'sort' => in_array($request->query('sort'), ['newest', 'oldest', 'artwork', 'hall'], true) ? (string) $request->query('sort') : 'newest',
        ];
        $publishedHallOptions = $allPublishedPlacements
            ->unique('environment_id')
            ->sortBy('environment_title', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (object $placement): array => [
                'id' => (int) $placement->environment_id,
                'title' => (string) $placement->environment_title,
            ])
            ->values();

        if ($publishedFilters['q'] !== '') {
            $needle = Str::lower($publishedFilters['q']);
            $workspace['publishedPlacements'] = $workspace['publishedPlacements']->filter(
                static fn (object $placement): bool => collect([
                    $placement->artwork_title,
                    $placement->environment_title,
                    $placement->room_title,
                    $placement->slot_name,
                ])->contains(static fn (mixed $value): bool => Str::contains(Str::lower((string) $value), $needle)),
            );
        }
        if ($publishedFilters['hall'] !== null) {
            $workspace['publishedPlacements'] = $workspace['publishedPlacements']->where('environment_id', $publishedFilters['hall']);
        }
        if ($publishedFilters['status'] !== '') {
            $workspace['publishedPlacements'] = $workspace['publishedPlacements']->filter(
                static fn (object $placement): bool => $publishedFilters['status'] === 'sold'
                    ? (bool) $placement->is_sold
                    : ! (bool) $placement->is_sold,
            );
        }
        $workspace['publishedPlacements'] = (match ($publishedFilters['sort']) {
            'oldest' => $workspace['publishedPlacements']->sortBy('placed_at'),
            'artwork' => $workspace['publishedPlacements']->sortBy('artwork_title', SORT_NATURAL | SORT_FLAG_CASE),
            'hall' => $workspace['publishedPlacements']->sortBy('environment_title', SORT_NATURAL | SORT_FLAG_CASE),
            default => $workspace['publishedPlacements']->sortByDesc('placed_at'),
        })->values();

        return view('exhibitions::artist.virtual.index', $workspace + [
            'context' => $context,
            'navCounts' => $this->dashboard->navigationCounts((int) $request->user()->id),
            'activeTab' => 'virtual',
            'virtualSection' => $virtualSection,
            'publishedFilters' => $publishedFilters,
            'publishedHallOptions' => $publishedHallOptions,
            'publishedPlacementTotal' => $allPublishedPlacements->count(),
        ]);
    }

    public function place(Request $request, ?string $artwork = null): View|RedirectResponse
    {
        $environmentPublicId = $request->filled('hall') ? (string) $request->query('hall') : null;
        try {
            $workspace = $this->ve03->workspace((int) $request->user()->id, $environmentPublicId);
        } catch (DomainException) {
            abort(404);
        }
        $availableArtworks = $workspace['artworks'];
        if ($availableArtworks->isEmpty()) {
            $message = $workspace['invalidDimensionArtworks']->isNotEmpty()
                ? 'Your published artworks need valid physical dimensions before they can be placed in a 3D exhibition.'
                : 'No artworks are currently ready for placement.';

            return redirect()->route('exhibitions.dashboard.virtual.index', ['tab' => 'available'])
                ->withErrors(['placement' => $message]);
        }
        $selectedArtwork = $artwork
            ? $availableArtworks->firstWhere('public_id', $artwork)
            : $availableArtworks->first();
        if (! $selectedArtwork && $artwork && $workspace['invalidDimensionArtworks']->firstWhere('public_id', $artwork)) {
            return redirect()->route('exhibitions.dashboard.virtual.index', ['tab' => 'available'])
                ->withErrors(['placement' => Ve03ArtworkDimensions::MISSING_MESSAGE]);
        }
        abort_unless($selectedArtwork, 404);

        try {
            $manifest = $this->ve03->manifest((int) $request->user()->id, (string) $selectedArtwork->public_id, $environmentPublicId);
        } catch (DomainException $exception) {
            return redirect()->route('exhibitions.dashboard.virtual.index', ['tab' => 'available'])
                ->withErrors(['placement' => $exception->getMessage()]);
        }

        $transcoderFilename = 'basis_transcoder.js';
        $transcoderUrl = route('exhibitions.virtual.ve03.transcoder', $transcoderFilename);

        return view('exhibitions::artist.virtual.place', [
            'context' => $this->access->context((int) $request->user()->id),
            'manifest' => $manifest,
            'availableArtworks' => $availableArtworks,
            'artworkPublicId' => (string) $selectedArtwork->public_id,
            'viewerConfig' => [
                'manifest_url' => route('exhibitions.dashboard.virtual.manifest', ['artwork' => $selectedArtwork->public_id, 'hall' => $manifest['environment']['id']]),
                'telemetry_url' => null,
                'transcoder_base_url' => substr($transcoderUrl, 0, -strlen($transcoderFilename)),
                'csrf_token' => csrf_token(),
            ],
            'placementEndpoints' => [
                'manifest' => route('exhibitions.dashboard.virtual.manifest', ['artwork' => '__ARTWORK__', 'hall' => $manifest['environment']['id']]),
                'hold' => route('exhibitions.dashboard.virtual.holds.create'),
                'refresh' => route('exhibitions.dashboard.virtual.holds.refresh', '__TOKEN__'),
                'release' => route('exhibitions.dashboard.virtual.holds.release', '__TOKEN__'),
                'confirm' => route('exhibitions.dashboard.virtual.submit'),
                'success' => route('exhibitions.dashboard.virtual.index'),
                'csrf' => csrf_token(),
            ],
            'navCounts' => $this->dashboard->navigationCounts((int) $request->user()->id),
            'activeTab' => 'virtual',
        ]);
    }

    public function manifest(Request $request, string $artwork): JsonResponse
    {
        return $this->json(fn () => $this->ve03->manifest(
            (int) $request->user()->id,
            $artwork,
            $request->filled('hall') ? (string) $request->query('hall') : null,
        ));
    }

    public function hold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'artwork_public_id' => ['required', 'uuid'],
            'slot_code' => ['required', 'string', 'max:80'],
            'moving_placement_public_id' => ['nullable', 'uuid'],
            'environment_public_id' => ['required', 'uuid'],
        ]);

        return $this->json(function () use ($request, $data): array {
            $hold = $this->ve03->createHold(
                (int) $request->user()->id,
                $data['artwork_public_id'],
                $data['slot_code'],
                $data['moving_placement_public_id'] ?? null,
                $data['environment_public_id'],
            );

            return ['token' => (string) $hold->hold_token, 'expires_at' => (string) $hold->expires_at];
        }, 201);
    }

    public function refreshHold(Request $request, string $token): JsonResponse
    {
        return $this->json(function () use ($request, $token): array {
            $hold = $this->ve03->refreshHold((int) $request->user()->id, $token);

            return ['token' => (string) $hold->hold_token, 'expires_at' => (string) $hold->expires_at];
        });
    }

    public function releaseHold(Request $request, string $token): JsonResponse
    {
        $this->ve03->releaseHold((int) $request->user()->id, $token);

        return response()->json(['released' => true]);
    }

    public function submit(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'artwork_public_id' => ['required', 'uuid'],
            'slot_code' => ['required', 'string', 'max:80'],
            'hold_token' => ['required', 'uuid'],
            'frame_style' => ['required', 'in:none,thin,wide'],
            'frame_color' => ['nullable', 'in:black,white,gold,silver,taupe,gray'],
            'environment_public_id' => ['required', 'uuid'],
        ]);
        try {
            $placement = $this->ve03->confirm(
                (int) $request->user()->id,
                $data['artwork_public_id'],
                $data['slot_code'],
                $data['hold_token'],
                $data['frame_style'],
                $data['frame_color'] ?? 'black',
                $data['environment_public_id'],
            );
        } catch (DomainException $exception) {
            return $this->placementError($request, $exception);
        }
        if ($request->expectsJson()) {
            return response()->json(['placement_id' => (string) $placement->public_id, 'state' => 'active', 'published' => true], 201);
        }

        return redirect()->route('exhibitions.dashboard.virtual.index')->with('status', 'Artwork placed and published immediately.');
    }

    public function move(Request $request, string $placement): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['slot_code' => ['required', 'string', 'max:80'], 'hold_token' => ['required', 'uuid']]);
        try {
            $updated = $this->ve03->move((int) $request->user()->id, $placement, $data['slot_code'], $data['hold_token']);
        } catch (DomainException $exception) {
            return $this->placementError($request, $exception);
        }
        if ($request->expectsJson()) {
            return response()->json(['placement_id' => (string) $updated->public_id, 'state' => (string) $updated->state]);
        }

        return back()->with('status', 'Placement moved successfully.');
    }

    public function withdraw(Request $request, string $placement): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        try {
            $updated = $this->ve03->withdraw((int) $request->user()->id, $placement, $data['reason']);
        } catch (DomainException $exception) {
            return $this->placementError($request, $exception);
        }
        if ($request->expectsJson()) {
            return response()->json(['placement_id' => (string) $updated->public_id, 'state' => 'released']);
        }

        return back()->with('status', 'Placement withdrawn and the display released.');
    }

    public function requestRepublish(Request $request, string $placement): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $this->ve03->requestRepublish((int) $request->user()->id, $placement, $data['message']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['republish' => $exception->getMessage()]);
        }

        return redirect()->route('exhibitions.dashboard.virtual.index', ['tab' => 'removed'])
            ->with('status', 'Your republishing request was sent to the administration for review.');
    }

    /** Retained only for historical VE-01 records; Phase 5 never creates review requests. */
    public function resubmit(Request $request, string $placement): RedirectResponse
    {
        $data = $request->validate(['artist_note' => ['nullable', 'string', 'max:2000']]);
        try {
            $this->legacy->resubmitPlacement((int) $request->user()->id, $placement, $data['artist_note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['placement' => $exception->getMessage()]);
        }

        return back()->with('status', 'Historical placement changes resubmitted.');
    }

    private function json(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json($callback(), $status);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function placementError(Request $request, DomainException $exception): JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        throw ValidationException::withMessages(['placement' => $exception->getMessage()]);
    }
}
