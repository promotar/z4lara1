<?php

namespace Modules\Store\Infrastructure\Integrations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use LogicException;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableLifecycleFeed;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableLifecycleRecord;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Events\ExternalSellableLifecycleChanged;

final readonly class DatabaseExternalSellableLifecycleFeed implements ExternalSellableLifecycleFeed
{
    private const ACTIONS = ['reserved', 'released', 'committed', 'returned'];

    public function __construct(
        private ConnectionInterface $database,
        private DomainEventPublisher $events,
    ) {}

    public function after(string $ownerCode, int $afterSequence, int $limit = 100): array
    {
        if (! $this->validOwnerCode($ownerCode) || $afterSequence < 0 || $limit < 1) {
            return [];
        }

        return $this->database->table('store_external_sellable_lifecycle_events')
            ->where('owner_code', $ownerCode)
            ->where('id', '>', $afterSequence)
            ->orderBy('id')
            ->limit(min($limit, 500))
            ->get()
            ->map(fn (object $row): ExternalSellableLifecycleRecord => $this->recordFromRow($row))
            ->values()
            ->all();
    }

    public function customerOwnsOrder(string $ownerCode, string $orderReference, int $userId): bool
    {
        $orderReference = trim($orderReference);
        if (! $this->validOwnerCode($ownerCode) || $orderReference === '' || $userId < 1) {
            return false;
        }

        $lifecycleBinding = $this->database->table('store_external_sellable_lifecycle_events')
            ->where('owner_code', $ownerCode)
            ->where(static function (Builder $query) use ($orderReference): void {
                $query->where('order_public_id', $orderReference)
                    ->orWhere('order_number', $orderReference);
            })
            ->orderBy('id')
            ->first(['customer_user_id']);

        if ($lifecycleBinding !== null) {
            return $lifecycleBinding->customer_user_id !== null
                && (int) $lifecycleBinding->customer_user_id === $userId;
        }

        // Supports external orders created before the lifecycle feed migration.
        return $this->database->table('store_orders as orders')
            ->join('store_order_items as item', 'item.order_id', '=', 'orders.id')
            ->join('store_product_variants as variant', 'variant.public_id', '=', 'item.variant_public_id')
            ->join('store_external_sellables as external', 'external.variant_id', '=', 'variant.id')
            ->where('external.owner_code', $ownerCode)
            ->where('orders.created_by', $userId)
            ->where(static function (Builder $query) use ($orderReference): void {
                $query->where('orders.public_id', $orderReference)
                    ->orWhere('orders.order_number', $orderReference);
            })
            ->exists();
    }

    /** Internal Store writer used from the checkout transaction. */
    public function recordOrderItem(
        int $orderId,
        int $orderItemId,
        string $action,
        string $occurrence = 'checkout',
    ): void {
        $this->assertAction($action);

        $row = $this->externalOrderLines()
            ->where('orders.id', $orderId)
            ->where('item.id', $orderItemId)
            ->first();

        if ($row !== null) {
            $this->persist($row, $action, $occurrence);
        }
    }

    /** Internal Store writer used from order and payment workflow transactions. */
    public function recordOrder(int $orderId, string $action, string $occurrence): void
    {
        $this->assertAction($action);

        foreach ($this->externalOrderLines()->where('orders.id', $orderId)->orderBy('item.id')->get() as $row) {
            $this->persist($row, $action, $occurrence);
        }
    }

    private function persist(object $row, string $action, string $occurrence): void
    {
        if (! preg_match('/^[a-z0-9-]{1,40}$/', $occurrence)) {
            throw new InvalidArgumentException('External sellable lifecycle occurrence is invalid.');
        }

        $now = now();
        $customerUserId = $row->customer_user_id === null ? null : (int) $row->customer_user_id;
        $existingBinding = $this->database->table('store_external_sellable_lifecycle_events')
            ->where('owner_code', (string) $row->owner_code)
            ->where('order_public_id', (string) $row->order_public_id)
            ->orderBy('id')
            ->first(['customer_user_id']);
        if ($existingBinding !== null) {
            $boundCustomerUserId = $existingBinding->customer_user_id === null
                ? null
                : (int) $existingBinding->customer_user_id;
            if ($boundCustomerUserId !== $customerUserId) {
                throw new LogicException('External sellable order ownership cannot change.');
            }
        }

        $idempotencyKey = implode(':', [
            'external-lifecycle',
            $action,
            $occurrence,
            (string) $row->order_public_id,
            (string) $row->order_item_public_id,
        ]);

        $inserted = $this->database->table('store_external_sellable_lifecycle_events')->insertOrIgnore([
            'owner_code' => (string) $row->owner_code,
            'external_id' => (string) $row->external_id,
            'action' => $action,
            'quantity' => (int) $row->quantity,
            'order_public_id' => (string) $row->order_public_id,
            'order_number' => (string) $row->order_number,
            'order_item_public_id' => (string) $row->order_item_public_id,
            'customer_user_id' => $customerUserId,
            'currency' => strtoupper((string) $row->currency),
            'unit_price_minor' => (int) $row->unit_price_minor,
            'discount_minor' => (int) $row->discount_minor,
            'line_total_minor' => (int) $row->line_total_minor,
            'payment_status' => (string) $row->payment_status,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);

        if ($inserted !== 1) {
            return;
        }

        $eventRow = $this->database->table('store_external_sellable_lifecycle_events')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($eventRow !== null) {
            $this->events->afterCommit(new ExternalSellableLifecycleChanged($this->recordFromRow($eventRow)));
        }
    }

    private function externalOrderLines(): Builder
    {
        return $this->database->table('store_orders as orders')
            ->join('store_order_items as item', 'item.order_id', '=', 'orders.id')
            ->join('store_product_variants as variant', 'variant.public_id', '=', 'item.variant_public_id')
            ->join('store_external_sellables as external', 'external.variant_id', '=', 'variant.id')
            ->select([
                'external.owner_code',
                'external.external_id',
                'orders.public_id as order_public_id',
                'orders.order_number',
                'orders.created_by as customer_user_id',
                'orders.payment_status',
                'item.public_id as order_item_public_id',
                'item.quantity',
                'item.currency',
                'item.unit_price_minor',
                'item.discount_minor',
                'item.line_total_minor',
            ]);
    }

    private function recordFromRow(object $row): ExternalSellableLifecycleRecord
    {
        return new ExternalSellableLifecycleRecord(
            sequence: (int) $row->id,
            ownerCode: (string) $row->owner_code,
            externalId: (string) $row->external_id,
            action: (string) $row->action,
            quantity: (int) $row->quantity,
            orderPublicId: (string) $row->order_public_id,
            orderNumber: (string) $row->order_number,
            orderItemPublicId: (string) $row->order_item_public_id,
            customerUserId: $row->customer_user_id === null ? null : (int) $row->customer_user_id,
            currency: (string) $row->currency,
            unitPriceMinor: (int) $row->unit_price_minor,
            discountMinor: (int) $row->discount_minor,
            lineTotalMinor: (int) $row->line_total_minor,
            paymentStatus: (string) $row->payment_status,
            idempotencyKey: (string) $row->idempotency_key,
        );
    }

    private function assertAction(string $action): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('External sellable lifecycle action is invalid.');
        }
    }

    private function validOwnerCode(string $ownerCode): bool
    {
        return preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $ownerCode) === 1;
    }
}
