@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-dashboard-overview>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',[
    'title' => __('exhibitions::ui.artist_welcome', ['name' => auth()->user()?->name ?: ($context->artistic_name ?: $context->legal_name)]),
    'subtitle' => __('exhibitions::ui.artist_overview_subtitle'),
])
@if($storeAvailable && ! $sellerSetup['ready'])
<section class="z4-seller-readiness" aria-labelledby="dashboard-selling-readiness-title">
    <div><span>{{ __('exhibitions::ui.auto_71c71ec462') }}</span><h2 id="dashboard-selling-readiness-title">{{ __('exhibitions::ui.auto_adc2f1ea5f') }}</h2><p>{{ $sellerSetup['ready'] ? __('exhibitions::ui.artist_setup_complete') : __('exhibitions::ui.artist_setup_incomplete') }}</p></div>
    <div class="z4-seller-readiness-score"><strong>{{ $sellerSetup['percentage'] }}%</strong><small>{{ __('exhibitions::ui.artist_requirements_complete', ['completed' => $sellerSetup['completed'], 'total' => $sellerSetup['total']]) }}</small></div>
    <div class="z4-seller-readiness-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sellerSetup['percentage'] }}"><span style="width:{{ $sellerSetup['percentage'] }}%"></span></div>
    @if(!$sellerSetup['ready'])<a class="z4-exhibitions-button" href="{{ route('exhibitions.verification.show') }}">{{ __('exhibitions::ui.auto_d1465525e6') }}</a>@endif
</section>
@elseif(! $storeAvailable)
<section class="z4-seller-readiness" aria-labelledby="dashboard-account-status-title">
    <div><span>{{ __('exhibitions::ui.auto_96403bbf5d') }}</span><h2 id="dashboard-account-status-title">{{ __('exhibitions::ui.auto_b670dc2dfc') }}</h2><p>{{ __('exhibitions::ui.auto_449b175699') }}</p></div>
</section>
@endif
@if($storeAvailable)
<section class="z4-artist-metrics">
@foreach(['artworks_total'=>__('exhibitions::ui.artist_metric_total'),'under_review'=>__('exhibitions::ui.artist_metric_review'),'published'=>__('exhibitions::ui.artist_metric_published'),'changes_required'=>__('exhibitions::ui.artist_metric_changes'),'reserved'=>__('exhibitions::ui.artist_metric_reserved'),'sold'=>__('exhibitions::ui.artist_metric_sold')] as $key=>$label)
    <article><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong><small>{{ __('exhibitions::ui.auto_f4b8c3564e') }}</small></article>
@endforeach
    <article>
        <span>{{ __('exhibitions::ui.auto_f162caf710') }}</span>
        <strong>{{ number_format($metrics['pending_amount'],2) }} JOD</strong>
        <small>{{ $sourceAvailability['earnings'] ? __('exhibitions::ui.artist_open_liability') : __('exhibitions::ui.artist_no_sales_ledger') }}</small>
    </article>
    <article>
        <span>{{ __('exhibitions::ui.auto_99906f264e') }}</span>
        <strong>{{ number_format($metrics['eligible_amount'],2) }} JOD</strong>
        <small>{{ $sourceAvailability['earnings'] ? __('exhibitions::ui.artist_approved_settlements') : __('exhibitions::ui.artist_no_settlement_ledger') }}</small>
    </article>
</section>
@endif
<div class="z4-exhibitions-columns">
    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_075278e20a') }}</h2><p>{{ __('exhibitions::ui.auto_0a2a56b6dc') }}</p></div></div>
        @forelse($tasks as $task)
            <a class="z4-artist-task" href="{{ $task['url'] }}"><strong>{{ $task['title'] }}</strong><span>{{ $task['detail'] }}</span></a>
        @empty
            <div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_a09d541677') }}</strong><p>{{ __('exhibitions::ui.auto_b211fa9595') }}</p></div>
        @endforelse
    </section>
    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_499e5b2ead') }}</h2><p>{{ __('exhibitions::ui.artist_notification_count', ['count' => $notificationCount]) }}</p></div></div>
        @forelse($notifications as $notification)
            <div class="z4-artist-notification"><strong>{{ __('exhibitions::ui.notification_type_'.$notification->type) }}</strong><span>{{ __('exhibitions::ui.notification_state_'.$notification->state) }} · {{ $notification->created_at }}</span></div>
        @empty
            <div class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_b796a68985') }}</div>
        @endforelse
    </section>
</div>
@if($storeAvailable && $sourceAvailability['sales'] && $sourceAvailability['earnings'])
    <section class="z4-exhibitions-panel">
        <h2>{{ __('exhibitions::ui.auto_f5fef61b03') }}</h2>
        <p>{{ __('exhibitions::ui.auto_b1ce806d28') }}</p>
    </section>
@elseif($storeAvailable)
    <section class="z4-exhibitions-panel z4-artist-truthful-empty">
        <h2>{{ __('exhibitions::ui.auto_1c0b5773ec') }}</h2>
        <p>{{ __('exhibitions::ui.auto_233c117975') }}</p>
    </section>
@endif
</div>
</main>
</x-frontend-layout>
