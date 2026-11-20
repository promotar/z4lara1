@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artist-sales-3">
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-sales>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',[
    'title' => __('exhibitions::ui.artist_page_sold_title'),
    'subtitle' => __('exhibitions::ui.artist_page_sold_subtitle'),
])

@if($totals->isNotEmpty())
<section class="z4-artist-finance-metrics z4-sales-summary" aria-label="{{ __('exhibitions::ui.auto_939d72b5af') }}">
    @foreach($totals as $total)
        <article>
            <span>{{ __('exhibitions::ui.auto_71857d5afc') }}</span>
            <strong>{{ number_format((int) $total->sales_count) }}</strong>
            <small>{{ __('exhibitions::ui.auto_bb5ebac63d') }}</small>
        </article>
        <article>
            <span>{{ __('exhibitions::ui.auto_54c1091d2a') }}</span>
            <strong>{{ number_format($total->gross_minor / (10 ** $total->scale), $total->scale) }} {{ $total->currency }}</strong>
        </article>
        <article>
            <span>{{ __('exhibitions::ui.auto_cd8b40ff9c') }}</span>
            <strong>{{ number_format($total->net_minor / (10 ** $total->scale), $total->scale) }} {{ $total->currency }}</strong>
        </article>
    @endforeach
</section>
@endif

<section class="z4-exhibitions-panel z4-artist-sales-panel">
    <div class="z4-exhibitions-table-wrap">
        <table class="z4-artist-sales-table">
            <thead>
                <tr>
                    <th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th>
                    <th>{{ __('exhibitions::ui.auto_57297718fd') }}</th>
                    <th>{{ __('exhibitions::ui.auto_29f03922d0') }}</th>
                    <th>{{ __('exhibitions::ui.auto_ccf99cf16c') }}</th>
                    <th>{{ __('exhibitions::ui.auto_ba3c18bab3') }}</th>
                    <th>{{ __('exhibitions::ui.auto_281ccf41d5') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php($orderState = \Illuminate\Support\Str::kebab($row->order_state ?: $row->fulfillment_state))
                @php($settlementState = \Illuminate\Support\Str::kebab($row->settlement_state))
                <tr>
                    <td data-label="{{ __('exhibitions::ui.data_artwork') }}">
                        <strong class="z4-sale-artwork-title">{{ $row->title }}</strong>
                        <time class="z4-sale-date" datetime="{{ \Illuminate\Support\Carbon::parse($row->sold_at)->toIso8601String() }}">
                            Sale date: {{ \Illuminate\Support\Carbon::parse($row->sold_at)->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                        </time>
                    </td>
                    <td data-label="Order status">
                        <div class="z4-sale-status-stack">
                            <span class="z4-sale-status is-{{ $orderState }}">{{ \Illuminate\Support\Str::headline($row->order_state ?: $row->fulfillment_state) }}</span>
                            <small>{{ $row->payment_status ? __('exhibitions::ui.state_'.$row->payment_status) : __('exhibitions::ui.payment_unavailable') }}</small>
                        </div>
                    </td>
                    <td data-label="Sale value"><span class="z4-sale-money">{{ number_format($row->sale_price_minor / (10 ** $row->scale), $row->scale) }} <small>{{ $row->currency }}</small></span></td>
                    <td data-label="Platform commission"><span class="z4-sale-money">{{ number_format($row->platform_commission_minor / (10 ** $row->scale), $row->scale) }} <small>{{ $row->currency }}</small></span></td>
                    <td data-label="Your earnings"><strong class="z4-sale-money is-earnings">{{ number_format($row->artist_net_minor / (10 ** $row->scale), $row->scale) }} <small>{{ $row->currency }}</small></strong></td>
                    <td data-label="Payout status"><span class="z4-sale-status is-{{ $settlementState }}">{{ __('exhibitions::ui.state_'.$row->settlement_state) }}</span></td>
                    <td class="z4-sale-action"><a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.fulfillment.show', $row->public_id) }}">{{ __('exhibitions::ui.auto_82967624e2') }} <span aria-hidden="true">&rarr;</span></a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="z4-exhibitions-empty">
                            <strong>{{ __('exhibitions::ui.auto_7decf414c6') }}</strong>
                            <p>{{ __('exhibitions::ui.auto_34317c19c7') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-platform-pagination :paginator="$rows" />
</section>
</div>
</main>
</x-frontend-layout>
