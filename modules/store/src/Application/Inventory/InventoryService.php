<?php

namespace Modules\Store\Application\Inventory;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Store\Domain\Inventory\Events\InventoryAdjusted;
use Modules\Store\Domain\Inventory\Events\InventoryReservationCommitted;
use Modules\Store\Domain\Inventory\Events\InventoryReservationReleased;
use Modules\Store\Domain\Inventory\Events\InventoryReserved;
use Modules\Store\Domain\Inventory\InventoryReconciliation;
use Modules\Store\Domain\Inventory\InventoryRepository;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Inventory\ReservationRecord;
use Modules\Store\Domain\Inventory\ReservationStatus;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\InsufficientInventory;
use Modules\Store\Domain\Shared\Exceptions\InventoryConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ReservationExpired;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class InventoryService
{
    public function __construct(
        private InventoryRepository $inventory,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function createItem(
        string $variantPublicId,
        InventoryTrackingMode $mode,
        int $openingOnHand,
        bool $allowBackorder,
        int $safetyStock,
        ActorContext $actor,
        string $idempotencyKey,
    ): string {
        if ($safetyStock < 0 || ($openingOnHand < 0 && ! $allowBackorder)) {
            throw new InvalidArgumentException('Opening inventory balance or safety stock is invalid.');
        }

        $publicId = (string) $this->ids->generate();
        $movementPublicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        return $this->transactions->run(function () use (
            $variantPublicId, $mode, $openingOnHand, $allowBackorder, $safetyStock,
            $actor, $idempotencyKey, $publicId, $movementPublicId, $now,
        ): string {
            $variantId = $this->inventory->variantIdByPublicId($variantPublicId, true)
                ?? throw new InvalidArgumentException('Variant does not exist.');
            $existing = $this->inventory->findItemByVariantId($variantId, true);
            if ($existing !== null) {
                return $existing->publicId;
            }

            $itemId = $this->inventory->createItem($publicId, $variantId, $mode, $openingOnHand, $allowBackorder, $safetyStock, $now);
            $this->inventory->insertMovement(
                $movementPublicId,
                $itemId,
                'opening_balance',
                $openingOnHand,
                $openingOnHand,
                'opening_balance',
                $idempotencyKey,
                $actor,
                $now,
            );

            return $publicId;
        });
    }

    public function adjust(string $itemPublicId, int $delta, string $reasonCode, string $idempotencyKey, ActorContext $actor, ?string $note = null): void
    {
        if ($delta === 0 || trim($reasonCode) === '' || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Inventory adjustment requires a non-zero delta, reason, and idempotency key.');
        }
        $now = $this->clock->now();

        $this->transactions->run(function () use ($itemPublicId, $delta, $reasonCode, $idempotencyKey, $actor, $note, $now): void {
            $item = $this->inventory->findItemByPublicId($itemPublicId, true)
                ?? throw new InvalidArgumentException('Inventory item does not exist.');
            if ($this->inventory->movementExists($item->id, $idempotencyKey)) {
                return;
            }

            $newOnHand = $item->onHand + $delta;
            if ($newOnHand < 0 && ! $item->allowBackorder) {
                throw new InsufficientInventory('Inventory adjustment would create negative on-hand stock.');
            }
            if (! $this->inventory->updateOnHand($item->id, $item->version, $newOnHand, $now)) {
                throw new InventoryConcurrencyConflict('Inventory adjustment lost a concurrency race.');
            }

            $this->inventory->insertMovement(
                (string) $this->ids->generate(),
                $item->id,
                'manual_adjustment',
                $delta,
                $newOnHand,
                $reasonCode,
                $idempotencyKey,
                $actor,
                $now,
                note: $note,
            );
            $this->events->afterCommit(new InventoryAdjusted($itemPublicId, $delta, $now));
        });
    }

    public function reserve(
        string $itemPublicId,
        int $quantity,
        string $referenceType,
        string $referencePublicId,
        string $idempotencyKey,
        ?DateTimeImmutable $expiresAt = null,
    ): string {
        if ($quantity <= 0 || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Reservation quantity and idempotency key are required.');
        }
        $reservationPublicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        return $this->transactions->run(function () use (
            $itemPublicId, $quantity, $referenceType, $referencePublicId, $idempotencyKey,
            $expiresAt, $reservationPublicId, $now,
        ): string {
            $item = $this->inventory->findItemByPublicId($itemPublicId, true)
                ?? throw new InvalidArgumentException('Inventory item does not exist.');
            if ($item->trackingMode !== InventoryTrackingMode::Tracked) {
                throw new InvalidArgumentException('Untracked inventory does not create stock reservations.');
            }

            $existing = $this->inventory->findReservationByIdempotency($item->id, $idempotencyKey);
            if ($existing !== null) {
                return $existing->publicId;
            }

            if (! $this->inventory->atomicReserve($item, $quantity, $now)) {
                throw new InsufficientInventory('Insufficient inventory for reservation.');
            }

            $this->inventory->createReservation(
                $reservationPublicId,
                $item->id,
                $quantity,
                $referenceType,
                $referencePublicId,
                $idempotencyKey,
                $expiresAt,
                $now,
            );
            $this->events->afterCommit(new InventoryReserved($itemPublicId, $reservationPublicId, $quantity, $now));

            return $reservationPublicId;
        });
    }

    public function release(string $reservationPublicId): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($reservationPublicId, $now): void {
            $reservation = $this->reservation($reservationPublicId);
            if (in_array($reservation->status, [ReservationStatus::Released, ReservationStatus::Expired, ReservationStatus::Cancelled], true)) {
                return;
            }
            if ($reservation->status === ReservationStatus::Committed) {
                throw new InvalidArgumentException('Committed inventory reservation cannot be released.');
            }
            if (! $this->inventory->atomicRelease($reservation->inventoryItemId, $reservation->quantity, $now)
                || ! $this->inventory->transitionReservation($reservation->id, ReservationStatus::Active, ReservationStatus::Released, $now)) {
                throw new InventoryConcurrencyConflict('Reservation release lost a concurrency race.');
            }
            $this->events->afterCommit(new InventoryReservationReleased($reservationPublicId, $now));
        });
    }

    public function commit(string $reservationPublicId, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($reservationPublicId, $actor, $now): void {
            $reservation = $this->reservation($reservationPublicId);
            if ($reservation->status === ReservationStatus::Committed) {
                return;
            }
            if ($reservation->status !== ReservationStatus::Active) {
                throw new InvalidArgumentException('Only an active reservation can be committed.');
            }
            if ($reservation->expiresAt !== null && $reservation->expiresAt <= $now) {
                throw new ReservationExpired('Inventory reservation has expired.');
            }

            $item = $this->inventory->findItemById($reservation->inventoryItemId, true)
                ?? throw new InvalidArgumentException('Inventory item does not exist.');

            if (! $this->inventory->atomicCommit($item, $reservation->quantity, $now)) {
                throw new InventoryConcurrencyConflict('Reservation commit could not update inventory atomically.');
            }

            $newOnHand = $item->onHand - $reservation->quantity;
            $movementKey = 'reservation-commit:'.$reservation->publicId;
            if (! $this->inventory->movementExists($item->id, $movementKey)) {
                $this->inventory->insertMovement(
                    (string) $this->ids->generate(),
                    $item->id,
                    'sale_commit',
                    -$reservation->quantity,
                    $newOnHand,
                    'reservation_commit',
                    $movementKey,
                    $actor,
                    $now,
                    'reservation',
                    $reservation->publicId,
                );
            }

            if (! $this->inventory->transitionReservation($reservation->id, ReservationStatus::Active, ReservationStatus::Committed, $now)) {
                throw new InventoryConcurrencyConflict('Reservation status commit lost a concurrency race.');
            }
            $this->events->afterCommit(new InventoryReservationCommitted($reservationPublicId, $now));
        });
    }

    public function reconcile(string $itemPublicId): InventoryReconciliation
    {
        $item = $this->inventory->findItemByPublicId($itemPublicId)
            ?? throw new InvalidArgumentException('Inventory item does not exist.');

        return new InventoryReconciliation(
            $itemPublicId,
            $item->onHand,
            $this->inventory->ledgerOnHand($item->id),
            $item->reserved,
            $this->inventory->activeReserved($item->id),
        );
    }

    public function updatePolicy(
        string $itemPublicId,
        int $expectedVersion,
        InventoryTrackingMode $mode,
        bool $allowBackorder,
        int $safetyStock,
        ?int $reorderPoint,
    ): void {
        if ($safetyStock < 0 || ($reorderPoint !== null && $reorderPoint < 0)) {
            throw new InvalidArgumentException('Inventory safety stock and reorder point cannot be negative.');
        }
        $now = $this->clock->now();
        $this->transactions->run(function () use ($itemPublicId, $expectedVersion, $mode, $allowBackorder, $safetyStock, $reorderPoint, $now): void {
            $item = $this->inventory->findItemByPublicId($itemPublicId, true)
                ?? throw new InvalidArgumentException('Inventory item does not exist.');
            if ($item->version !== $expectedVersion) {
                throw new InventoryConcurrencyConflict('Inventory policy version is stale.');
            }
            if (! $this->inventory->updatePolicy($item->id, $expectedVersion, $mode, $allowBackorder, $safetyStock, $reorderPoint, $now)) {
                throw new InventoryConcurrencyConflict('Inventory policy update lost a concurrency race.');
            }
        });
    }

    private function reservation(string $publicId): ReservationRecord
    {
        return $this->inventory->findReservationByPublicId($publicId, true)
            ?? throw new InvalidArgumentException('Inventory reservation does not exist.');
    }
}
