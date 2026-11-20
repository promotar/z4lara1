<?php

namespace Modules\Store\Infrastructure\Persistence\Inventory;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Inventory\InventoryItemRecord;
use Modules\Store\Domain\Inventory\InventoryRepository;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Inventory\ReservationRecord;
use Modules\Store\Domain\Inventory\ReservationStatus;
use Modules\Store\Domain\Shared\ActorContext;

final class DatabaseInventoryRepository implements InventoryRepository
{
    public function __construct(private readonly ?string $connectionName = null) {}

    public function variantIdByPublicId(string $variantPublicId, bool $forUpdate = false): ?int
    {
        $query = $this->db()->table('store_product_variants')->where('public_id', $variantPublicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $value = $query->value('id');

        return $value === null ? null : (int) $value;
    }

    public function findItemByVariantId(int $variantId, bool $forUpdate = false): ?InventoryItemRecord
    {
        return $this->item('variant_id', $variantId, $forUpdate);
    }

    public function findItemByPublicId(string $publicId, bool $forUpdate = false): ?InventoryItemRecord
    {
        return $this->item('public_id', $publicId, $forUpdate);
    }

    public function findItemById(int $itemId, bool $forUpdate = false): ?InventoryItemRecord
    {
        return $this->item('id', $itemId, $forUpdate);
    }

    public function createItem(
        string $publicId,
        int $variantId,
        InventoryTrackingMode $mode,
        int $opening,
        bool $allowBackorder,
        int $safetyStock,
        DateTimeImmutable $now,
    ): int {
        return (int) $this->db()->table('store_inventory_items')->insertGetId([
            'public_id' => $publicId,
            'variant_id' => $variantId,
            'tracking_mode' => $mode->value,
            'on_hand' => $opening,
            'reserved' => 0,
            'allow_backorder' => $allowBackorder,
            'safety_stock' => $safetyStock,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateOnHand(int $itemId, int $expectedVersion, int $newOnHand, DateTimeImmutable $now): bool
    {
        return $this->db()->table('store_inventory_items')
            ->where('id', $itemId)
            ->where('version', $expectedVersion)
            ->update([
                'on_hand' => $newOnHand,
                'version' => $this->db()->raw('version + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    public function movementExists(int $itemId, string $idempotencyKey): bool
    {
        return $this->db()->table('store_inventory_movements')
            ->where('inventory_item_id', $itemId)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    public function insertMovement(
        string $publicId,
        int $itemId,
        string $type,
        int $delta,
        int $balanceAfter,
        string $reasonCode,
        string $idempotencyKey,
        ActorContext $actor,
        DateTimeImmutable $now,
        ?string $referenceType = null,
        ?string $referencePublicId = null,
        ?string $note = null,
    ): void {
        $this->db()->table('store_inventory_movements')->insert([
            'public_id' => $publicId,
            'inventory_item_id' => $itemId,
            'type' => $type,
            'quantity_delta' => $delta,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_public_id' => $referencePublicId,
            'reason_code' => $reasonCode,
            'note' => $note,
            'idempotency_key' => $idempotencyKey,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    public function findReservationByIdempotency(int $itemId, string $idempotencyKey): ?ReservationRecord
    {
        $row = $this->db()->table('store_inventory_reservations')
            ->where('inventory_item_id', $itemId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $row === null ? null : $this->toReservation($row);
    }

    public function findReservationByPublicId(string $publicId, bool $forUpdate = false): ?ReservationRecord
    {
        $query = $this->db()->table('store_inventory_reservations')->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row === null ? null : $this->toReservation($row);
    }

    public function atomicReserve(InventoryItemRecord $item, int $quantity, DateTimeImmutable $now): bool
    {
        $query = $this->db()->table('store_inventory_items')
            ->where('id', $item->id)
            ->where('tracking_mode', InventoryTrackingMode::Tracked->value);

        if (! $item->allowBackorder) {
            $query->whereRaw('(on_hand - reserved - safety_stock) >= ?', [$quantity]);
        }

        return $query->update([
            'reserved' => $this->db()->raw('reserved + '.(int) $quantity),
            'version' => $this->db()->raw('version + 1'),
            'updated_at' => $now,
        ]) === 1;
    }

    public function createReservation(
        string $publicId,
        int $itemId,
        int $quantity,
        string $referenceType,
        string $referencePublicId,
        string $idempotencyKey,
        ?DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void {
        $this->db()->table('store_inventory_reservations')->insert([
            'public_id' => $publicId,
            'inventory_item_id' => $itemId,
            'quantity' => $quantity,
            'status' => ReservationStatus::Active->value,
            'reference_type' => $referenceType,
            'reference_public_id' => $referencePublicId,
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function atomicRelease(int $itemId, int $quantity, DateTimeImmutable $now): bool
    {
        return $this->db()->table('store_inventory_items')
            ->where('id', $itemId)
            ->where('reserved', '>=', $quantity)
            ->update([
                'reserved' => $this->db()->raw('reserved - '.(int) $quantity),
                'version' => $this->db()->raw('version + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    public function atomicCommit(InventoryItemRecord $item, int $quantity, DateTimeImmutable $now): bool
    {
        $query = $this->db()->table('store_inventory_items')
            ->where('id', $item->id)
            ->where('reserved', '>=', $quantity);

        if (! $item->allowBackorder) {
            $query->where('on_hand', '>=', $quantity);
        }

        return $query->update([
            'reserved' => $this->db()->raw('reserved - '.(int) $quantity),
            'on_hand' => $this->db()->raw('on_hand - '.(int) $quantity),
            'version' => $this->db()->raw('version + 1'),
            'updated_at' => $now,
        ]) === 1;
    }

    public function transitionReservation(int $reservationId, ReservationStatus $from, ReservationStatus $to, DateTimeImmutable $now): bool
    {
        $values = ['status' => $to->value, 'updated_at' => $now];
        if ($to === ReservationStatus::Committed) {
            $values['committed_at'] = $now;
        }
        if (in_array($to, [ReservationStatus::Released, ReservationStatus::Expired, ReservationStatus::Cancelled], true)) {
            $values['released_at'] = $now;
        }

        return $this->db()->table('store_inventory_reservations')
            ->where('id', $reservationId)
            ->where('status', $from->value)
            ->update($values) === 1;
    }

    public function ledgerOnHand(int $itemId): int
    {
        return (int) $this->db()->table('store_inventory_movements')
            ->where('inventory_item_id', $itemId)
            ->sum('quantity_delta');
    }

    public function activeReserved(int $itemId): int
    {
        return (int) $this->db()->table('store_inventory_reservations')
            ->where('inventory_item_id', $itemId)
            ->where('status', ReservationStatus::Active->value)
            ->sum('quantity');
    }

    public function updatePolicy(
        int $itemId,
        int $expectedVersion,
        InventoryTrackingMode $mode,
        bool $allowBackorder,
        int $safetyStock,
        ?int $reorderPoint,
        DateTimeImmutable $now,
    ): bool {
        return $this->db()->table('store_inventory_items')
            ->where('id', $itemId)
            ->where('version', $expectedVersion)
            ->update([
                'tracking_mode' => $mode->value,
                'allow_backorder' => $allowBackorder,
                'safety_stock' => $safetyStock,
                'reorder_point' => $reorderPoint,
                'version' => DB::raw('version + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    private function item(string $field, mixed $value, bool $forUpdate): ?InventoryItemRecord
    {
        $query = $this->db()->table('store_inventory_items')->where($field, $value);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row === null ? null : new InventoryItemRecord(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            variantId: (int) $row->variant_id,
            trackingMode: InventoryTrackingMode::from((string) $row->tracking_mode),
            onHand: (int) $row->on_hand,
            reserved: (int) $row->reserved,
            allowBackorder: (bool) $row->allow_backorder,
            safetyStock: (int) $row->safety_stock,
            version: (int) $row->version,
            reorderPoint: ! property_exists($row, 'reorder_point') || $row->reorder_point === null ? null : (int) $row->reorder_point,
        );
    }

    private function toReservation(object $row): ReservationRecord
    {
        return new ReservationRecord(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            inventoryItemId: (int) $row->inventory_item_id,
            quantity: (int) $row->quantity,
            status: ReservationStatus::from((string) $row->status),
            idempotencyKey: (string) $row->idempotency_key,
            expiresAt: $row->expires_at === null ? null : new DateTimeImmutable((string) $row->expires_at),
        );
    }

    private function db(): ConnectionInterface
    {
        return DB::connection($this->connectionName);
    }
}
