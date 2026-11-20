<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\Store\Application\Coupons\CouponApplicationService;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Domain\Orders\OrderTransitionPolicy;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\TransactionManager;
use Modules\Store\Events\OrderStatusChanged;
use Modules\Store\Infrastructure\Integrations\DatabaseExternalSellableLifecycleFeed;

final readonly class OrderWorkflowService
{
    public function __construct(
        private TransactionManager $transactions,
        private OrderTransitionPolicy $transitions,
        private OrderEventRecorder $events,
        private InventoryService $inventory,
        private CouponApplicationService $coupons,
        private StoreSettingsService $settings,
        private DatabaseExternalSellableLifecycleFeed $externalLifecycle,
    ) {}

    /** @param array<string, mixed> $context */
    public function transition(
        string $orderPublicId,
        OrderStatus $target,
        ActorContext $actor,
        array $context = [],
        bool $override = false,
    ): void {
        $result = $this->transactions->run(function () use ($orderPublicId, $target, $actor, $context, $override): array {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }

            $from = OrderStatus::from((string) $order->status);
            $reason = trim((string) ($context['reason'] ?? $context['override_reason'] ?? ''));
            if (isset($context['cancellation_request_event_id'])) {
                $pendingRequest = $this->pendingCancellationRequest((int) $order->id);
                if ($pendingRequest === null || (int) $pendingRequest->id !== (int) $context['cancellation_request_event_id']) {
                    throw new InvalidArgumentException('This cancellation request is no longer awaiting review.');
                }
            }
            if (isset($context['return_request_event_id'])) {
                $pendingRequest = $this->pendingReturnRequest((int) $order->id);
                if ($pendingRequest === null || (int) $pendingRequest->id !== (int) $context['return_request_event_id']) {
                    throw new InvalidArgumentException('This return request is no longer awaiting review.');
                }
            }
            $runtime = $this->settings->current();
            if ($target === OrderStatus::Returned && ! array_key_exists('return_to_stock', $context)) {
                $context['return_to_stock'] = (bool) $runtime['returned_products_restore_stock'];
            }
            $this->transitions->assertAllowed($from, $target, $override, $reason);
            $this->assertContext($target, $context);
            if ($target === OrderStatus::Delivered
                && (bool) ($context['payment_collected'] ?? false)
                && ! (bool) $runtime['cod_allow_collection']) {
                throw new InvalidArgumentException('Recording Cash on Delivery collection is disabled in Store settings.');
            }

            if ($target === OrderStatus::Cancelled) {
                $this->releaseReservations((int) $order->id);
                if ((bool) $runtime['coupon_release_on_cancel']) {
                    $this->coupons->releaseUsageForOrder((int) $order->id);
                }
            } elseif ($target === OrderStatus::Delivered) {
                $this->commitReservations((int) $order->id, $actor);
            } elseif ($target === OrderStatus::Confirmed) {
                $this->keepReservationsActive((int) $order->id);
            } elseif ($target === OrderStatus::Returned && (bool) $context['return_to_stock']) {
                $this->restoreReturnedInventory((int) $order->id, (string) $order->public_id, $actor);
            }

            $updates = [
                'status' => $target->value,
                'updated_by' => $actor->id,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ];

            if ($target === OrderStatus::Shipped) {
                $updates += [
                    'shipping_company' => $this->nullable($context['shipping_company'] ?? null),
                    'courier_name' => $this->nullable($context['courier_name'] ?? null),
                    'courier_phone' => $this->nullable($context['courier_phone'] ?? null),
                    'tracking_number' => $this->nullable($context['tracking_number'] ?? null),
                    'shipping_notes' => $this->nullable($context['shipping_notes'] ?? null),
                    'shipped_at' => $context['shipped_at'] ?? now(),
                ];
            } elseif ($target === OrderStatus::Delivered) {
                $updates['delivered_at'] = $context['delivered_at'] ?? now();
                if ((bool) ($context['payment_collected'] ?? false)) {
                    $updates += [
                        'payment_status' => 'paid',
                        'payment_collected_minor' => (int) $order->total_minor,
                        'payment_collected_at' => now(),
                        'payment_note' => $this->nullable($context['payment_note'] ?? null),
                    ];
                }
            } elseif ($target === OrderStatus::Cancelled) {
                $updates += [
                    'cancellation_reason_code' => (string) $context['cancellation_reason_code'],
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                    'payment_status' => $order->payment_status === 'paid' ? 'paid' : 'cancelled',
                ];
            } elseif ($target === OrderStatus::Returned) {
                $updates += [
                    'return_reason' => $reason,
                    'return_condition' => (string) $context['return_condition'],
                    'return_to_stock' => (bool) ($context['return_to_stock'] ?? false),
                    'returned_at' => now(),
                ];
            }

            DB::table('store_orders')->where('id', $order->id)->update($updates);
            $this->events->record(
                (int) $order->id,
                'status_changed',
                $actor,
                $reason !== '' ? $reason : "Order moved to {$target->label()}.",
                $from->value,
                $target->value,
                array_filter($context, fn (mixed $value): bool => $value !== null && $value !== ''),
            );

            $lifecycleAction = match ($target) {
                OrderStatus::Cancelled => 'released',
                OrderStatus::Delivered => 'committed',
                OrderStatus::Returned => 'returned',
                default => null,
            };
            if ($lifecycleAction !== null) {
                $this->externalLifecycle->recordOrder(
                    (int) $order->id,
                    $lifecycleAction,
                    'status-'.$target->value,
                );
            }

            return [
                'public_id' => (string) $order->public_id,
                'order_number' => (string) $order->order_number,
                'email' => $order->customer_email === null ? null : (string) $order->customer_email,
            ];
        });

        Event::dispatch(new OrderStatusChanged(
            $result['public_id'],
            $result['order_number'],
            $target->value,
            $result['email'],
        ));
    }

    public function addNote(string $orderPublicId, string $note, ActorContext $actor): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('An internal note is required.');
        }

        $this->transactions->run(function () use ($orderPublicId, $note, $actor): void {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }
            $this->events->record((int) $order->id, 'note_added', $actor, $note);
        });
    }

    public function acceptCancellationRequest(string $orderPublicId, ActorContext $actor): void
    {
        $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            throw new InvalidArgumentException('Order does not exist.');
        }

        $request = $this->pendingCancellationRequest((int) $order->id);
        if ($request === null) {
            throw new InvalidArgumentException('There is no cancellation request awaiting review.');
        }

        $reason = trim((string) $request->message);
        $this->transition(
            $orderPublicId,
            OrderStatus::Cancelled,
            $actor,
            [
                'cancellation_reason_code' => 'customer_request',
                'reason' => $reason !== '' ? $reason : 'Customer cancellation request accepted by the store.',
                'cancellation_request_event_id' => (int) $request->id,
            ],
        );
    }

    public function rejectCancellationRequest(
        string $orderPublicId,
        string $reason,
        bool $preventFurtherAppeals,
        ActorContext $actor,
    ): void {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A customer-facing rejection reason is required.');
        }

        $this->transactions->run(function () use ($orderPublicId, $reason, $preventFurtherAppeals, $actor): void {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }
            if (! in_array((string) $order->status, ['new', 'confirmed', 'processing', 'ready', 'shipped'], true)) {
                throw new InvalidArgumentException('Cancellation requests cannot be reviewed after an order is completed, cancelled, or returned.');
            }

            $request = $this->pendingCancellationRequest((int) $order->id);
            if ($request === null) {
                throw new InvalidArgumentException('There is no cancellation request awaiting review.');
            }

            $rejectionCount = DB::table('store_order_events')
                ->where('order_id', $order->id)
                ->where('event_type', 'cancellation_rejected')
                ->count();
            if ($preventFurtherAppeals && $rejectionCount < 1) {
                throw new InvalidArgumentException('Further appeals can only be closed when rejecting the second or a later cancellation request.');
            }

            $this->events->record(
                (int) $order->id,
                'cancellation_rejected',
                $actor,
                $reason,
                (string) $order->status,
                (string) $order->status,
                [
                    'request_event_id' => (int) $request->id,
                    'rejection_number' => $rejectionCount + 1,
                    'prevent_further_appeals' => $preventFurtherAppeals,
                ],
                'customer',
            );
        });
    }

    public function acceptReturnRequest(
        string $orderPublicId,
        string $condition,
        bool $returnToStock,
        ActorContext $actor,
    ): void {
        $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            throw new InvalidArgumentException('Order does not exist.');
        }
        if ((string) $order->status !== OrderStatus::Delivered->value) {
            throw new InvalidArgumentException('Only a delivered order can have its return request accepted.');
        }

        $request = $this->pendingReturnRequest((int) $order->id);
        if ($request === null) {
            throw new InvalidArgumentException('There is no return request awaiting review.');
        }

        $this->transition(
            $orderPublicId,
            OrderStatus::Returned,
            $actor,
            [
                'reason' => trim((string) $request->message),
                'return_condition' => $condition,
                'return_to_stock' => $returnToStock,
                'return_request_event_id' => (int) $request->id,
            ],
        );
    }

    public function rejectReturnRequest(string $orderPublicId, string $reason, ActorContext $actor): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A customer-facing rejection reason is required.');
        }

        $this->transactions->run(function () use ($orderPublicId, $reason, $actor): void {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }
            if ((string) $order->status !== OrderStatus::Delivered->value) {
                throw new InvalidArgumentException('Return requests can only be reviewed while the order is delivered.');
            }

            $request = $this->pendingReturnRequest((int) $order->id);
            if ($request === null) {
                throw new InvalidArgumentException('There is no return request awaiting review.');
            }

            $this->events->record(
                (int) $order->id,
                'return_rejected',
                $actor,
                $reason,
                (string) $order->status,
                (string) $order->status,
                ['request_event_id' => (int) $request->id],
                'customer',
            );
        });
    }

    public function deleteCancelled(string $orderPublicId, string $confirmation): string
    {
        return $this->transactions->run(function () use ($orderPublicId, $confirmation): string {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }
            if ((string) $order->status !== OrderStatus::Cancelled->value) {
                throw new InvalidArgumentException('Only cancelled orders can be deleted permanently.');
            }
            if (! hash_equals((string) $order->order_number, trim($confirmation))) {
                throw new InvalidArgumentException('Type the exact order number to confirm permanent deletion.');
            }

            $reservations = DB::table('store_order_items as item')
                ->join('store_inventory_reservations as reservation', 'reservation.public_id', '=', 'item.inventory_reservation_public_id')
                ->where('item.order_id', $order->id)
                ->get(['reservation.public_id', 'reservation.status']);

            if ($reservations->contains(
                static fn (object $reservation): bool => $reservation->status === 'committed',
            )) {
                throw new InvalidArgumentException('This order has committed inventory and cannot be deleted.');
            }

            foreach ($reservations as $reservation) {
                if ($reservation->status === 'active') {
                    $this->inventory->release((string) $reservation->public_id);
                }
            }

            $reservationIds = $reservations->pluck('public_id')->all();
            if ($reservationIds !== []) {
                DB::table('store_inventory_reservations')->whereIn('public_id', $reservationIds)->delete();
            }

            DB::table('store_orders')->where('id', $order->id)->delete();

            return (string) $order->order_number;
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePayment(string $orderPublicId, array $data, ActorContext $actor): void
    {
        $this->transactions->run(function () use ($orderPublicId, $data, $actor): void {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }

            $status = (string) $data['payment_status'];
            if ($status === 'paid' && ! (bool) $this->settings->current()['cod_allow_collection']) {
                throw new InvalidArgumentException('Recording Cash on Delivery collection is disabled in Store settings.');
            }
            $collected = $status === 'paid' ? (int) $order->total_minor : 0;

            DB::table('store_orders')->where('id', $order->id)->update([
                'payment_status' => $status,
                'payment_collected_minor' => $collected,
                'payment_collected_at' => $status === 'paid' ? now() : null,
                'payment_note' => $this->nullable($data['payment_note'] ?? null),
                'updated_by' => $actor->id,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

            $this->events->record(
                (int) $order->id,
                'payment_updated',
                $actor,
                $this->nullable($data['payment_note'] ?? null) ?? "Payment status set to {$status}.",
                metadata: ['payment_status' => $status, 'collected_minor' => $collected],
            );

            if ($status === 'paid' && (string) $order->payment_status !== 'paid') {
                $this->externalLifecycle->recordOrder((int) $order->id, 'committed', 'payment-paid');
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function updateShipment(string $orderPublicId, array $data, ActorContext $actor): void
    {
        $this->transactions->run(function () use ($orderPublicId, $data, $actor): void {
            $order = DB::table('store_orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException('Order does not exist.');
            }

            $status = OrderStatus::from((string) $order->status);
            if (! in_array($status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Returned], true)) {
                throw new InvalidArgumentException('Shipment details can be edited after the order is shipped.');
            }

            $shipment = [
                'shipping_company' => $this->nullable($data['shipping_company'] ?? null),
                'courier_name' => $this->nullable($data['courier_name'] ?? null),
                'courier_phone' => $this->nullable($data['courier_phone'] ?? null),
                'tracking_number' => $this->nullable($data['tracking_number'] ?? null),
                'shipping_notes' => $this->nullable($data['shipping_notes'] ?? null),
            ];

            DB::table('store_orders')->where('id', $order->id)->update($shipment + [
                'updated_by' => $actor->id,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

            $this->events->record(
                (int) $order->id,
                'shipment_updated',
                $actor,
                'Shipment details updated.',
                metadata: array_filter($shipment, static fn (?string $value): bool => $value !== null),
            );
        });
    }

    /** @param array<string, mixed> $context */
    private function assertContext(OrderStatus $target, array $context): void
    {
        if ($target === OrderStatus::Cancelled
            && (trim((string) ($context['cancellation_reason_code'] ?? '')) === ''
                || trim((string) ($context['reason'] ?? '')) === '')) {
            throw new InvalidArgumentException('Cancellation reason and reason code are required.');
        }
        if ($target === OrderStatus::Returned
            && (trim((string) ($context['return_condition'] ?? '')) === ''
                || trim((string) ($context['reason'] ?? '')) === '')) {
            throw new InvalidArgumentException('Return reason and product condition are required.');
        }
    }

    private function pendingCancellationRequest(int $orderId): ?object
    {
        $events = DB::table('store_order_events')
            ->where('order_id', $orderId)
            ->whereIn('event_type', ['status_changed', 'cancellation_requested', 'cancellation_rejected'])
            ->orderBy('id')
            ->get();
        $request = $events->where('event_type', 'cancellation_requested')->last();
        if ($request === null) {
            return null;
        }

        return $events->contains(static fn (object $event): bool => in_array($event->event_type, ['status_changed', 'cancellation_rejected'], true)
            && (int) $event->id > (int) $request->id)
                ? null
                : $request;
    }

    private function pendingReturnRequest(int $orderId): ?object
    {
        $events = DB::table('store_order_events')
            ->where('order_id', $orderId)
            ->whereIn('event_type', ['status_changed', 'return_requested', 'return_rejected'])
            ->orderBy('id')
            ->get();
        $request = $events->where('event_type', 'return_requested')->last();
        if ($request === null) {
            return null;
        }

        return $events->contains(static fn (object $event): bool => in_array($event->event_type, ['status_changed', 'return_rejected'], true)
            && (int) $event->id > (int) $request->id)
                ? null
                : $request;
    }

    private function releaseReservations(int $orderId): void
    {
        foreach ($this->reservationIds($orderId) as $reservation) {
            if ($reservation->status === 'active') {
                $this->inventory->release((string) $reservation->public_id);
            }
        }
    }

    private function commitReservations(int $orderId, ActorContext $actor): void
    {
        foreach ($this->reservationIds($orderId) as $reservation) {
            if ($reservation->status === 'active') {
                $this->inventory->commit((string) $reservation->public_id, $actor);
            }
        }
    }

    private function keepReservationsActive(int $orderId): void
    {
        DB::table('store_inventory_reservations')
            ->where('status', 'active')
            ->whereIn('public_id', DB::table('store_order_items')->where('order_id', $orderId)->whereNotNull('inventory_reservation_public_id')->select('inventory_reservation_public_id'))
            ->update(['expires_at' => null, 'updated_at' => now()]);
    }

    private function restoreReturnedInventory(int $orderId, string $orderPublicId, ActorContext $actor): void
    {
        $rows = DB::table('store_order_items as item')
            ->join('store_inventory_reservations as reservation', 'reservation.public_id', '=', 'item.inventory_reservation_public_id')
            ->join('store_inventory_items as inventory', 'inventory.id', '=', 'reservation.inventory_item_id')
            ->where('item.order_id', $orderId)
            ->where('reservation.status', 'committed')
            ->get(['item.public_id as item_public_id', 'item.quantity', 'inventory.public_id as inventory_public_id']);

        foreach ($rows as $row) {
            $this->inventory->adjust(
                (string) $row->inventory_public_id,
                (int) $row->quantity,
                'customer_return',
                "order-return:{$orderPublicId}:{$row->item_public_id}",
                $actor,
                'Returned order approved for restocking.',
            );
        }
    }

    private function reservationIds(int $orderId): iterable
    {
        return DB::table('store_order_items as item')
            ->join('store_inventory_reservations as reservation', 'reservation.public_id', '=', 'item.inventory_reservation_public_id')
            ->where('item.order_id', $orderId)
            ->get(['reservation.public_id', 'reservation.status']);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
