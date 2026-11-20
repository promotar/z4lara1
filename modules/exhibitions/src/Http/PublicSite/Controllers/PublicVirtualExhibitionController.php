<?php

namespace Modules\Exhibition\Http\PublicSite\Controllers;

use DomainException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03RuntimeManifestService;
use Modules\Exhibition\Application\VirtualExhibitions\VirtualExhibitionService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicVirtualExhibitionController extends Controller
{
    public function __construct(
        private readonly VirtualExhibitionService $service,
        private readonly ArtworkCommerceGateway $commerce,
        private readonly Ve03RuntimeManifestService $ve03Runtime,
        private readonly ArtworkEffectiveAvailabilityService $availability,
    ) {}

    public function index(): View
    {
        $legacyRooms = DB::table('exhibition_virtual_rooms as room')
            ->where('room.status', 'published')
            ->select([
                'room.id',
                'room.public_id',
                'room.title',
                'room.slug',
                'room.short_description',
                'room.published_at',
            ])
            ->selectSub(
                fn ($query) => $query->from('exhibition_virtual_placement_requests as placement')
                    ->whereColumn('placement.room_id', 'room.id')
                    ->where('placement.status', 'published')
                    ->selectRaw('count(*)'),
                'artworks_count',
            )
            ->selectSub(
                fn ($query) => $query->from('exhibition_virtual_room_slots as slot')
                    ->whereColumn('slot.room_id', 'room.id')
                    ->where('slot.enabled', true)
                    ->selectRaw('count(*)'),
                'displays_count',
            )
            ->orderByDesc('room.published_at')
            ->orderBy('room.title')
            ->get();

        $rooms = $this->ve03Runtime->publishedRooms()->concat($legacyRooms)->values();

        return view('exhibitions::public.virtual-index', compact('rooms'));
    }

    public function calibration(): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $room = (object) [
            'title' => 'Native WebGL 360 Calibration',
            'short_description' => 'Renderer-only orientation and spherical projection check.',
            'initial_yaw' => 0,
            'initial_pitch' => 0,
            'initial_fov' => 75,
            'minimum_fov' => 35,
            'maximum_fov' => 110,
            'auto_rotate_enabled' => false,
            'auto_rotate_speed' => 0,
            'auto_rotate_delay' => 4000,
        ];
        $placements = [
            ['id' => 'cal-north', 'title' => 'NORTH / HORIZON', 'artist' => 'Calibration', 'name' => 'NORTH / HORIZON', 'yaw' => 0, 'pitch' => 0, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
            ['id' => 'cal-east', 'title' => 'EAST', 'artist' => 'Calibration', 'name' => 'EAST', 'yaw' => 90, 'pitch' => 0, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
            ['id' => 'cal-west', 'title' => 'WEST', 'artist' => 'Calibration', 'name' => 'WEST', 'yaw' => -90, 'pitch' => 0, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
            ['id' => 'cal-south', 'title' => 'SOUTH', 'artist' => 'Calibration', 'name' => 'SOUTH', 'yaw' => 180, 'pitch' => 0, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
            ['id' => 'cal-ceiling', 'title' => 'TOP / CEILING', 'artist' => 'Calibration', 'name' => 'TOP / CEILING', 'yaw' => 0, 'pitch' => 45, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
            ['id' => 'cal-floor', 'title' => 'BOTTOM / FLOOR', 'artist' => 'Calibration', 'name' => 'BOTTOM / FLOOR', 'yaw' => 0, 'pitch' => -45, 'roll' => 0, 'width' => 18, 'maximum_height' => 12, 'enabled' => true],
        ];

        return view('exhibitions::virtual.calibration', compact('room', 'placements'));
    }

    public function calibrationPanorama(): BinaryFileResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $path = dirname(__DIR__, 4).'/tests/Fixtures/calibration-panorama.png';
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(string $room): View
    {
        $record = $this->ve03Runtime->publishedRoom($room);
        if ($record) {
            $ve03 = $this->ve03Runtime->activeForRoom($record);
            abort_unless($ve03, 404);

            return $this->ve03View($record, $ve03);
        }

        try {
            $record = $this->service->liveRoom($room);
        } catch (DomainException) {
            abort(404);
        }
        $placements = DB::table('exhibition_virtual_placement_requests as placement')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
            ->where('placement.room_id', $record->id)->where('placement.status', 'published')->where('artwork.state', 'published')
            ->select('placement.*', 'artwork.id as artwork_id', 'artwork.public_id as artwork_public_id', 'artwork.slug as artwork_slug', 'artwork.title as current_title', 'publication.store_variant_public_id')->orderBy('placement.id')->get();

        foreach ($placements as $placement) {
            $snapshot = json_decode((string) $placement->artwork_snapshot, true) ?: [];
            $commerce = $this->commerce->status($placement->artwork_public_id);
            $effectiveAvailability = $this->availability->forArtwork((int) $placement->artwork_id, $commerce->availability);
            $sold = in_array($effectiveAvailability, ['reserved', 'sold'], true);
            $placement->viewer = $snapshot + [
                'artwork_public_id' => $placement->artwork_public_id,
                'details_url' => route('exhibitions.artworks.show', $placement->artwork_slug ?: $placement->artwork_public_id),
                'media_url' => isset($snapshot['media_public_id']) ? route('exhibitions.artworks.media', [$placement->artwork_public_id, $snapshot['media_public_id']]) : null,
                'store_available' => $commerce->storeAvailable, 'purchasable' => $commerce->purchasable && ! $sold,
                'price_minor' => $commerce->priceMinor, 'currency' => $commerce->currency,
                'availability' => $sold ? 'Sold' : ($commerce->purchasable ? 'Available' : 'Unavailable'),
                'store_message' => $commerce->message ?: ($commerce->storeAvailable ? null : 'Purchasing is temporarily unavailable.'),
                'cart_url' => Route::has('store.cart.items.store') ? route('store.cart.items.store') : null,
                'checkout_url' => Route::has('store.checkout.index') ? route('store.checkout.index') : null,
                'store_variant_public_id' => $placement->store_variant_public_id,
            ];
        }

        $ve03 = $this->ve03Runtime->activeForRoom($record);
        if ($ve03) {
            return $this->ve03View($record, $ve03);
        }

        return view('exhibitions::public.virtual-room', ['room' => $record, 'placements' => $placements]);
    }

    /** @param array{environment: object, version: object, manifest: array<string, mixed>, etag: string} $ve03 */
    private function ve03View(object $record, array $ve03): View
    {
        $accessiblePlacements = collect($ve03['manifest']['placements'] ?? [])
            ->map(function (array $placement): object {
                $artwork = (array) ($placement['artwork'] ?? []);

                return (object) [
                    'public_id' => (string) ($placement['id'] ?? ''),
                    'viewer' => [
                        'artwork_public_id' => (string) ($artwork['id'] ?? ''),
                        'title' => (string) ($artwork['title'] ?? 'Untitled artwork'),
                        'artist' => (string) ($artwork['artist'] ?? 'Artist'),
                        'availability' => (string) ($artwork['availability'] ?? 'Unavailable'),
                        'media_url' => $artwork['near_texture_url'] ?? $artwork['room_texture_url'] ?? null,
                        'details_url' => route('exhibitions.artworks.show', (string) ($artwork['id'] ?? '')),
                    ],
                ];
            })
            ->values();
        // Checkout return positions are one-shot. Reusing a stale camera position on
        // every reload can put a visitor back inside geometry after an asset update.
        $returnContext = session()->pull('store.checkout.return_context');
        $returnContext = is_array($returnContext)
            && ($returnContext['owner_code'] ?? null) === 'exhibitions'
            && ($returnContext['type'] ?? null) === 'virtual_exhibition'
            && ($returnContext['reference'] ?? null) === $record->public_id
            && data_get($returnContext, 'state.environment_version_id') === (string) $ve03['version']->public_id
            ? (array) ($returnContext['state'] ?? [])
            : null;

        return view('exhibitions::public.ve03-room', [
            'room' => $record,
            'placements' => $accessiblePlacements,
            've03' => $ve03,
            'returnContext' => $returnContext,
        ]);
    }

    public function panorama(string $room): StreamedResponse
    {
        try {
            $record = $this->service->liveRoom($room);
        } catch (DomainException) {
            abort(404);
        }
        abort_unless($record->panorama_path && Storage::disk($record->panorama_disk)->exists($record->panorama_path), 404);

        return Storage::disk($record->panorama_disk)->response($record->panorama_path, null, ['Content-Type' => $record->panorama_mime, 'Cache-Control' => 'public, max-age=3600', 'ETag' => '"'.$record->panorama_checksum.'"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
