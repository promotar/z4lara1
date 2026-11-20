<?php

namespace Modules\Exhibition\Application\Store;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;
use Modules\Exhibition\Application\Commission\CommissionResolver;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleRecord;

final readonly class StoreSaleLifecycleService
{
    public function __construct(
        private ArtworkInventoryService $inventory,
        private ArtistSettlementService $settlements,
        private ExhibitionNotificationService $notifications,
        private CommissionResolver $commissions,
        private ExhibitionSettingsService $settings,
    ) {}

    public function apply(StoreLifecycleRecord $record): void
    {
        match ($record->action) {
            'reserved' => $this->reserve($record),
            'released' => $this->release($record),
            'committed' => $this->commit($record),
            'returned' => $this->returned($record),
            default => throw new DomainException('Unsupported Store sale lifecycle action.'),
        };
    }

    /**
     * Repair historical Store reservations created before pending sales were
     * recorded at reservation time. This is idempotent and never changes the
     * Store order, inventory state, or fulfillment state.
     */
    public function ensurePendingSaleRecord(StoreLifecycleRecord $record): int
    {
        if (! in_array($record->action, ['reserved', 'committed'], true)) {
            throw new DomainException('Only reserved or committed Store records can create a pending sale record.');
        }

        $this->assertDiscountPolicy($record);
        $fulfillments = $this->fulfillments($record);

        if ($fulfillments->count() !== $record->quantity) {
            throw new DomainException('Store record does not match the existing Exhibitions fulfillment quantity.');
        }

        $created = 0;

        foreach ($fulfillments as $fulfillment) {
            if (DB::table('exhibition_settlements')->where('fulfillment_id', $fulfillment->id)->exists()) {
                continue;
            }

            $this->ensureSettlement($fulfillment, $record, (int) $fulfillment->store_unit_number);
            $created++;
        }

        return $created;
    }

    private function reserve(StoreLifecycleRecord $record): void
    {
        $this->assertDiscountPolicy($record);
        $artwork = $this->artwork($record->externalId);
        $commission = $this->commissions->forArtwork($record->externalId);
        $items = $this->inventory->reserveFromStore(
            $record->externalId,
            $record->orderPublicId,
            $record->orderItemPublicId,
            $record->quantity,
            $record->idempotencyKey,
        );

        foreach ($items as $index => $item) {
            $unit = $index + 1;
            $reference = $record->orderPublicId.':'.$record->orderItemPublicId.':'.$unit;
            $attributes = [
                'public_id' => (string) Str::uuid(),
                'artwork_id' => $artwork->id,
                'inventory_item_id' => $item->id,
                'artist_user_id' => $artwork->artist_user_id,
                'customer_user_id' => $record->customerUserId,
                'store_order_reference' => $reference,
                'store_order_public_id' => $record->orderPublicId,
                'store_order_number' => $record->orderNumber,
                'store_order_item_public_id' => $record->orderItemPublicId,
                'store_unit_number' => $unit,
                'state' => 'store_reserved',
                'preparation_deadline' => now()->addDays(max(1, (int) ($artwork->preparation_days ?: config('exhibitions.fulfillment.default_preparation_days', 7)))),
                'packaging_instructions' => (string) ($artwork->shipping_notes ?: config('exhibitions.fulfillment.default_packaging_instructions')),
                'artist_notified_at' => null,
                'custody_holder' => 'artist',
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('exhibition_fulfillments', 'commission_basis_points_snapshot')) {
                $attributes['commission_basis_points_snapshot'] = $commission->basisPoints;
                $attributes['commission_source_snapshot'] = $commission->source;
            }
            DB::table('exhibition_fulfillments')->insertOrIgnore($attributes);
            $fulfillment = DB::table('exhibition_fulfillments')
                ->where('store_order_item_public_id', $record->orderItemPublicId)
                ->where('store_unit_number', $unit)
                ->first();
            DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'fulfillment_id' => $fulfillment->id,
                'actor_role' => 'store',
                'action' => 'store_reservation_received',
                'from_state' => null,
                'to_state' => 'store_reserved',
                'idempotency_key' => $record->idempotencyKey.':fulfillment:'.$unit,
                'notes' => 'Store reservation received; fulfillment remains private until the sale is committed.',
                'metadata' => json_encode(['store_sequence' => $record->sequence, 'unit' => $unit], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // A Store reservation makes an original artwork unavailable and
            // is therefore visible as a pending sale immediately. The
            // settlement remains `pending_sale`; payout gates still require
            // collected payment, delivery and the configured return window.
            $this->ensureSettlement($fulfillment, $record, $unit);
        }

        if ($this->fulfillments($record)->count() !== $record->quantity) {
            throw new DomainException('Store reservation did not create the exact Exhibitions fulfillment quantity.');
        }

    }

    private function commit(StoreLifecycleRecord $record): void
    {
        $this->assertDiscountPolicy($record);
        $fulfillments = $this->fulfillments($record);

        if ($fulfillments->count() !== $record->quantity) {
            throw new DomainException('Store commitment does not match the reserved Exhibitions units.');
        }

        foreach ($fulfillments as $fulfillment) {
            $item = DB::table('exhibition_artwork_inventory_items')->where('id', $fulfillment->inventory_item_id)->first();

            if ($item->state === 'reserved') {
                $this->inventory->sellFromStore($item->public_id, $record->orderPublicId, $record->idempotencyKey.':sale:'.$fulfillment->store_unit_number);
            } elseif ($item->state !== 'sold') {
                throw new DomainException('Store commitment found an inventory unit outside the reserved/sold states.');
            }

            $unit = (int) $fulfillment->store_unit_number;
            $settlement = $this->ensureSettlement($fulfillment, $record, $unit);

            if ($record->paymentStatus === 'paid' && $settlement->state === 'pending_sale') {
                $settlement = $this->settlements->collected(
                    $settlement->public_id,
                    $record->orderPublicId,
                    $record->idempotencyKey.':collected:'.$unit,
                );
            }

            $fromState = $fulfillment->state;
            if (in_array($fromState, ['store_reserved', 'artist_notification_sent'], true)) {
                DB::table('exhibition_fulfillments')->where('id', $fulfillment->id)->update([
                    'state' => 'sale_confirmed',
                    'artist_notified_at' => now(),
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                $fulfillment->state = 'sale_confirmed';
            }

            if ($record->orderStatus === 'delivered' && ! in_array($fulfillment->state, ['delivered', 'return_requested', 'returned', 'cancelled'], true)) {
                DB::table('exhibition_fulfillments')->where('id', $fulfillment->id)->update([
                    'state' => 'delivered',
                    'delivered_at' => now(),
                    'custody_holder' => 'customer',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'actor_role' => 'store',
                    'action' => 'store_delivery_synchronized',
                    'from_state' => $fulfillment->state,
                    'to_state' => 'delivered',
                    'idempotency_key' => $record->idempotencyKey.':delivered:'.$unit,
                    'notes' => 'Store order delivery synchronized automatically.',
                    'metadata' => json_encode(['store_sequence' => $record->sequence, 'order_status' => $record->orderStatus], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $fulfillment->state = 'delivered';
            }

            if ($settlement->state === 'awaiting_delivery' && $fulfillment->state === 'delivered') {
                $this->settlements->beginReturnWindow(
                    $settlement->public_id,
                    $this->settings->value('exhibitions.settlement.eligibility_trigger', 'delivered_return_window_completed') === 'delivered'
                        ? 0
                        : (int) $this->settings->value('exhibitions.settlement.return_window_days', 14),
                    $record->idempotencyKey.':return-window:'.$unit,
                );
            }

            DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'fulfillment_id' => $fulfillment->id,
                'actor_role' => 'store',
                'action' => 'store_sale_committed',
                'from_state' => $fromState,
                'to_state' => $fulfillment->state,
                'idempotency_key' => $record->idempotencyKey.':commit:'.$unit,
                'notes' => 'Store committed the sale and the financial snapshot was reconciled.',
                'metadata' => json_encode([
                    'store_sequence' => $record->sequence,
                    'payment_status' => $record->paymentStatus,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $artist = (int) $fulfillments->first()->artist_user_id;
        $this->notifications->dispatchCommitted(
            'artwork_sold',
            [$artist],
            ['message' => 'Your artwork was sold. View the confirmed sale and your earnings; the platform will handle fulfillment.', 'link' => '/exhibitions/dashboard/sales'],
            'store-sale-committed:'.$record->orderItemPublicId,
        );
    }

    private function release(StoreLifecycleRecord $record): void
    {
        $fulfillments = $this->fulfillments($record);

        if ($fulfillments->count() !== $record->quantity) {
            throw new DomainException('Store release does not match the reserved Exhibitions units.');
        }

        foreach ($fulfillments as $fulfillment) {
            if (! in_array($fulfillment->state, ['store_reserved', 'sale_confirmed', 'artist_notification_sent', 'preparing', 'ready_for_pickup', 'cancelled'], true)) {
                throw new DomainException('Store release requires platform operations review after collection.');
            }

            if ($fulfillment->state !== 'cancelled') {
                DB::table('exhibition_fulfillments')->where('id', $fulfillment->id)->update([
                    'state' => 'cancelled',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'actor_role' => 'store',
                    'action' => 'store_reservation_released',
                    'from_state' => $fulfillment->state,
                    'to_state' => 'cancelled',
                    'idempotency_key' => $record->idempotencyKey.':release:'.$fulfillment->store_unit_number,
                    'notes' => 'Store released the order reservation.',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $item = DB::table('exhibition_artwork_inventory_items')->where('id', $fulfillment->inventory_item_id)->first();

            if (in_array($item->state, ['reserved', 'temporarily_held', 'sold'], true)) {
                $this->inventory->releaseFromStore($item->public_id, $record->orderPublicId, $record->idempotencyKey.':inventory-release:'.$fulfillment->store_unit_number);
            }

            $settlement = DB::table('exhibition_settlements')->where('fulfillment_id', $fulfillment->id)->first();
            if ($settlement && $settlement->state !== 'reversed') {
                $this->settlements->cancelSale(
                    $settlement->public_id,
                    'Store cancelled the artwork order.',
                    $record->idempotencyKey.':settlement-cancel:'.$fulfillment->store_unit_number,
                );
            }
        }

        $this->notifications->dispatchCommitted(
            'shipment_update',
            [(int) $fulfillments->first()->artist_user_id],
            ['message' => 'The Store order was cancelled before collection. The reserved artwork unit is available again.', 'link' => '/exhibitions/dashboard/sales'],
            'store-order-released:'.$record->orderItemPublicId,
        );
    }

    private function returned(StoreLifecycleRecord $record): void
    {
        $fulfillments = $this->fulfillments($record);

        if ($fulfillments->count() !== $record->quantity) {
            throw new DomainException('Store return does not match the committed Exhibitions units.');
        }

        foreach ($fulfillments as $fulfillment) {
            $item = DB::table('exhibition_artwork_inventory_items')->where('id', $fulfillment->inventory_item_id)->first();

            if ($item->state === 'sold') {
                $this->inventory->returnFromStore($item->public_id, $record->orderPublicId, $record->idempotencyKey.':inventory-return:'.$fulfillment->store_unit_number);
            }

            if (! in_array($fulfillment->state, ['delivered', 'return_requested', 'returned'], true)) {
                throw new DomainException('Store return arrived before the Exhibitions delivery state.');
            }

            if ($fulfillment->state === 'delivered') {
                DB::table('exhibition_fulfillments')->where('id', $fulfillment->id)->update([
                    'state' => 'return_requested',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'actor_role' => 'store',
                    'action' => 'store_return_requested',
                    'from_state' => 'delivered',
                    'to_state' => 'return_requested',
                    'idempotency_key' => $record->idempotencyKey.':return-request:'.$fulfillment->store_unit_number,
                    'notes' => 'Store completed its reviewed return workflow.',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $fulfillment->state = 'return_requested';
            }

            if ($fulfillment->state !== 'returned') {
                DB::table('exhibition_fulfillments')->where('id', $fulfillment->id)->update([
                    'state' => 'returned',
                    'custody_holder' => 'platform',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'actor_role' => 'store',
                    'action' => 'store_return_received',
                    'from_state' => $fulfillment->state,
                    'to_state' => 'returned',
                    'idempotency_key' => $record->idempotencyKey.':return:'.$fulfillment->store_unit_number,
                    'notes' => 'Store confirmed a returned order; the artwork remains unavailable pending inspection.',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $settlement = DB::table('exhibition_settlements')->where('fulfillment_id', $fulfillment->id)->first();

            if ($settlement && $settlement->state !== 'reversed') {
                $this->settlements->cancelSale(
                    $settlement->public_id,
                    'Store confirmed the artwork return; commission and artist earning were reversed.',
                    $record->idempotencyKey.':settlement-cancel:'.$fulfillment->store_unit_number,
                );
            }
        }

        $this->notifications->dispatchCommitted(
            'return_requested',
            [(int) $fulfillments->first()->artist_user_id],
            ['message' => 'Store confirmed a reviewed return. The artwork and settlement remain held for platform inspection.', 'link' => '/exhibitions/dashboard/sales'],
            'store-returned:'.$record->orderItemPublicId,
        );
    }

    private function artwork(string $publicId): object
    {
        return DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->where('artwork.public_id', $publicId)
            ->where('artwork.state', 'published')
            ->select('artwork.*', 'identity.core_user_id as artist_user_id')
            ->first() ?? throw new DomainException('Store lifecycle references an unavailable Exhibitions artwork.');
    }

    private function fulfillments(StoreLifecycleRecord $record): object
    {
        return DB::table('exhibition_fulfillments')
            ->where('store_order_public_id', $record->orderPublicId)
            ->where('store_order_item_public_id', $record->orderItemPublicId)
            ->orderBy('store_unit_number')
            ->get();
    }

    private function allocate(int $total, int $quantity, int $unit): int
    {
        $base = intdiv($total, $quantity);

        return $base + ($unit <= ($total % $quantity) ? 1 : 0);
    }

    private function ensureSettlement(object $fulfillment, StoreLifecycleRecord $record, int $unit): object
    {
        $discount = $this->allocate($record->discountMinor, $record->quantity, $unit);
        $basis = max(0, $record->unitPriceMinor - $discount);
        $resolution = $this->commissions->forArtwork($record->externalId);
        $commissionBasisPoints = isset($fulfillment->commission_basis_points_snapshot) && $fulfillment->commission_basis_points_snapshot !== null
            ? (int) $fulfillment->commission_basis_points_snapshot
            : $resolution->basisPoints;
        $commissionSource = isset($fulfillment->commission_source_snapshot) && $fulfillment->commission_source_snapshot !== null
            ? (string) $fulfillment->commission_source_snapshot
            : $resolution->source;

        return $this->settlements->create(
            $fulfillment->public_id,
            [
                'currency' => $record->currency,
                'scale' => 3,
                'sale_price_minor' => $record->unitPriceMinor,
                'order_discount_minor' => $discount,
                'platform_commission_minor' => intdiv($basis * $commissionBasisPoints, 10_000),
            ],
            [
                'policy_version' => 'exhibitions-1.0.0',
                'commission_basis_points' => $commissionBasisPoints,
                'commission_source' => $commissionSource,
                'commission_base_minor' => $basis,
                'calculation_basis' => 'artwork_price_after_discount',
                'shipping_excluded' => true,
                'tax_excluded' => true,
                'store_lifecycle_sequence' => $record->sequence,
                'store_order_public_id' => $record->orderPublicId,
                'store_order_item_public_id' => $record->orderItemPublicId,
                'store_unit_number' => $unit,
            ],
            'store-order:'.$record->orderItemPublicId.':settlement:'.$unit,
        );
    }

    private function assertDiscountPolicy(StoreLifecycleRecord $record): void
    {
        if ($record->discountMinor > 0 && ! (bool) $this->settings->value('exhibitions.commission.discounts_allowed', false)) {
            throw new DomainException('Discounts are disabled for Exhibitions artwork commissions.');
        }
    }
}
