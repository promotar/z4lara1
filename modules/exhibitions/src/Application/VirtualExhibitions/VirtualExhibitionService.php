<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Modules\Exhibition\Domain\VirtualExhibitions\PlacementState;
use Modules\Exhibition\Domain\VirtualExhibitions\RoomState;
use Modules\Exhibition\Events\V1\VirtualPlacementStateChanged;
use Throwable;

final readonly class VirtualExhibitionService
{
    public function __construct(
        private AuditRecorder $audit,
        private ArtistDashboardAccess $artistAccess,
        private ArtworkCommerceGateway $commerce,
        private ExhibitionNotificationService $notifications,
        private PanoramaStorage $panoramas,
    ) {}

    /** @param array<string,mixed> $data */
    public function createRoom(int $actor, array $data): object
    {
        return DB::transaction(function () use ($actor, $data): object {
            $publicId = (string) Str::uuid();
            $slug = $this->uniqueSlug((string) $data['title']);
            $id = DB::table('exhibition_virtual_rooms')->insertGetId($this->roomPayload($data) + [
                'public_id' => $publicId,
                'slug' => $slug,
                'status' => RoomState::Draft->value,
                'created_by' => $actor,
                'updated_by' => $actor,
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData('virtual_room.created', 'virtual_room', $publicId, newValues: ['title' => $data['title'], 'slug' => $slug], actorUserId: $actor));

            return DB::table('exhibition_virtual_rooms')->where('id', $id)->first();
        });
    }

    /** @param array<string,mixed> $data */
    public function updateRoom(int $actor, string $publicId, array $data, int $expectedVersion): object
    {
        return DB::transaction(function () use ($actor, $publicId, $data, $expectedVersion): object {
            $room = DB::table('exhibition_virtual_rooms')->where('public_id', $publicId)->lockForUpdate()->first() ?? throw new DomainException('Virtual exhibition room not found.');
            if ((int) $room->lock_version !== $expectedVersion) {
                throw new DomainException('This room changed in another session. Reload before saving again.');
            }
            $payload = $this->roomPayload($data) + ['updated_by' => $actor, 'lock_version' => $expectedVersion + 1, 'updated_at' => now()];
            DB::table('exhibition_virtual_rooms')->where('id', $room->id)->update($payload);
            $this->audit->record(new AuditEntryData('virtual_room.updated', 'virtual_room', $publicId, previousValues: ['lock_version' => $expectedVersion], newValues: ['lock_version' => $expectedVersion + 1], actorUserId: $actor));

            return DB::table('exhibition_virtual_rooms')->where('id', $room->id)->first();
        });
    }

    public function uploadPanorama(int $actor, string $roomPublicId, UploadedFile $file): object
    {
        $room = DB::table('exhibition_virtual_rooms')->where('public_id', $roomPublicId)->first() ?? throw new DomainException('Virtual exhibition room not found.');
        $stored = $this->panoramas->store($roomPublicId, $file);

        try {
            DB::transaction(function () use ($actor, $room, $stored): void {
                DB::table('exhibition_virtual_rooms')->where('id', $room->id)->update([
                    'panorama_disk' => $stored['disk'], 'panorama_path' => $stored['path'], 'panorama_mime' => $stored['mime'],
                    'panorama_size' => $stored['size'], 'panorama_width' => $stored['width'], 'panorama_height' => $stored['height'],
                    'panorama_checksum' => $stored['checksum'], 'updated_by' => $actor,
                    'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
                ]);
                $this->audit->record(new AuditEntryData('virtual_room.panorama_uploaded', 'virtual_room', $room->public_id, newValues: ['width' => $stored['width'], 'height' => $stored['height'], 'size' => $stored['size'], 'checksum' => $stored['checksum']], actorUserId: $actor));
            });
        } catch (Throwable $exception) {
            Storage::disk($stored['disk'])->delete($stored['path']);
            throw $exception;
        }

        if ($room->panorama_path) {
            Storage::disk($room->panorama_disk)->delete($room->panorama_path);
        }

        return DB::table('exhibition_virtual_rooms')->where('id', $room->id)->first();
    }

    /** @param array<string,mixed> $data */
    public function createSlot(int $actor, string $roomPublicId, array $data): object
    {
        return DB::transaction(function () use ($actor, $roomPublicId, $data): object {
            $room = DB::table('exhibition_virtual_rooms')->where('public_id', $roomPublicId)->lockForUpdate()->first() ?? throw new DomainException('Virtual exhibition room not found.');
            $this->assertCoordinates($data);
            $publicId = (string) Str::uuid();
            $id = DB::table('exhibition_virtual_room_slots')->insertGetId($this->slotPayload($data) + [
                'public_id' => $publicId, 'room_id' => $room->id, 'created_by' => $actor, 'updated_by' => $actor,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('exhibition_virtual_rooms')->where('id', $room->id)->update(['lock_version' => DB::raw('lock_version + 1'), 'updated_by' => $actor, 'updated_at' => now()]);
            $this->audit->record(new AuditEntryData('virtual_slot.created', 'virtual_room_slot', $publicId, newValues: $this->coordinateSnapshot($data), actorUserId: $actor));

            return DB::table('exhibition_virtual_room_slots')->where('id', $id)->first();
        });
    }

    /** @param array<string,mixed> $data */
    public function updateSlot(int $actor, string $slotPublicId, array $data): object
    {
        return DB::transaction(function () use ($actor, $slotPublicId, $data): object {
            $slot = DB::table('exhibition_virtual_room_slots')->where('public_id', $slotPublicId)->lockForUpdate()->first() ?? throw new DomainException('Display slot not found.');
            $this->assertCoordinates($data);
            $previous = ['yaw' => (float) $slot->anchor_yaw, 'pitch' => (float) $slot->anchor_pitch, 'roll' => (float) $slot->roll, 'width' => (float) $slot->angular_width];
            DB::table('exhibition_virtual_room_slots')->where('id', $slot->id)->update($this->slotPayload($data) + ['updated_by' => $actor, 'updated_at' => now()]);
            if ($slot->active_request_id) {
                DB::table('exhibition_virtual_placement_requests')->where('id', $slot->active_request_id)->update([
                    'placement_yaw' => $data['anchor_yaw'], 'placement_pitch' => $data['anchor_pitch'], 'placement_roll' => $data['roll'],
                    'placement_width' => $data['angular_width'], 'maximum_height' => $data['maximum_angular_height'] ?? null,
                    'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
                ]);
            }
            $this->audit->record(new AuditEntryData('virtual_slot.updated', 'virtual_room_slot', $slotPublicId, previousValues: $previous, newValues: $this->coordinateSnapshot($data), actorUserId: $actor));

            return DB::table('exhibition_virtual_room_slots')->where('id', $slot->id)->first();
        });
    }

    public function deleteSlot(int $actor, string $slotPublicId): void
    {
        DB::transaction(function () use ($actor, $slotPublicId): void {
            $slot = DB::table('exhibition_virtual_room_slots')->where('public_id', $slotPublicId)->lockForUpdate()->first() ?? throw new DomainException('Display slot not found.');
            if ($slot->active_request_id !== null) {
                throw new DomainException('An occupied or reserved slot cannot be deleted. Disable it or remove its placement first.');
            }
            if (DB::table('exhibition_virtual_placement_requests')->where('slot_id', $slot->id)->exists()) {
                DB::table('exhibition_virtual_room_slots')->where('id', $slot->id)->update(['enabled' => false, 'updated_by' => $actor, 'updated_at' => now()]);
                $this->audit->record(new AuditEntryData('virtual_slot.disabled', 'virtual_room_slot', $slotPublicId, reason: 'Slot has retained placement history.', actorUserId: $actor));
                return;
            }
            DB::table('exhibition_virtual_room_slots')->where('id', $slot->id)->delete();
            $this->audit->record(new AuditEntryData('virtual_slot.deleted', 'virtual_room_slot', $slotPublicId, actorUserId: $actor));
        });
    }

    public function submitPlacement(int $coreUserId, string $roomPublicId, string $slotPublicId, string $artworkPublicId, ?string $note): object
    {
        $context = $this->artistAccess->context($coreUserId);
        if ($context->account_state !== 'active') {
            throw new DomainException('A suspended or restricted artist cannot submit a placement request.');
        }

        try {
            return DB::transaction(function () use ($coreUserId, $context, $roomPublicId, $slotPublicId, $artworkPublicId, $note): object {
                $room = $this->liveRoom($roomPublicId, true);
                $slot = DB::table('exhibition_virtual_room_slots')->where('public_id', $slotPublicId)->where('room_id', $room->id)->lockForUpdate()->first() ?? throw new DomainException('The selected display slot does not exist in this room.');
                if (! $slot->enabled || $slot->active_request_id !== null) {
                    throw new DomainException('The selected display slot is no longer available. Choose another slot.');
                }
                $artwork = $this->eligibleArtwork((int) $context->artist_identity_id, $artworkPublicId, true);
                if (DB::table('exhibition_virtual_placement_requests')->where('artwork_id', $artwork->id)->whereIn('status', ['submitted', 'changes_requested', 'approved', 'published'])->exists()) {
                    throw new DomainException('This artwork already has an active virtual exhibition placement.');
                }
                $commerce = $this->commerce->status($artworkPublicId);
                if (! $commerce->storeAvailable || ! $commerce->purchasable) {
                    throw new DomainException($commerce->message ?: 'The artwork is not currently sellable through Store.');
                }

                $publicId = (string) Str::uuid();
                $requestId = DB::table('exhibition_virtual_placement_requests')->insertGetId([
                    'public_id' => $publicId, 'room_id' => $room->id, 'slot_id' => $slot->id, 'artwork_id' => $artwork->id,
                    'artist_identity_id' => $context->artist_identity_id, 'status' => PlacementState::Draft->value, 'artist_note' => $note,
                    'artwork_snapshot' => json_encode($this->artworkSnapshot($artwork), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'placement_yaw' => $slot->anchor_yaw, 'placement_pitch' => $slot->anchor_pitch, 'placement_roll' => $slot->roll,
                    'placement_width' => $slot->angular_width, 'maximum_height' => $slot->maximum_angular_height,
                    'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->recordTransition($requestId, $publicId, null, PlacementState::Draft, $coreUserId, 'Placement draft created.', ['room' => $room->public_id, 'slot' => $slot->public_id]);
                $this->transitionLocked($requestId, PlacementState::Submitted, $coreUserId, 'Submitted by artist.');
                DB::table('exhibition_virtual_room_slots')->where('id', $slot->id)->update(['active_request_id' => $requestId, 'updated_at' => now()]);

                $this->notifications->dispatchCommitted('virtual_placement_submitted', $this->reviewerRecipients(), ['message' => 'A virtual exhibition placement request is ready for review.', 'placement_public_id' => $publicId], 'virtual-placement-submitted:'.$publicId);

                return DB::table('exhibition_virtual_placement_requests')->where('id', $requestId)->first();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw new DomainException('The selected display slot was reserved by another request. Choose another slot.', previous: $exception);
            }
            throw $exception;
        }
    }

    public function withdrawPlacement(int $coreUserId, string $publicId, string $reason): object
    {
        $context = $this->artistAccess->context($coreUserId);

        return DB::transaction(function () use ($coreUserId, $context, $publicId, $reason): object {
            $request = DB::table('exhibition_virtual_placement_requests')->where('public_id', $publicId)->where('artist_identity_id', $context->artist_identity_id)->lockForUpdate()->first() ?? throw new DomainException('Placement request not found.');
            $this->transitionLocked($request->id, PlacementState::Withdrawn, $coreUserId, $reason);
            $this->releaseSlot($request->id);

            return DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->first();
        });
    }

    public function resubmitPlacement(int $coreUserId, string $publicId, ?string $note): object
    {
        $context = $this->artistAccess->context($coreUserId);
        if ($context->account_state !== 'active') {
            throw new DomainException('A suspended or restricted artist cannot resubmit a placement request.');
        }

        return DB::transaction(function () use ($coreUserId, $context, $publicId, $note): object {
            $request = DB::table('exhibition_virtual_placement_requests')->where('public_id', $publicId)->where('artist_identity_id', $context->artist_identity_id)->lockForUpdate()->first() ?? throw new DomainException('Placement request not found.');
            if (! $this->artworkStillEligible($request->artwork_id)) {
                throw new DomainException('The artwork is no longer eligible for placement.');
            }
            DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->update(['artist_note' => $note ?? $request->artist_note, 'updated_at' => now()]);
            $this->transitionLocked($request->id, PlacementState::Submitted, $coreUserId, 'Artist resubmitted requested changes.');
            $this->notifications->dispatchCommitted('virtual_placement_submitted', $this->reviewerRecipients(), ['message' => 'A revised virtual exhibition placement is ready for review.', 'placement_public_id' => $publicId], 'virtual-placement-resubmitted:'.$publicId.':'.$request->lock_version);

            return DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->first();
        });
    }

    /** @param array<string,mixed> $adjustments */
    public function reviewPlacement(int $actor, string $publicId, string $decision, string $reason, array $adjustments = [], ?string $newSlotPublicId = null): object
    {
        return DB::transaction(function () use ($actor, $publicId, $decision, $reason, $adjustments, $newSlotPublicId): object {
            $request = DB::table('exhibition_virtual_placement_requests')->where('public_id', $publicId)->lockForUpdate()->first() ?? throw new DomainException('Placement request not found.');
            if ($newSlotPublicId !== null && $newSlotPublicId !== '') {
                $request = $this->moveRequest($request, $newSlotPublicId, $actor);
            }
            if ($adjustments !== []) {
                $this->assertCoordinates([
                    'anchor_yaw' => $adjustments['placement_yaw'] ?? $request->placement_yaw,
                    'anchor_pitch' => $adjustments['placement_pitch'] ?? $request->placement_pitch,
                    'roll' => $adjustments['placement_roll'] ?? $request->placement_roll,
                    'angular_width' => $adjustments['placement_width'] ?? $request->placement_width,
                    'maximum_angular_height' => $adjustments['maximum_height'] ?? $request->maximum_height,
                ]);
                DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->update([
                    'placement_yaw' => $adjustments['placement_yaw'] ?? $request->placement_yaw,
                    'placement_pitch' => $adjustments['placement_pitch'] ?? $request->placement_pitch,
                    'placement_roll' => $adjustments['placement_roll'] ?? $request->placement_roll,
                    'placement_width' => $adjustments['placement_width'] ?? $request->placement_width,
                    'maximum_height' => $adjustments['maximum_height'] ?? $request->maximum_height,
                    'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
                ]);
            }

            $target = PlacementState::from($decision);
            $this->transitionLocked($request->id, $target, $actor, $reason, ['reviewer_note' => $reason]);
            if (in_array($target, [PlacementState::Rejected, PlacementState::Removed], true)) {
                $this->releaseSlot($request->id);
            } elseif ($target === PlacementState::Approved) {
                $room = DB::table('exhibition_virtual_rooms')->where('id', $request->room_id)->first();
                if ($room && $this->roomIsVisible($room) && $this->artworkStillEligible($request->artwork_id)) {
                    $this->transitionLocked($request->id, PlacementState::Published, $actor, 'Published after approval in a live room.');
                }
            }

            $updated = DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->first();
            $artistUserId = (int) DB::table('exhibition_artist_identities')->where('id', $updated->artist_identity_id)->value('core_user_id');
            $event = match ($updated->status) {
                'changes_requested' => 'virtual_placement_changes_requested',
                'published' => 'virtual_placement_published',
                'approved' => 'virtual_placement_approved',
                'rejected' => 'virtual_placement_rejected',
                'removed' => 'virtual_placement_removed',
                default => null,
            };
            if ($event !== null) {
                $this->notifications->dispatchCommitted($event, [$artistUserId], ['message' => 'Your virtual exhibition placement is now '.str_replace('_', ' ', $updated->status).'.', 'placement_public_id' => $updated->public_id], $event.':'.$updated->public_id.':'.$updated->lock_version);
            }

            return $updated;
        });
    }

    public function changeRoomState(int $actor, string $publicId, RoomState $target, string $reason): object
    {
        return DB::transaction(function () use ($actor, $publicId, $target, $reason): object {
            $room = DB::table('exhibition_virtual_rooms')->where('public_id', $publicId)->lockForUpdate()->first() ?? throw new DomainException('Virtual exhibition room not found.');
            $current = RoomState::from($room->status);
            $current->assertTransitionTo($target);
            if ($target === RoomState::Published) {
                if (! $room->panorama_path) {
                    throw new DomainException('Upload a valid panorama before publishing this room.');
                }
                if (! DB::table('exhibition_virtual_room_slots')->where('room_id', $room->id)->where('enabled', true)->exists()) {
                    throw new DomainException('Create at least one enabled display slot before publishing this room.');
                }
            }
            DB::table('exhibition_virtual_rooms')->where('id', $room->id)->update([
                'status' => $target->value, 'published_at' => $target === RoomState::Published ? ($room->published_at ?: now()) : $room->published_at,
                'updated_by' => $actor, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData('virtual_room.'.$target->value, 'virtual_room', $publicId, previousValues: ['status' => $current->value], newValues: ['status' => $target->value], reason: $reason, actorUserId: $actor));

            if ($target === RoomState::Published) {
                $approved = DB::table('exhibition_virtual_placement_requests')->where('room_id', $room->id)->where('status', PlacementState::Approved->value)->lockForUpdate()->get();
                foreach ($approved as $request) {
                    if ($this->artworkStillEligible($request->artwork_id)) {
                        $this->transitionLocked($request->id, PlacementState::Published, $actor, 'Room published.');
                    }
                }
            }
            return DB::table('exhibition_virtual_rooms')->where('id', $room->id)->first();
        });
    }

    public function liveRoom(string $publicOrSlug, bool $lock = false): object
    {
        $query = DB::table('exhibition_virtual_rooms')->where(fn ($query) => $query->where('public_id', $publicOrSlug)->orWhere('slug', $publicOrSlug));
        if ($lock) {
            $query->lockForUpdate();
        }
        $room = $query->first() ?? throw new DomainException('Virtual exhibition room not found.');
        if (! $this->roomIsVisible($room)) {
            throw new DomainException('This virtual exhibition room is not currently available.');
        }

        return $room;
    }

    public function roomIsVisible(object $room): bool
    {
        return $room->status === RoomState::Published->value;
    }

    public function eligibleArtwork(int $artistIdentityId, string $publicId, bool $required = false): ?object
    {
        $row = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as artist', 'artist.id', '=', 'artwork.artist_identity_id')
            ->join('exhibition_artwork_publications as publication', function ($join): void {
                $join->on('publication.artwork_id', '=', 'artwork.id')->where('publication.state', 'published');
            })
            ->join('exhibition_artwork_pricing as pricing', function ($join): void {
                $join->on('pricing.artwork_id', '=', 'artwork.id')->where('pricing.state', 'approved');
            })
            ->where('artwork.public_id', $publicId)->where('artwork.artist_identity_id', $artistIdentityId)
            ->where('artwork.state', 'published')->where('artist.state', 'active')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('exhibition_artwork_media as media')->whereColumn('media.artwork_id', 'artwork.id')->where('media.state', 'ready')->where('media.is_primary', true))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('exhibition_artwork_authenticity as authenticity')->whereColumn('authenticity.artwork_id', 'artwork.id')->where('authenticity.state', 'documentation_accepted'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('exhibition_artwork_inventory_items as inventory')->whereColumn('inventory.artwork_id', 'artwork.id')->where('inventory.state', 'available'))
            ->select('artwork.*', 'publication.store_product_public_id', 'publication.store_variant_public_id', 'pricing.approved_amount_snapshot', 'pricing.currency as approved_currency')
            ->first();
        if ($row !== null && ! Ve03ArtworkDimensions::isValid($row)) {
            if ($required) {
                throw new DomainException(Ve03ArtworkDimensions::MISSING_MESSAGE);
            }

            return null;
        }
        if ($required && $row === null) {
            throw new DomainException('The selected artwork is not eligible for virtual exhibition placement.');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function roomPayload(array $data): array
    {
        return [
            'title' => trim((string) $data['title']), 'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null, 'initial_yaw' => $data['initial_yaw'] ?? 0,
            'initial_pitch' => $data['initial_pitch'] ?? 0, 'initial_fov' => $data['initial_fov'] ?? 75,
            'minimum_fov' => $data['minimum_fov'] ?? 35, 'maximum_fov' => $data['maximum_fov'] ?? 110,
            'auto_rotate_enabled' => (bool) ($data['auto_rotate_enabled'] ?? false), 'auto_rotate_speed' => $data['auto_rotate_speed'] ?? 2,
            'auto_rotate_delay' => $data['auto_rotate_delay'] ?? 4000,
        ];
    }

    /** @return array<string,mixed> */
    private function slotPayload(array $data): array
    {
        return [
            'code' => trim((string) $data['code']), 'name' => trim((string) $data['name']), 'anchor_yaw' => $data['anchor_yaw'],
            'anchor_pitch' => $data['anchor_pitch'], 'roll' => $data['roll'] ?? 0, 'angular_width' => $data['angular_width'],
            'maximum_angular_height' => $data['maximum_angular_height'] ?? null, 'display_order' => $data['display_order'] ?? 0,
            'enabled' => (bool) ($data['enabled'] ?? false), 'admin_notes' => $data['admin_notes'] ?? null,
        ];
    }

    private function assertCoordinates(array $data): void
    {
        $yaw = (float) ($data['anchor_yaw'] ?? 999);
        $pitch = (float) ($data['anchor_pitch'] ?? 999);
        $roll = (float) ($data['roll'] ?? 0);
        $width = (float) ($data['angular_width'] ?? 0);
        $height = isset($data['maximum_angular_height']) ? (float) $data['maximum_angular_height'] : null;
        if ($yaw < -180 || $yaw > 180 || $pitch < -89 || $pitch > 89 || $roll < -180 || $roll > 180 || $width < 2 || $width > 120 || ($height !== null && ($height < 2 || $height > 120))) {
            throw new DomainException('Slot coordinates are outside the safe spherical bounds.');
        }
    }

    /** @return array<string,float|null> */
    private function coordinateSnapshot(array $data): array
    {
        return ['yaw' => (float) $data['anchor_yaw'], 'pitch' => (float) $data['anchor_pitch'], 'roll' => (float) ($data['roll'] ?? 0), 'width' => (float) $data['angular_width'], 'maximum_height' => isset($data['maximum_angular_height']) ? (float) $data['maximum_angular_height'] : null];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'virtual-room';
        $slug = $base;
        $suffix = 2;
        while (DB::table('exhibition_virtual_rooms')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function transitionLocked(int $requestId, PlacementState $target, ?int $actor, string $reason, array $updates = []): void
    {
        $request = DB::table('exhibition_virtual_placement_requests')->where('id', $requestId)->lockForUpdate()->first() ?? throw new DomainException('Placement request not found.');
        $current = PlacementState::from($request->status);
        $current->assertTransitionTo($target);
        $payload = ['status' => $target->value, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()] + $updates;
        if ($target === PlacementState::Submitted) $payload['submitted_at'] = now();
        if (in_array($target, [PlacementState::ChangesRequested, PlacementState::Approved, PlacementState::Rejected], true)) {
            $payload['reviewed_at'] = now(); $payload['reviewed_by'] = $actor;
        }
        if ($target === PlacementState::Approved) $payload['approved_by'] = $actor;
        if ($target === PlacementState::Published) $payload['published_at'] = now();
        if ($target === PlacementState::Removed) { $payload['removed_at'] = now(); $payload['removal_reason'] = $reason; }
        DB::table('exhibition_virtual_placement_requests')->where('id', $requestId)->update($payload);
        $this->recordTransition($requestId, $request->public_id, $current, $target, $actor, $reason);
    }

    private function recordTransition(int $requestId, string $publicId, ?PlacementState $from, PlacementState $to, ?int $actor, string $reason, array $context = []): void
    {
        DB::table('exhibition_virtual_placement_transitions')->insert([
            'public_id' => (string) Str::uuid(), 'placement_request_id' => $requestId, 'actor_user_id' => $actor,
            'from_status' => $from?->value, 'to_status' => $to->value, 'reason' => $reason,
            'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'occurred_at' => now(), 'created_at' => now(),
        ]);
        $this->audit->record(new AuditEntryData('virtual_placement.'.$to->value, 'virtual_placement', $publicId, previousValues: ['status' => $from?->value], newValues: ['status' => $to->value], reason: $reason, context: $context, actorUserId: $actor));
        Event::dispatch(new VirtualPlacementStateChanged($publicId, $from?->value, $to->value, $actor));
    }

    private function releaseSlot(int $requestId): void
    {
        DB::table('exhibition_virtual_room_slots')->where('active_request_id', $requestId)->update(['active_request_id' => null, 'updated_at' => now()]);
    }

    private function moveRequest(object $request, string $newSlotPublicId, int $actor): object
    {
        $slots = DB::table('exhibition_virtual_room_slots')->whereIn('id', [$request->slot_id])->orWhere('public_id', $newSlotPublicId)->orderBy('id')->lockForUpdate()->get();
        $old = $slots->firstWhere('id', $request->slot_id);
        $new = $slots->firstWhere('public_id', $newSlotPublicId);
        if (! $new || (int) $new->room_id !== (int) $request->room_id || ! $new->enabled || ($new->active_request_id !== null && (int) $new->active_request_id !== (int) $request->id)) {
            throw new DomainException('The requested destination slot is unavailable.');
        }
        if ($old && (int) $old->id !== (int) $new->id) {
            DB::table('exhibition_virtual_room_slots')->where('id', $old->id)->where('active_request_id', $request->id)->update(['active_request_id' => null, 'updated_at' => now()]);
            DB::table('exhibition_virtual_room_slots')->where('id', $new->id)->update(['active_request_id' => $request->id, 'updated_at' => now()]);
            DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->update([
                'slot_id' => $new->id, 'placement_yaw' => $new->anchor_yaw, 'placement_pitch' => $new->anchor_pitch,
                'placement_roll' => $new->roll, 'placement_width' => $new->angular_width, 'maximum_height' => $new->maximum_angular_height,
                'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData('virtual_placement.moved', 'virtual_placement', $request->public_id, previousValues: ['slot_id' => $old->public_id], newValues: ['slot_id' => $new->public_id], reason: 'Reviewer moved placement.', actorUserId: $actor));
        }

        return DB::table('exhibition_virtual_placement_requests')->where('id', $request->id)->first();
    }

    private function artworkStillEligible(int $artworkId): bool
    {
        $artwork = DB::table('exhibition_artworks')->where('id', $artworkId)->first();
        if (! $artwork) return false;
        $eligible = $this->eligibleArtwork((int) $artwork->artist_identity_id, (string) $artwork->public_id);

        return $eligible !== null && $this->commerce->status((string) $artwork->public_id)->purchasable;
    }

    /** @return array<string,mixed> */
    private function artworkSnapshot(object $artwork): array
    {
        $media = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('state', 'ready')->orderByDesc('is_primary')->orderBy('sort_order')->first();
        $artist = DB::table('exhibition_artist_identities as identity')->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')->where('identity.id', $artwork->artist_identity_id)->orderByDesc('application.id')->first();

        return [
            'title' => $artwork->title, 'artist' => $artist?->artistic_name ?: $artist?->legal_name, 'media_public_id' => $media?->public_id,
            'media_width' => $media?->width, 'media_height' => $media?->height, 'short_description' => $artwork->short_description,
            'width' => $artwork->width, 'height' => $artwork->height, 'dimension_unit' => $artwork->dimension_unit,
            'material' => $artwork->material, 'technique' => $artwork->technique, 'completion_year' => $artwork->completion_year,
            'is_framed' => (bool) $artwork->is_framed, 'store_product_public_id' => $artwork->store_product_public_id,
            'store_variant_public_id' => $artwork->store_variant_public_id,
        ];
    }

    /** @return list<int> */
    private function reviewerRecipients(): array
    {
        try {
            if (! Schema::hasTable('permissions') || ! Schema::hasTable('model_has_permissions') || ! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('model_has_roles')) {
                return [];
            }

            $permissionIds = DB::table('permissions')
                ->where('name', 'exhibitions.virtual.review')
                ->where('guard_name', 'web')
                ->pluck('id');
            if ($permissionIds->isEmpty()) {
                return [];
            }

            $direct = DB::table('model_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->pluck('model_id');
            $throughRoles = DB::table('model_has_roles')
                ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                ->whereIn('role_has_permissions.permission_id', $permissionIds)
                ->pluck('model_has_roles.model_id');

            return $direct->merge($throughRoles)->map(fn ($id) => (int) $id)->unique()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
