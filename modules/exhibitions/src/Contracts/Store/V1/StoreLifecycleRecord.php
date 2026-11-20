<?php

namespace Modules\Exhibition\Contracts\Store\V1;

use InvalidArgumentException;

final readonly class StoreLifecycleRecord
{
    public function __construct(
        public int $sequence,
        public string $ownerCode,
        public string $externalId,
        public string $action,
        public int $quantity,
        public string $orderPublicId,
        public string $orderNumber,
        public string $orderItemPublicId,
        public ?int $customerUserId,
        public string $currency,
        public int $unitPriceMinor,
        public int $discountMinor,
        public int $lineTotalMinor,
        public string $paymentStatus,
        public string $idempotencyKey,
        public ?string $orderStatus = null,
    ) {
        if ($sequence < 1 || $quantity < 1 || $quantity > 100_000 || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Store lifecycle identity, sequence, and quantity are required.');
        }

        if (! in_array($action, ['reserved', 'released', 'committed', 'returned'], true)) {
            throw new InvalidArgumentException('Unsupported Store lifecycle action.');
        }

        if (
            preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $ownerCode) !== 1
            || trim($externalId) === ''
            || mb_strlen($externalId) > 128
            || trim($orderPublicId) === ''
            || mb_strlen($orderPublicId) > 64
            || trim($orderNumber) === ''
            || mb_strlen($orderNumber) > 64
            || trim($orderItemPublicId) === ''
            || mb_strlen($orderItemPublicId) > 64
            || mb_strlen($idempotencyKey) > 160
        ) {
            throw new InvalidArgumentException('Store lifecycle public references are invalid.');
        }

        if ($customerUserId !== null && $customerUserId < 1) {
            throw new InvalidArgumentException('Store lifecycle customer binding must be a positive stable identifier.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $paymentStatus) !== 1) {
            throw new InvalidArgumentException('Store lifecycle currency or payment status is invalid.');
        }

        if ($unitPriceMinor < 0 || $discountMinor < 0 || $lineTotalMinor < 0 || $unitPriceMinor > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('Store lifecycle money values are invalid.');
        }

        if ($orderStatus !== null && ! in_array($orderStatus, ['new', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'], true)) {
            throw new InvalidArgumentException('Store lifecycle order status is invalid.');
        }

        $gross = $unitPriceMinor * $quantity;
        if ($discountMinor > $gross || $lineTotalMinor !== $gross - $discountMinor) {
            throw new InvalidArgumentException('Store lifecycle line totals do not reconcile.');
        }
    }

    public static function fromExternal(object $record): self
    {
        $idempotencyKey = (string) $record->idempotencyKey;
        $orderStatus = isset($record->orderStatus) ? (string) $record->orderStatus : null;
        if ($orderStatus === null && preg_match('/(?:^|:)status-([a-z0-9-]+)(?::|$)/', $idempotencyKey, $match) === 1) {
            $orderStatus = $match[1];
        }

        return new self(
            sequence: (int) $record->sequence,
            ownerCode: (string) $record->ownerCode,
            externalId: (string) $record->externalId,
            action: (string) $record->action,
            quantity: (int) $record->quantity,
            orderPublicId: (string) $record->orderPublicId,
            orderNumber: (string) $record->orderNumber,
            orderItemPublicId: (string) $record->orderItemPublicId,
            customerUserId: $record->customerUserId === null ? null : (int) $record->customerUserId,
            currency: strtoupper((string) $record->currency),
            unitPriceMinor: (int) $record->unitPriceMinor,
            discountMinor: (int) $record->discountMinor,
            lineTotalMinor: (int) $record->lineTotalMinor,
            paymentStatus: (string) $record->paymentStatus,
            idempotencyKey: $idempotencyKey,
            orderStatus: $orderStatus,
        );
    }
}
