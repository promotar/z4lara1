<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Reports\StoreReportingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StoreReportController extends Controller
{
    public function index(Request $request, StoreReportingService $reports): View
    {
        $filters = $this->filters($request);

        return view('store::admin.reports.index', [
            'report' => $reports->report($filters, $request->user()->can('store.orders.customer.view')),
            'filters' => $filters,
        ]);
    }

    public function print(Request $request, StoreReportingService $reports): View
    {
        $filters = $this->filters($request);

        return view('store::admin.reports.print', [
            'report' => $reports->report($filters, $request->user()->can('store.orders.customer.view')),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, StoreReportingService $reports): StreamedResponse
    {
        $filters = $this->filters($request);
        $type = $request->validate([
            'type' => ['required', 'in:sales,orders,products,inventory'],
        ])['type'];
        $report = $reports->report($filters, $request->user()->can('store.orders.customer.view'));
        $filename = "store-{$type}-{$report['period']['from_date']}-{$report['period']['to_date']}.csv";

        $canViewCustomer = $request->user()->can('store.orders.customer.view');

        return response()->streamDownload(function () use ($type, $report, $reports, $filters, $canViewCustomer): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            if ($type === 'sales') {
                fputcsv($stream, ['period', 'sales', 'paid_orders', 'average_order', 'discounts', 'shipping', 'tax']);
                fputcsv($stream, [
                    $report['period']['label'],
                    $report['metrics']['sales_formatted'],
                    $report['metrics']['realized_orders'],
                    $report['metrics']['average_order_formatted'],
                    $report['metrics']['discounts_formatted'],
                    $report['metrics']['shipping_formatted'],
                    $report['metrics']['tax_formatted'],
                ]);
            } elseif ($type === 'orders') {
                fputcsv($stream, ['order_number', 'customer', 'placed_at', 'status', 'payment_status', 'total']);
                foreach ($reports->exportOrders($filters, $canViewCustomer) as $order) {
                    fputcsv($stream, [
                        $order['order_number'], $order['customer_name'], $order['placed_at'],
                        $order['status'], $order['payment_status'], $order['total_formatted'],
                    ]);
                }
            } elseif ($type === 'products') {
                fputcsv($stream, ['product', 'quantity_sold', 'sales']);
                foreach ($report['best_products'] as $product) {
                    fputcsv($stream, [$product['name'], $product['quantity'], $product['sales_formatted']]);
                }
            } else {
                fputcsv($stream, ['product', 'sku', 'on_hand', 'reserved', 'available', 'low_stock_threshold']);
                foreach ($reports->exportInventory() as $item) {
                    fputcsv($stream, [
                        $item['product_name'], $item['sku'], $item['on_hand'],
                        $item['reserved'], $item['available'], $item['safety_stock'],
                    ]);
                }
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $request->merge(['period' => $request->input('period', 'last30')]);

        return $request->validate([
            'period' => ['required', 'in:today,last7,last30,this_month,previous_month,custom'],
            'date_from' => ['nullable', 'required_if:period,custom', 'date'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:date_from'],
        ]);
    }
}
