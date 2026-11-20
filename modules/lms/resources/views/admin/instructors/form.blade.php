@php($isEdit = (bool) $instructor)
@php($linkedUser = $selectedUser ?? $instructor?->user)
@php($avatarUrl = $instructor?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($instructor->avatar_path) : '')
<x-app-layout>
<div class="lms-course-shell lms-people-page lms-instructor-form-page"><div class="lms-course-container lms-narrow-container">
    <header class="lms-people-header">
        <div><a class="lms-back-link" href="{{ route('admin.plugins.lms.instructors') }}">{{ __('lms::ui.auto_c7ccf2eb5c') }}</a><h1>{{ $isEdit ? __('lms::ui.admin_instructor_edit_title') : __('lms::ui.admin_instructor_add_title') }}</h1><p>{{ __('lms::ui.auto_cb2a4230a2') }}</p></div>
    </header>
    @if($errors->any())<div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.plugins.lms.instructors.update', $instructor) : route('admin.plugins.lms.instructors.store') }}" class="lms-people-form-card" data-instructor-form>
        @csrf @if($isEdit)@method('PUT')@endif
        <section>
            <div class="lms-form-section"><span class="lms-section-number">1</span><div><h2>{{ __('lms::ui.auto_c285ddb8ec') }}</h2><p>{{ $isEdit ? __('lms::ui.admin_instructor_fixed_desc') : __('lms::ui.admin_instructor_add_desc') }}</p></div></div>
            <input type="hidden" name="user_id" value="{{ old('user_id', $linkedUser?->id) }}" data-instructor-user-id>
            @if($isEdit && $linkedUser)
                <div class="lms-selected-user is-selected" data-selected-user>
                    <span>{{ strtoupper(\Illuminate\Support\Str::substr($linkedUser->name ?: $linkedUser->email, 0, 1)) }}</span>
                    <div><strong data-selected-user-name>{{ $linkedUser->name ?: 'Unnamed user' }}</strong><small data-selected-user-email>{{ $linkedUser->email }}</small></div>
                </div>
            @else
                <div class="lms-user-picker" data-instructor-user-picker data-endpoint="{{ route('admin.plugins.lms.instructor-users', [], false) }}">
                    <label><span>{{ __('lms::ui.auto_b2dea6f867') }}</span><input class="lms-input" type="search" autocomplete="off" placeholder="{{ __('lms::ui.auto_cd6a675740') }}" data-user-search aria-controls="lms-user-results" aria-expanded="false"></label>
                    <div class="lms-user-picker-results" id="lms-user-results" data-user-results hidden></div>
                    <div class="lms-selected-user" data-selected-user hidden>
                        <span data-selected-user-initial>U</span>
                        <div><strong data-selected-user-name></strong><small data-selected-user-email></small></div>
                        <button type="button" data-clear-user>{{ __('lms::ui.auto_f4ec5f57bd') }}</button>
                        <em data-restorable-hint hidden style="display:block;color:#9a3412;font-size:12px;font-style:normal;margin-top:6px">{{ __('lms::ui.restorable_hint') }}</em>
                    </div>
                    <small class="lms-field-help">{{ __('lms::ui.auto_53a5c6231d') }}</small>
                </div>
            @endif
        </section>
        <section>
            <div class="lms-form-section"><span class="lms-section-number">2</span><div><h2>{{ __('lms::ui.auto_e0db70a18f') }}</h2><p>{{ __('lms::ui.auto_df8c752522') }}</p></div></div>
            <div class="lms-form-grid">
                <label><span>{{ __('lms::ui.auto_1e4dbc7eaa') }}</span><input class="lms-input" name="phone" value="{{ old('phone', $instructor?->phone) }}"></label>
                <label><span>{{ __('lms::ui.auto_4906aa314c') }}</span><select class="lms-select" name="status"><option value="active" @selected(old('status', $instructor?->status ?? 'active') === 'active')>{{ __('lms::ui.auto_4d3d769b81') }}</option><option value="inactive" @selected(old('status', $instructor?->status) === 'inactive')>{{ __('lms::ui.auto_3cab03c00d') }}</option></select></label>
                <label class="lms-field-wide"><span>{{ __('lms::ui.auto_5642641819') }}</span><select class="lms-select" name="publishing_policy"><option value="review" @selected(old('publishing_policy', $instructor?->can_publish_directly ? 'direct' : 'review') === 'review')>{{ __('lms::ui.auto_a8beb2b127') }}</option><option value="direct" @selected(old('publishing_policy', $instructor?->can_publish_directly ? 'direct' : 'review') === 'direct')>{{ __('lms::ui.auto_e251a90d85') }}</option></select><small class="lms-field-help">{{ __('lms::ui.auto_9738977d2d') }}</small></label>
                <div class="lms-field-wide"><span class="lms-label">{{ __('lms::ui.auto_23010e0216') }}</span><input type="hidden" name="avatar_media_url" value="{{ old('avatar_media_url') }}" data-lms-media-url="admin-instructor-avatar"><input type="hidden" name="avatar_media_name" value="{{ old('avatar_media_name') }}" data-lms-media-name="admin-instructor-avatar"><button class="lms-btn lms-btn-ghost" type="button" data-lms-media-trigger="admin-instructor-picker" data-media-target="admin-instructor-avatar" data-media-type="image" data-media-accept="image/*" data-media-description="Choose or upload the instructor profile image.">{{ __('lms::ui.auto_1271b0deb6') }}</button><span class="lms-media-field-label" data-lms-media-label="admin-instructor-avatar">{{ $avatarUrl ? 'Current profile image' : 'No image selected.' }}</span><img class="lms-media-field-preview" src="{{ $avatarUrl }}" alt="" @if(!$avatarUrl) hidden @endif data-lms-media-preview="admin-instructor-avatar"></div>
            </div>
        </section>
        <section>
            <div class="lms-form-section"><span class="lms-section-number">3</span><div><h2>{{ __('lms::ui.auto_b29db920a5') }}</h2><p>{{ __('lms::ui.auto_fa60c4ea47') }}</p></div></div>
            <div class="lms-course-check-grid">@forelse($courses as $course)<label><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked(in_array($course->id, array_map('intval', (array) old('course_ids', $selectedCourses)), true))><span><strong>{{ $course->title }}</strong>@if($course->instructor_id && (!$isEdit || $course->instructor_id !== $instructor->id))<small>{{ __('lms::ui.auto_5218e5387f') }}</small>@else<small>{{ __('lms::ui.auto_023a7398f8') }}</small>@endif</span></label>@empty<div class="lms-empty-inline">{{ __('lms::ui.auto_9ecbd00f8e') }}</div>@endforelse</div>
        </section>
        <div class="lms-form-actions"><a href="{{ route('admin.plugins.lms.instructors') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_ea4788705e') }}</a><button class="lms-btn lms-btn-primary">{{ $isEdit ? __('lms::ui.admin_course_save_changes') : __('lms::ui.admin_instructor_add_title') }}</button></div>
    </form>
    @include('lms::shared.media-picker', ['mediaPickerKey' => 'admin-instructor-picker', 'mediaIndexUrl' => route('admin.plugins.lms.media.index', [], false), 'mediaStoreUrl' => route('admin.plugins.lms.media.store', [], false)])
