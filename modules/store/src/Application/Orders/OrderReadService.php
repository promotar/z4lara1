<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Store\Application\Returns\ReturnWorkflowService;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Domain\Orders\OrderTransitionPolicy;

final readonly class OrderReadService
{
    public function __construct(
        private OrderTransitionPolicy $transitions,
        private ReturnWorkflowService $returns,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $itemTotals = DB::table('store_order_items')
            ->select([
                'order_id',
                DB::raw('COUNT(id) as items_count'),
                DB::raw('COALESCE(SUM(quantity), 0) as units_count'),
            ])
            ->groupBy('order_id');

        $query = DB::table('store_orders as orders')
            ->leftJoinSub($itemTotals, 'item_totals', 'item_totals.order_id', '=', 'orders.id')
            ->select([
                'orders.*',
                DB::raw('COALESCE(item_totals.items_count, 0) as items_count'),
                DB::raw('COALESCE(item_totals.units_count, 0) as units_count'),
            ]);

        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $canViewCustomer = (bool) ($filters['_can_view_customer'] ?? false);
            $query->where(function ($builder) use ($search, $canViewCustomer): void {
                $term = '%'.$search.'%';
                $builder->where('orders.order_number', 'like', $term);
                if ($canViewCustomer) {
                    $builder->orWhere('orders.customer_name', 'like', $term)
                        ->orWhere('orders.customer_phone', 'like', $term)
                        ->orWhere('orders.customer_email', 'like', $term);
                }
            });
        }

        foreach (['status', 'payment_method', 'area'] as $field) {
            if (($value = trim((string) ($filters[$field] ?? ''))) !== '') {
                $query->where('orders.'.$field, $value);
            }
        }

        if ((bool) ($filters['cancellation_requested'] ?? false)) {
            $query->whereExists(function ($requests): void {
                $requests->selectRaw('1')
                    ->from('store_order_events as cancellation_request')
                    ->whereColumn('cancellation_request.order_id', 'orders.id')
                    ->where('cancellation_request.event_type', 'cancellation_requested')
                    ->whereNotExists(function ($resolution): void {
                        $resolution->selectRaw('1')
                            ->from('store_order_events as cancellation_resolution')
                            ->whereColumn('cancellation_resolution.order_id', 'cancellation_request.order_id')
                            ->whereIn('cancellation_resolution.event_type', ['status_changed', 'cancellation_rejected', 'cancellation_accepted'])
                            ->whereColumn('cancellation_resolution.id', '>', 'cancellation_request.id');
                    });
            });
        }

        if ((bool) ($filters['return_requested'] ?? false)) {
            $query->whereExists(function ($requests): void {
                $requests->selectRaw('1')
                    ->from('store_return_requests as return_request')
                    ->whereColumn('return_request.order_id', 'orders.id')
                    ->whereIn('return_request.status', [
                        'requested', 'evidence_required', 'under_review', 'approved',
                        'in_transit', 'received', 'inspection', 'returned',
                        'refund_details_required', 'refund_details_submitted', 'refund_sent', 'refund_received',
                    ]);
            });
        }

        if (($from = $filters['date_from'] ?? null) !== null) {
            $query->whereDate('orders.placed_at', '>=', $from);
        }
        if (($to = $filters['date_to'] ?? null) !== null) {
            $query->whereDate('orders.placed_at', '<=', $to);
        }
        if (($minimum = $filters['min_total'] ?? null) !== null && $minimum !== '') {
            $query->where('orders.total_minor', '>=', $this->minor((string) $minimum));
        }
        if (($maximum = $filters['max_total'] ?? null) !== null && $maximum !== '') {
            $query->where('orders.total_minor', '<=', $this->minor((string) $maximum));
        }

        match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => $query->orderBy('orders.placed_at')->orderBy('orders.id'),
            'highest' => $query->orderByDesc('orders.total_minor')->orderByDesc('orders.placed_at'),
            'lowest' => $query->orderBy('orders.total_minor')->orderByDesc('orders.placed_at'),
            default => $query->orderByDesc('orders.placed_at')->orderByDesc('orders.id'),
        };

        return $query->paginate(25)->withQueryString();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::cases()), 0);

        foreach (DB::table('store_orders')->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')->get() as $row) {
            $counts[(string) $row->status] = (int) $row->aggregate;
        }

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /** @return array{order: object, items: Collection, events: Collection, allowed_transitions: list<OrderStatus>} */
    public function detail(string $publicId): array
    {
        $order = DB::table('store_orders')->where('public_id', $publicId)->first();
        abort_if($order === null, 404);

        return [
            'order' => $order,
            'items' => DB::table('store_order_items')->where('order_id', $order->id)->orderBy('id')->get(),
            'events' => DB::table('store_order_events')->where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get(),
            'allowed_transitions' => $this->transitions->allowed(OrderStatus::from((string) $order->status)),
        ] + $this->returns->viewData((int) $order->id);
    }

    private function minor(string $amount): int
    {
        return (int) round(((float) $amount) * 1000);
    }
}
