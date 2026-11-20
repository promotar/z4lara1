@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-payout-flow-1" data-exhibitions-stylesheet>@endpush
@php
    $stateLabels = [
        'pending_sale' => __('exhibitions::ui.settlement_state_pending_sale'), 'awaiting_delivery' => __('exhibitions::ui.settlement_state_awaiting_delivery'), 'return_window' => __('exhibitions::ui.settlement_state_return_window'),
        'eligible_for_settlement' => __('exhibitions::ui.settlement_state_eligible'), 'payout_requested' => __('exhibitions::ui.settlement_state_payout_requested'), 'under_review' => __('exhibitions::ui.settlement_state_under_review'),
        'approved' => __('exhibitions::ui.settlement_state_approved'), 'scheduled_for_payment' => __('exhibitions::ui.settlement_state_processing'), 'payment_sent' => __('exhibitions::ui.settlement_state_sent'),
        'paid' => __('exhibitions::ui.settlement_state_paid'), 'held' => __('exhibitions::ui.settlement_state_held'), 'reversed' => __('exhibitions::ui.settlement_state_reversed'), 'failed' => __('exhibitions::ui.settlement_state_failed'),
    ];
@endphp
<x-app-layout><main class="z4-exhibitions-admin"><div class="z4-exhibitions-container">
<header class="z4-exhibitions-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_c482980d38') }}</span><h1>{{ __('exhibitions::ui.auto_62b921314b') }}</h1><p>{{ __('exhibitions::ui.auto_c32c3c47ef') }}</p></div></header>
<form class="z4-exhibitions-filters"><select name="state"><option value="">{{ __('exhibitions::ui.auto_f093416a7d') }}</option>@foreach($stateLabels as $value => $label)<option value="{{ $value }}" @selected($state===$value)>{{ $label }}</option>@endforeach</select><button>{{ __('exhibitions::ui.auto_d7778d0c64') }}</button></form>
<section class="z4-exhibitions-panel"><div class="z4-exhibitions-table-wrap"><table><thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_281ccf41d5') }}</th><th>{{ __('exhibitions::ui.auto_82169c325d') }}</th><th>{{ __('exhibitions::ui.auto_ff0a3b7f3d') }}</th><th></th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><strong>{{ $row->title }}</strong><small>{{ __('exhibitions::ui.auto_2e15c9dafe') }}</small></td><td><span class="z4-exhibitions-pill">{{ $stateLabels[$row->state] ?? __('exhibitions::ui.state_'.$row->state) }}</span></td><td><strong>{{ number_format($row->artist_net_minor/(10**$row->scale),$row->scale) }} {{ $row->currency }}</strong></td><td>{{ $row->updated_at }}</td><td><a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.settlements.show',$row->public_id) }}">{{ __('exhibitions::ui.auto_c3bf447eab') }}</a></td></tr>@empty<tr><td colspan="5"><div class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_add93f44e7') }}</div></td></tr>@endforelse
</tbody></table></div><x-platform-pagination :paginator="$rows" /></section></div></main></x-app-layout>
