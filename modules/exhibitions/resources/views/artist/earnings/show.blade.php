@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-payout-flow-1" data-exhibitions-stylesheet>@endpush
@php
    $stateLabels = [
        'pending_sale' => 'Sale pending', 'awaiting_delivery' => 'Awaiting delivery', 'return_window' => 'Waiting period',
        'eligible_for_settlement' => 'Ready to request', 'payout_requested' => 'Request under review', 'under_review' => 'Under review',
        'approved' => 'Request approved', 'scheduled_for_payment' => 'Payment processing', 'payment_sent' => 'Payment sent',
        'paid' => 'Payment received', 'held' => 'On hold', 'failed' => 'Payment issue', 'reversed' => 'Cancelled',
    ];
    $windowComplete = $settlement->return_window_ends_at && now()->greaterThanOrEqualTo($settlement->return_window_ends_at);
    $canRequest = in_array($settlement->state, ['return_window','eligible_for_settlement'], true) && $windowComplete;
@endphp
<x-frontend-layout><main class="z4-exhibitions-admin z4-artist-dashboard"><div class="z4-exhibitions-container">
<a class="z4-exhibitions-back" href="{{ route('exhibitions.dashboard.earnings') }}">&larr; {{ __('exhibitions::ui.earnings_and_payouts') }}</a>
<header class="z4-exhibitions-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_33d958dac2') }}</span><h1>{{ $settlement->title }}</h1><p>{{ __('exhibitions::ui.auto_9b098f4ef5') }}</p></div><span class="z4-exhibitions-pill">{{ $stateLabels[$settlement->state] ?? __('exhibitions::ui.state_'.$settlement->state) }}</span></header>
@if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="z4-artist-finance-metrics"><article><span>{{ __('exhibitions::ui.auto_62a4164bbc') }}</span><strong>{{ number_format($settlement->sale_price_minor/(10**$settlement->scale),$settlement->scale) }} {{ $settlement->currency }}</strong></article><article><span>{{ __('exhibitions::ui.auto_ccf99cf16c') }}</span><strong>{{ number_format($settlement->platform_commission_minor/(10**$settlement->scale),$settlement->scale) }} {{ $settlement->currency }}</strong></article><article><span>{{ __('exhibitions::ui.auto_0aa82584b0') }}</span><strong>{{ number_format($settlement->artist_net_minor/(10**$settlement->scale),$settlement->scale) }} {{ $settlement->currency }}</strong></article></section>

<section class="z4-exhibitions-panel z4-payout-status-card">
    <div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_efd1e491da') }}</span><h2>{{ $stateLabels[$settlement->state] ?? __('exhibitions::ui.state_'.$settlement->state) }}</h2>
    @if($settlement->state === 'return_window' && !$windowComplete)<p>You can request payment after {{ $settlement->return_window_ends_at }} if no order problem is reported.</p>
    @elseif($canRequest)<p>{{ __('exhibitions::ui.auto_0d215d084d') }}</p>
    @elseif($settlement->state === 'payout_requested')<p>{{ __('exhibitions::ui.auto_af0af50ff8') }}</p>
    @elseif($settlement->state === 'approved')<p>{{ __('exhibitions::ui.auto_6244b68bdc') }}</p>
    @elseif($settlement->state === 'payment_sent')<p>{{ __('exhibitions::ui.auto_9167fc8c98') }}</p>
    @elseif($settlement->state === 'paid')<p>{{ __('exhibitions::ui.auto_ef1d509d35') }}</p>
    @elseif($settlement->state === 'held')<p>{{ __('exhibitions::ui.auto_19e4837f86') }}</p>
    @else<p>{{ __('exhibitions::ui.auto_3f6a7a1616') }}</p>@endif</div>
    <div class="z4-payout-method"><span>{{ __('exhibitions::ui.auto_f0aaaae189') }}</span>@if($payout)<strong>{{ __('exhibitions::ui.state_'.$payout->method) }}</strong><small>{{ $payout->masked_identifier }} · {{ $payout->verified_at ? 'Verified' : 'Verification pending' }}</small>@else<strong>{{ __('exhibitions::ui.auto_f3c7d07fc2') }}</strong><a href="{{ route('exhibitions.verification.payout') }}">{{ __('exhibitions::ui.auto_d1465525e6') }}</a>@endif</div>
