@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
@endpush
@php
    $factor = 10 ** $sale->scale;
    $otherDeductions = $sale->order_discount_minor
        + $sale->payment_cost_minor
        + $sale->packaging_cost_minor
        + $sale->insurance_minor
        + $sale->refund_minor;
    $fulfillmentLabels = [
        'store_reserved' => 'Awaiting sale confirmation',
        'sale_confirmed' => 'Platform handling started',
        'artist_notification_sent' => 'Platform handling started',
        'preparing' => 'Platform preparing artwork',
        'ready_for_pickup' => 'Platform pickup in progress',
        'collected' => 'Collected by platform',
        'quality_inspection' => 'Platform quality inspection',
        'ready_for_shipment' => 'Ready for shipment',
        'shipped' => 'Shipped',
        'delayed' => 'Shipment delayed',
        'delivered' => 'Delivered',
        'return_requested' => 'Return requested',
        'returned' => 'Returned',
        'damaged' => 'Damage review',
        'lost' => 'Loss review',
        'cancelled' => 'Cancelled',
    ];
@endphp
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard">
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',[
    'title' => $sale->title,
    'subtitle' => __('exhibitions::ui.artist_page_sale_show_subtitle'),
])

<section class="z4-artist-finance-metrics" aria-label="{{ __('exhibitions::ui.auto_dc2b8c82a3') }}">
    <article>
        <span>{{ __('exhibitions::ui.auto_29f03922d0') }}</span>
        <strong>{{ number_format($sale->sale_price_minor / $factor, $sale->scale) }} {{ $sale->currency }}</strong>
    </article>
    <article>
        <span>{{ __('exhibitions::ui.auto_ccf99cf16c') }}</span>
        <strong>{{ number_format($sale->platform_commission_minor / $factor, $sale->scale) }} {{ $sale->currency }}</strong>
    </article>
    <article>
        <span>{{ __('exhibitions::ui.auto_4df6125426') }}</span>
        <strong>{{ number_format($otherDeductions / $factor, $sale->scale) }} {{ $sale->currency }}</strong>
    </article>
    <article>
        <span>{{ __('exhibitions::ui.auto_ba3c18bab3') }}</span>
        <strong>{{ number_format($sale->artist_net_minor / $factor, $sale->scale) }} {{ $sale->currency }}</strong>
    </article>
</section>

<section class="z4-exhibitions-grid">
    <article class="z4-exhibitions-panel">
        <h2>{{ __('exhibitions::ui.auto_3908c119dd') }}</h2>
        <dl class="z4-exhibitions-details">
            <div><dt>{{ __('exhibitions::ui.auto_57297718fd') }}</dt><dd>{{ \Illuminate\Support\Str::headline($sale->order_state ?: $sale->fulfillment_state) }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_59758c8f7f') }}</dt><dd>{{ $sale->payment_status ? __('exhibitions::ui.state_'.$sale->payment_status) : __('exhibitions::ui.state_unavailable') }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_b35b399e2b') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale->sold_at)->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_281ccf41d5') }}</dt><dd>{{ __('exhibitions::ui.state_'.$sale->settlement_state) }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_ea9f595aff') }}</dt><dd>{{ $fulfillmentLabels[$sale->fulfillment_state] ?? __('exhibitions::ui.state_'.$sale->fulfillment_state) }}</dd></div>
            @if($sale->adjustment_minor !== 0)
                <div><dt>{{ __('exhibitions::ui.auto_ee1aaeb7a8') }}</dt><dd>{{ number_format($sale->adjustment_minor / $factor, $sale->scale) }} {{ $sale->currency }}</dd></div>
            @endif
        </dl>
    </article>
    <article class="z4-exhibitions-panel">
        <h2>{{ __('exhibitions::ui.auto_5e6308f8e0') }}</h2>
        <p>{{ __('exhibitions::ui.auto_c008824397') }}</p>
        <p>{{ __('exhibitions::ui.auto_3e99fe0c5e') }}</p>
        <a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.earnings.show', $sale->settlement_public_id) }}">{{ __('exhibitions::ui.auto_ca8473414f') }}</a>
    </article>
</section>
</div>
</main>
</x-frontend-layout>
