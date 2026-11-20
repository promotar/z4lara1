@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>
@endpush
@php
    $stateLabels = [
        'draft' => 'Draft',
        'submitted' => 'Awaiting email verification',
        'under_review' => 'Application under review',
        'documents_required' => 'Documents required',
        'approved' => 'Application approved',
        'contract_pending' => 'Verification and agreement pending',
        'rejected' => 'Application not approved',
    ];
    $actionRoute = match($application->state) {
        'draft', 'documents_required', 'rejected' => route('exhibitions.application'),
        'approved', 'contract_pending' => route('exhibitions.verification.show'),
        default => route('exhibitions.application.status'),
    };
    $actionLabel = match($application->state) {
        'draft' => 'Complete application',
        'documents_required' => 'Provide requested information',
        'rejected' => 'Update and reapply',
        'approved', 'contract_pending' => 'Complete seller setup',
        default => 'View application status',
    };
@endphp
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-onboarding-dashboard>
<div class="z4-exhibitions-container">
    <header class="z4-artist-dashboard-header"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_97835788db') }}</span><h1>{{ __('exhibitions::ui.auto_10acc396ee') }}</h1><p>{{ __('exhibitions::ui.auto_d03ec0166a') }}</p></div><a class="z4-artist-profile-link" href="{{ route('exhibitions.application.status') }}">{{ $application->artistic_name ?: $application->legal_name }}</a></header>
    <nav class="z4-artist-dashboard-nav" aria-label="{{ __('exhibitions::ui.auto_10acc396ee') }}"><a class="is-active" href="{{ route('exhibitions.dashboard.overview') }}"><span>{{ __('exhibitions::ui.auto_3b878279a0') }}</span></a><a href="{{ route('exhibitions.application.status') }}"><span>{{ __('exhibitions::ui.auto_e498749f3c') }}</span></a></nav>
    <section class="z4-exhibitions-panel z4-exhibitions-next-action">
        <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ $stateLabels[$application->state] ?? ucfirst(str_replace('_', ' ', $application->state)) }}</h2><p>{{ __('exhibitions::ui.auto_1c49e30d1b') }}</p></div></div>
        @if($application->state === 'rejected' && $application->current_reason)<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_72f9812744') }}</strong><p>{{ $application->current_reason }}</p></div>@endif
        @if($application->next_action)<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_3d6623b8d5') }}</strong><p>{{ $application->next_action }}</p>@if($application->response_deadline)<p><strong>{{ __('exhibitions::ui.auto_7ae97bdfba') }}</strong> {{ $application->response_deadline }}</p>@endif</div>@endif
        <a class="z4-exhibitions-button is-primary" href="{{ $actionRoute }}">{{ $actionLabel }}</a>
    </section>
    <section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_d104a0f24f') }}</h2><p>{{ __('exhibitions::ui.auto_807c72b1a0') }}</p></div></div><ol class="z4-exhibitions-timeline">@foreach($history as $entry)<li><span></span><div><strong>{{ \Modules\Exhibition\Domain\Applications\ApplicationState::from($entry->to_state)->label() }}</strong><time>{{ $entry->occurred_at }}</time>@if($entry->next_action)<p><b>{{ __('exhibitions::ui.auto_abb398c082') }}</b> {{ $entry->next_action }}</p>@endif</div></li>@endforeach</ol></section>
</div>
</main>
</x-frontend-layout>
