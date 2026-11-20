<?php

namespace Modules\Store\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Application\Settings\StoreMoneyFormatter;

final class StoreAccountSectionProvider
{
    public function __construct(private readonly StoreMoneyFormatter $money) {}

    /** @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    public function append(array $sections, User $user): array
    {
        if (! Schema::hasTable('store_orders')) {
            return $sections;
        }

        $orders = DB::table('store_orders')
            ->where('created_by', $user->getKey())
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['public_id', 'order_number', 'status', 'currency', 'total_minor', 'placed_at'])
            ->map(function (object $order): object {
                $order->total_formatted = $this->money->format((int) $order->total_minor, (string) $order->currency);

                return $order;
            });
        $total = DB::table('store_orders')->where('created_by', $user->getKey())->count();

        $sections[] = [
            'key' => 'store',
            'label' => __('store::ui.account_customer_area'),
            'icon' => 'S',
            'order' => 30,
            'badge' => (string) $total,
            'view' => 'store::account.summary',
            'data' => [
                'orders' => $orders,
                'ordersUrl' => route('store.account.orders.index'),
            ],
        ];

        return $sections;
    }
}