</section>

@if($canRequest)
<section class="z4-exhibitions-panel z4-payout-action-panel"><h2>{{ __('exhibitions::ui.auto_b3604ff492') }}</h2><p>{{ __('exhibitions::ui.auto_5b28cd7cc2') }}</p><form method="post" action="{{ route('exhibitions.dashboard.earnings.request-payout',$settlement->public_id) }}">@csrf<input type="hidden" name="idempotency_key" value="artist-payout-request-{{ \Illuminate\Support\Str::uuid() }}"><button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_d628ffa85a') }}</button></form></section>
@endif

@if($settlement->state === 'payment_sent')
<section class="z4-exhibitions-panel z4-payout-action-panel"><h2>{{ __('exhibitions::ui.auto_f0847c73ba') }}</h2><p>{{ __('exhibitions::ui.auto_3dd42bfc83') }} <strong>{{ $settlement->payout_reference }}</strong></p><div class="z4-payout-actions"><form method="post" action="{{ route('exhibitions.dashboard.earnings.confirm-receipt',$settlement->public_id) }}">@csrf<input type="hidden" name="idempotency_key" value="artist-payout-confirm-{{ \Illuminate\Support\Str::uuid() }}"><button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_d3d50dda3d') }}</button></form><details><summary>{{ __('exhibitions::ui.auto_70ab44feea') }}</summary><form method="post" action="{{ route('exhibitions.dashboard.earnings.report-receipt-issue',$settlement->public_id) }}" class="z4-exhibitions-form">@csrf<label><span>{{ __('exhibitions::ui.auto_c41155bae2') }}</span><textarea name="summary" required minlength="10" maxlength="4000" placeholder="Explain what is missing or incorrect."></textarea></label><input type="hidden" name="idempotency_key" value="artist-payout-case-{{ \Illuminate\Support\Str::uuid() }}"><button type="submit">{{ __('exhibitions::ui.auto_eb94ab5240') }}</button></form></details></div></section>
@elseif($openCase)
<section class="z4-exhibitions-panel z4-payout-action-panel"><h2>{{ __('exhibitions::ui.auto_ae1db9275d') }}</h2><p>{{ __('exhibitions::ui.auto_499a70b132') }}</p><a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.cases.show',$openCase->public_id) }}">{{ __('exhibitions::ui.auto_6db859d898') }}</a></section>
@endif

<details class="z4-exhibitions-panel"><summary><strong>{{ __('exhibitions::ui.auto_f4f0fe2800') }}</strong><span>{{ __('exhibitions::ui.auto_a2b551ec15') }}</span></summary><div class="z4-exhibitions-table-wrap"><table><thead><tr><th>{{ __('exhibitions::ui.auto_8d6c7b44d1') }}</th><th>{{ __('exhibitions::ui.auto_b2f4069085') }}</th><th>{{ __('exhibitions::ui.auto_44749712db') }}</th></tr></thead><tbody>@foreach($ledger as $entry)<tr><td><strong>{{ __('exhibitions::ui.state_'.$entry->entry_type) }}</strong><small>{{ $entry->description }}</small></td><td>{{ number_format($entry->amount_minor/(10**$settlement->scale),$settlement->scale) }} {{ $entry->currency }}</td><td>{{ $entry->occurred_at }}</td></tr>@endforeach</tbody></table></div><a class="z4-exhibitions-button is-secondary" href="{{ route('exhibitions.dashboard.earnings.download',$settlement->public_id) }}">{{ __('exhibitions::ui.auto_59f053bbb8') }}</a><ol class="z4-exhibitions-timeline">@foreach($events as $event)<li><strong>{{ __('exhibitions::ui.state_'.$event->action) }}</strong><p>{{ $event->reason }}</p><small>{{ $event->occurred_at }}</small></li>@endforeach</ol></details>
</div></main></x-frontend-layout>