</div></div>
@unless($isEdit)
<script>
(() => {
    const form = document.querySelector('[data-instructor-form]');
    const picker = document.querySelector('[data-instructor-user-picker]');
    if (!form || !picker) return;
    const search = picker.querySelector('[data-user-search]');
    const results = picker.querySelector('[data-user-results]');
    const selected = picker.querySelector('[data-selected-user]');
    const userId = form.querySelector('[data-instructor-user-id]');
    let timer;
    let controller;

    const hideResults = () => { results.hidden = true; search.setAttribute('aria-expanded', 'false'); };
    const choose = user => {
        userId.value = String(user.id);
        selected.hidden = false;
        selected.querySelector('[data-selected-user-initial]').textContent = String(user.name || user.email || 'U').slice(0, 1).toUpperCase();
        selected.querySelector('[data-selected-user-name]').textContent = user.name || 'Unnamed user';
        selected.querySelector('[data-selected-user-email]').textContent = user.email || '';
        const restorableHint = selected.querySelector('[data-restorable-hint]');
        if (restorableHint) { restorableHint.hidden = !user.restorable; }
        search.hidden = true;
        search.closest('label').hidden = true;
        hideResults();
    };
    const render = users => {
        results.replaceChildren();
        if (!users.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No available registered users found.';
            results.appendChild(empty);
        }
        users.forEach(user => {
            const button = document.createElement('button');
            button.type = 'button';
            const strong = document.createElement('strong');
            const small = document.createElement('small');
            strong.textContent = user.name || 'Unnamed user';
            small.textContent = user.email || '';
            button.append(strong, small);
            button.addEventListener('click', () => choose(user));
            results.appendChild(button);
        });
        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    };
    const load = () => {
        controller?.abort();
        controller = new AbortController();
        const url = new URL(picker.dataset.endpoint, window.location.origin);
        url.searchParams.set('search', search.value.trim());
        fetch(url, {headers: {'Accept': 'application/json'}, signal: controller.signal})
            .then(response => response.ok ? response.json() : Promise.reject())
            .then(payload => render(Array.isArray(payload.data) ? payload.data : []))
            .catch(error => { if (error?.name !== 'AbortError') hideResults(); });
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 220); });
    search.addEventListener('focus', load);
    picker.querySelector('[data-clear-user]').addEventListener('click', () => {
        userId.value = '';
        selected.hidden = true;
        search.closest('label').hidden = false;
        search.hidden = false;
        search.value = '';
        search.focus();
        load();
    });
    form.addEventListener('submit', event => {
        if (userId.value) return;
        event.preventDefault();
        search.setCustomValidity('Select a registered user.');
        search.reportValidity();
        search.setCustomValidity('');
    });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) hideResults(); });
})();
</script>
@endunless
</x-app-layout>
