@php
    $isEdit = (bool) $student;
    $selectedCourses = old('enrolled_course_ids', $student?->enrolled_course_ids ?? []);
    $avatarUrl = $student?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->avatar_path) : '';
@endphp

<x-app-layout>
    <div class="lms-course-shell lms-student-form-page">
        <div class="lms-course-container">
            <div class="lms-course-topbar">
                <a href="{{ route('admin.plugins.lms.students') }}" class="lms-back-link">{{ __('lms::ui.auto_1e69250a30') }}</a>
                <div class="lms-course-title">
                    <h1>{{ $isEdit ? 'Edit Student' : 'Add New Student' }}</h1>
                    <p>{{ $isEdit ? 'Update LMS student details.' : 'Create an LMS student record.' }}</p>
                </div>
                <div></div>
            </div>

            @if ($errors->any())
                <div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.plugins.lms.students.update', $student) : route('admin.plugins.lms.students.store') }}" class="lms-card lms-student-form-card">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="lms-form-grid">
                    <label>
                        <span>{{ __('lms::ui.auto_2688a544cd') }}</span>
                        <input class="lms-input" name="name" value="{{ old('name', $student?->name) }}" required>
                    </label>

                    <label>
                        <span>{{ __('lms::ui.auto_2800934ee0') }}</span>
                        <input class="lms-input" type="email" name="email" value="{{ old('email', $student?->email) }}" required>
                    </label>

                    <label>
                        <span>{{ __('lms::ui.auto_bcc254b55c') }}</span>
                        <input class="lms-input" name="phone" value="{{ old('phone', $student?->phone) }}">
                    </label>

                    <label>
                        <span>{{ __('lms::ui.auto_f553affa20') }}</span>
                        <select class="lms-select" name="status" required>
                            <option value="active" @selected(old('status', $student?->status ?? 'active') === 'active')>{{ __('lms::ui.auto_4d3d769b81') }}</option>
                            <option value="inactive" @selected(old('status', $student?->status) === 'inactive')>{{ __('lms::ui.auto_3cab03c00d') }}</option>
                        </select>
                    </label>

                    <label>
                        <span>{{ __('lms::ui.auto_97ce2ccb5d') }}</span>
                        <input class="lms-input" type="date" name="registered_at" value="{{ old('registered_at', $student?->registered_at?->format('Y-m-d')) }}">
                    </label>

                    <div>
                        <span class="lms-label">{{ __('lms::ui.auto_32036005d1') }}</span>
                        <input type="hidden" name="avatar_media_url" value="{{ old('avatar_media_url') }}" data-lms-media-url="admin-student-avatar">
                        <input type="hidden" name="avatar_media_name" value="{{ old('avatar_media_name') }}" data-lms-media-name="admin-student-avatar">
                        <button class="lms-btn lms-btn-ghost" type="button" data-lms-media-trigger="admin-student-picker" data-media-target="admin-student-avatar" data-media-type="image" data-media-accept="image/*" data-media-description="Choose or upload the student avatar.">{{ __('lms::ui.auto_48792feb3e') }}</button>
                        <span class="lms-media-field-label" data-lms-media-label="admin-student-avatar">{{ $avatarUrl ? 'Current avatar' : 'No image selected.' }}</span>
                        <img class="lms-media-field-preview" src="{{ $avatarUrl }}" alt="" @if(!$avatarUrl) hidden @endif data-lms-media-preview="admin-student-avatar">
                    </div>
                </div>

                <label class="lms-student-course-picker">
                    <span>{{ __('lms::ui.auto_f1f0a2b7d6') }}</span>
                    <select class="lms-select" name="enrolled_course_ids[]" multiple size="6">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected(in_array($course->id, array_map('intval', (array) $selectedCourses), true))>{{ $course->title }}</option>
                        @endforeach
                    </select>
                    <small>{{ __('lms::ui.auto_f203d5bc70') }}</small>
                </label>

                <label>
                    <span>{{ __('lms::ui.auto_f4c6f851b0') }}</span>
                    <textarea class="lms-textarea" name="notes" rows="5">{{ old('notes', $student?->notes) }}</textarea>
                </label>

                <div class="lms-form-actions">
                    <a href="{{ route('admin.plugins.lms.students') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_ea4788705e') }}</a>
                    <button type="submit" class="lms-btn lms-btn-primary">{{ $isEdit ? 'Save Changes' : 'Create Student' }}</button>
                </div>
            </form>
            @include('lms::shared.media-picker', ['mediaPickerKey' => 'admin-student-picker', 'mediaIndexUrl' => route('admin.plugins.lms.media.index', [], false), 'mediaStoreUrl' => route('admin.plugins.lms.media.store', [], false)])
        </div>
    </div>
</x-app-layout>
