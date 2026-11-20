<?php

namespace Modules\Store\Application\Customer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Store\Application\Orders\OrderEventRecorder;
use Modules\Store\Application\Orders\OrderWorkflowService;
use Modules\Store\Application\Orders\StoreAdminNotificationService;
use Modules\Store\Application\Returns\ReturnWorkflowService;
use Modules\Store\Application\Reviews\ProductReviewService;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Domain\Shared\ActorContext;
use RuntimeException;

final readonly class CustomerOrderService
{
    public function __construct(
        private CartService $cart,
        private OrderWorkflowService $workflow,
        private OrderEventRecorder $events,
        private StoreAdminNotificationService $adminNotifications,
        private ProductReviewService $reviews,
        private ReturnWorkflowService $returns,
    ) {}

    public function paginate(int $userId, string $filter = 'all'): LengthAwarePaginator
    {
        $query = DB::table('store_orders as orders')
            ->select('orders.*')
            ->addSelect([
                'first_product_name' => DB::table('store_order_items')
                    ->select('product_name')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderBy('id')
                    ->limit(1),
                'first_image_url' => DB::table('store_order_items')
                    ->select('image_url')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderBy('id')
                    ->limit(1),
                'items_count' => DB::table('store_order_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('order_id', 'orders.id'),
            ])
            ->where('orders.created_by', $userId)
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('orders.id');

        match ($filter) {
            'current' => $query->whereIn('orders.status', ['new', 'confirmed', 'processing', 'ready', 'shipped']),
            'completed' => $query->where('orders.status', 'delivered'),
            'cancelled' => $query->where('orders.status', 'cancelled'),
            'returned' => $query->where('orders.status', 'returned'),
            default => null,
        };

        return $query->paginate(12)->withQueryString();
    }

    /** @return array<string, mixed> */
    public function detail(int $userId, string $publicId): array
    {
        $order = $this->ownedOrder($userId, $publicId);
        $items = DB::table('store_order_items')->where('order_id', $order->id)->orderBy('id')->get();
        $orderEvents = DB::table('store_order_events')
            ->where('order_id', $order->id)
            ->whereIn('event_type', [
                'created',
                'status_changed',
                'cancellation_requested',
                'cancellation_rejected',
                'cancellation_accepted',
                'return_requested',
                'return_rejected',
                'return_status_changed',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $timeline = $orderEvents->map(function (object $event): object {
            $status = $event->to_status === null ? null : OrderStatus::tryFrom((string) $event->to_status);

            $message = $status?->customerMessage();
            if ($status === OrderStatus::Cancelled && trim((string) $event->message) !== '') {
                $message = 'Cancellation reason: '.trim((string) $event->message);
            }

            $isCancellationRequest = $event->event_type === 'cancellation_requested';
            $isCancellationRejection = $event->event_type === 'cancellation_rejected';
            $isCancellationAcceptance = $event->event_type === 'cancellation_accepted';
            $isReturnRequest = $event->event_type === 'return_requested';
            $isReturnRejection = $event->event_type === 'return_rejected';
            $isReturnStatus = $event->event_type === 'return_status_changed';
            $returnMetadata = $isReturnStatus && filled($event->metadata)
                ? (json_decode((string) $event->metadata, true) ?: [])
                : [];
            $returnStatus = (string) ($returnMetadata['return_status'] ?? '');

            return (object) [
                'type' => (string) $event->event_type,
                'status' => $status?->value,
                'label' => $isCancellationRequest
                    ? 'Cancellation Requested'
                    : ($isCancellationRejection
                        ? 'Cancellation Request Rejected'
                        : ($isCancellationAcceptance
                            ? 'Cancellation Request Approved'
                            : ($isReturnRequest
                            ? 'Return Requested'
                            : ($isReturnRejection
                                ? 'Return Request Rejected'
                                : ($isReturnStatus ? 'Return - '.Str::headline($returnStatus) : ($status?->customerLabel() ?? 'Order Updated')))))),
                'message' => $isCancellationRequest
                    ? 'Your cancellation request was recorded for store review.'
                    : ($isCancellationRejection
                        ? 'Store response: '.trim((string) $event->message)
                        : ($isCancellationAcceptance
                            ? trim((string) $event->message)
                            : ($isReturnRequest
                            ? 'Your return request was recorded for store review.'
                            : ($isReturnRejection
                                ? 'Store response: '.trim((string) $event->message)
                                : ($isReturnStatus ? trim((string) $event->message) : $message))))),
                'created_at' => $event->created_at,
            ];
        });

        $status = OrderStatus::from((string) $order->status);
        $items = $this->reviews->decorateOrderItems($order, $items);
        $pendingRequest = $this->pendingCancellationRequest((int) $order->id, $orderEvents);
        $latestRejection = $orderEvents->where('event_type', 'cancellation_rejected')->last();
        $pendingReturnRequest = $this->pendingReturnRequest((int) $order->id, $orderEvents);
        $latestReturnRejection = $orderEvents->where('event_type', 'return_rejected')->last();
        $pendingReturnMetadata = $pendingReturnRequest === null ? [] : $this->eventMetadata($pendingReturnRequest);
        $returnData = $this->returns->viewData((int) $order->id);
        $returnProcess = $returnData['return_process'];
        $activeReturnStatuses = ['requested', 'evidence_required', 'under_review', 'approved', 'in_transit', 'received', 'inspection', 'returned', 'refund_details_required', 'refund_details_submitted', 'refund_sent', 'refund_received'];
        $appealBlocked = $this->cancellationAppealBlocked($orderEvents);
        $directCancellationStatuses = [OrderStatus::New, OrderStatus::Confirmed];
        $approvalCancellationStatuses = [OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Shipped];
        $canCancel = in_array($status, $directCancellationStatuses, true)
            && $pendingRequest === null
            && ! $appealBlocked;

        return [
            'order' => $order,
            'items' => $items,
            'timeline' => $timeline,
            'can_cancel' => $canCancel,
            'can_request_cancel' => in_array($status, $approvalCancellationStatuses, true)
                && $pendingRequest === null
                && ! $appealBlocked,
            'cancellation_request_pending' => $pendingRequest !== null,
            'cancellation_request_reason' => $pendingRequest?->message,
            'cancellation_rejection_reason' => $latestRejection?->message,
            'cancellation_appeal_blocked' => $appealBlocked,
            'cancellation_rejection_count' => $orderEvents->where('event_type', 'cancellation_rejected')->count(),
            'can_request_return' => $status === OrderStatus::Delivered
                && ($returnProcess === null || ! in_array((string) $returnProcess->status, $activeReturnStatuses, true)),
            'return_request_pending' => $returnProcess !== null && in_array((string) $returnProcess->status, $activeReturnStatuses, true),
            'return_request_reason' => $returnProcess?->reason ?? $pendingReturnRequest?->message,
            'return_request_condition' => $returnProcess?->reported_condition ?? ($pendingReturnMetadata['condition'] ?? null),
            'return_rejection_reason' => $returnProcess?->status === 'rejected' ? $returnProcess->admin_response : $latestReturnRejection?->message,
            'invoice_available' => $order->invoice_path !== null
                && trim((string) $order->invoice_path) !== ''
                && Storage::disk('local')->exists((string) $order->invoice_path),
        ] + $returnData;
    }

    /** @return array{added: list<string>, unavailable: list<string>} */
    public function reorder(int $userId, string $publicId, Session $session): array
    {
        $order = $this->ownedOrder($userId, $publicId);
        $items = DB::table('store_order_items')->where('order_id', $order->id)->orderBy('id')->get();
        $added = [];
        $unavailable = [];
        $this->cart->removeCoupon($session);

        foreach ($items as $item) {
            try {
                $this->cart->add($session, (string) $item->variant_public_id, (int) $item->quantity);
                $added[] = (string) $item->product_name;
            } catch (RuntimeException) {
                $unavailable[] = (string) $item->product_name;
            }
        }

        return ['added' => $added, 'unavailable' => $unavailable];
    }

    public function cancel(int $userId, string $publicId, string $reason, string $userName): void
    {
        $order = $this->ownedOrder($userId, $publicId);
        $status = OrderStatus::from((string) $order->status);
        if (! in_array($status, [OrderStatus::New, OrderStatus::Confirmed], true)) {
            throw new InvalidArgumentException('This order can no longer be cancelled directly.');
        }
        $cancellationEvents = $this->cancellationEvents((int) $order->id);
        if ($this->cancellationAppealBlocked($cancellationEvents)) {
            throw new InvalidArgumentException('The store has closed further cancellation appeals for this order.');
        }
        if ($this->pendingCancellationRequest((int) $order->id, $cancellationEvents) !== null) {
            throw new InvalidArgumentException('A cancellation request is already awaiting store review.');
        }

        $this->workflow->transition(
            $publicId,
            OrderStatus::Cancelled,
            new ActorContext('user', (string) $userId, $userName),
            ['cancellation_reason_code' => 'customer_request', 'reason' => trim($reason)],
        );
    }

    public function requestCancellation(int $userId, string $publicId, string $reason, string $userName): void
    {
        $order = $this->ownedOrder($userId, $publicId);
        $status = OrderStatus::from((string) $order->status);
        if (! in_array($status, [OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Shipped], true)) {
            if (in_array($status, [OrderStatus::New, OrderStatus::Confirmed], true)) {
                throw new InvalidArgumentException('This order can be cancelled directly without store approval.');
            }
            throw new InvalidArgumentException('A cancellation request is not available for a completed, cancelled, or returned order.');
        }
        $cancellationEvents = $this->cancellationEvents((int) $order->id);
        if ($this->cancellationAppealBlocked($cancellationEvents)) {
            throw new InvalidArgumentException('The store has closed further cancellation appeals for this order.');
        }
        if ($this->pendingCancellationRequest((int) $order->id, $cancellationEvents) !== null) {
            throw new InvalidArgumentException('A cancellation request is already awaiting store review.');
        }

        $this->events->record(
            (int) $order->id,
            'cancellation_requested',
            new ActorContext('user', (string) $userId, $userName),
            trim($reason),
            fromStatus: (string) $order->status,
            toStatus: (string) $order->status,
            visibility: 'customer',
        );
        $this->adminNotifications->send(
            'cancellation_request',
            "Cancellation requested for {$order->order_number}",
            "The customer requested cancellation for order {$order->order_number}.",
        );
    }

    public function requestReturn(
        int $userId,
        string $publicId,
        string $reason,
        string $condition,
        string $preferredResolution,
        string $userName,
    ): void {
        $this->returns->createRequest($userId, $publicId, $reason, $condition, $preferredResolution, $userName);
    }

    public function ownedOrder(int $userId, string $publicId): object
    {
        $order = DB::table('store_orders')
            ->where('created_by', $userId)
            ->where('public_id', $publicId)
            ->first();

        return $order ?? throw new CustomerResourceNotFound('Order does not exist.');
    }

    private function pendingCancellationRequest(int $orderId, ?Collection $events = null): ?object
    {
        $events ??= $this->cancellationEvents($orderId);
        $request = $events->where('event_type', 'cancellation_requested')->last();
        if ($request === null) {
            return null;
        }

        return $events->contains(fn (object $event): bool => in_array($event->event_type, ['status_changed', 'cancellation_rejected', 'cancellation_accepted'], true) && (int) $event->id > (int) $request->id)
            ? null
            : $request;
    }

    private function cancellationEvents(int $orderId): Collection
    {
        return DB::table('store_order_events')
            ->where('order_id', $orderId)
            ->whereIn('event_type', ['status_changed', 'cancellation_requested', 'cancellation_rejected', 'cancellation_accepted'])
            ->orderBy('id')
            ->get();
    }

    private function pendingReturnRequest(int $orderId, ?Collection $events = null): ?object
    {
        $events ??= $this->returnEvents($orderId);
        $request = $events->where('event_type', 'return_requested')->last();
        if ($request === null) {
            return null;
        }

        return $events->contains(fn (object $event): bool => in_array($event->event_type, ['status_changed', 'return_rejected'], true)
            && (int) $event->id > (int) $request->id)
                ? null
                : $request;
    }

    private function returnEvents(int $orderId): Collection
    {
        return DB::table('store_order_events')
            ->where('order_id', $orderId)
            ->whereIn('event_type', ['status_changed', 'return_requested', 'return_rejected'])
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function eventMetadata(object $event): array
    {
        if (! filled($event->metadata ?? null)) {
            return [];
        }

        $metadata = json_decode((string) $event->metadata, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function cancellationAppealBlocked(Collection $events): bool
    {
        return $events->where('event_type', 'cancellation_rejected')->contains(function (object $event): bool {
            $metadata = filled($event->metadata ?? null)
                ? json_decode((string) $event->metadata, true)
                : [];

            return (bool) ($metadata['prevent_further_appeals'] ?? false);
        });
    }
}
