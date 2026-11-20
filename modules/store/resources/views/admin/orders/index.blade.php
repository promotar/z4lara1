<x-app-layout>
@php
    $storeDate = app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class);
    $isCancellationRequests = (bool) ($filters['cancellation_requested'] ?? false);
    $isReturnRequests = (bool) ($filters['return_requested'] ?? false);
    $ordersPageTitle = match (true) {
        $isCancellationRequests => __('store::ui.admin_orders_cancel_title'),
        $isReturnRequests => __('store::ui.admin_orders_return_title'),
        ($filters['status'] ?? null) === 'cancelled' => __('store::ui.admin_orders_cancelled_title'),
        default => __('store::ui.admin_orders_title'),
    };
    $ordersPageDescription = match (true) {
        $isCancellationRequests => __('store::ui.admin_orders_cancel_desc'),
        $isReturnRequests => __('store::ui.admin_orders_return_desc'),
        default => __('store::ui.admin_orders_desc'),
    };
@endphp
<main class="z4-store-admin z4-store-orders-page">
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.admin_orders_eyebrow') }}</span>
                <h1>{{ $ordersPageTitle }}</h1>
                <p>{{ $ordersPageDescription }}</p>
            </div>
        </header>

        <nav class="z4-order-tabs" aria-label="{{ __('store::ui.auto_b88da0320f') }}">
            <a class="{{ empty($filters['status']) && !$isCancellationRequests && !$isReturnRequests ? 'is-active' : '' }}" href="{{ route('admin.plugins.store.orders.index') }}">{{ __('store::ui.auto_b1c94ca2fb') }} <span>{{ $counts['all'] }}</span></a>
            @foreach($statuses as $status)
                <a class="{{ ($filters['status'] ?? '') === $status->value ? 'is-active' : '' }}" href="{{ route('admin.plugins.store.orders.index', ['status' => $status->value]) }}">{{ $status->label() }} <span>{{ $counts[$status->value] ?? 0 }}</span></a>
            @endforeach
        </nav>

        <form class="z4-store-filter-card z4-order-filters" method="get">
            @if($isCancellationRequests)<input type="hidden" name="cancellation_requested" value="1">@endif
            @if($isReturnRequests)<input type="hidden" name="return_requested" value="1">@endif
            <label class="is-wide"><span>{{ __('store::ui.auto_13348442cc') }}</span><input class="z4-store-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $canViewCustomer ? 'Order number, customer, phone or email' : 'Order number' }}"></label>
            <label><span>{{ __('store::ui.auto_ec53a8c4f0') }}</span><select class="z4-store-select" name="status"><option value="">{{ __('store::ui.auto_96e3d39826') }}</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label><span>{{ __('store::ui.auto_c453a4b8e8') }}</span><select class="z4-store-select" name="payment_method"><option value="">{{ __('store::ui.auto_0b58e10c12') }}</option><option value="cash_on_delivery" @selected(($filters['payment_method'] ?? '') === 'cash_on_delivery')>{{ __('store::ui.auto_469ded86e4') }}</option></select></label>
            <label><span>{{ __('store::ui.auto_5da618e8e4') }}</span><input class="z4-store-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label><span>To</span><input class="z4-store-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label><span>{{ __('store::ui.auto_deec4ff199') }}</span><input class="z4-store-input" name="area" value="{{ $filters['area'] ?? '' }}"></label>
            <label><span>{{ __('store::ui.auto_91e4f878a2') }}</span><input class="z4-store-input" type="number" min="0" step="0.001" name="min_total" value="{{ $filters['min_total'] ?? '' }}"></label>
            <label><span>{{ __('store::ui.auto_7b6ceaca1b') }}</span><input class="z4-store-input" type="number" min="0" step="0.001" name="max_total" value="{{ $filters['max_total'] ?? '' }}"></label>
            <label><span>{{ __('store::ui.auto_6b46ae4842') }}</span><select class="z4-store-select" name="sort">@foreach(['newest'=>__('store::ui.admin_orders_newest'),'oldest'=>__('store::ui.admin_orders_oldest'),'highest'=>__('store::ui.admin_orders_highest'),'lowest'=>__('store::ui.admin_orders_lowest')] as $value=>$label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="z4-store-filter-actions"><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_79ee641715') }}</button><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.orders.index') }}">{{ __('store::ui.auto_dc30bc0c79') }}</a></div>
        </form>

        <section class="z4-store-table-card">
            <header class="z4-store-table-card-header"><div><h2>{{ $ordersPageTitle }}</h2><p>{{ __('store::ui.auto_dbdd214512') }}</p></div></header>
            <div class="z4-store-table-scroll">
                <table class="z4-store-table z4-order-table">
                    <thead><tr><th>{{ __('store::ui.auto_a240fa2792') }}</th><th>{{ __('store::ui.auto_ce26601dac') }}</th><th>{{ __('store::ui.auto_f429e45eaf') }}</th><th>{{ __('store::ui.auto_9dea4016db') }}</th><th>{{ __('store::ui.auto_96b0141273') }}</th><th>{{ __('store::ui.auto_c453a4b8e8') }}</th><th>{{ __('store::ui.auto_deec4ff199') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($orders as $order)
                        @php($digits = strtoupper($order->currency) === 'JOD' ? 3 : 2)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong><small>{{ $order->public_id }}</small></td>
                            <td>
                                @if($canViewCustomer)
                                    <strong>{{ $order->customer_name }}</strong><small>{{ $order->customer_phone }}</small>
                                @else
                                    <span>{{ __('store::ui.auto_ecdee5de15') }}</span>
                                @endif
                            </td>
                            <td><time>{{ $storeDate->format($order->placed_at, 'Y-m-d H:i') }}</time><span class="z4-order-timezone-label">· {{ $storeDate->countryTimeLabel() }}</span></td>
                            <td>{{ __('store::ui.auto_db859b8cb5') }}</td>
                            <td><strong>{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int)$order->total_minor, $order->currency) }}</strong></td>
                            <td><span>COD</span><small>{{ str_replace('_', ' ', $order->payment_status) }}</small></td>
                            <td>{{ $order->area ?: $order->city }}</td>
                            <td><span class="z4-order-status is-{{ $order->status }}">{{ \Modules\Store\Domain\Orders\OrderStatus::from($order->status)->label() }}</span></td>
                            <td>
                                <div class="z4-store-heading-actions">
                                    <a class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" href="{{ route('admin.plugins.store.orders.show', $order->public_id) }}">{{ __('store::ui.auto_c3bf447eab') }}</a>
                                    @if($order->status === 'cancelled')
                                        @can('store.orders.delete')
                                            <form method="post"
                                                  action="{{ route('admin.plugins.store.orders.destroy', $order->public_id) }}"
                                                  data-z4-confirm
                                                  data-confirm-title="{{ __('store::ui.auto_c1e4be4e43') }}"
                                                  data-confirm-message="This will permanently remove the order, its products, timeline, coupon usage, and released inventory reservations."
                                                  data-confirm-label="Delete permanently"
                                                  data-confirm-require="{{ $order->order_number }}"
                                                  data-confirm-input="confirmation"
                                                  data-confirm-tone="danger">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="confirmation" value="">
                                                <button class="z4-store-btn z4-store-btn-danger z4-store-btn-small" type="submit">{{ __('store::ui.auto_f2a6c498fb') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="z4-store-empty"><strong>{{ __('store::ui.auto_a2ed6302e7') }}</strong><p>{{ __('store::ui.auto_8bb72b2097') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-platform-pagination :paginator="$orders" />
        </section>
    </div>
</main>
</x-app-layout>
