<?php

namespace Modules\Exhibition\Application\Inventory;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationGateway;

final readonly class ArtworkInventoryService
{
    private const STATES = ['available', 'temporarily_held', 'reserved', 'sold', 'unavailable', 'archived'];

    public function __construct(private ArtworkPublicationGateway $store, private AuditRecorder $audit) {}

    public function initialize(int $actor, string $artworkPublicId, string $reason): array
    {
        $reason = $this->reason($reason);
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkPublicId)->first() ?? abort(404);
        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $artwork->id)->where('state', 'published')->first();
        if (! $publication) {
            throw new DomainException('Only a Store-published artwork can initialize inventory.');
        }
        $items = $this->prepareInventory($artwork, $actor, $reason);

        $this->sync($artwork, 'Inventory initialized.', 'inventory:init-sync:'.$artwork->public_id);
        $this->audit->record(new AuditEntryData(action: 'exhibitions.inventory.initialized', targetType: 'artwork', targetId: $artworkPublicId, newValues: ['units' => count($items)], reason: $reason, actorUserId: $actor));

        return $items;
    }

    /** @return list<object> */
    public function prepareForPublication(?int $actor, string $artworkPublicId, string $reason): array
    {
        $reason = $this->reason($reason);
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkPublicId)->first() ?? abort(404);
        if (! in_array($artwork->state, ['approved', 'published'], true)) {
            throw new DomainException('Only an approved artwork can prepare publication inventory.');
        }

        return $this->prepareInventory($artwork, $actor, $reason);
    }

    /** @return list<object> */
    private function prepareInventory(object $artwork, ?int $actor, string $reason): array
    {
        $total = $this->isLimitedEdition($artwork) ? (int) $artwork->edition_total : 1;
        if ($total < 1) {
            throw new DomainException('Approved edition size must be at least one.');
        }

        return DB::transaction(function () use ($artwork, $total, $actor, $reason): array {
            $expectedKeys = [];
            for ($number = 1; $number <= $total; $number++) {
                $edition = $this->isLimitedEdition($artwork) ? $number : null;
                $key = $edition === null ? 'original' : 'edition-'.$edition;
                $expectedKeys[] = $key;
                DB::table('exhibition_artwork_inventory_items')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(), 'artwork_id' => $artwork->id, 'inventory_key' => $key,
                    'edition_number' => $edition, 'certificate_reference' => $this->certificate($artwork->public_id, $edition),
                    'state' => 'available', 'store_sync_state' => 'pending', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $item = DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artwork->id)->where('inventory_key', $key)->first();
                DB::table('exhibition_artwork_inventory_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(), 'inventory_item_id' => $item->id, 'actor_user_id' => $actor,
                    'action' => 'initialize', 'from_state' => null, 'to_state' => $item->state,
                    'idempotency_key' => 'inventory:init:'.$artwork->public_id.':'.$key,
                    'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $items = DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artwork->id)->lockForUpdate()->orderBy('inventory_key')->get();
            $actualKeys = $items->pluck('inventory_key')->all();
            sort($expectedKeys);
            sort($actualKeys);
            if (count($items) !== $total || $actualKeys !== $expectedKeys) {
                throw new DomainException('Existing inventory does not match the approved edition size.');
            }

            return $items->all();
        });
    }

    /**
     * Reserve the exact number of locally owned units selected by a durable
     * Store order event. Row locks make the local invariant independent from
     * Store's own stock lock and therefore prevent a second consumer from
     * assigning the same original or edition unit.
     *
     * @return list<object>
     */
    public function reserveFromStore(
        string $artworkPublicId,
        string $orderPublicId,
        string $orderItemPublicId,
        int $quantity,
        string $idempotencyKey,
    ): array {
        $artworkPublicId = trim($artworkPublicId);
        $orderPublicId = trim($orderPublicId);
        $orderItemPublicId = trim($orderItemPublicId);
        $idempotencyKey = trim($idempotencyKey);
        $maximum = max(1, (int) config('exhibitions.inventory.maximum_store_event_quantity', 1000));

        if ($artworkPublicId === '' || $orderPublicId === '' || $orderItemPublicId === '' || $idempotencyKey === '') {
            throw new DomainException('Store lifecycle artwork, order, item, and idempotency references are required.');
        }

        if ($quantity < 1 || $quantity > $maximum) {
            throw new DomainException('Store lifecycle quantity is outside the approved processing limit.');
        }

        $artwork = DB::table('exhibition_artworks')
            ->where('public_id', $artworkPublicId)
            ->where('state', 'published')
            ->first() ?? throw new DomainException('Store cannot reserve an unpublished Exhibitions artwork.');

        $items = DB::transaction(function () use (
            $artwork,
            $orderPublicId,
            $orderItemPublicId,
            $quantity,
            $idempotencyKey,
        ): array {
            $existing = [];

            for ($unit = 1; $unit <= $quantity; $unit++) {
                $event = DB::table('exhibition_artwork_inventory_events')
                    ->where('idempotency_key', $idempotencyKey.':reserve:'.$unit)
                    ->first();

                if ($event !== null) {
                    $existing[] = DB::table('exhibition_artwork_inventory_items')
                        ->where('id', $event->inventory_item_id)
                        ->first();
                }
            }

            if (count($existing) === $quantity) {
                return $existing;
            }

            if ($existing !== []) {
                throw new DomainException('Store reservation replay is incomplete and requires reconciliation.');
            }

            $available = DB::table('exhibition_artwork_inventory_items')
                ->where('artwork_id', $artwork->id)
                ->where('state', 'available')
                ->orderByRaw('edition_number IS NULL DESC')
                ->orderBy('edition_number')
                ->orderBy('id')
                ->limit($quantity)
                ->lockForUpdate()
                ->get();

            if ($available->count() !== $quantity) {
                throw new DomainException('Inventory conflict: Store requested more artwork units than remain available.');
            }

            $reserved = [];

            foreach ($available->values() as $index => $item) {
                $unit = $index + 1;
                $reserved[] = $this->transition(
                    $item->public_id,
                    'store_reserve',
                    ['available'],
                    'reserved',
                    $idempotencyKey.':reserve:'.$unit,
                    'Durable Store order reservation received.',
                    $orderPublicId.':'.$orderItemPublicId,
                );
            }

            return $reserved;
        }, 3);

        return $items;
    }

    public function sellFromStore(string $itemPublicId, string $saleReference, string $idempotencyKey): object
    {
        if (trim($saleReference) === '' || trim($idempotencyKey) === '') {
            throw new DomainException('Store sale and idempotency references are required.');
        }

        $result = $this->transition(
            $itemPublicId,
            'store_commit',
            ['reserved'],
            'sold',
            $idempotencyKey,
            'Durable Store payment or delivery commitment received.',
            trim($saleReference),
        );
        DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->update([
            'sale_reference' => trim($saleReference),
            'updated_at' => now(),
        ]);

        return DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->first();
    }

    public function releaseFromStore(string $itemPublicId, string $orderReference, string $idempotencyKey): object
    {
        if (trim($orderReference) === '' || trim($idempotencyKey) === '') {
            throw new DomainException('Store release and idempotency references are required.');
        }

        $result = $this->transition(
            $itemPublicId,
            'store_release',
            ['temporarily_held', 'reserved', 'sold'],
            'available',
            $idempotencyKey,
            'Durable Store cancellation released the artwork before collection.',
            trim($orderReference),
        );

        DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->update([
            'sale_reference' => null,
            'updated_at' => now(),
        ]);

        return DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->first();
    }

    public function returnFromStore(string $itemPublicId, string $orderReference, string $idempotencyKey): object
    {
        if (trim($orderReference) === '' || trim($idempotencyKey) === '') {
            throw new DomainException('Store return and idempotency references are required.');
        }

        return $this->transition(
            $itemPublicId,
            'store_return',
            ['sold'],
            'unavailable',
            $idempotencyKey,
            'Store confirmed the return; the unit remains unavailable pending inspection.',
            trim($orderReference),
        );
    }

    public function hold(string $itemPublicId, string $holdToken, int $minutes, string $idempotencyKey): object
    {
        if (trim($holdToken) === '' || trim($idempotencyKey) === '') {
            throw new DomainException('Hold token and idempotency key are required.');
        }
        if ($minutes < 1 || $minutes > (int) config('exhibitions.inventory.maximum_hold_minutes', 30)) {
            throw new DomainException('Hold duration is outside the approved limit.');
        }
        $result = $this->transition($itemPublicId, 'hold', ['available'], 'temporarily_held', $idempotencyKey, 'Approved purchase flow hold.', null, $holdToken, now()->addMinutes($minutes));
        $this->syncForItem($result, 'Availability changed by temporary hold.', 'inventory:sync:'.$idempotencyKey);

        return $result;
    }

    public function confirm(string $itemPublicId, string $orderReference, string $idempotencyKey): object
    {
        if (trim($orderReference) === '') {
            throw new DomainException('Store order reference is required.');
        }
        $result = $this->transition($itemPublicId, 'confirm', ['temporarily_held'], 'reserved', $idempotencyKey, 'Store confirmed the order.', $orderReference);
        $this->syncForItem($result, 'Availability changed by order confirmation.', 'inventory:sync:'.$idempotencyKey);

        return $result;
    }

    public function sell(string $itemPublicId, string $saleReference, string $idempotencyKey): object
    {
        if (trim($saleReference) === '') {
            throw new DomainException('Sale reference is required.');
        }
        $result = $this->transition($itemPublicId, 'sell', ['reserved'], 'sold', $idempotencyKey, 'Sale completed.', $saleReference);
        DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->update(['sale_reference' => $saleReference, 'updated_at' => now()]);
        $this->syncForItem($result, 'Availability changed by completed sale.', 'inventory:sync:'.$idempotencyKey);

        return DB::table('exhibition_artwork_inventory_items')->where('id', $result->id)->first();
    }

    public function cancel(string $itemPublicId, string $reference, string $reason, string $idempotencyKey): object
    {
        $result = $this->transition($itemPublicId, 'cancel', ['temporarily_held', 'reserved'], 'available', $idempotencyKey, $this->reason($reason), $reference ?: null);
        $this->syncForItem($result, 'Availability released after cancellation.', 'inventory:sync:'.$idempotencyKey);

        return $result;
    }

    public function markReturned(string $itemPublicId, string $reference, string $reason, string $idempotencyKey): object
    {
        $result = $this->transition($itemPublicId, 'return', ['sold'], 'unavailable', $idempotencyKey, $this->reason($reason), $reference ?: null);
        $this->syncForItem($result, 'Returned artwork held unavailable for inspection.', 'inventory:sync:'.$idempotencyKey);

        return $result;
    }

    public function releaseExpired(int $limit = 100): int
    {
        $ids = DB::table('exhibition_artwork_inventory_items')->where('state', 'temporarily_held')->where('hold_expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('public_id');
        $released = 0;
        foreach ($ids as $id) {
            $item = DB::table('exhibition_artwork_inventory_items')->where('public_id', $id)->first();
            $key = 'inventory:expire:'.$item->public_id.':'.strtotime((string) $item->hold_expires_at);
            $this->transition($item->public_id, 'expire', ['temporarily_held'], 'available', $key, 'Approved hold expired.');
            $this->syncForItem($item, 'Availability released after hold expiry.', 'inventory:sync:'.$key);
            $released++;
        }

        return $released;
    }

    public function override(int $actor, string $itemPublicId, string $targetState, string $reason, string $idempotencyKey): object
    {
        if (! in_array($targetState, self::STATES, true)) {
            throw new DomainException('Unsupported inventory state.');
        }
        $item = DB::table('exhibition_artwork_inventory_items')->where('public_id', $itemPublicId)->first() ?? abort(404);
        if ($item->state === 'sold' && $targetState === 'available') {
            throw new DomainException('A sold unit cannot be made available by override; use the return workflow.');
        }
        $result = $this->transition($itemPublicId, 'override', self::STATES, $targetState, $idempotencyKey, $this->reason($reason), null, null, null, $actor);
        $this->syncForItem($result, 'Availability changed by authorized override.', 'inventory:sync:'.$idempotencyKey);
        $this->audit->record(new AuditEntryData(action: 'exhibitions.inventory.overridden', targetType: 'inventory_item', targetId: $itemPublicId, previousValues: ['state' => $item->state], newValues: ['state' => $targetState], reason: $reason, actorUserId: $actor));

        return $result;
    }

    private function transition(string $publicId, string $action, array $allowed, string $target, string $key, string $reason, ?string $reference = null, ?string $holdToken = null, mixed $expiresAt = null, ?int $actor = null): object
    {
        return DB::transaction(function () use ($publicId, $action, $allowed, $target, $key, $reason, $reference, $holdToken, $expiresAt, $actor): object {
            $existing = DB::table('exhibition_artwork_inventory_events')->where('idempotency_key', $key)->first();
            if ($existing) {
                return DB::table('exhibition_artwork_inventory_items')->where('id', $existing->inventory_item_id)->first();
            }
            $item = DB::table('exhibition_artwork_inventory_items')->where('public_id', $publicId)->lockForUpdate()->first() ?? abort(404);
            if ($item->state === 'temporarily_held' && $item->hold_expires_at && now()->greaterThanOrEqualTo($item->hold_expires_at) && $action === 'hold') {
                DB::table('exhibition_artwork_inventory_items')->where('id', $item->id)->update(['state' => 'available', 'hold_token' => null, 'hold_expires_at' => null, 'updated_at' => now()]);
                $item->state = 'available';
            }
            if (! in_array($item->state, $allowed, true)) {
                throw new DomainException('Inventory conflict: this unit is no longer available for that action.');
            }
            DB::table('exhibition_artwork_inventory_items')->where('id', $item->id)->update([
                'state' => $target, 'hold_token' => $target === 'temporarily_held' ? $holdToken : null,
                'hold_expires_at' => $target === 'temporarily_held' ? $expiresAt : null,
                'reservation_reference' => $target === 'reserved' ? $reference : ($target === 'available' ? null : $item->reservation_reference),
                'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
            ]);
            DB::table('exhibition_artwork_inventory_events')->insert([
                'public_id' => (string) Str::uuid(), 'inventory_item_id' => $item->id, 'actor_user_id' => $actor,
                'action' => $action, 'from_state' => $item->state, 'to_state' => $target, 'idempotency_key' => $key,
                'external_reference' => $reference, 'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('exhibition_artwork_inventory_items')->where('id', $item->id)->first();
        }, 3);
    }

    private function syncForItem(object $item, string $reason, string $key): void
    {
        $artwork = DB::table('exhibition_artworks')->where('id', $item->artwork_id)->first();
        $this->sync($artwork, $reason, $key);
    }

    private function sync(object $artwork, string $reason, string $key): void
    {
        $quantity = DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artwork->id)->where('state', 'available')->count();
        $result = $this->store->syncAvailability($artwork->public_id, $quantity, $reason, $key);
        DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artwork->id)->update([
            'store_sync_state' => $result->succeeded ? 'synced' : 'failed',
            'store_sync_failure' => $result->succeeded ? null : ($result->failureCode.': '.$result->message),
            'last_store_sync_at' => $result->succeeded ? now() : null, 'updated_at' => now(),
        ]);
        if (! $result->succeeded) {
            throw new DomainException('Inventory changed safely, but Store availability synchronization failed: '.($result->message ?: $result->failureCode));
        }
    }

    private function certificate(string $artworkPublicId, ?int $edition): string
    {
        return 'EXH-'.strtoupper(substr(str_replace('-', '', $artworkPublicId), 0, 12)).'-'.($edition === null ? 'ORIGINAL' : str_pad((string) $edition, 4, '0', STR_PAD_LEFT));
    }

    private function isLimitedEdition(object $artwork): bool
    {
        return in_array($artwork->edition_type, ['limited', 'limited_edition'], true);
    }

    private function reason(string $reason): string
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required.');
        }

        return trim($reason);
    }
}
