<?php

namespace Modules\Store\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Store\Application\Catalog\CatalogTranslationSelector;
use Modules\Store\Application\Settings\StoreDateTimeFormatter;
use Modules\Store\Application\Settings\StoreMoneyFormatter;
use Modules\Store\Application\Settings\StoreSettingsService;

final readonly class StoreReportingService
{
    public function __construct(
        private ConnectionInterface $database,
        private StoreMoneyFormatter $money,
        private StoreSettingsService $settings,
        private StoreDateTimeFormatter $dateTime,
        private CatalogTranslationSelector $translations,
    ) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function report(array $filters, bool $canViewCustomer = true): array
    {
        $period = $this->period($filters);
        $summary = $this->summary($period);
        $orders = $this->orders($period, 250);
        $productPerformance = $this->productPerformance($period);
        $inventory = $this->inventory(100);
        $salesMinor = $summary['sales_minor'];
        $realizedOrders = $summary['realized_orders'];
        $deliveredOrders = $summary['status_counts']['delivered'];

        return [
            'period' => $period,
            'metrics' => [
                'orders' => $summary['orders'],
                'new_orders' => $summary['status_counts']['new'],
                'processing_orders' => $summary['status_counts']['processing'],
                'realized_orders' => $realizedOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $summary['status_counts']['cancelled'],
                'returned_orders' => $summary['status_counts']['returned'],
                'sales_minor' => $salesMinor,
                'sales_formatted' => $this->money->format($salesMinor),
                'average_order_minor' => $realizedOrders === 0 ? 0 : intdiv($salesMinor, $realizedOrders),
                'average_order_formatted' => $this->money->format($realizedOrders === 0 ? 0 : intdiv($salesMinor, $realizedOrders)),
                'discounts_minor' => $summary['discounts_minor'],
                'discounts_formatted' => $this->money->format($summary['discounts_minor']),
                'shipping_minor' => $summary['shipping_minor'],
                'shipping_formatted' => $this->money->format($summary['shipping_minor']),
                'tax_minor' => $summary['tax_minor'],
                'tax_formatted' => $this->money->format($summary['tax_minor']),
                'completion_rate' => $summary['orders'] === 0 ? 0.0 : round(($deliveredOrders / $summary['orders']) * 100, 1),
                'low_stock_products' => $inventory['metrics']['low_stock'],
                'out_of_stock_products' => $inventory['metrics']['out_of_stock'],
                'inventory_quantity' => $inventory['metrics']['available_quantity'],
            ],
            'status_counts' => $summary['status_counts'],
            'sales_chart' => $this->salesChart($period),
            'orders' => $orders->sortByDesc('placed_at')->map(
                fn (object $order): array => [
                    'public_id' => (string) $order->public_id,
                    'order_number' => (string) $order->order_number,
                    'customer_name' => $canViewCustomer ? (string) $order->customer_name : 'Restricted',
                    'placed_at' => $this->dateTime->format((string) $order->placed_at, 'Y-m-d H:i:s', $period['timezone']),
                    'status' => (string) $order->status,
                    'payment_status' => (string) $order->payment_status,
                    'total_minor' => (int) $order->total_minor,
                    'total_formatted' => $this->money->format((int) $order->total_minor, (string) $order->currency),
                ],
            )->values(),
            'orders_truncated' => $summary['orders'] > $orders->count(),
            'recent_orders' => $orders->sortByDesc('placed_at')->take(10)->map(
                fn (object $order): array => [
                    'public_id' => (string) $order->public_id,
                    'order_number' => (string) $order->order_number,
                    'customer_name' => $canViewCustomer ? (string) $order->customer_name : 'Restricted',
                    'placed_at' => $this->dateTime->format((string) $order->placed_at, 'Y-m-d H:i:s', $period['timezone']),
                    'total_formatted' => $this->money->format((int) $order->total_minor, (string) $order->currency),
                    'status' => (string) $order->status,
                ],
            )->values(),
            'low_stock' => $inventory['low_stock'],
            'out_of_stock' => $inventory['out_of_stock'],
            'inventory' => $inventory['all'],
            'inventory_truncated' => $inventory['metrics']['total'] > $inventory['all']->count(),
            'best_products' => $productPerformance['best_products'],
            'least_products' => $productPerformance['least_products'],
            'best_variants' => $productPerformance['best_variants'],
        ];
    }

    /** @param array<string, mixed> $filters @return array{preset:string,label:string,from:CarbonImmutable,to:CarbonImmutable,from_date:string,to_date:string,timezone:string} */
    private function period(array $filters): array
    {
        $timezone = (string) $this->settings->current()['timezone'];
        $now = CarbonImmutable::now($timezone);
        $preset = (string) ($filters['period'] ?? 'last30');
        [$from, $to, $label] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay(), __('store::ui.admin_range_today')],
            'last7' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), __('store::ui.admin_range_last7')],
            'this_month' => [$now->startOfMonth(), $now->endOfDay(), __('store::ui.admin_range_this_month')],
            'previous_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), __('store::ui.admin_range_prev_month')],
            'custom' => [
                CarbonImmutable::parse((string) $filters['date_from'], $timezone)->startOfDay(),
                CarbonImmutable::parse((string) $filters['date_to'], $timezone)->endOfDay(),
                __('store::ui.admin_range_custom'),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay(), __('store::ui.admin_range_last30')],
        };

        $requestedFrom = trim((string) ($filters['date_from'] ?? ''));
        $requestedTo = trim((string) ($filters['date_to'] ?? ''));
        if ($preset !== 'custom' && $requestedFrom !== '' && $requestedTo !== '') {
            $explicitFrom = CarbonImmutable::parse($requestedFrom, $timezone)->startOfDay();
            $explicitTo = CarbonImmutable::parse($requestedTo, $timezone)->endOfDay();

            if ($explicitFrom->toDateString() !== $from->toDateString()
                || $explicitTo->toDateString() !== $to->toDateString()) {
                $preset = 'custom';
                $from = $explicitFrom;
                $to = $explicitTo;
                $label = __('store::ui.admin_range_custom');
            }
        }

        return [
            'preset' => $preset,
            'label' => $label,
            'from' => $from->utc(),
            'to' => $to->utc(),
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'timezone' => $timezone,
        ];
    }

    /** @param array<string, mixed> $filters @return iterable<array<string, mixed>> */
    public function exportOrders(array $filters, bool $canViewCustomer = true): iterable
    {
        $period = $this->period($filters);
        foreach ($this->database->table('store_orders')
            ->whereBetween('placed_at', [$period['from'], $period['to']])
            ->orderBy('id')
            ->cursor() as $order) {
            yield [
                'order_number' => (string) $order->order_number,
                'customer_name' => $canViewCustomer ? (string) $order->customer_name : 'Restricted',
                'placed_at' => $this->dateTime->format((string) $order->placed_at, 'Y-m-d H:i:s', $period['timezone']),
                'status' => (string) $order->status,
                'payment_status' => (string) $order->payment_status,
                'total_formatted' => $this->money->format((int) $order->total_minor, (string) $order->currency),
            ];
        }
    }

    /** @return iterable<array<string, mixed>> */
    public function exportInventory(): iterable
    {
        foreach ($this->inventoryQuery()->orderBy('inventory.id')->cursor() as $row) {
            yield $this->inventoryRow($row);
        }
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable} $period @return array<string, mixed> */
    private function summary(array $period): array
    {
        $statuses = ['new', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'];
        $query = $this->database->table('store_orders')
            ->whereBetween('placed_at', [$period['from'], $period['to']])
            ->selectRaw('COUNT(*) as orders');
        foreach ($statuses as $status) {
            $query->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as {$status}_count", [$status]);
        }
        $query
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' AND status NOT IN ('cancelled', 'returned') THEN 1 ELSE 0 END) as realized_count")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' AND status NOT IN ('cancelled', 'returned') THEN total_minor ELSE 0 END) as sales_minor")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' AND status NOT IN ('cancelled', 'returned') THEN discount_minor ELSE 0 END) as discounts_minor")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' AND status NOT IN ('cancelled', 'returned') THEN shipping_minor ELSE 0 END) as shipping_minor")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' AND status NOT IN ('cancelled', 'returned') THEN tax_minor ELSE 0 END) as tax_minor");
        $row = $query->first();
        $statusCounts = [];
        foreach ($statuses as $status) {
            $statusCounts[$status] = (int) ($row->{$status.'_count'} ?? 0);
        }

        return [
            'orders' => (int) ($row->orders ?? 0),
            'realized_orders' => (int) ($row->realized_count ?? 0),
            'sales_minor' => (int) ($row->sales_minor ?? 0),
            'discounts_minor' => (int) ($row->discounts_minor ?? 0),
            'shipping_minor' => (int) ($row->shipping_minor ?? 0),
            'tax_minor' => (int) ($row->tax_minor ?? 0),
            'status_counts' => $statusCounts,
        ];
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable} $period */
    private function orders(array $period, int $limit): Collection
    {
        return $this->database->table('store_orders')
            ->whereBetween('placed_at', [$period['from'], $period['to']])
            ->orderByDesc('placed_at')
            ->limit($limit)
            ->get([
                'id', 'public_id', 'order_number', 'status', 'payment_status', 'currency',
                'customer_name', 'subtotal_minor', 'discount_minor', 'shipping_minor',
                'tax_minor', 'total_minor', 'payment_collected_minor', 'placed_at',
            ]);
    }

    /** @return array{all:Collection,low_stock:Collection,out_of_stock:Collection,metrics:array<string,int>} */
    private function inventory(int $limit): array
    {
        $metrics = $this->database->table('store_inventory_items')
            ->where('tracking_mode', 'tracked')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN (on_hand - reserved) <= safety_stock THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('SUM(CASE WHEN (on_hand - reserved) <= 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(on_hand - reserved) as available_quantity')
            ->first();
        $all = $this->inventoryQuery()
            ->orderByRaw('(inventory.on_hand - inventory.reserved) ASC')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->inventoryRow($row));

        return [
            'all' => $all->values(),
            'low_stock' => $all->filter(fn (array $item): bool => $item['available'] <= $item['safety_stock'])->take(10)->values(),
            'out_of_stock' => $all->filter(fn (array $item): bool => $item['available'] <= 0)->take(10)->values(),
            'metrics' => [
                'total' => (int) ($metrics->total ?? 0),
                'low_stock' => (int) ($metrics->low_stock ?? 0),
                'out_of_stock' => (int) ($metrics->out_of_stock ?? 0),
                'available_quantity' => (int) ($metrics->available_quantity ?? 0),
            ],
        ];
    }

    private function inventoryQuery(): mixed
    {
        $locale = (string) app()->getLocale();

        return $this->database->table('store_inventory_items as inventory')
            ->join('store_product_variants as variant', 'variant.id', '=', 'inventory.variant_id')
            ->join('store_products as product', 'product.id', '=', 'variant.product_id')
            ->leftJoinSub($this->translations->products($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->leftJoin('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->where('inventory.tracking_mode', 'tracked')
            ->select([
                'inventory.public_id',
                'variant.public_id as variant_public_id',
                'variant.sku',
                'translation.name as product_name',
                'inventory.on_hand',
                'inventory.reserved',
                'inventory.safety_stock',
            ]);
    }

    /** @return array<string, mixed> */
    private function inventoryRow(object $row): array
    {
        $available = (int) $row->on_hand - (int) $row->reserved;

        return [
            'public_id' => (string) $row->public_id,
            'variant_public_id' => (string) $row->variant_public_id,
            'product_name' => (string) ($row->product_name ?? 'Unnamed product'),
            'sku' => (string) ($row->sku ?? ''),
            'on_hand' => (int) $row->on_hand,
            'reserved' => (int) $row->reserved,
            'available' => $available,
            'safety_stock' => (int) $row->safety_stock,
        ];
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable} $period @return array<string, Collection> */
    private function productPerformance(array $period): array
    {
        // Order items already store their net line total after the allocated order discount.
        $revenue = 'item.line_total_minor';
        $base = $this->database->table('store_order_items as item')
            ->join('store_orders as sale_order', 'sale_order.id', '=', 'item.order_id')
            ->whereBetween('sale_order.placed_at', [$period['from'], $period['to']])
            ->where('sale_order.payment_status', 'paid');
        $base->whereNotIn('sale_order.status', ['cancelled', 'returned']);

        $productSelect = fn ($query) => $query
            ->select([
                'item.product_public_id as public_id',
                'item.product_name as name',
                'item.image_url',
            ])
            ->selectRaw('SUM(item.quantity) as quantity')
            ->selectRaw("SUM({$revenue}) as sales_minor")
            ->groupBy('item.product_public_id', 'item.product_name', 'item.image_url');
        $bestProducts = $productSelect(clone $base)->orderByDesc('quantity')->limit(10)->get();
        $leastProducts = $productSelect(clone $base)->orderBy('quantity')->limit(10)->get();
        $bestVariants = (clone $base)
            ->select([
                'item.variant_public_id as public_id',
                'item.product_name',
                'item.variant_name',
                'item.sku',
            ])
            ->selectRaw('SUM(item.quantity) as quantity')
            ->selectRaw("SUM({$revenue}) as sales_minor")
            ->groupBy('item.variant_public_id', 'item.product_name', 'item.variant_name', 'item.sku')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get();

        $format = fn (Collection $rows): Collection => $rows->map(function (object $row): array {
            $result = (array) $row;
            $result['quantity'] = (int) $row->quantity;
            $result['sales_minor'] = (int) $row->sales_minor;
            $result['sales_formatted'] = $this->money->format((int) $row->sales_minor);

            return $result;
        })->values();

        return [
            'best_products' => $format($bestProducts),
            'least_products' => $format($leastProducts),
            'best_variants' => $format($bestVariants),
        ];
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable} $period */
    private function salesChart(array $period): Collection
    {
        $monthly = $period['from']->diffInDays($period['to']) > 62;
        $driver = $this->database->getDriverName();
        $bucket = $driver === 'sqlite'
            ? ($monthly ? "strftime('%Y-%m', placed_at)" : "strftime('%Y-%m-%d', placed_at)")
            : ($monthly ? "DATE_FORMAT(placed_at, '%Y-%m')" : "DATE_FORMAT(placed_at, '%Y-%m-%d')");
        $rows = $this->database->table('store_orders')
            ->whereBetween('placed_at', [$period['from'], $period['to']])
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->selectRaw("{$bucket} as period_label")
            ->selectRaw('SUM(total_minor) as sales_minor')
            ->groupByRaw($bucket)
            ->orderBy('period_label')
            ->get();
        $maximum = max([1, ...$rows->map(fn (object $row): int => (int) $row->sales_minor)->all()]);

        return $rows->map(fn (object $row): array => [
            'label' => (string) $row->period_label,
            'sales_minor' => (int) $row->sales_minor,
            'sales_formatted' => $this->money->format((int) $row->sales_minor),
            'percent' => round(((int) $row->sales_minor / $maximum) * 100, 2),
        ])->values();
    }
}
