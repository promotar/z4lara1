<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;

final readonly class Ve03ArtistPlacementService
{
    private const HOLD_MINUTES = 5;

    private const FRAME_STYLES = ['none', 'thin', 'wide'];

    private const FRAME_COLORS = ['black', 'white', 'gold', 'silver', 'taupe', 'gray'];

    public function __construct(
        private ArtistDashboardAccess $access,
        private VirtualExhibitionService $legacy,
        private ArtworkCommerceGateway $commerce,
        private AuditRecorder $audit,
        private Ve03ArtworkProjectionService $projection,
        private ArtworkEffectiveAvailabilityService $availability,
        private Ve03PlacementGeometry $geometry,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(int $coreUserId, ?string $environmentPublicId = null): array
    {
        $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
        $publishedArtworks = DB::table('exhibition_artworks')
            ->where('artist_identity_id', $identityId)
            ->where('state', 'published')
            ->orderBy('title')
            ->get();
        $invalidDimensionArtworks = $publishedArtworks
            ->reject(fn (object $artwork): bool => Ve03ArtworkDimensions::isValid($artwork))
            ->each(function (object $artwork): void {
                $artwork->placement_dimensions_message = Ve03ArtworkDimensions::MISSING_MESSAGE;
            })
            ->values();
        $artworks = $publishedArtworks
            ->filter(fn (object $artwork): bool => Ve03ArtworkDimensions::isValid($artwork)
                && $this->legacy->eligibleArtwork($identityId, (string) $artwork->public_id) !== null
                && $this->availableForSale((string) $artwork->public_id))
            ->values();
        $placements = DB::table('exhibition_virtual_placements as placement')
            ->join('exhibition_virtual_environment_slots as slot', 'slot.id', '=', 'placement.slot_id')
            ->join('exhibition_virtual_environment_rooms as room', 'room.id', '=', 'slot.room_id')
            ->join('exhibition_virtual_environments as environment', 'environment.id', '=', 'placement.environment_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->where('placement.artist_identity_id', $identityId)
            ->orderByDesc('placement.updated_at')
            ->get([
                'placement.*',
                'artwork.public_id as artwork_public_id',
                'artwork.title as artwork_title',
                'artwork.width as artwork_width',
                'artwork.height as artwork_height',
                'artwork.dimension_unit as artwork_dimension_unit',
                'environment.title as environment_title',
                'room.title as room_title',
                'slot.name as slot_name',
                'slot.slot_code',
            ]);

        $releaseEvents = $placements->isEmpty()
            ? collect()
            : DB::table('exhibition_virtual_placement_events')
                ->whereIn('placement_id', $placements->pluck('id'))
                ->where('to_state', 'released')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get()
                ->unique('placement_id')
                ->keyBy('placement_id');

        $republishRequests = $placements->isEmpty()
            ? collect()
            : DB::table('exhibition_virtual_republish_requests')
                ->whereIn('placement_id', $placements->pluck('id'))
                ->orderByDesc('requested_at')
                ->orderByDesc('id')
                ->get()
                ->unique('placement_id')
                ->keyBy('placement_id');
        $latestPlacementsByEnvironmentArtwork = $placements
            ->unique(fn (object $placement): string => $placement->environment_id.'-'.$placement->artwork_id)
            ->keyBy(fn (object $placement): string => $placement->environment_id.'-'.$placement->artwork_id);

        $placements->each(function (object $placement) use ($releaseEvents, $republishRequests, $latestPlacementsByEnvironmentArtwork, $coreUserId): void {
            $event = $releaseEvents->get($placement->id);
            $request = $republishRequests->get($placement->id);
            $context = $event ? (json_decode((string) $event->context, true) ?: []) : [];
            $placement->release_origin = $context['release_origin']
                ?? ($event && (int) $event->actor_user_id === $coreUserId ? 'artist' : 'administration');
            $placement->release_reason = $event?->reason;
            $placement->release_occurred_at = $event?->occurred_at ?? $placement->released_at;
            $placementKey = $placement->environment_id.'-'.$placement->artwork_id;
            $placement->is_current_admin_removal = (int) ($latestPlacementsByEnvironmentArtwork->get($placementKey)?->id ?? 0) === (int) $placement->id;
            $placement->republish_request_public_id = $request?->public_id;
            $placement->republish_request_state = $request?->state;
            $placement->republish_artist_message = $request?->artist_message;
            $placement->republish_decision_reason = $request?->decision_reason;
            $placement->republish_requested_at = $request?->requested_at;
            $placement->republish_decided_at = $request?->decided_at;
            $commerce = $this->commerce->status((string) $placement->artwork_public_id);
            $inventoryAvailability = $this->availability->forArtwork((int) $placement->artwork_id, $commerce->availability);
            $placement->is_sold = $placement->state === 'sold'
                || $placement->sold_at !== null
                || $inventoryAvailability === 'sold';
            $placement->is_reserved = ! $placement->is_sold && $inventoryAvailability === 'reserved';
            $dimensionRecord = (object) [
                'width' => $placement->artwork_width,
                'height' => $placement->artwork_height,
                'dimension_unit' => $placement->artwork_dimension_unit,
            ];
            $placement->placement_dimensions_valid = Ve03ArtworkDimensions::isValid($dimensionRecord);
            $placement->placement_dimensions_message = $placement->placement_dimensions_valid
                ? null
                : Ve03ArtworkDimensions::MISSING_MESSAGE;
        });

        $publishedPlacements = $placements
            ->filter(fn (object $placement): bool => in_array($placement->state, ['active', 'sold'], true))
            ->values();
        $adminRemovedPlacements = $placements
            ->filter(fn (object $placement): bool => $placement->state === 'released' && $placement->release_origin === 'administration')
            ->values();
        $currentAdministrationBlocks = $latestPlacementsByEnvironmentArtwork
            ->filter(fn (object $placement): bool => $placement->state === 'released'
                && $placement->release_origin === 'administration'
                && $placement->republish_request_state !== 'approved');
        $environments = DB::table('exhibition_virtual_environments')
            ->where('status', 'published')
            ->where('artist_placement_open', true)
            ->whereNotNull('active_version_id')
            ->orderBy('title')
            ->orderBy('id')
            ->get();
        $environmentCapacities = $environments->isEmpty()
            ? collect()
            : DB::table('exhibition_virtual_environment_slots')
                ->whereIn('version_id', $environments->pluck('active_version_id'))
                ->where('enabled_by_default', true)
                ->selectRaw('version_id, COUNT(*) AS artwork_capacity')
                ->groupBy('version_id')
                ->pluck('artwork_capacity', 'version_id');
        $environment = $environmentPublicId
            ? $environments->firstWhere('public_id', $environmentPublicId)
            : $environments->first();
        if ($environmentPublicId && ! $environment) {
            throw new DomainException('The selected virtual exhibition is unavailable for artist placement.');
        }
        $isBlockedInEnvironment = static fn (int $artworkId, int $environmentId): bool => $publishedPlacements
            ->contains(fn (object $placement): bool => (int) $placement->artwork_id === $artworkId
                && (int) $placement->environment_id === $environmentId)
            || $currentAdministrationBlocks
                ->contains(fn (object $placement): bool => (int) $placement->artwork_id === $artworkId
                    && (int) $placement->environment_id === $environmentId);
        $environments->each(function (object $placementEnvironment) use ($artworks, $environmentCapacities, $isBlockedInEnvironment): void {
            $placementEnvironment->artwork_capacity = (int) ($environmentCapacities->get($placementEnvironment->active_version_id) ?? 0);
            $placementEnvironment->available_artwork_count = $artworks
                ->reject(fn (object $artwork): bool => $isBlockedInEnvironment((int) $artwork->id, (int) $placementEnvironment->id))
                ->count();
        });
        $blockedArtworkIds = $environmentPublicId && $environment
            ? $artworks
                ->filter(fn (object $artwork): bool => $isBlockedInEnvironment((int) $artwork->id, (int) $environment->id))
                ->pluck('id')
            : $artworks
                ->filter(fn (object $artwork): bool => $environments->isNotEmpty()
                    && $environments->every(fn (object $placementEnvironment): bool => $isBlockedInEnvironment(
                        (int) $artwork->id,
                        (int) $placementEnvironment->id,
                    )))
                ->pluck('id');
        $artworks = $artworks
            ->reject(fn (object $artwork): bool => $blockedArtworkIds->contains((int) $artwork->id))
            ->values();
        $primaryMedia = $artworks->isEmpty()
            ? collect()
            : DB::table('exhibition_artwork_media')
                ->whereIn('artwork_id', $artworks->pluck('id'))
                ->where('state', 'ready')
                ->where('is_primary', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->unique('artwork_id')
                ->keyBy('artwork_id');
        $artworks->each(function (object $artwork) use ($primaryMedia): void {
            $media = $primaryMedia->get($artwork->id);
            $artwork->placement_preview_url = $media
                ? route('exhibitions.artworks.media', [$artwork->public_id, $media->public_id])
                : null;
        });

        return [
            'artworks' => $artworks,
            'invalidDimensionArtworks' => $invalidDimensionArtworks,
            'placements' => $placements,
            'publishedPlacements' => $publishedPlacements,
            'adminRemovedPlacements' => $adminRemovedPlacements,
            'environments' => $environments,
            'environment' => $environment,
            'unavailable' => $environment ? null : __('exhibitions::ui.no_virtual_exhibition_open'),
        ];
    }

    /** @return array<string, mixed> */
    public function manifest(int $coreUserId, string $artworkPublicId, ?string $environmentPublicId = null): array
    {
        $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
        $artwork = $this->legacy->eligibleArtwork($identityId, $artworkPublicId, true);
        if (! $this->availableForSale($artworkPublicId)) {
            throw new DomainException('The artwork is no longer available for placement.');
        }
        $environment = $this->activeEnvironment($environmentPublicId);
        $version = $this->activeVersion($environment);
        $this->discardExpiredHolds((int) $environment->id);
        $dimensions = Ve03ArtworkDimensions::fromArtwork($artwork);
        $width = $dimensions->widthMeters;
        $height = $dimensions->heightMeters;
        $occupiedRows = DB::table('exhibition_virtual_slot_occupancies as occupancy')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'occupancy.placement_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'occupancy.artwork_id')
            ->where('occupancy.environment_id', $environment->id)
            ->get(['occupancy.slot_id', 'occupancy.artist_identity_id', 'occupancy.placement_id', 'placement.state', 'artwork.width', 'artwork.height', 'artwork.dimension_unit']);
        $occupied = $occupiedRows->keyBy('slot_id');
        $holdRows = DB::table('exhibition_virtual_slot_holds as hold')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'hold.artwork_id')
            ->where('hold.environment_id', $environment->id)
            ->where('hold.expires_at', '>', now())
            ->get(['hold.*', 'artwork.width', 'artwork.height', 'artwork.dimension_unit']);
        $holds = $holdRows->keyBy('slot_id');
        $rooms = DB::table('exhibition_virtual_environment_rooms')->where('version_id', $version->id)->orderBy('sort_order')->get();
        $slotRows = DB::table('exhibition_virtual_environment_slots')->where('version_id', $version->id)->orderBy('id')->get();
        $slots = $slotRows->groupBy('room_id');
        $metadata = json_decode((string) $version->metadata_report, true) ?: [];
        $spawn = $metadata['spawns'][0] ?? ['translation' => [0, 0, 0], 'yaw_degrees' => 0, 'pitch_degrees' => 0, 'room_code' => ''];
        $publicRoom = $this->publicRoomForEnvironment($environment);
        $placements = $publicRoom ? $this->projection->publicPlacements($publicRoom, $version) : [];
        $projectedStates = collect($placements)->mapWithKeys(fn (array $placement): array => [
            (string) $placement['slot_code'] => ($placement['artwork']['sold'] ?? false) ? 'sold' : 'occupied',
        ]);
        $blockers = $this->blockers($slotRows, $occupiedRows, $holdRows, $placements, $identityId);
        $runtimeHash = strtolower((string) $version->runtime_sha256);
        $primaryMedia = DB::table('exhibition_artwork_media')
            ->where('artwork_id', $artwork->id)
            ->where('state', 'ready')
            ->where('is_primary', true)
            ->first();

        return [
            'schema_version' => 1,
            'placement_mode' => true,
            'environment' => [
                'id' => (string) $environment->public_id, 'title' => (string) $environment->title, 'version' => (int) $version->version_number,
                'placement_version' => (int) $environment->placement_version,
                'url' => route('exhibitions.virtual.ve03.asset', [$runtimeHash, 'environment']),
                'collision_url' => route('exhibitions.virtual.ve03.asset', [$runtimeHash, 'collision']),
                'hidden_empty_frame_materials' => ['Picture_Frame'],
            ],
            'artwork' => [
                'id' => (string) $artwork->public_id,
                'title' => (string) $artwork->title,
                'dimensions' => ['width_m' => $width, 'height_m' => $height],
                'preview_url' => $primaryMedia
                    ? route('exhibitions.artworks.media', [$artwork->public_id, $primaryMedia->public_id])
                    : null,
            ],
            'hold_seconds' => self::HOLD_MINUTES * 60,
            'spawn' => ['position' => array_map('floatval', $spawn['translation']), 'yaw' => deg2rad((float) ($spawn['yaw_degrees'] ?? 0)), 'pitch' => deg2rad((float) ($spawn['pitch_degrees'] ?? 0)), 'room_code' => (string) ($spawn['room_code'] ?? '')],
            'viewer' => [
                'walk_speed' => (float) config('exhibitions.virtual_exhibitions.ve03.walk_speed', 2.2),
                'max_step_height' => (float) config('exhibitions.virtual_exhibitions.ve03.max_step_height', .28),
                'max_slope_degrees' => (float) config('exhibitions.virtual_exhibitions.ve03.max_slope_degrees', 42),
                'interaction_distance' => (float) config('exhibitions.virtual_exhibitions.ve03.interaction_distance', 3),
                'artwork_safety_distance' => (float) config('exhibitions.virtual_exhibitions.ve03.artwork_safety_distance', 1.0),
                'artwork_safety_side_margin' => (float) config('exhibitions.virtual_exhibitions.ve03.artwork_safety_side_margin', .4),
            ],
            'placements' => $placements,
            'rooms' => $rooms->map(function (object $room) use ($slots, $occupied, $holds, $projectedStates, $blockers, $identityId, $width, $height): array {
                return [
                    'code' => (string) $room->room_code,
                    'title' => (string) $room->title,
                    'bounds' => json_decode((string) $room->bounds, true) ?: [],
                    'slots' => collect($slots->get($room->id, []))->map(function (object $slot) use ($occupied, $holds, $projectedStates, $blockers, $identityId, $width, $height): array {
                        $occupancy = $occupied->get($slot->id);
                        $hold = $holds->get($slot->id);
                        $fit = $this->geometry->evaluate($slot, $width, $height, $blockers);
                        $projectedState = $projectedStates->get((string) $slot->slot_code);
                        $occupancyState = $occupancy
                            ? ($occupancy->state === 'sold'
                                ? 'sold'
                                : ((int) $occupancy->artist_identity_id === $identityId ? 'occupied_by_you' : 'occupied'))
                            : null;
                        $state = ($projectedState === 'sold' ? 'sold' : null)
                            ?? $occupancyState
                            ?? $projectedState
                            ?? ($hold ? ((int) $hold->artist_identity_id === $identityId ? 'selected_by_you' : 'held') : null)
                            ?? (! $slot->enabled_by_default || ! $fit['fits'] ? 'incompatible' : 'available');
                        $slotMetadata = json_decode((string) $slot->metadata, true) ?: [];

                        return [
                            'code' => (string) $slot->slot_code,
                            'name' => (string) $slot->name,
                            'state' => $state,
                            'reason' => match ($state) {
                                'incompatible' => $slot->enabled_by_default ? $fit['reason'] : 'This wall position is disabled by the exhibition curator.',
                                'held' => 'Another artist is previewing this display now.',
                                'occupied_by_you' => 'Your artwork is published in this display.',
                                'occupied' => 'An artwork is already displayed here.',
                                'sold' => 'A sold artwork remains displayed here.',
                                default => null,
                            },
                            'selectable' => in_array($state, ['available', 'selected_by_you'], true),
                            'hold_expires_at' => $hold ? (string) $hold->expires_at : null,
                            'transform' => ['position' => json_decode((string) $slot->translation, true), 'quaternion' => json_decode((string) $slot->rotation, true)],
                            'display' => [
                                'support' => $slotMetadata['display_support'] ?? null,
                                'support_color' => $slotMetadata['support_color'] ?? null,
                            ],
                            'limits' => [
                                'min_width_m' => 0.01,
                                'min_height_m' => 0.01,
                                'max_width_m' => $fit['max_width_m'],
                                'max_height_m' => $fit['max_height_m'],
                                'visual_width_m' => max(0.2, min($width, max(0.2, $fit['max_width_m']))),
                                'visual_height_m' => max(0.2, min($height, max(0.2, $fit['max_height_m']))),
                                'minimum_gap_m' => $fit['gap_m'],
                                'wall_id' => $fit['wall_id'],
                            ],
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    public function createHold(int $coreUserId, string $artworkPublicId, string $slotCode, ?string $movingPlacementPublicId = null, ?string $environmentPublicId = null): object
    {
        try {
            return DB::transaction(function () use ($coreUserId, $artworkPublicId, $slotCode, $movingPlacementPublicId, $environmentPublicId): object {
                [$identityId, $artwork, $environment, $version, $slot] = $this->selection($coreUserId, $artworkPublicId, $slotCode, true, $environmentPublicId);
                $this->discardExpiredHolds((int) $environment->id);
                $this->assertArtworkAvailableForHold($identityId, $artwork, $environment, $movingPlacementPublicId);
                if (DB::table('exhibition_virtual_slot_occupancies')->where('slot_id', $slot->id)->lockForUpdate()->exists()) {
                    throw new DomainException('This display is already occupied.');
                }
                $this->assertNotProjected($environment, $version, $slot);
                $existing = DB::table('exhibition_virtual_slot_holds')->where('environment_id', $environment->id)->where('artist_identity_id', $identityId)->lockForUpdate()->first();
                if ($existing) {
                    DB::table('exhibition_virtual_slot_holds')->where('id', $existing->id)->delete();
                }
                if (DB::table('exhibition_virtual_slot_holds')->where('slot_id', $slot->id)->where('expires_at', '>', now())->lockForUpdate()->exists()) {
                    throw new DomainException('This display is temporarily held by another artist.');
                }
                $movingPlacementId = $movingPlacementPublicId === null
                    ? null
                    : DB::table('exhibition_virtual_placements')
                        ->where('public_id', $movingPlacementPublicId)
                        ->where('artist_identity_id', $identityId)
                        ->value('id');
                $this->assertSafePlacement(
                    $artwork,
                    $environment,
                    $version,
                    $slot,
                    $identityId,
                    $movingPlacementId === null ? null : (int) $movingPlacementId,
                );
                $token = (string) Str::uuid();
                $id = DB::table('exhibition_virtual_slot_holds')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'hold_token' => $token, 'environment_id' => $environment->id,
                    'version_id' => $version->id, 'slot_id' => $slot->id, 'artwork_id' => $artwork->id,
                    'artist_identity_id' => $identityId, 'expires_at' => now()->addMinutes(self::HOLD_MINUTES), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $hold = DB::table('exhibition_virtual_slot_holds')->where('id', $id)->first();
                $this->audit->record(new AuditEntryData('ve03.placement.hold_created', 've03_slot_hold', (string) $hold->public_id, newValues: ['slot_code' => $slotCode, 'expires_at' => (string) $hold->expires_at], actorUserId: $coreUserId));

                return $hold;
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isConstraintViolation($exception)) {
                throw new DomainException('This display was just reserved by another artist. Choose another display.');
            }
            throw $exception;
        }
    }

    public function refreshHold(int $coreUserId, string $token): object
    {
        return DB::transaction(function () use ($coreUserId, $token): object {
            $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
            $hold = DB::table('exhibition_virtual_slot_holds')->where('hold_token', $token)->where('artist_identity_id', $identityId)->lockForUpdate()->first();
            if (! $hold || now()->greaterThanOrEqualTo($hold->expires_at)) {
                if ($hold) {
                    DB::table('exhibition_virtual_slot_holds')->where('id', $hold->id)->delete();
                }
                throw new DomainException('The display hold expired. Select the display again.');
            }
            DB::table('exhibition_virtual_slot_holds')->where('id', $hold->id)->update(['expires_at' => now()->addMinutes(self::HOLD_MINUTES), 'updated_at' => now()]);

            return DB::table('exhibition_virtual_slot_holds')->where('id', $hold->id)->first();
        });
    }

    public function releaseHold(int $coreUserId, string $token): void
    {
        $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
        DB::table('exhibition_virtual_slot_holds')->where('hold_token', $token)->where('artist_identity_id', $identityId)->delete();
    }

    public function confirm(
        int $coreUserId,
        string $artworkPublicId,
        string $slotCode,
        string $token,
        string $frameStyle = 'none',
        string $frameColor = 'black',
        ?string $environmentPublicId = null,
    ): object {
        $frame = $this->validatedFrame($frameStyle, $frameColor);
        try {
            return DB::transaction(function () use ($coreUserId, $artworkPublicId, $slotCode, $token, $frame, $environmentPublicId): object {
                [$identityId, $artwork, $environment, $version, $slot] = $this->selection($coreUserId, $artworkPublicId, $slotCode, true, $environmentPublicId);
                $hold = $this->lockedValidHold($identityId, (int) $artwork->id, (int) $slot->id, $token);
                if (DB::table('exhibition_virtual_slot_occupancies')->where('slot_id', $slot->id)->lockForUpdate()->exists()) {
                    throw new DomainException('This display is already occupied.');
                }
                $this->assertNotProjected($environment, $version, $slot);
                if (DB::table('exhibition_virtual_slot_occupancies')->where('environment_id', $environment->id)->where('artwork_id', $artwork->id)->lockForUpdate()->exists()) {
                    throw new DomainException('This artwork is already displayed in this hall. Choose another hall or move its current placement.');
                }
                $fit = $this->assertSafePlacement($artwork, $environment, $version, $slot, $identityId);
                $dimensions = Ve03ArtworkDimensions::fromArtwork($artwork);
                $geometry = [
                    'width_m' => $dimensions->widthMeters,
                    'height_m' => $dimensions->heightMeters,
                    'wall_id' => $fit['wall_id'],
                    'minimum_gap_m' => $fit['gap_m'],
                ];
                $publicId = (string) Str::uuid();
                $placementId = DB::table('exhibition_virtual_placements')->insertGetId([
                    'public_id' => $publicId, 'environment_id' => $environment->id, 'version_id' => $version->id, 'slot_id' => $slot->id,
                    'artwork_id' => $artwork->id, 'artist_identity_id' => $identityId, 'state' => 'active', 'lock_version' => 1,
                    'placed_at' => now(),
                    'metadata' => json_encode(['confirmation' => 'artist_immediate', 'frame' => $frame, 'geometry' => $geometry], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('exhibition_virtual_slot_occupancies')->insert([
                    'environment_id' => $environment->id, 'version_id' => $version->id, 'slot_id' => $slot->id, 'placement_id' => $placementId,
                    'artwork_id' => $artwork->id, 'artist_identity_id' => $identityId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('exhibition_virtual_slot_holds')->where('environment_id', $environment->id)->where('artwork_id', $artwork->id)->delete();
                DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update(['placement_version' => DB::raw('placement_version + 1'), 'updated_at' => now()]);
                $this->consumeApprovedRepublishRequest((int) $artwork->id, $identityId, (int) $environment->id);
                $this->event($placementId, $coreUserId, null, 'active', 'Artist confirmed placement; published immediately.', ['slot_code' => $slotCode, 'frame' => $frame]);
                $this->audit->record(new AuditEntryData('ve03.placement.activated', 've03_placement', $publicId, newValues: ['state' => 'active', 'slot_code' => $slotCode, 'frame' => $frame], reason: 'Artist confirmed placement; no administrative approval required.', actorUserId: $coreUserId));

                return DB::table('exhibition_virtual_placements')->where('id', $placementId)->first();
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isConstraintViolation($exception)) {
                throw new DomainException('The display or artwork was just occupied in another session. No duplicate placement was created.');
            }
            throw $exception;
        }
    }

    public function move(int $coreUserId, string $placementPublicId, string $slotCode, string $token): object
    {
        try {
            return DB::transaction(function () use ($coreUserId, $placementPublicId, $slotCode, $token): object {
                $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
                $placement = DB::table('exhibition_virtual_placements')->where('public_id', $placementPublicId)->where('artist_identity_id', $identityId)->lockForUpdate()->first() ?? throw new DomainException('Placement not found.');
                $this->assertMovable($placement);
                $artwork = DB::table('exhibition_artworks')->where('id', $placement->artwork_id)->first() ?? throw new DomainException('Artwork not found.');
                $hold = $this->lockedValidHold($identityId, (int) $artwork->id, null, $token);
                $placementEnvironmentPublicId = DB::table('exhibition_virtual_environments')->where('id', $placement->environment_id)->value('public_id');
                [, , $environment, $version, $destination] = $this->selection($coreUserId, (string) $artwork->public_id, $slotCode, true, (string) $placementEnvironmentPublicId);
                if ((int) $environment->id !== (int) $placement->environment_id) {
                    throw new DomainException('The destination belongs to another exhibition.');
                }
                if ((int) $hold->slot_id !== (int) $destination->id) {
                    throw new DomainException('The display hold expired or does not match this selection.');
                }
                $source = DB::table('exhibition_virtual_slot_occupancies')->where('placement_id', $placement->id)->lockForUpdate()->first() ?? throw new DomainException('Placement occupancy is missing.');
                if (DB::table('exhibition_virtual_slot_occupancies')->where('slot_id', $destination->id)->lockForUpdate()->exists()) {
                    throw new DomainException('The destination display is occupied.');
                }
                $this->assertNotProjected($environment, $version, $destination);
                $fit = $this->assertSafePlacement($artwork, $environment, $version, $destination, $identityId, (int) $placement->id);
                $metadata = json_decode((string) ($placement->metadata ?? ''), true) ?: [];
                $dimensions = Ve03ArtworkDimensions::fromArtwork($artwork);
                $metadata['geometry'] = [
                    'width_m' => $dimensions->widthMeters,
                    'height_m' => $dimensions->heightMeters,
                    'wall_id' => $fit['wall_id'],
                    'minimum_gap_m' => $fit['gap_m'],
                ];
                DB::table('exhibition_virtual_slot_occupancies')->where('id', $source->id)->delete();
                DB::table('exhibition_virtual_slot_occupancies')->insert([
                    'environment_id' => $environment->id, 'version_id' => $version->id, 'slot_id' => $destination->id, 'placement_id' => $placement->id,
                    'artwork_id' => $artwork->id, 'artist_identity_id' => $identityId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('exhibition_virtual_placements')->where('id', $placement->id)->update([
                    'version_id' => $version->id,
                    'slot_id' => $destination->id,
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_virtual_slot_holds')->where('id', $hold->id)->delete();
                DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update(['placement_version' => DB::raw('placement_version + 1'), 'updated_at' => now()]);
                $this->event((int) $placement->id, $coreUserId, 'active', 'active', 'Artist moved active placement atomically.', ['from_slot_id' => (int) $source->slot_id, 'to_slot_code' => $slotCode]);

                return DB::table('exhibition_virtual_placements')->where('id', $placement->id)->first();
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isConstraintViolation($exception)) {
                throw new DomainException('The destination was occupied in another session. The original placement was preserved.');
            }
            throw $exception;
        }
    }

    public function withdraw(int $coreUserId, string $placementPublicId, string $reason): object
    {
        return DB::transaction(function () use ($coreUserId, $placementPublicId, $reason): object {
            $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
            $placement = DB::table('exhibition_virtual_placements')->where('public_id', $placementPublicId)->where('artist_identity_id', $identityId)->lockForUpdate()->first() ?? throw new DomainException('Placement not found.');
            $this->assertMovable($placement);
            $environment = DB::table('exhibition_virtual_environments')->where('id', $placement->environment_id)->lockForUpdate()->first();
            if ($environment?->placement_lock_at && now()->greaterThanOrEqualTo($environment->placement_lock_at)) {
                throw new DomainException('Placement changes are locked for this exhibition.');
            }
            DB::table('exhibition_virtual_slot_occupancies')->where('placement_id', $placement->id)->delete();
            DB::table('exhibition_virtual_placements')->where('id', $placement->id)->update(['state' => 'released', 'released_at' => now(), 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]);
            DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update(['placement_version' => DB::raw('placement_version + 1'), 'updated_at' => now()]);
            $this->event((int) $placement->id, $coreUserId, 'active', 'released', $reason, ['release_origin' => 'artist']);
            $this->audit->record(new AuditEntryData('ve03.placement.released', 've03_placement', $placementPublicId, previousValues: ['state' => 'active'], newValues: ['state' => 'released'], reason: $reason, actorUserId: $coreUserId));

            return DB::table('exhibition_virtual_placements')->where('id', $placement->id)->first();
        });
    }

    public function removeByAdministrator(int $actorUserId, string $placementPublicId, string $reason): object
    {
        return DB::transaction(function () use ($actorUserId, $placementPublicId, $reason): object {
            $placement = DB::table('exhibition_virtual_placements')
                ->where('public_id', $placementPublicId)
                ->lockForUpdate()
                ->first() ?? throw new DomainException('Placement not found.');
            $this->assertMovable($placement);
            $environment = DB::table('exhibition_virtual_environments')
                ->where('id', $placement->environment_id)
                ->lockForUpdate()
                ->first() ?? throw new DomainException('Virtual exhibition not found.');

            DB::table('exhibition_virtual_slot_occupancies')->where('placement_id', $placement->id)->delete();
            DB::table('exhibition_virtual_placements')->where('id', $placement->id)->update([
                'state' => 'released',
                'released_at' => now(),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update([
                'placement_version' => DB::raw('placement_version + 1'),
                'updated_at' => now(),
            ]);
            $this->event((int) $placement->id, $actorUserId, 'active', 'released', $reason, ['release_origin' => 'administration']);
            $this->audit->record(new AuditEntryData(
                've03.placement.removed_by_administration',
                've03_placement',
                $placementPublicId,
                previousValues: ['state' => 'active'],
                newValues: ['state' => 'released', 'release_origin' => 'administration'],
                reason: $reason,
                actorUserId: $actorUserId,
            ));

            return DB::table('exhibition_virtual_placements')->where('id', $placement->id)->first();
        });
    }

    public function requestRepublish(int $coreUserId, string $placementPublicId, string $message): object
    {
        return DB::transaction(function () use ($coreUserId, $placementPublicId, $message): object {
            $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
            $placement = DB::table('exhibition_virtual_placements')
                ->where('public_id', $placementPublicId)
                ->where('artist_identity_id', $identityId)
                ->lockForUpdate()
                ->first() ?? throw new DomainException('Removed placement not found.');
            if ($placement->state !== 'released' || $this->releaseOrigin($placement, $coreUserId) !== 'administration') {
                throw new DomainException('Only an artwork removed by the administration can be submitted for republishing approval.');
            }
            $latestPlacementId = DB::table('exhibition_virtual_placements')
                ->where('artwork_id', $placement->artwork_id)
                ->where('artist_identity_id', $identityId)
                ->where('environment_id', $placement->environment_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');
            if ((int) $latestPlacementId !== (int) $placement->id) {
                throw new DomainException('This removal record has already been superseded by a newer placement.');
            }
            if (DB::table('exhibition_virtual_republish_requests')
                ->where('placement_id', $placement->id)
                ->whereIn('state', ['pending', 'approved'])
                ->lockForUpdate()
                ->exists()) {
                throw new DomainException('A pending or approved republishing request already exists for this artwork.');
            }

            $publicId = (string) Str::uuid();
            $id = DB::table('exhibition_virtual_republish_requests')->insertGetId([
                'public_id' => $publicId,
                'placement_id' => $placement->id,
                'artist_identity_id' => $identityId,
                'state' => 'pending',
                'artist_message' => trim($message),
                'requested_at' => now(),
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData(
                've03.placement.republish_requested',
                've03_republish_request',
                $publicId,
                newValues: ['state' => 'pending', 'placement_id' => $placementPublicId],
                reason: trim($message),
                actorUserId: $coreUserId,
            ));

            return DB::table('exhibition_virtual_republish_requests')->where('id', $id)->first();
        });
    }

    public function reviewRepublishRequest(int $actorUserId, string $requestPublicId, string $decision, string $reason): object
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Choose approval or rejection for the republishing request.');
        }

        return DB::transaction(function () use ($actorUserId, $requestPublicId, $decision, $reason): object {
            $request = DB::table('exhibition_virtual_republish_requests')
                ->where('public_id', $requestPublicId)
                ->lockForUpdate()
                ->first() ?? throw new DomainException('Republishing request not found.');
            if ($request->state !== 'pending') {
                throw new DomainException('Only a pending republishing request can be reviewed.');
            }

            DB::table('exhibition_virtual_republish_requests')->where('id', $request->id)->update([
                'state' => $decision,
                'reviewer_user_id' => $actorUserId,
                'decision_reason' => trim($reason),
                'decided_at' => now(),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData(
                've03.placement.republish_'.$decision,
                've03_republish_request',
                $requestPublicId,
                previousValues: ['state' => 'pending'],
                newValues: ['state' => $decision],
                reason: trim($reason),
                actorUserId: $actorUserId,
            ));

            return DB::table('exhibition_virtual_republish_requests')->where('id', $request->id)->first();
        });
    }

    private function activeEnvironment(?string $environmentPublicId = null): object
    {
        return DB::table('exhibition_virtual_environments')
            ->where('status', 'published')
            ->where('artist_placement_open', true)
            ->whereNotNull('active_version_id')
            ->when($environmentPublicId, fn ($query) => $query->where('public_id', $environmentPublicId))
            ->orderBy('id')
            ->first()
            ?? throw new DomainException(__('exhibitions::ui.no_virtual_exhibition_open'));
    }

    private function activeVersion(object $environment): object
    {
        return DB::table('exhibition_virtual_environment_versions')->where('id', $environment->active_version_id)->where('environment_id', $environment->id)->where('status', 'published')->first()
            ?? throw new DomainException('The active virtual exhibition version is unavailable.');
    }

    /** @return array{int, object, object, object, object} */
    private function selection(int $coreUserId, string $artworkPublicId, string $slotCode, bool $lock, ?string $environmentPublicId = null): array
    {
        $identityId = (int) $this->access->context($coreUserId)->artist_identity_id;
        $artwork = $this->legacy->eligibleArtwork($identityId, $artworkPublicId, true);
        if (! $this->availableForSale($artworkPublicId)) {
            throw new DomainException('The artwork is no longer available for placement.');
        }
        $environmentQuery = DB::table('exhibition_virtual_environments')->where('status', 'published')->where('artist_placement_open', true)->whereNotNull('active_version_id')->orderBy('id');
        if ($environmentPublicId) {
            $environmentQuery->where('public_id', $environmentPublicId);
        }
        if ($lock) {
            $environmentQuery->lockForUpdate();
        }
        $environment = $environmentQuery->first() ?? throw new DomainException(__('exhibitions::ui.no_virtual_exhibition_open'));
        $this->assertAdministrationRepublishAllowed($coreUserId, $identityId, $artwork, (int) $environment->id);
        $version = $this->activeVersion($environment);
        $slotQuery = DB::table('exhibition_virtual_environment_slots')->where('version_id', $version->id)->where('slot_code', $slotCode);
        if ($lock) {
            $slotQuery->lockForUpdate();
        }
        $slot = $slotQuery->first() ?? throw new DomainException('Display not found in the active exhibition.');
        if (! $slot->enabled_by_default) {
            throw new DomainException('This wall position is disabled by the exhibition curator.');
        }
        $dimensions = Ve03ArtworkDimensions::fromArtwork($artwork);
        $fit = $this->geometry->evaluate($slot, $dimensions->widthMeters, $dimensions->heightMeters);
        if (! $fit['fits']) {
            throw new DomainException($fit['reason'] ?? 'The available wall space is too tight for this artwork.');
        }

        return [$identityId, $artwork, $environment, $version, $slot];
    }

    private function lockedValidHold(int $identityId, int $artworkId, ?int $slotId, string $token): object
    {
        $hold = DB::table('exhibition_virtual_slot_holds')
            ->where('hold_token', $token)
            ->where('artist_identity_id', $identityId)
            ->where('artwork_id', $artworkId)
            ->when($slotId !== null, fn ($query) => $query->where('slot_id', $slotId))
            ->lockForUpdate()
            ->first();
        if (! $hold || now()->greaterThanOrEqualTo($hold->expires_at)) {
            if ($hold) {
                DB::table('exhibition_virtual_slot_holds')->where('id', $hold->id)->delete();
            }
            throw new DomainException('The display hold expired or does not match this selection.');
        }

        return $hold;
    }

    private function assertMovable(object $placement): void
    {
        if ($placement->state !== 'active') {
            throw new DomainException('Only an active unsold placement can be changed.');
        }
        $artworkPublicId = DB::table('exhibition_artworks')->where('id', $placement->artwork_id)->value('public_id');
        $commerce = $this->commerce->status((string) $artworkPublicId);
        if ($placement->sold_at || $commerce->availability === 'sold' || ($commerce->storeAvailable && $commerce->availableQuantity < 1)) {
            throw new DomainException('A sold artwork cannot be moved or withdrawn and remains on display.');
        }
    }

    private function assertArtworkAvailableForHold(int $identityId, object $artwork, object $environment, ?string $movingPlacementPublicId): void
    {
        $occupancy = DB::table('exhibition_virtual_slot_occupancies as occupancy')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'occupancy.placement_id')
            ->where('occupancy.artwork_id', $artwork->id)
            ->where('occupancy.environment_id', $environment->id)
            ->lockForUpdate()
            ->first(['occupancy.placement_id', 'placement.public_id', 'placement.artist_identity_id', 'placement.environment_id', 'placement.state']);

        if ($occupancy) {
            $isMovingThisPlacement = $movingPlacementPublicId !== null
                && hash_equals((string) $occupancy->public_id, $movingPlacementPublicId)
                && (int) $occupancy->artist_identity_id === $identityId
                && (int) $occupancy->environment_id === (int) $environment->id
                && $occupancy->state === 'active';
            if (! $isMovingThisPlacement) {
                throw new DomainException('This artwork is already displayed in this hall. Choose another hall or move its current placement.');
            }

            return;
        }

    }

    private function assertAdministrationRepublishAllowed(int $coreUserId, int $identityId, object $artwork, int $environmentId): void
    {
        $latestPlacement = DB::table('exhibition_virtual_placements')
            ->where('artwork_id', $artwork->id)
            ->where('artist_identity_id', $identityId)
            ->where('environment_id', $environmentId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        if (! $latestPlacement
            || $latestPlacement->state !== 'released'
            || $this->releaseOrigin($latestPlacement, $coreUserId) !== 'administration') {
            return;
        }

        $approved = DB::table('exhibition_virtual_republish_requests')
            ->where('placement_id', $latestPlacement->id)
            ->where('state', 'approved')
            ->exists();
        if (! $approved) {
            throw new DomainException('This artwork was removed by the administration. Submit a republishing request and wait for approval before placing it again.');
        }
    }

    private function consumeApprovedRepublishRequest(int $artworkId, int $identityId, int $environmentId): void
    {
        $request = DB::table('exhibition_virtual_republish_requests as request')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'request.placement_id')
            ->where('placement.artwork_id', $artworkId)
            ->where('placement.artist_identity_id', $identityId)
            ->where('placement.environment_id', $environmentId)
            ->where('request.state', 'approved')
            ->orderByDesc('request.decided_at')
            ->orderByDesc('request.id')
            ->lockForUpdate()
            ->first(['request.*']);
        if (! $request) {
            return;
        }

        DB::table('exhibition_virtual_republish_requests')->where('id', $request->id)->update([
            'state' => 'used',
            'consumed_at' => now(),
            'lock_version' => DB::raw('lock_version + 1'),
            'updated_at' => now(),
        ]);
    }

    private function releaseOrigin(object $placement, ?int $artistUserId = null): ?string
    {
        $event = DB::table('exhibition_virtual_placement_events')
            ->where('placement_id', $placement->id)
            ->where('to_state', 'released')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();
        if (! $event) {
            return null;
        }
        $context = json_decode((string) $event->context, true) ?: [];

        return $context['release_origin']
            ?? ($artistUserId !== null && (int) $event->actor_user_id === $artistUserId ? 'artist' : 'administration');
    }

    private function discardExpiredHolds(int $environmentId): void
    {
        DB::table('exhibition_virtual_slot_holds')->where('environment_id', $environmentId)->where('expires_at', '<=', now())->delete();
    }

    private function publicRoomForEnvironment(object $environment): ?object
    {
        return DB::table('exhibition_virtual_rooms')->where('status', 'published')->orderBy('id')->first();
    }

    private function assertNotProjected(object $environment, object $version, object $slot): void
    {
        $publicRoom = $this->publicRoomForEnvironment($environment);
        if (! $publicRoom) {
            return;
        }

        $placement = collect($this->projection->publicPlacements($publicRoom, $version))
            ->firstWhere('slot_code', (string) $slot->slot_code);
        if ($placement) {
            $message = ($placement['artwork']['sold'] ?? false)
                ? 'A sold artwork remains displayed here.'
                : 'An artwork is already displayed here.';
            throw new DomainException($message);
        }
    }

    /**
     * Re-evaluate the wall while the environment row is locked. The check is
     * deliberately repeated after preview selection so stale browser data can
     * never create overlapping placements.
     *
     * @return array{fits: bool, max_width_m: float, max_height_m: float, gap_m: float, reason: ?string, wall_id: ?string}
     */
    private function assertSafePlacement(
        object $artwork,
        object $environment,
        object $version,
        object $slot,
        ?int $excludedHoldIdentityId = null,
        ?int $excludedPlacementId = null,
    ): array {
        $slots = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $version->id)
            ->lockForUpdate()
            ->get();
        $occupancies = DB::table('exhibition_virtual_slot_occupancies as occupancy')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'occupancy.placement_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'occupancy.artwork_id')
            ->where('occupancy.environment_id', $environment->id)
            ->lockForUpdate()
            ->get([
                'occupancy.slot_id',
                'occupancy.placement_id',
                'placement.state',
                'artwork.width',
                'artwork.height',
                'artwork.dimension_unit',
            ]);
        $holds = DB::table('exhibition_virtual_slot_holds as hold')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'hold.artwork_id')
            ->where('hold.environment_id', $environment->id)
            ->where('hold.expires_at', '>', now())
            ->lockForUpdate()
            ->get([
                'hold.slot_id',
                'hold.artist_identity_id',
                'artwork.width',
                'artwork.height',
                'artwork.dimension_unit',
            ]);
        $publicRoom = $this->publicRoomForEnvironment($environment);
        $projected = $publicRoom ? $this->projection->publicPlacements($publicRoom, $version) : [];
        $dimensions = Ve03ArtworkDimensions::fromArtwork($artwork);
        $fit = $this->geometry->evaluate(
            $slot,
            $dimensions->widthMeters,
            $dimensions->heightMeters,
            $this->blockers($slots, $occupancies, $holds, $projected, $excludedHoldIdentityId, $excludedPlacementId),
        );

        if (! $slot->enabled_by_default) {
            throw new DomainException('This wall position is disabled by the exhibition curator.');
        }
        if (! $fit['fits']) {
            throw new DomainException($fit['reason'] ?? 'The available wall space is too tight for this artwork.');
        }

        return $fit;
    }

    /**
     * @param  iterable<int, object>  $slots
     * @param  iterable<int, object>  $occupancies
     * @param  iterable<int, object>  $holds
     * @param  iterable<int, array<string, mixed>>  $projectedPlacements
     * @return list<array{slot: object, width_m: ?float, height_m: ?float, dimensions_known: bool, kind: string}>
     */
    private function blockers(
        iterable $slots,
        iterable $occupancies,
        iterable $holds,
        iterable $projectedPlacements,
        ?int $excludedHoldIdentityId = null,
        ?int $excludedPlacementId = null,
    ): array {
        $slotsById = collect($slots)->keyBy(fn (object $slot): int => (int) $slot->id);
        $slotsByCode = $slotsById->keyBy(fn (object $slot): string => (string) $slot->slot_code);
        $occupiedSlotCodes = [];
        $blockers = [];

        foreach ($occupancies as $occupancy) {
            $blockingSlot = $slotsById->get((int) $occupancy->slot_id);
            if ($blockingSlot) {
                // Also suppress the projection duplicate when the current
                // placement is excluded while it is being moved.
                $occupiedSlotCodes[(string) $blockingSlot->slot_code] = true;
            }
            if ($excludedPlacementId !== null && (int) $occupancy->placement_id === $excludedPlacementId) {
                continue;
            }
            if (! $blockingSlot) {
                continue;
            }
            $blockers[] = $this->blocker($blockingSlot, $occupancy, 'placement');
        }

        foreach ($holds as $hold) {
            if ($excludedHoldIdentityId !== null && (int) $hold->artist_identity_id === $excludedHoldIdentityId) {
                continue;
            }
            $blockingSlot = $slotsById->get((int) $hold->slot_id);
            if ($blockingSlot) {
                $blockers[] = $this->blocker($blockingSlot, $hold, 'hold');
            }
        }

        foreach ($projectedPlacements as $projected) {
            $slotCode = (string) ($projected['slot_code'] ?? '');
            if ($slotCode === '' || isset($occupiedSlotCodes[$slotCode])) {
                continue;
            }
            $blockingSlot = $slotsByCode->get($slotCode);
            if (! $blockingSlot) {
                continue;
            }
            $blockers[] = [
                'slot' => $blockingSlot,
                'width_m' => is_numeric(data_get($projected, 'dimensions.width_m'))
                    && (float) data_get($projected, 'dimensions.width_m') > 0
                        ? (float) data_get($projected, 'dimensions.width_m')
                        : null,
                'height_m' => is_numeric(data_get($projected, 'dimensions.height_m'))
                    && (float) data_get($projected, 'dimensions.height_m') > 0
                        ? (float) data_get($projected, 'dimensions.height_m')
                        : null,
                'dimensions_known' => is_numeric(data_get($projected, 'dimensions.width_m'))
                    && (float) data_get($projected, 'dimensions.width_m') > 0
                    && is_numeric(data_get($projected, 'dimensions.height_m'))
                    && (float) data_get($projected, 'dimensions.height_m') > 0,
                'kind' => 'projection',
            ];
        }

        return $blockers;
    }

    /** @return array{slot: object, width_m: ?float, height_m: ?float, dimensions_known: bool, kind: string} */
    private function blocker(object $slot, object $record, string $kind): array
    {
        $dimensions = Ve03ArtworkDimensions::tryFromArtwork($record);

        return [
            'slot' => $slot,
            'width_m' => $dimensions?->widthMeters,
            'height_m' => $dimensions?->heightMeters,
            'dimensions_known' => $dimensions !== null,
            'kind' => $kind,
        ];
    }

    /** @return array{style:string,color:?string} */
    private function validatedFrame(string $style, string $color): array
    {
        $style = strtolower(trim($style));
        $color = strtolower(trim($color));
        if (! in_array($style, self::FRAME_STYLES, true)) {
            throw new DomainException('Choose no frame, a thin frame, or a wide frame.');
        }
        if ($style !== 'none' && ! in_array($color, self::FRAME_COLORS, true)) {
            throw new DomainException('Choose one of the available frame colors.');
        }

        return ['style' => $style, 'color' => $style === 'none' ? null : $color];
    }

    private function availableForSale(string $artworkPublicId): bool
    {
        $commerce = $this->commerce->status($artworkPublicId);

        return $commerce->storeAvailable
            && $commerce->purchasable
            && $commerce->availability !== 'sold'
            && $commerce->availableQuantity > 0;
    }

    /** @param array<string, mixed> $context */
    private function event(int $placementId, ?int $actor, ?string $from, string $to, string $reason, array $context = []): void
    {
        DB::table('exhibition_virtual_placement_events')->insert([
            'public_id' => (string) Str::uuid(), 'placement_id' => $placementId, 'actor_user_id' => $actor, 'from_state' => $from,
            'to_state' => $to, 'reason' => $reason, 'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'created_at' => now(),
        ]);
    }

    private function isConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
