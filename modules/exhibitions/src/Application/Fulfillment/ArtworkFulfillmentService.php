<?php

namespace Modules\Exhibition\Application\Fulfillment;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;
use Modules\Exhibition\Application\Security\PrivateEvidenceUpload;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final readonly class ArtworkFulfillmentService
{
    private const PLATFORM_TRANSITIONS = [
        'sale_confirmed' => ['collected', 'damaged'],
        // Backward-compatible entry states from sales created before platform-managed preparation.
        'artist_notification_sent' => ['collected', 'damaged'],
        'preparing' => ['collected', 'damaged'],
        'ready_for_pickup' => ['collected', 'damaged'], 'collected' => ['quality_inspection', 'damaged', 'lost'],
        'quality_inspection' => ['ready_for_shipment', 'damaged'], 'ready_for_shipment' => ['shipped', 'damaged', 'lost'],
        'shipped' => ['delivered', 'delayed', 'damaged', 'lost'], 'delayed' => ['shipped', 'delivered', 'damaged', 'lost'],
        'delivered' => ['return_requested'], 'return_requested' => ['returned'],
    ];

    public function __construct(
        private ArtworkInventoryService $inventory,
        private AuditRecorder $audit,
        private ArtistSettlementService $settlements,
        private ExhibitionSettingsService $settings,
    ) {}

    public function receiveConfirmedSale(string $storeOrderReference, string $inventoryItemPublicId, string $idempotencyKey, ?int $preparationDays = null): object
    {
        if (trim($storeOrderReference) === '' || trim($idempotencyKey) === '') {
            throw new DomainException('Confirmed Store order and idempotency references are required.');
        }
        if ($existing = DB::table('exhibition_fulfillment_events')->where('idempotency_key', $idempotencyKey)->first()) {
            return DB::table('exhibition_fulfillments')->where('id', $existing->fulfillment_id)->first();
        }
        $item = DB::table('exhibition_artwork_inventory_items')->where('public_id', $inventoryItemPublicId)->first() ?? abort(404);
        $art = DB::table('exhibition_artworks as artwork')->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')->where('artwork.id', $item->artwork_id)->select('artwork.*', 'identity.core_user_id as artist_user_id')->first();
        $days = $preparationDays ?? max(1, (int) ($art->preparation_days ?: config('exhibitions.fulfillment.default_preparation_days', 7)));

        return DB::transaction(function () use ($storeOrderReference, $inventoryItemPublicId, $item, $art, $idempotencyKey, $days): object {
            $this->inventory->confirm($inventoryItemPublicId, $storeOrderReference, 'fulfillment:reserve:'.$idempotencyKey);
            $this->inventory->sell($inventoryItemPublicId, $storeOrderReference, 'fulfillment:sale:'.$idempotencyKey);
            DB::table('exhibition_fulfillments')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'artwork_id' => $art->id, 'inventory_item_id' => $item->id, 'artist_user_id' => $art->artist_user_id, 'store_order_reference' => $storeOrderReference, 'state' => 'sale_confirmed', 'preparation_deadline' => now()->addDays($days), 'packaging_instructions' => (string) ($art->shipping_notes ?: config('exhibitions.fulfillment.default_packaging_instructions')), 'artist_notified_at' => now(), 'custody_holder' => 'artist', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $record = DB::table('exhibition_fulfillments')->where('store_order_reference', $storeOrderReference)->first();
            DB::table('exhibition_fulfillment_events')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'fulfillment_id' => $record->id, 'actor_role' => 'store', 'action' => 'confirmed_sale_received', 'from_state' => null, 'to_state' => 'sale_confirmed', 'idempotency_key' => $idempotencyKey, 'notes' => 'Confirmed Store sale received; platform fulfillment opened without customer data.', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('exhibition_fulfillments')->where('id', $record->id)->first();
        });
    }

    public function platformTransition(int $actor, string $fulfillmentPublicId, string $target, string $notes, string $idempotencyKey, array $metadata = []): object
    {
        if ($event = DB::table('exhibition_fulfillment_events')->where('idempotency_key', $idempotencyKey)->first()) {
            return DB::table('exhibition_fulfillments')->where('id', $event->fulfillment_id)->first();
        }
        $record = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        $this->assertOperationallyConfirmed($record);
        if (! in_array($target, self::PLATFORM_TRANSITIONS[$record->state] ?? [], true)) {
            throw new DomainException('Invalid fulfillment transition from '.$record->state.' to '.$target.'.');
        }
        $allowedMetadata = array_intersect_key($metadata, array_flip(['pickup_scheduled_at', 'condition_summary', 'certificate_included', 'carrier_reference', 'custody_holder']));
        $result = $this->transition($fulfillmentPublicId, [$record->state], $target, 'platform_transition', $this->notes($notes), $idempotencyKey, $actor, 'platform', $allowedMetadata);
        $updates = [];
        if ($target === 'collected') {
            $updates = ['collected_at' => now(), 'custody_holder' => $allowedMetadata['custody_holder'] ?? 'platform'];
        }
        if ($target === 'quality_inspection') {
            $updates = ['inspected_at' => now(), 'condition_summary' => $allowedMetadata['condition_summary'] ?? null, 'certificate_included' => (bool) ($allowedMetadata['certificate_included'] ?? false)];
        }
        if ($target === 'shipped') {
            $updates = ['shipped_at' => now(), 'carrier_reference' => $allowedMetadata['carrier_reference'] ?? $record->carrier_reference, 'custody_holder' => $allowedMetadata['custody_holder'] ?? 'carrier'];
        }
        if ($target === 'delivered') {
            $updates = ['delivered_at' => now(), 'custody_holder' => 'customer'];
        }
        if ($updates) {
            DB::table('exhibition_fulfillments')->where('id', $result->id)->update($updates + ['updated_at' => now()]);
        }
        if ($target === 'delivered' && Schema::hasTable('exhibition_settlements')) {
            $settlement = DB::table('exhibition_settlements')->where('fulfillment_id', $result->id)->first();
            if ($settlement?->state === 'awaiting_delivery') {
                $days = $this->settings->value('exhibitions.settlement.eligibility_trigger', 'delivered_return_window_completed') === 'delivered'
                    ? 0
                    : (int) $this->settings->value('exhibitions.settlement.return_window_days', 14);
                $this->settlements->beginReturnWindow($settlement->public_id, $days, 'fulfillment:delivery-window:'.$idempotencyKey);
            }
        }
        if ($target === 'returned') {
            $this->inventory->markReturned($this->itemPublicId($result), 'return:'.$result->store_order_reference, $notes, 'fulfillment:return:'.$idempotencyKey);
        }
        $this->audit->record(new AuditEntryData(action: 'exhibitions.fulfillment.transitioned', targetType: 'fulfillment', targetId: $fulfillmentPublicId, previousValues: ['state' => $record->state], newValues: ['state' => $target], reason: $notes, actorUserId: $actor));

        return DB::table('exhibition_fulfillments')->where('id', $result->id)->first();
    }

    public function schedulePickup(int $actor, string $fulfillmentPublicId, string $when, string $notes, string $idempotencyKey): object
    {
        $fulfillment = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        $this->assertOperationallyConfirmed($fulfillment);
        $timestamp = now()->parse($when);
        if ($timestamp->isPast()) {
            throw new DomainException('Pickup must be scheduled in the future.');
        }
        $record = $this->eventWithoutStateChange($actor, $fulfillmentPublicId, 'pickup_scheduled', $this->notes($notes), $idempotencyKey, ['pickup_scheduled_at' => $timestamp->toIso8601String()]);
        DB::table('exhibition_fulfillments')->where('id', $record->id)->update(['pickup_scheduled_at' => $timestamp, 'updated_at' => now()]);

        return DB::table('exhibition_fulfillments')->where('id', $record->id)->first();
    }

    public function cancel(int $actor, string $fulfillmentPublicId, string $notes, string $idempotencyKey): object
    {
        $record = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        if (! in_array($record->state, ['store_reserved', 'sale_confirmed', 'artist_notification_sent', 'preparing', 'ready_for_pickup'], true)) {
            throw new DomainException('Cancellation is no longer an allowed fulfillment action.');
        }
        $result = $this->transition($fulfillmentPublicId, [$record->state], 'cancelled', 'cancelled', $this->notes($notes), $idempotencyKey, $actor, 'platform');
        $this->inventory->markReturned($this->itemPublicId($result), $record->store_order_reference, $notes, 'fulfillment:cancel:'.$idempotencyKey);

        return $result;
    }

    public function addEvidence(int $actor, string $fulfillmentPublicId, string $role, UploadedFile $file): object
    {
        if (! in_array($role, ['collection_condition', 'inspection_condition', 'damage', 'return'], true)) {
            throw new DomainException('Unsupported fulfillment evidence role.');
        }
        $upload = PrivateEvidenceUpload::inspect($file);
        $record = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        $this->assertOperationallyConfirmed($record);
        $public = (string) Str::uuid();
        $path = 'exhibitions/fulfillment/'.$record->public_id.'/'.$public.'.'.$upload['extension'];
        if (! Storage::disk('local')->put($path, $upload['bytes'])) {
            throw new DomainException('Evidence could not be stored securely.');
        }
        DB::table('exhibition_fulfillment_evidence')->insert(['public_id' => $public, 'fulfillment_id' => $record->id, 'uploaded_by' => $actor, 'role' => $role, 'disk' => 'local', 'path' => $path, 'original_name' => $upload['original_name'], 'mime_type' => $upload['mime_type'], 'size_bytes' => $upload['size_bytes'], 'checksum_sha256' => $upload['checksum_sha256'], 'created_at' => now()]);

        return DB::table('exhibition_fulfillment_evidence')->where('public_id', $public)->first();
    }

    private function transition(string $id, array $allowed, string $target, string $action, string $notes, string $key, ?int $actor, string $role, array $metadata = [], ?int $owner = null): object
    {
        return DB::transaction(function () use ($id, $allowed, $target, $action, $notes, $key, $actor, $role, $metadata, $owner): object {
            $event = DB::table('exhibition_fulfillment_events')->where('idempotency_key', $key)->first();
            if ($event) {
                return DB::table('exhibition_fulfillments')->where('id', $event->fulfillment_id)->first();
            }$q = DB::table('exhibition_fulfillments')->where('public_id', $id);
            if ($owner !== null) {
                $q->where('artist_user_id', $owner);
            }$record = $q->lockForUpdate()->first() ?? abort(404);
            if (! in_array($record->state, $allowed, true)) {
                throw new DomainException('Invalid or stale fulfillment action.');
            }DB::table('exhibition_fulfillments')->where('id', $record->id)->update(['state' => $target, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]);
            DB::table('exhibition_fulfillment_events')->insert(['public_id' => (string) Str::uuid(), 'fulfillment_id' => $record->id, 'actor_user_id' => $actor, 'actor_role' => $role, 'action' => $action, 'from_state' => $record->state, 'to_state' => $target, 'idempotency_key' => $key, 'notes' => $notes, 'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('exhibition_fulfillments')->where('id', $record->id)->first();
        }, 3);
    }

    private function eventWithoutStateChange(int $actor, string $id, string $action, string $notes, string $key, array $metadata): object
    {
        $record = DB::table('exhibition_fulfillments')->where('public_id', $id)->first() ?? abort(404);

        return $this->transition($id, [$record->state], $record->state, $action, $notes, $key, $actor, 'platform', $metadata);
    }

    private function itemPublicId(object $record): string
    {
        return (string) DB::table('exhibition_artwork_inventory_items')->where('id', $record->inventory_item_id)->value('public_id');
    }

    private function notes(string $notes): string
    {
        if (trim($notes) === '') {
            throw new DomainException('Operational notes are required.');
        }

        return trim($notes);
    }

    private function assertOperationallyConfirmed(object $record): void
    {
        $legacyUncommitted = in_array($record->state, ['artist_notification_sent', 'preparing', 'ready_for_pickup'], true)
            && Schema::hasTable('exhibition_settlements')
            && ! DB::table('exhibition_settlements')->where('fulfillment_id', $record->id)->exists();

        if ($record->state === 'store_reserved' || $legacyUncommitted) {
            throw new DomainException('A confirmed sale is required before platform fulfillment can begin.');
        }
    }
}
