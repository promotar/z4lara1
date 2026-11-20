<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03ArtistPlacementService;
use Modules\Exhibition\Application\VirtualExhibitions\VirtualExhibitionService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Modules\Exhibition\Domain\VirtualExhibitions\RoomState;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VirtualExhibitionAdminController extends Controller
{
    public function __construct(
        private readonly VirtualExhibitionService $service,
        private readonly Ve03ArtistPlacementService $ve03,
        private readonly ArtworkCommerceGateway $commerce,
    ) {}

    public function index(): View
    {
        $rooms = DB::table('exhibition_virtual_rooms as room')
            ->select('room.*')
            ->selectSub(fn ($query) => $query->from('exhibition_virtual_room_slots as slot')->whereColumn('slot.room_id', 'room.id')->selectRaw('count(*)'), 'slot_count')
            ->selectSub(fn ($query) => $query->from('exhibition_virtual_room_slots as slot')->whereColumn('slot.room_id', 'room.id')->where('slot.enabled', true)->whereNull('slot.active_request_id')->selectRaw('count(*)'), 'available_slots')
            ->selectSub(fn ($query) => $query->from('exhibition_virtual_room_slots as slot')->whereColumn('slot.room_id', 'room.id')->whereNotNull('slot.active_request_id')->selectRaw('count(*)'), 'reserved_slots')
            ->selectSub(fn ($query) => $query->from('exhibition_virtual_placement_requests as placement')->whereColumn('placement.room_id', 'room.id')->where('placement.status', 'published')->selectRaw('count(*)'), 'published_artworks')
            ->orderByDesc('updated_at')->paginate(30);

        return view('exhibitions::admin.virtual.rooms-index', compact('rooms'));
    }

    public function create(): View
    {
        return view('exhibitions::admin.virtual.room-form', ['room' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $room = $this->service->createRoom((int) $request->user()->id, $this->roomData($request));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['room' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.exhibitions.virtual.rooms.edit', $room->public_id)->with('status', 'Virtual exhibition room created. Upload its panorama and define display slots.');
    }

    public function edit(string $room): View
    {
        $record = $this->room($room);

        return view('exhibitions::admin.virtual.room-form', ['room' => $record]);
    }

    public function update(Request $request, string $room): RedirectResponse
    {
        $data = $this->roomData($request);
        $version = (int) $request->validate(['lock_version' => ['required', 'integer', 'min:1']])['lock_version'];
        try {
            $this->service->updateRoom((int) $request->user()->id, $room, $data, $version);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['room' => $exception->getMessage()]);
        }

        return back()->with('status', 'Room settings saved.');
    }

    public function uploadPanorama(Request $request, string $room): RedirectResponse
    {
        $request->validate(['panorama' => ['required', 'file', 'max:'.config('exhibitions.virtual_exhibitions.panorama.maximum_kilobytes', 25600)]]);
        try {
            $this->service->uploadPanorama((int) $request->user()->id, $room, $request->file('panorama'));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['panorama' => $exception->getMessage()]);
        }

        return back()->with('status', 'The 360 panorama is ready.');
    }

    public function layout(string $room): View
    {
        $record = $this->room($room);
        $slots = DB::table('exhibition_virtual_room_slots')->where('room_id', $record->id)->orderBy('display_order')->orderBy('id')->get();

        return view('exhibitions::admin.virtual.layout', ['room' => $record, 'slots' => $slots]);
    }

    public function createSlot(Request $request, string $room): RedirectResponse
    {
        try {
            $this->service->createSlot((int) $request->user()->id, $room, $this->slotData($request));
        } catch (DomainException|QueryException $exception) {
            throw ValidationException::withMessages(['slot' => $exception instanceof DomainException ? $exception->getMessage() : 'The slot code must be unique inside this room.']);
        }

        return back()->with('status', 'Display slot created.');
    }

    public function updateSlot(Request $request, string $slot): RedirectResponse
    {
        try {
            $this->service->updateSlot((int) $request->user()->id, $slot, $this->slotData($request));
        } catch (DomainException|QueryException $exception) {
            throw ValidationException::withMessages(['slot' => $exception instanceof DomainException ? $exception->getMessage() : 'The slot code must be unique inside this room.']);
        }

        return back()->with('status', 'Display slot saved.');
    }

    public function deleteSlot(Request $request, string $slot): RedirectResponse
    {
        try {
            $this->service->deleteSlot((int) $request->user()->id, $slot);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['slot' => $exception->getMessage()]);
        }

        return back()->with('status', 'Display slot removed or disabled with its history retained.');
    }

    public function panorama(string $room): StreamedResponse
    {
        $record = $this->room($room);
        abort_unless($record->panorama_path && Storage::disk($record->panorama_disk)->exists($record->panorama_path), 404);

        return Storage::disk($record->panorama_disk)->response($record->panorama_path, null, ['Content-Type' => $record->panorama_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function state(Request $request, string $room): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(RoomState::class)], 'reason' => ['required', 'string', 'min:8', 'max:2000']]);
        try {
            $this->service->changeRoomState((int) $request->user()->id, $room, RoomState::from($data['status']), $data['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Room state updated.');
    }

    public function requests(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $query = DB::table('exhibition_virtual_placement_requests as placement')
            ->join('exhibition_virtual_rooms as room', 'room.id', '=', 'placement.room_id')
            ->join('exhibition_virtual_room_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'placement.artist_identity_id')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->select('placement.*', 'room.title as room_title', 'room.public_id as room_public_id', 'slot.name as slot_name', 'slot.code as slot_code', 'artwork.title as artwork_title', 'application.artistic_name', 'application.legal_name')
            ->when($status !== '', fn ($query) => $query->where('placement.status', $status))
            ->orderByDesc('placement.submitted_at')->orderByDesc('placement.id');

        $republishRequests = DB::table('exhibition_virtual_republish_requests as request')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'request.placement_id')
            ->join('exhibition_virtual_environment_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_virtual_environment_rooms as room', 'room.id', '=', 'slot.room_id')
            ->join('exhibition_virtual_environments as environment', 'environment.id', '=', 'placement.environment_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'request.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->orderByRaw("CASE WHEN request.state = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('request.requested_at')
            ->get([
                'request.*',
                'placement.public_id as placement_public_id',
                'artwork.title as artwork_title',
                'environment.title as environment_title',
                'room.title as room_title',
                'slot.name as slot_name',
                'application.artistic_name',
                'application.legal_name',
            ]);

        return view('exhibitions::admin.virtual.requests-index', [
            'requests' => $query->paginate(30)->withQueryString(),
            'statusFilter' => $status,
            'republishRequests' => $republishRequests,
        ]);
    }

    public function publishedArtworks(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $directPlacements = DB::table('exhibition_virtual_placements as placement')
            ->join('exhibition_virtual_environment_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_virtual_environment_rooms as room', 'room.id', '=', 'slot.room_id')
            ->join('exhibition_virtual_environments as environment', 'environment.id', '=', 'placement.environment_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'placement.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->whereIn('placement.state', ['active', 'sold'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('artwork.title', 'like', "%{$search}%")
                        ->orWhere('application.artistic_name', 'like', "%{$search}%")
                        ->orWhere('application.legal_name', 'like', "%{$search}%")
                        ->orWhere('environment.title', 'like', "%{$search}%")
                        ->orWhere('room.title', 'like', "%{$search}%")
                        ->orWhere('slot.name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('placement.placed_at')
            ->paginate(30, [
                'placement.*',
                'artwork.public_id as artwork_public_id',
                'artwork.title as artwork_title',
                'environment.title as environment_title',
                'room.title as room_title',
                'slot.name as slot_name',
                'slot.slot_code',
                'application.artistic_name',
                'application.legal_name',
            ], 'current_page')
            ->withQueryString();

        $directPlacements->getCollection()->each(function (object $placement): void {
            $commerce = $this->commerce->status((string) $placement->artwork_public_id);
            $placement->is_sold = $placement->state === 'sold'
                || $placement->sold_at !== null
                || $commerce->availability === 'sold'
                || ($commerce->storeAvailable && $commerce->availableQuantity < 1);
        });

        $legacyPlacements = DB::table('exhibition_virtual_placement_requests as placement')
            ->join('exhibition_virtual_rooms as room', 'room.id', '=', 'placement.room_id')
            ->join('exhibition_virtual_room_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'placement.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('placement.status', 'published')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('artwork.title', 'like', "%{$search}%")
                        ->orWhere('application.artistic_name', 'like', "%{$search}%")
                        ->orWhere('application.legal_name', 'like', "%{$search}%")
                        ->orWhere('room.title', 'like', "%{$search}%")
                        ->orWhere('slot.name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('placement.published_at')
            ->paginate(30, [
                'placement.*',
                'artwork.title as artwork_title',
                'room.title as room_title',
                'slot.name as slot_name',
                'slot.code as slot_code',
                'application.artistic_name',
                'application.legal_name',
            ], 'legacy_page')
            ->withQueryString();

        return view('exhibitions::admin.virtual.published-artworks', compact('directPlacements', 'legacyPlacements', 'search'));
    }

    public function removeDirectPlacement(Request $request, string $placement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        try {
            $this->ve03->removeByAdministrator((int) $request->user()->id, $placement, $data['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['placement' => $exception->getMessage()]);
        }

        return back()->with('status', 'The artwork was removed from the virtual exhibition and the artist can see the recorded reason.');
    }

    public function reviewRepublishRequest(Request $request, string $republishRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        try {
            $this->ve03->reviewRepublishRequest(
                (int) $request->user()->id,
                $republishRequest,
                $data['decision'],
                $data['reason'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['republish_request' => $exception->getMessage()]);
        }

        return back()->with('status', 'The republishing request decision was recorded and is now visible to the artist.');
    }

    public function review(string $placement): View
    {
        $record = DB::table('exhibition_virtual_placement_requests as placement')
            ->join('exhibition_virtual_rooms as room', 'room.id', '=', 'placement.room_id')
            ->join('exhibition_virtual_room_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'placement.artist_identity_id')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('placement.public_id', $placement)
            ->select('placement.*', 'room.title as room_title', 'room.public_id as room_public_id', 'room.panorama_path', 'room.status as room_status', 'slot.name as slot_name', 'artwork.title as artwork_title', 'artwork.public_id as artwork_public_id', 'application.artistic_name', 'application.legal_name')->first() ?? abort(404);
        $slots = DB::table('exhibition_virtual_room_slots')->where('room_id', $record->room_id)->where('enabled', true)->where(fn ($query) => $query->whereNull('active_request_id')->orWhere('active_request_id', $record->id))->orderBy('display_order')->get();
        $history = DB::table('exhibition_virtual_placement_transitions')->where('placement_request_id', $record->id)->orderBy('occurred_at')->get();

        return view('exhibitions::admin.virtual.request-review', compact('record', 'slots', 'history'));
    }

    public function decide(Request $request, string $placement): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['changes_requested', 'approved', 'rejected', 'removed'])],
            'reason' => ['required', 'string', 'min:8', 'max:2000'], 'slot_public_id' => ['nullable', 'uuid'],
            'placement_yaw' => ['nullable', 'numeric', 'between:-180,180'], 'placement_pitch' => ['nullable', 'numeric', 'between:-89,89'],
            'placement_roll' => ['nullable', 'numeric', 'between:-180,180'], 'placement_width' => ['nullable', 'numeric', 'between:2,120'],
            'maximum_height' => ['nullable', 'numeric', 'between:2,120'],
        ]);
        $adjustments = array_filter(array_intersect_key($data, array_flip(['placement_yaw', 'placement_pitch', 'placement_roll', 'placement_width', 'maximum_height'])), static fn ($value) => $value !== null);
        try {
            $this->service->reviewPlacement((int) $request->user()->id, $placement, $data['decision'], $data['reason'], $adjustments, $data['slot_public_id'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', 'Placement review saved and audited.');
    }

    private function room(string $publicId): object
    {
        return DB::table('exhibition_virtual_rooms')->where('public_id', $publicId)->first() ?? abort(404);
    }

    /** @return array<string,mixed> */
    private function roomData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'short_description' => ['nullable', 'string', 'max:500'], 'description' => ['nullable', 'string', 'max:10000'],
            'initial_yaw' => ['required', 'numeric', 'between:-180,180'], 'initial_pitch' => ['required', 'numeric', 'between:-89,89'],
            'initial_fov' => ['required', 'numeric', 'between:30,120'], 'minimum_fov' => ['required', 'numeric', 'between:20,100'], 'maximum_fov' => ['required', 'numeric', 'between:40,140', 'gte:minimum_fov'],
            'auto_rotate_speed' => ['required', 'numeric', 'between:-20,20'], 'auto_rotate_delay' => ['required', 'integer', 'between:0,60000'],
        ]);
        $data['auto_rotate_enabled'] = $request->boolean('auto_rotate_enabled');

        return $data;
    }

    /** @return array<string,mixed> */
    private function slotData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:64'], 'name' => ['required', 'string', 'max:140'],
            'anchor_yaw' => ['required', 'numeric', 'between:-180,180'], 'anchor_pitch' => ['required', 'numeric', 'between:-89,89'],
            'roll' => ['required', 'numeric', 'between:-180,180'], 'angular_width' => ['required', 'numeric', 'between:2,120'],
            'maximum_angular_height' => ['nullable', 'numeric', 'between:2,120'], 'display_order' => ['required', 'integer', 'between:0,10000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['enabled'] = $request->boolean('enabled');

        return $data;
    }
}
