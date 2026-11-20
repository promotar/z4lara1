<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('lms::ui.my-learning') }}</h2>
            <p>{{ __('lms::ui.your-enrolled-courses-and-diplomas') }}</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            @if($studentActive ?? false)
                <a class="account-section-action" href="{{ $portalUrl }}">{{ $portalLabel }}</a>
            @endif
            <a class="account-section-action" href="{{ route('lms.front.courses') }}">{{ __('lms::ui.browse-courses') }}</a>
        </div>
    </div>

    <div class="account-item-list">
        @forelse ($enrollments as $enrollment)
            <a class="account-item" href="{{ $enrollment->slug ? route('lms.front.courses.show', $enrollment->slug) : $portalUrl }}">
                <span>
                    <strong>{{ $enrollment->title ?: __('lms::ui.account_unavailable_course') }}</strong>
                    <small>{{ $enrollment->enrolled_at ? \Illuminate\Support\Carbon::parse($enrollment->enrolled_at)->format('d M Y') : __('lms::ui.account_enrollment_date_unavailable') }}</small>
                </span>
                <span>{{ ucfirst((string) ($enrollment->status ?: 'unavailable')) }}</span>
            </a>
        @empty
            <p class="account-empty-state">{{ __('lms::ui.no-course-enrollments-are-connected-to-this-account') }}</p>
        @endforelse
    </div>

    <div class="account-section-heading lms-account-actions">
        <div>
            <h3>{{ __('lms::ui.share-your-knowledge') }}</h3>
            <p>{{ __('lms::ui.existing-members-can-request-instructor-access-without-creat') }}</p>
        </div>
        @if($teachingDisabled)
            <span class="account-section-action lms-account-action-disabled" aria-disabled="true">{{ $teachingLabel }}</span>
        @else
            <a class="account-section-action" href="{{ $teachingUrl }}">{{ $teachingLabel }}</a>
        @endif
    </div>
</section>
