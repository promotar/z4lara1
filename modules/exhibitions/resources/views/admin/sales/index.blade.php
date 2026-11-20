@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-sales-1">
@endpush

<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading">
        <div>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_7fb724ffaf') }}</span>
            <h1>{{ __('exhibitions::ui.auto_086e032128') }}</h1>
            <p>{{ __('exhibitions::ui.auto_d937f49638') }}</p>
        </div>
        <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.settlements.index') }}">{{ __('exhibitions::ui.auto_a6d864f636') }}</a>
    </header>

    <aside class="z4-exhibitions-notice" role="note">
        <strong>{{ __('exhibitions::ui.auto_d1ece4a3f3') }}</strong>
        <p>{{ __('exhibitions::ui.auto_c64f82e5ec') }}</p>
    </aside>

    <form class="z4-exhibitions-filters z4-sales-filters" method="get">
        <label>{{ __('exhibitions::ui.auto_13348442cc') }}<input name="search" value="{{ $search }}" placeholder="{{ __('exhibitions::ui.auto_5feda9888d') }}"></label>
        <label>{{ __('exhibitions::ui.auto_57297718fd') }}<select name="order_state">
            <option value="">{{ __('exhibitions::ui.auto_38db97f111') }}</option>
            @foreach(['new','confirmed','processing','ready','shipped','delivered','cancelled','returned'] as $state)
                <option value="{{ $state }}" @selected($orderState === $state)>{{ __('exhibitions::ui.state_'.$state) }}</option>
            @endforeach
        </select></label>
        <label>{{ __('exhibitions::ui.auto_281ccf41d5') }}<select name="settlement_state">
            <option value="">{{ __('exhibitions::ui.auto_f093416a7d') }}</option>
            @foreach(['pending_sale','awaiting_delivery','return_window','eligible_for_settlement','payout_requested','approved','scheduled_for_payment','payment_sent','paid','held','reversed','failed'] as $state)
                <option value="{{ $state }}" @selected($settlementState === $state)>{{ __('exhibitions::ui.settlement_state_'.$state) }}</option>
            @endforeach
        </select></label>
        <button>{{ __('exhibitions::ui.auto_9639e32cab') }}</button>
    </form>

    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-table-wrap">
            <table class="z4-exhibitions-table z4-sales-table">
                <thead><tr>
                    <th>{{ __('exhibitions::ui.auto_a240fa2792') }}</th><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_4047c5607d') }}</th><th>{{ __('exhibitions::ui.auto_a61109b693') }}</th><th>{{ __('exhibitions::ui.auto_c28c0af956') }}</th><th>{{ __('exhibitions::ui.auto_8dd2e14b27') }}</th><th>{{ __('exhibitions::ui.auto_82169c325d') }}</th><th>{{ __('exhibitions::ui.auto_57297718fd') }}</th><th>{{ __('exhibitions::ui.auto_281ccf41d5') }}</th><th>{{ __('exhibitions::ui.auto_b35b399e2b') }}</th>
                </tr></thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $divisor = 10 ** $row->scale;
                        $afterDiscount = max(0, (int) $row->sale_price_minor - (int) $row->order_discount_minor);
                        $commissionRate = $afterDiscount > 0 ? ((int) $row->platform_commission_minor / $afterDiscount) * 100 : 0;
                        $orderNumber = $row->store_order_number ?: $row->store_order_reference;
                        $settlementLabel = match ((string) $row->state) {
                            'pending_sale' => __('exhibitions::ui.sale_state_pending_sale'),
                            'awaiting_delivery' => __('exhibitions::ui.sale_state_awaiting_delivery'),
                            'return_window' => __('exhibitions::ui.sale_state_return_window'),
                            'eligible_for_settlement' => __('exhibitions::ui.sale_state_eligible'),
                            'payout_requested' => __('exhibitions::ui.sale_state_payout_requested'),
                            'approved' => __('exhibitions::ui.sale_state_approved'),
                            'scheduled_for_payment' => __('exhibitions::ui.sale_state_processing'),
                            'payment_sent' => __('exhibitions::ui.sale_state_sent'),
                            'paid' => __('exhibitions::ui.sale_state_paid'),
                            'reversed' => __('exhibitions::ui.sale_state_cancelled'),
                            default => __('exhibitions::ui.sale_state_on_hold'),
                        };
                    @endphp
                    <tr>
                        <td data-label="{{ __('exhibitions::ui.data_order') }}">
                            @can('store.orders.details')
                                @if($row->store_order_public_id && Route::has('admin.plugins.store.orders.show'))
                                    <a class="z4-sales-order-link" href="{{ route('admin.plugins.store.orders.show', $row->store_order_public_id) }}">{{ $orderNumber }}</a>
                                @else<strong>{{ $orderNumber }}</strong>@endif
                            @else<strong>{{ $orderNumber }}</strong>@endcan
                            <small>{{ __('exhibitions::ui.auto_9b6ee1634a') }}</small>
                        </td>
                        <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $row->title }}</strong><small>{{ $row->artwork_public_id }}</small></td>
                        <td data-label="{{ __('exhibitions::ui.data_artist') }}"><strong>{{ $row->artist_name }}</strong></td>
                        <td data-label="{{ __('exhibitions::ui.data_after_discount') }}">{{ number_format($afterDiscount / $divisor, $row->scale) }} {{ $row->currency }}</td>
                        <td data-label="{{ __('exhibitions::ui.data_commission_rate') }}"><strong>{{ number_format($commissionRate, 3) }}%</strong></td>
                        <td data-label="{{ __('exhibitions::ui.data_platform_commission') }}">{{ number_format($row->platform_commission_minor / $divisor, $row->scale) }} {{ $row->currency }}</td>
                        <td data-label="{{ __('exhibitions::ui.data_artist_earning') }}"><strong>{{ number_format($row->artist_net_minor / $divisor, $row->scale) }} {{ $row->currency }}</strong></td>
                        <td data-label="{{ __('exhibitions::ui.data_order_status') }}"><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.state_'.($row->order_state ?: $row->fulfillment_state)) }}</span></td>
                        <td data-label="{{ __('exhibitions::ui.data_payout_status') }}"><a href="{{ route('admin.plugins.exhibitions.settlements.show', $row->public_id) }}">{{ $settlementLabel }}</a><small>{{ $row->state === 'eligible_for_settlement' ? __('exhibitions::ui.sale_state_eligible') : __('exhibitions::ui.state_'.$row->state) }}</small></td>
                        <td data-label="{{ __('exhibitions::ui.data_sale_date') }}"><time datetime="{{ $row->created_at }}">{{ Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m-d') }}<small>{{ Illuminate\Support\Carbon::parse($row->created_at)->format('H:i') }}</small></time></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_b32e82defe') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-platform-pagination :paginator="$rows" />
    </section>
</div>
</main>
</x-app-layout>
