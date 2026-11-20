<?php

namespace Modules\Store\Domain\Inventory;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface InventoryRepository
{
    public function variantIdByPublicId(string $variantPublicId, bool $forUpdate = false): ?int;

    public function findItemByVariantId(int $variantId, bool $forUpdate = false): ?InventoryItemRecord;

    public function findItemByPublicId(string $publicId, bool $forUpdate = false): ?InventoryItemRecord;

    public function findItemById(int $itemId, bool $forUpdate = false): ?InventoryItemRecord;

    public function createItem(string $publicId, int $variantId, InventoryTrackingMode $mode, int $opening, bool $allowBackorder, int $safetyStock, DateTimeImmutable $now): int;

    public function updateOnHand(int $itemId, int $expectedVersion, int $newOnHand, DateTimeImmutable $now): bool;

    public function movementExists(int $itemId, string $idempotencyKey): bool;

    public function insertMovement(string $publicId, int $itemId, string $type, int $delta, int $balanceAfter, string $reasonCode, string $idempotencyKey, ActorContext $actor, DateTimeImmutable $now, ?string $referenceType = null, ?string $referencePublicId = null, ?string $note = null): void;

    public function findReservationByIdempotency(int $itemId, string $idempotencyKey): ?ReservationRecord;

    public function findReservationByPublicId(string $publicId, bool $forUpdate = false): ?ReservationRecord;

    public function atomicReserve(InventoryItemRecord $item, int $quantity, DateTimeImmutable $now): bool;

    public function createReservation(string $publicId, int $itemId, int $quantity, string $referenceType, string $referencePublicId, string $idempotencyKey, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): void;

    public function atomicRelease(int $itemId, int $quantity, DateTimeImmutable $now): bool;

    public function atomicCommit(InventoryItemRecord $item, int $quantity, DateTimeImmutable $now): bool;

    public function transitionReservation(int $reservationId, ReservationStatus $from, ReservationStatus $to, DateTimeImmutable $now): bool;

    public function ledgerOnHand(int $itemId): int;

    public function activeReserved(int $itemId): int;

    public function updatePolicy(int $itemId, int $expectedVersion, InventoryTrackingMode $mode, bool $allowBackorder, int $safetyStock, ?int $reorderPoint, DateTimeImmutable $now): bool;
}
