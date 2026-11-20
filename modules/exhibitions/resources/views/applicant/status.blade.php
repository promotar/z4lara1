@php
    $reasonLabels = [
        'Draft created.' => __('exhibitions::ui.reason_draft_created'),
        'Application submitted by applicant.' => __('exhibitions::ui.reason_submitted'),
        'Email address was already verified. Application automatically moved to review.' => __('exhibitions::ui.reason_email_verified'),
        'Existing platform user added as an artist by an authorized administrator.' => __('exhibitions::ui.reason_admin_added'),
    ];
@endphp
@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-applicant-files-3" data-exhibitions-stylesheet>@endpush
<x-app-layout>
    <main class="z4-exhibitions-admin" data-exhibitions-application-status>
        <div class="z4-exhibitions-container z4-exhibitions-form-container">
            <header class="z4-exhibitions-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.application_number', ['id' => $application->public_id]) }}</span><h1>{{ __('exhibitions::ui.auto_4148cf4a81') }}</h1><p>{{ __('exhibitions::ui.auto_14b48e44be') }}</p></div><span class="z4-exhibitions-status {{ $application->state === 'active' ? 'is-ready' : '' }}">{{ $state->label() }}</span></header>
            @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
            @if($dashboardAccessible)
                <nav class="z4-artist-dashboard-tabs" aria-label="{{ __('exhibitions::ui.auto_6ce0096786') }}">
                    <a href="{{ route('exhibitions.dashboard.overview') }}">{{ __('exhibitions::ui.auto_2938c7f7e5') }}</a>
                    <a class="is-active" aria-current="page" href="{{ route('exhibitions.application.status') }}">{{ __('exhibitions::ui.auto_f257f0e5b3') }}</a>
                </nav>
            @endif
            @if($application->current_reason || $application->next_action || $application->response_deadline)
                <section class="z4-exhibitions-panel z4-exhibitions-next-action"><h2>{{ $application->state === 'rejected' ? __('exhibitions::ui.application_decision') : __('exhibitions::ui.next_required_action') }}</h2>@if($application->current_reason)<p><strong>{{ $application->state === 'rejected' ? __('exhibitions::ui.rejection_reason') : __('exhibitions::ui.reviewer_note') }}:</strong> {{ $application->current_reason }}</p>@endif @if($application->next_action)<p>{{ $application->next_action }}</p>@endif @if($application->response_deadline)<p><strong>{{ __('exhibitions::ui.auto_7ae97bdfba') }}</strong> {{ $application->response_deadline }}</p>@endif</section>
            @endif
            @if($application->state === 'documents_required')<p class="z4-application-status-action"><a class="z4-exhibitions-button" href="{{ route('exhibitions.application') }}">{{ __('exhibitions::ui.auto_ecde2ee4e4') }}</a></p>@endif
            @if($application->state === 'rejected')<p class="z4-application-status-action"><a class="z4-exhibitions-button is-primary" href="{{ route('exhibitions.application') }}">{{ __('exhibitions::ui.auto_85a5983081') }}</a></p>@endif
            <section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_de78fbb96e') }}</h2><p>{{ __('exhibitions::ui.auto_25d4fb03ac') }}</p></div></div><ol class="z4-exhibitions-timeline">@foreach($history as $entry)<li><span></span><div><strong>{{ \Modules\Exhibition\Domain\Applications\ApplicationState::from($entry->to_state)->label() }}</strong><time>{{ $entry->occurred_at }}</time>@if($entry->reason)<p>{{ $reasonLabels[$entry->reason] ?? $entry->reason }}</p>@endif @if($entry->next_action)<p><b>{{ __('exhibitions::ui.auto_db5ca4cc88') }}</b> {{ $entry->next_action }}</p>@endif</div></li>@endforeach</ol></section>
            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_784fe4cc67') }}</h2><p>{{ __('exhibitions::ui.auto_3bfb66b0b6') }}</p></div></div>
                <h3>{{ __('exhibitions::ui.auto_8e5dc9666c') }}</h3>
                <ul class="z4-application-sample-grid">
                    @forelse($samples as $sample)
                        @php
                            $sampleDownloadUrl = route('exhibitions.application.samples.download', $sample->public_id);
                            $isPreviewableImage = in_array($sample->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
                        @endphp
                        <li class="z4-application-sample-card">
                            @if($isPreviewableImage)
                                <a class="z4-application-sample-preview" href="{{ route('exhibitions.application.samples.preview', $sample->public_id) }}" target="_blank" rel="noopener" title="View {{ $sample->original_name }} full size">
                                    <img src="{{ route('exhibitions.application.samples.preview', $sample->public_id) }}" alt="Artwork sample: {{ $sample->original_name }}" loading="lazy">
                                </a>
                            @else
                                <div class="z4-application-sample-file"><span>{{ __('exhibitions::ui.auto_d272b46994') }}</span></div>
                            @endif
                            <div class="z4-application-sample-caption">
                                <a href="{{ $sampleDownloadUrl }}">{{ $sample->original_name }}</a>
                                <span>{{ number_format($sample->size_bytes / 1024, 1) }} KB</span>
                            </div>
                        </li>
                    @empty
                        <li class="z4-application-sample-empty">{{ __('exhibitions::ui.auto_f946e4b512') }}</li>
                    @endforelse
                </ul>
                <h3>{{ __('exhibitions::ui.auto_a32532e8c4') }}</h3>
                <ul class="z4-exhibitions-file-list">
                    @forelse($supportingDocuments as $document)
                        <li><a href="{{ route('exhibitions.application.supporting-documents.download', $document->public_id) }}">{{ $document->original_name }}</a><span>{{ number_format($document->size_bytes / 1024, 1) }} KB</span></li>
                    @empty
                        <li>{{ __('exhibitions::ui.auto_901664f73e') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </main>
</x-app-layout>
