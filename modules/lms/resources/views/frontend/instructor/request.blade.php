<x-frontend-layout>
<main class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    <div class="ainpa-portal-wrap ainpa-settings-wrap">
        <header class="ainpa-page-heading"><div><a class="ainpa-back-link" href="{{ route('lms.front.courses') }}">← Courses</a><span>Teach with {{ $lmsBrand }}</span><h1>Instructor Access</h1><p>Request permission to publish courses, diplomas, and lessons from your existing account.</p></div></header>
        @if(session('status'))<div class="ainpa-portal-alert">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="ainpa-portal-alert is-error">{{ $errors->first() }}</div>@endif
        @if($application?->status === 'pending')
            <section class="ainpa-request-status"><span class="ainpa-status ainpa-status-draft">Pending review</span><h2>Your request is with the administration</h2><p>Submitted {{ $application->requested_at?->diffForHumans() }}. You will receive an LMS notification after it is reviewed.</p><dl><div><dt>Expertise</dt><dd>{{ $application->expertise }}</dd></div><div><dt>Message</dt><dd>{{ $application->message }}</dd></div></dl></section>
        @else
            @if($application?->status === 'rejected')<div class="ainpa-portal-alert is-error"><strong>Previous request was not approved.</strong>@if($application->decision_note)<br>{{ $application->decision_note }}@endif You may update the information and submit again.</div>@endif
            <form method="POST" action="{{ route('lms.front.instructor-request.store') }}" class="ainpa-settings-form">@csrf
                <section><header><span>1</span><div><h2>Teaching Profile</h2><p>Tell the administration what you plan to teach.</p></div></header><div class="ainpa-form-grid"><label class="ainpa-field-full"><span>Area of expertise *</span><input name="expertise" maxlength="255" value="{{ old('expertise', $application?->expertise) }}" required placeholder="Example: Painting, art history, sculpture"></label><label class="ainpa-field-full"><span>Request details *</span><textarea name="message" rows="7" minlength="20" maxlength="3000" required placeholder="Briefly describe your experience and the courses or diplomas you want to publish.">{{ old('message', $application?->message) }}</textarea></label></div></section>
                <footer><a class="ainpa-btn ainpa-btn-secondary" href="{{ route('lms.front.courses') }}">Cancel</a><button class="ainpa-btn ainpa-btn-primary">Submit Request</button></footer>
            </form>
        @endif
    </div>
</main>
</x-frontend-layout>
