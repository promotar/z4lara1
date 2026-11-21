@php
    $editorMode = $editorMode ?? 'admin';
    $isInstructorEditor = $editorMode === 'instructor';
    $locale = $locale ?? 'en';
    $locales = $locales ?? [];
    $formLocale = $isInstructorEditor ? app()->getLocale() : $locale;
    $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
    $statusLabels = [
        'draft' => $t('lms::ui.admin_course_status_draft'),
        'scheduled' => $t('lms::ui.admin_course_status_scheduled'),
        'published' => $t('lms::ui.admin_course_status_published'),
    ];
    $layoutComponent = $isInstructorEditor ? 'frontend-layout' : 'app-layout';
    $isEditing = isset($course) && $course;
    $programLabel = $isEditing && $course->program_type === 'diploma' ? $t('lms::ui.admin_course_type_diploma') : $t('lms::ui.admin_course_type_course');
    $pageTitle = $isEditing ? $t('lms::ui.admin_course_page_edit', ['type' => $programLabel]) : $t('lms::ui.admin_course_page_create');
    $formAction = $isInstructorEditor
        ? ($isEditing ? route('lms.front.instructor.courses.update', $course) : route('lms.front.instructor.courses.store'))
        : ($isEditing ? route('admin.plugins.lms.courses.update', $course) : route('admin.plugins.lms.courses.store'));
    $courseStoreUrl = $isInstructorEditor ? route('lms.front.instructor.courses.store') : route('admin.plugins.lms.courses.store');
    $courseEditUrlTemplate = $isInstructorEditor
        ? route('lms.front.instructor.courses.edit', ['course' => '__COURSE__'])
        : route('admin.plugins.lms.courses.edit', ['course' => '__COURSE__']);
    $curriculumUrlTemplate = $isInstructorEditor
        ? route('lms.front.instructor.courses.curriculum', ['course' => '__COURSE__'])
        : route('admin.plugins.lms.courses.curriculum', ['course' => '__COURSE__']);
    $lessonStoreUrl = $isInstructorEditor ? route('lms.front.instructor.lessons.store') : route('admin.plugins.lms.lessons.store');
    $lessonUpdateUrlTemplate = $isInstructorEditor
        ? route('lms.front.instructor.lessons.update', ['lesson' => '__LESSON__'])
        : route('admin.plugins.lms.lessons.update', ['lesson' => '__LESSON__']);
    $lessonDeleteUrlTemplate = $isInstructorEditor
        ? route('lms.front.instructor.lessons.destroy', ['lesson' => '__LESSON__'])
        : route('admin.plugins.lms.lessons.destroy', ['lesson' => '__LESSON__']);
    $mediaIndexUrl = $isInstructorEditor ? route('lms.front.media.index', [], false) : route('admin.plugins.lms.media.index', [], false);
    $mediaStoreUrl = $isInstructorEditor ? route('lms.front.media.store', [], false) : route('admin.plugins.lms.media.store', [], false);
    $mediaDeleteUrl = $isInstructorEditor ? route('lms.front.media.destroy', [], false) : route('admin.plugins.lms.media.destroy', [], false);
    $mediaFileTemplate = route('lms.front.media.file', ['path' => '__PATH__'], false);
    $courseViewUrl = $isEditing && $course->status === 'published'
        ? route('lms.front.courses.show', ['course' => $course->slug])
        : null;
    $coursePreviewUrl = $isEditing && ! $isInstructorEditor && $course->status !== 'published'
        ? route('admin.plugins.lms.courses.preview', $course)
        : null;
    $imageUrl = null;
    if ($isEditing && $course->featured_image_path) {
        $storedImagePath = ltrim((string) $course->featured_image_path, '/');
        $imageUrl = str_starts_with($storedImagePath, 'http://') || str_starts_with($storedImagePath, 'https://')
            ? $storedImagePath
            : route('lms.front.media.file', ['path' => $storedImagePath], false);
    }
    $units = collect($curriculumUnits ?? []);
    $isTranslationMode = $isEditing
        && ! $isInstructorEditor
        && $locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();
    $categoryTranslation = (string) ($categoryTranslation ?? '');
    $lessons = collect($courseLessons ?? [])
        ->when($isEditing, fn ($items) => $items->where('course_id', (int) $course->id))
        ->values();
    $statusValue = old('status', $isEditing ? $course->status : 'draft');
    $legacyInstructorId = '';
    if ($isEditing && ! $course->instructor_id && trim((string) $course->instructor_name) !== '') {
        $legacyMatches = collect($formOptions['instructors'])
            ->filter(fn ($option) => mb_strtolower(trim((string) $option['label'])) === mb_strtolower(trim((string) $course->instructor_name)));
        if ($legacyMatches->count() === 1) {
            $legacyInstructorId = (string) $legacyMatches->first()['value'];
        }
    }
    $selectedInstructor = (string) old('instructor_id', $isEditing ? ($course->instructor_id ?: $legacyInstructorId) : '');
    $selectedCategory = old('category', $isEditing ? $course->category : '');
    $selectedProgramType = old('program_type', $isEditing ? ($course->program_type ?: 'course') : 'course');
    $defaultOutcomes = [];
    $defaultFaqs = [];
    $outcomeRows = collect(old('outcomes', $isEditing ? ($course->outcomes ?: $defaultOutcomes) : $defaultOutcomes))
        ->map(fn ($item) => is_array($item)
            ? ['title' => trim((string) ($item['title'] ?? '')), 'text' => trim((string) ($item['text'] ?? ''))]
            : ['title' => trim((string) $item), 'text' => ''])
        ->filter(fn (array $item) => $item['title'] !== '' || $item['text'] !== '')
        ->values();
    $faqRows = collect(old('faqs', $isEditing ? ($course->faqs ?: $defaultFaqs) : $defaultFaqs))
        ->filter(fn ($item) => is_array($item))->values();
@endphp

<x-dynamic-component :component="$layoutComponent">
    @if ($isInstructorEditor)
        <link rel="stylesheet" href="{{ asset('platform/plugins/lms/admin/course-form.css') }}?v={{ filemtime(base_path('modules/lms/resources/assets/admin/course-form.css')) }}">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

    @if ($isInstructorEditor)
        <div class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
            @include('lms::frontend.portal-nav', ['portal' => 'instructor', 'portalAccountName' => $instructor->name])
    @endif

    <div class="lms-course-shell lms-builder" data-lms-builder data-course-id="{{ $course?->id }}">
        <div class="lms-course-container">
            <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" data-course-form novalidate>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="switch_locale" value="" data-switch-locale>
                <input type="hidden" name="action" value="save_draft" data-switch-action disabled>
                @if ($isEditing) @method('PUT') @else <input type="hidden" name="draft_course_id" value="" data-draft-course-id> @endif
                <input type="hidden" name="visibility" value="{{ old('visibility', $isEditing ? $course->visibility : 'public') }}">
                <input type="hidden" name="pricing_type" value="{{ old('pricing_type', $isEditing ? $course->pricing_type : 'paid') }}" data-pricing-type>
                <input type="hidden" name="currency" value="{{ old('currency', $isEditing ? $course->currency : 'JOD') }}">
                <input type="hidden" name="enrollment_limit_type" value="{{ old('enrollment_limit_type', $isEditing ? $course->enrollment_limit_type : 'limited') }}" data-seats-limit-type>
                <input type="hidden" name="enrollment_type" value="{{ old('enrollment_type', $isEditing ? $course->enrollment_type : 'open') }}">
                <input type="hidden" name="access_duration" value="{{ old('access_duration', $isEditing ? $course->access_duration : 'lifetime') }}">
                <input type="hidden" name="course_progress" value="{{ old('course_progress', $isEditing ? $course->course_progress : 'complete_previous') }}">
                @foreach (old('requirements', $course?->requirements ?: []) as $item)<input type="hidden" name="requirements[]" value="{{ $item }}">@endforeach
                @foreach (old('target_audience', $course?->target_audience ?: []) as $item)<input type="hidden" name="target_audience[]" value="{{ $item }}">@endforeach

                <header class="lms-builder-header">
                    <div class="lms-builder-actions">
                        <button class="lms-icon-button" type="button" title="{{ $t('lms::ui.auto_d1b79e8786') }}"><i data-lucide="more-vertical"></i></button>
                        @if($statusValue !== 'published')
                            <button class="lms-btn lms-btn-primary" type="submit" name="action" value="publish"><i data-lucide="upload"></i> {{ str_contains(strtolower((string) (collect($formOptions['statuses'])->last()['label'] ?? '')), 'submit') ? $t('lms::ui.admin_course_submit') : $t('lms::ui.admin_course_publish') }}</button>
                            <button class="lms-btn lms-btn-ghost" type="submit" name="action" value="save"><i data-lucide="save"></i> {{ $isEditing ? $t('lms::ui.admin_course_save_changes') : $t('lms::ui.admin_course_save_draft') }}</button>
                        @else
                            <button class="lms-btn lms-btn-primary" type="submit" name="action" value="save"><i data-lucide="save"></i> {{ $t('lms::ui.auto_f5d6040ed7') }}</button>
                        @endif
                        @if ($courseViewUrl)
                            <a class="lms-btn lms-btn-ghost" href="{{ $courseViewUrl }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> {{ $t('lms::ui.auto_dd7e87f510') }}</a>
                        @elseif ($coursePreviewUrl)
                            <a class="lms-btn lms-btn-ghost" href="{{ $coursePreviewUrl }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> {{ $t('lms::ui.auto_e4a024ffb9') }}</a>
                        @elseif (! $isInstructorEditor)
                            <button class="lms-btn lms-btn-ghost" type="submit" name="action" value="preview"><i data-lucide="eye"></i> {{ $t('lms::ui.auto_fc18a238ac') }}</button>
                        @endif
                    </div>
                    <div class="lms-builder-heading">
                        <nav>{{ $t('lms::ui.auto_389433c98f') }} <span>/</span> {{ $t('lms::ui.auto_7d2bf53c67') }} <span>/</span> {{ $pageTitle }}</nav>
                        <div><h1>{{ $pageTitle }}</h1><span class="lms-heading-icon"><i data-lucide="graduation-cap"></i></span></div>
                        <p>{{ $t('lms::ui.auto_3686002119') }}</p>
                    </div>
                </header>

                @if ($errors->any())
                    <div class="lms-alert lms-alert-error">{{ $t('lms::ui.auto_2b1af1a83e') }}</div>
                @endif
                @if (session('status'))
                    <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
                @endif

                @if (count($locales) > 1)
                    <div class="lms-course-lang-tabs" style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px" @if(! $isEditing) data-create-tabs @endif>
                        @foreach ($locales as $code => $name)
                            @php $tabTranslation = $isEditing ? $course->translations->firstWhere('locale', $code) : null; @endphp
                            <a href="{{ $isEditing ? ($isInstructorEditor ? route('lms.front.instructor.courses.edit', ['course' => $course, 'locale' => $code]) : route('admin.plugins.lms.courses.edit', ['course' => $course, 'locale' => $code])) : '#' }}"
                               @if(! $isEditing) data-create-locale="{{ $code }}" @endif
                               style="display:inline-flex;align-items:center;gap:6px;border:1px solid #dfe3ea;border-radius:7px;background:{{ $locale === $code ? '#7c1d1d' : '#fff' }} !important;color:{{ $locale === $code ? '#fff' : '#344054' }} !important;border-color:{{ $locale === $code ? '#7c1d1d' : '#dfe3ea' }} !important;padding:7px 13px;font-size:12px;font-weight:800;text-decoration:none">
                                {{ $name }}
                                <span style="font-size:10px;opacity:.8">{{ $code === 'en' ? '' : ($tabTranslation ? '✓' : '+') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="lms-builder-grid">
                    <main class="lms-builder-main">
<nav class="lms-steps" data-steps-nav data-has-errors="{{ $errors->any() ? '1' : '0' }}" aria-label="{{ $t('lms::ui.auto_64b2b1ee70') }}"><button type="button" class="lms-step-tab is-active" data-step-tab="1"><b>1</b><span>{{ $t('lms::ui.auto_a862265abf') }}</span></button><button type="button" class="lms-step-tab" data-step-tab="2"><b>2</b><span>{{ $t('lms::ui.auto_8d52dd6af3') }}</span></button><button type="button" class="lms-step-tab" data-step-tab="3"><b>3</b><span>{{ $t('lms::ui.auto_3b3590bf9c') }}</span></button></nav>

                        <section class="lms-card lms-builder-card">
                            <h2>{{ $t('lms::ui.auto_2a75ee6a97') }}</h2>
                            <div class="lms-builder-form-grid two">
                                <div class="lms-field"><label class="lms-label" for="program_type">{{ $t('lms::ui.auto_14c0d2736a') }} <span>*</span></label><select class="lms-select" id="program_type" name="program_type" required><option value="course" @selected($selectedProgramType === 'course')>{{ $t('lms::ui.auto_3730c98664') }}</option><option value="diploma" @selected($selectedProgramType === 'diploma')>{{ $t('lms::ui.auto_1f0561465e') }}</option></select></div>
                                <div class="lms-field"><label class="lms-label" for="title"><span data-program-title-label>{{ $selectedProgramType === 'diploma' ? $t('lms::ui.admin_course_diploma_title') : $t('lms::ui.admin_course_title') }}</span> <span>*</span></label><input class="lms-input" id="title" name="title" value="{{ old('title', $isEditing ? $course->title : '') }}" required data-course-title></div>
                                <div class="lms-field"><label class="lms-label" for="slug">{{ $t('lms::ui.auto_c6c957d13b') }} <span>*</span></label><input class="lms-input" id="slug" name="slug" value="{{ old('slug', $isEditing ? $course->slug : '') }}" required data-course-slug></div>
                                <div class="lms-field"><label class="lms-label" for="category">{{ $t('lms::ui.auto_3adbdb3ac0') }} <span>*</span></label>
                                    @if ($isTranslationMode)
                                    <input class="lms-input" id="category" name="category_translation" value="{{ old('category_translation', $categoryTranslation) }}" placeholder="{{ $selectedCategory }}" maxlength="120" autocomplete="off">
                                    <p class="lms-help">{{ $t('lms::ui.category_translation_hint') }}</p>
                                    @else
                                    <select class="lms-select" id="category" name="category" data-course-category><option value="">{{ $t('lms::ui.auto_03368e3c1e') }}</option>@foreach ($formOptions['categories'] as $option)<option value="{{ $option['value'] }}" @selected($selectedCategory === $option['value'])>{{ $option['label'] }}</option>@endforeach
@if($selectedCategory !== '' && ! collect($formOptions['categories'])->contains(fn ($option) => $option['value'] === $selectedCategory))<option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }}</option>@endif</select><input class="lms-input" id="category_new" name="category_new" value="{{ old('category_new') }}" placeholder="{{ $t('lms::ui.auto_b9a492bdfa') }}" maxlength="120" style="margin-top:8px" data-course-category-new><button class="lms-btn lms-btn-ghost lms-category-add" type="button" data-course-category-add><i data-lucide="plus"></i> {{ $t('lms::ui.auto_d0d4e3688f') }}</button><p class="lms-help" data-category-hint>{{ $t('lms::ui.auto_26821f4ec6') }}</p>
                                    @endif
                            </div>
                                <div class="lms-field"><label class="lms-label" for="instructor_id">{{ $t('lms::ui.auto_07f8bcc8ac') }} <span>*</span></label><select class="lms-select" id="instructor_id" name="instructor_id" required data-course-instructor><option value="">{{ $t('lms::ui.auto_b87122e47d') }}</option>@foreach ($formOptions['instructors'] as $option)<option value="{{ $option['value'] }}" @selected($selectedInstructor === (string) $option['value'] || ($selectedInstructor === '' && $isInstructorEditor && count($formOptions['instructors']) === 1))>{{ $option['label'] }}@if($option['email'] !== '') — {{ $option['email'] }}@endif</option>@endforeach</select></div>
                            </div>

                            <div class="lms-builder-form-grid three">
                                <div class="lms-field"><label class="lms-label" for="status">{{ $t('lms::ui.auto_c6e3fcee5f') }} <span>*</span></label><select class="lms-select" id="status" name="status" required data-course-status>@foreach ($isEditing ? $formOptions['statuses'] : [['value' => 'draft', 'label' => $t('lms::ui.admin_course_status_draft')]] as $option)<option value="{{ $option['value'] }}" @selected($statusValue === $option['value'])>{{ $option['label'] }}</option>@endforeach</select>@if(! $isEditing)<p class="lms-help">{{ $t('lms::ui.auto_7443dd9b11') }}</p>@endif</div>
                                <div class="lms-field"><label class="lms-label" for="enrollment_limit">{{ $t('lms::ui.auto_4a5c594451') }}</label><div class="lms-input-with-icon"><i data-lucide="users"></i><input class="lms-input" id="enrollment_limit" name="enrollment_limit" type="number" min="1" value="{{ old('enrollment_limit', $isEditing ? $course->enrollment_limit : 30) }}" data-seats-input></div><label class="lms-check"><input type="checkbox" data-seats-unlimited value="1" @checked(old('enrollment_limit_type', $isEditing ? $course->enrollment_limit_type : 'limited') === 'unlimited')><span>{{ $t('lms::ui.auto_8311cb2338') }}</span></label></div>
                                <div class="lms-field"><label class="lms-label" for="access_duration_select">{{ $t('lms::ui.auto_bb4183b1c2') }}</label><select class="lms-select" id="access_duration_select" data-access-duration><option value="six_months" @selected(old('access_duration', $course?->access_duration) === 'six_months')>{{ $t('lms::ui.auto_1d4545e8da') }}</option><option value="one_year" @selected(old('access_duration', $course?->access_duration) === 'one_year')>{{ $t('lms::ui.auto_cc1a252ae1') }}</option><option value="lifetime" @selected(old('access_duration', $course?->access_duration ?: 'lifetime') === 'lifetime')>{{ $t('lms::ui.auto_1a10be3692') }}</option></select></div>
                                <div class="lms-field"><label class="lms-label" for="publish_date">{{ $t('lms::ui.auto_db3794c7d7') }}</label><input class="lms-input" id="publish_date" name="publish_date" type="datetime-local" value="{{ old('publish_date', $course?->publish_date?->format('Y-m-d\TH:i')) }}"></div>
                                <div class="lms-field"><label class="lms-label" for="language">{{ $t('lms::ui.auto_d9d60cc013') }} <span>*</span></label><select class="lms-select" id="language" name="language" required>@foreach ($formOptions['languages'] as $option)<option value="{{ $option['value'] }}" @selected(old('language', $course?->language ?: 'English') === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></div>
                                <div class="lms-field"><label class="lms-label" for="level">{{ $t('lms::ui.auto_be6d529baa') }} <span>*</span></label><select class="lms-select" id="level" name="level" required>@foreach ($formOptions['levels'] as $option)<option value="{{ $option['value'] }}" @selected(old('level', $course?->level ?: 'Beginner') === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></div>
                                <div class="lms-field"><label class="lms-label" for="subtitle">{{ $t('lms::ui.auto_68d742fe54') }}</label><input class="lms-input" id="subtitle" name="subtitle" value="{{ old('subtitle', $course?->subtitle) }}"></div>
                            </div>
                            <div class="lms-builder-form-grid three lms-training-row">
                                <div class="lms-field"><label class="lms-label" for="training_mode">{{ $t('lms::ui.auto_2dd1207d4b') }} <span>*</span></label><select class="lms-select" id="training_mode" name="training_mode" required data-training-mode><option value="online" @selected(old('training_mode', $isEditing ? ($course->training_mode ?: 'online') : 'online') === 'online')>{{ $t('lms::ui.auto_54f664c70c') }}</option><option value="in_person" @selected(old('training_mode', $isEditing ? ($course->training_mode ?: 'online') : 'online') === 'in_person')>{{ $t('lms::ui.auto_de5b330558') }}</option><option value="both" @selected(old('training_mode', $isEditing ? ($course->training_mode ?: 'online') : 'online') === 'both')>{{ $t('lms::ui.auto_5bd749228f') }}</option></select></div>
                                <div class="lms-field" data-online-price-field><label class="lms-label" for="online_price">{{ $t('lms::ui.auto_2d1c7bd872') }}</label><input class="lms-input" id="online_price" name="online_price" type="number" min="0" step="0.01" value="{{ old('online_price', $isEditing ? $course->online_price : '') }}" data-online-price><p class="lms-help">{{ $t('lms::ui.auto_f34299607e') }}</p></div>
                                <div class="lms-field" data-in-person-price-field><label class="lms-label" for="in_person_price">{{ $t('lms::ui.auto_8b278f3490') }}</label><input class="lms-input" id="in_person_price" name="in_person_price" type="number" min="0" step="0.01" value="{{ old('in_person_price', $isEditing ? $course->in_person_price : '') }}" data-in-person-price><p class="lms-help">{{ $t('lms::ui.auto_171ca14e75') }}</p></div>
                            </div>

                            <div class="lms-course-media-copy">
                                <div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_f3f1f1dc3f') }} <span>*</span></label><input type="hidden" name="featured_image_media_url" value="{{ old('featured_image_media_url') }}" data-course-media-url><input type="hidden" name="featured_image_media_name" value="{{ old('featured_image_media_name') }}" data-course-media-name><button class="lms-builder-upload" type="button" data-open-media="course"><i data-lucide="cloud-upload"></i><strong>{{ $t('lms::ui.auto_48792feb3e') }}</strong><small>{{ $t('lms::ui.auto_9f5f51c11c') }}</small>@if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $t('lms::ui.auto_53ef0c01ad') }}" data-course-image-preview>@endif</button><p class="lms-help">{{ $t('lms::ui.auto_407f370e7b') }}</p><button class="lms-btn lms-btn-ghost lms-remove-image @if(! $imageUrl) lms-hidden @endif" type="button" data-remove-course-image><i data-lucide="trash-2"></i> {{ $t('lms::ui.auto_f4701af485') }}</button></div>
                                <div class="lms-field"><label class="lms-label" for="short_description">{{ $t('lms::ui.auto_80d410090e') }} <span>*</span></label><textarea class="lms-textarea lms-short-copy" id="short_description" name="short_description" maxlength="500" required>{{ old('short_description', $course?->short_description) }}</textarea><div class="lms-counter"><span data-short-count>0</span>/500</div></div>
                            </div>

                            <div class="lms-field"><label class="lms-label" for="overview">{{ $t('lms::ui.auto_3b878279a0') }} <span style="color:#7a899e;font-weight:600">{{ $t('lms::ui.auto_a84de01774') }}</span></label><textarea class="lms-textarea" id="overview" name="overview" rows="4" maxlength="600" placeholder="{{ $t('lms::ui.builder_overview_placeholder') }}">{{ old('overview', $course?->overview) }}</textarea><div class="lms-counter"><span data-overview-count>0</span>/600</div></div>
<div class="lms-field"><label class="lms-label" for="description">{{ $t('lms::ui.auto_823371b341') }} <span>*</span></label><textarea id="description" name="description" data-course-editor>{{ old('description', $course?->description) }}</textarea></div>

                            <section class="lms-course-about-editor" aria-labelledby="course-about-heading">
                                <div class="lms-card-heading-row">
                                    <div><h2 id="course-about-heading">{{ $t('lms::ui.auto_8c536fca86') }}</h2><p>{{ $t('lms::ui.auto_3f3daef9ec') }}</p></div>
                                </div>
                                <div class="lms-repeatable-list" data-outcomes-list>
                                    @foreach ($outcomeRows as $outcomeIndex => $outcome)
                                        <div class="lms-repeatable-row lms-learn-editor-row" data-outcome-row><div><input class="lms-input" name="outcomes[{{ $outcomeIndex }}][title]" value="{{ $outcome['title'] }}" maxlength="255" placeholder="{{ $t('lms::ui.auto_aeed1f7e3a') }}"><textarea class="lms-textarea" name="outcomes[{{ $outcomeIndex }}][text]" maxlength="1000" placeholder="{{ $t('lms::ui.builder_outcome_text_placeholder') }}">{{ $outcome['text'] }}</textarea></div><button class="lms-icon-button" type="button" data-remove-row aria-label="{{ $t('lms::ui.auto_b5fb6135ca') }}"><i data-lucide="trash-2"></i></button></div>
                                    @endforeach
                                </div>
                                <button class="lms-btn lms-btn-ghost lms-add-row-bottom" type="button" data-add-outcome><i data-lucide="plus"></i> {{ $t('lms::ui.auto_19f6e26702') }}</button>
                            </section>

                            <section class="lms-course-about-editor" aria-labelledby="course-faq-heading">
                                <div class="lms-card-heading-row">
                                    <button class="lms-btn lms-btn-ghost" type="button" data-add-faq><i data-lucide="plus"></i> {{ $t('lms::ui.auto_fe90d72a62') }}</button>
                                    <div><h2 id="course-faq-heading">{{ $t('lms::ui.auto_44618f4882') }}</h2><p>{{ $t('lms::ui.auto_8f73282229') }}</p></div>
                                </div>
                                <div class="lms-repeatable-list" data-faq-list>
                                    @foreach ($faqRows as $faqIndex => $faq)
                                        <div class="lms-repeatable-row lms-faq-editor-row" data-faq-row>
                                            <div><input class="lms-input" name="faqs[{{ $faqIndex }}][question]" value="{{ $faq['question'] ?? '' }}" maxlength="255" placeholder="{{ $t('lms::ui.auto_a97ea56b0e') }}"><textarea class="lms-textarea" name="faqs[{{ $faqIndex }}][answer]" maxlength="1000" placeholder="{{ $t('lms::ui.builder_faq_answer_placeholder') }}">{{ $faq['answer'] ?? '' }}</textarea></div>
                                            <button class="lms-icon-button" type="button" data-remove-row aria-label="{{ $t('lms::ui.auto_b6b4215805') }}"><i data-lucide="trash-2"></i></button>
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <details class="lms-builder-advanced">
                                <summary>{{ $t('lms::ui.auto_f5484f5d29') }}</summary>
                                <div class="lms-builder-form-grid two">
                                    <div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_894963574f') }}</label><input class="lms-input" name="promo_video_url" type="url" value="{{ old('promo_video_url', $course?->promo_video_url) }}" data-promo-video-url></div>
                                    <div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_815087ba3a') }}</label><button class="lms-btn lms-btn-ghost lms-full" type="button" data-open-media="promo"><i data-lucide="play-square"></i> {{ $t('lms::ui.auto_48792feb3e') }}</button><p class="lms-help" data-promo-video-name>{{ old('promo_video_url', $course?->promo_video_url) ? $t('lms::ui.admin_course_video_selected') : $t('lms::ui.admin_course_no_video') }}</p></div>
                                </div>
                            </details>
                        </section>

                        <section class="lms-card lms-builder-card lms-curriculum-card" data-curriculum>
                            <div class="lms-card-heading-row"><div class="lms-card-heading-actions"><button class="lms-btn lms-btn-primary" type="button" data-add-unit><i data-lucide="plus"></i> {{ $t('lms::ui.auto_a3cd596190') }}</button></div><div><h2>{{ $t('lms::ui.auto_42f66b37e7') }} <i data-lucide="book-open"></i></h2><p>{{ $t('lms::ui.auto_886b20eb5b') }}</p></div></div>
                            <div class="lms-unit-list" data-unit-list>
                                @forelse ($units as $unit)
                                    @php $unitLessons = $lessons->where('module_title', $unit['title'])->sortBy('lesson_order'); @endphp
                                    <article class="lms-unit" data-unit data-key="{{ $unit['key'] }}" data-title="{{ $unit['title'] }}" data-previous-title="{{ $unit['title'] }}" data-order="{{ $unit['order'] }}">
                            <header><span class="lms-unit-grip" title="{{ $t('lms::ui.auto_475df2d1e5') }}" aria-label="{{ $t('lms::ui.auto_475df2d1e5') }}"><i data-lucide="grip-vertical"></i></span><button type="button" class="lms-unit-toggle" data-toggle-unit><i data-lucide="chevron-up"></i></button><input value="{{ $unit['title'] }}" aria-label="{{ $t('lms::ui.auto_25e70db460') }}" data-unit-title><button class="lms-icon-button" type="button" title="{{ $t('lms::ui.auto_8864be0b8a') }}" data-delete-unit><i data-lucide="trash-2"></i></button></header>
                                        <div class="lms-unit-lessons" data-unit-lessons>
                                            @foreach ($unitLessons as $lesson)
                                    <div class="lms-curriculum-lesson" data-lesson-id="{{ $lesson['id'] }}"><span class="lms-lesson-grip" title="{{ $t('lms::ui.auto_9739b26543') }}" aria-label="{{ $t('lms::ui.auto_9739b26543') }}"><i data-lucide="grip-vertical"></i></span><span class="lms-duration">{{ sprintf('%02d:%02d', $lesson['duration_hours'], $lesson['duration_minutes']) }}</span><span class="lms-lesson-type"><i data-lucide="{{ $lesson['lesson_type'] === 'video' ? 'video' : ($lesson['lesson_type'] === 'pdf' ? 'file-text' : 'align-left') }}"></i></span><strong><span data-lesson-order-number>{{ $lesson['lesson_order'] }}.</span> <span data-lesson-title-text>{{ $lesson['title'] }}</span></strong><span class="lms-published"><i data-lucide="circle-check"></i></span><button class="lms-icon-button" type="button" title="{{ $t('lms::ui.auto_536a135a75') }}" data-edit-lesson="{{ $lesson['id'] }}"><i data-lucide="pencil"></i></button><button class="lms-icon-button" type="button" title="{{ $t('lms::ui.auto_a40983c1a8') }}" data-delete-lesson="{{ $lesson['id'] }}"><i data-lucide="trash-2"></i></button></div>
                                            @endforeach
                                            <button class="lms-add-unit-lesson" type="button" data-add-lesson-to-unit><i data-lucide="plus"></i> {{ $t('lms::ui.auto_82ec46fa1f') }}</button>
                                        </div>
                                    </article>
                                @empty
                                    <div class="lms-curriculum-empty" data-curriculum-empty><i data-lucide="book-open"></i><strong>{{ $t('lms::ui.auto_02e13caecc') }}</strong><p>{{ $t('lms::ui.auto_bf98635922') }}</p></div>
                                @endforelse
                            </div>
                            <p class="lms-save-state" data-curriculum-status></p>
                        </section>
<section class="lms-card lms-builder-card" data-step-group="5" data-review-card><div class="lms-card-heading-row"><div><h2>{{ $t('lms::ui.admin_course_review_publish') }} <i data-lucide="badge-check"></i></h2><p>{{ $t('lms::ui.auto_50fb4fd168') }}</p></div></div><div class="lms-review-grid"><div><span>{{ $t('lms::ui.auto_b78a322350') }}</span><strong data-review-title>—</strong></div><div><span>{{ $t('lms::ui.auto_07a10f202c') }}</span><strong data-review-program>—</strong></div><div><span>{{ $t('lms::ui.auto_3adbdb3ac0') }}</span><strong data-review-category>—</strong></div><div><span>{{ $t('lms::ui.auto_07f8bcc8ac') }}</span><strong data-review-instructor>—</strong></div><div><span>{{ $t('lms::ui.auto_c243ca8e66') }}</span><strong data-review-training>—</strong></div><div><span>{{ $t('lms::ui.auto_fd9402f78b') }}</span><strong data-review-online>—</strong></div><div><span>{{ $t('lms::ui.auto_7089f0d3de') }}</span><strong data-review-inperson>—</strong></div><div><span>{{ $t('lms::ui.auto_0c5d4e7801') }}</span><strong data-review-units>—</strong></div></div><div class="lms-review-actions"><button class="lms-btn lms-btn-ghost" type="button" data-step-goto="1"><i data-lucide="arrow-left"></i> {{ $t('lms::ui.auto_2eee5dbccd') }}</button><button class="lms-btn lms-btn-ghost" type="button" data-review-save><i data-lucide="save"></i> {{ $t('lms::ui.auto_daa6b483e9') }}</button><button class="lms-btn lms-btn-primary" type="button" data-review-publish><i data-lucide="upload"></i> {{ $t('lms::ui.auto_110a4b01be') }}</button></div></section>
                    </main>

                    <aside class="lms-builder-aside">
<section class="lms-card lms-builder-side-card" data-publish-card><h2>{{ $t('lms::ui.auto_9fb1f5afe9') }} <i data-lucide="radio"></i></h2><div class="lms-status-panel {{ $statusValue === 'published' ? 'published' : '' }}"><strong><span></span><b data-status-label>{{ $statusLabels[$statusValue] ?? ucfirst($statusValue) }}</b></strong><p data-status-copy>{{ $statusValue === 'published' ? $t('lms::ui.admin_course_visible') : $t('lms::ui.admin_course_not_visible') }}</p></div><p class="lms-side-label">{{ $t('lms::ui.auto_e56d9d6cee') }}</p><strong data-publish-summary>{{ $course?->published_at?->translatedFormat('M d, Y h:i A') ?: $t('lms::ui.admin_course_not_published') }}</strong><button class="lms-btn lms-btn-ghost lms-full" type="button" data-focus-publish><i data-lucide="settings-2"></i> {{ $t('lms::ui.admin_course_change_status') }}</button></section>
                        <section class="lms-card lms-builder-side-card"><h2>{{ $t('lms::ui.auto_07f8bcc8ac') }} <i data-lucide="user-round"></i></h2><div class="lms-person-summary"><span>{{ strtoupper(mb_substr($selectedInstructor ?: 'U', 0, 1)) }}</span><div><strong data-instructor-summary>{{ $selectedInstructor ?: $t('lms::ui.admin_course_unassigned') }}</strong><small>{{ $t('lms::ui.auto_ddda746cc1') }}</small></div></div><button class="lms-btn lms-btn-ghost lms-full" type="button" data-focus-instructor>{{ $t('lms::ui.auto_31f8ff581f') }}</button></section>
                        <section class="lms-card lms-builder-side-card"><h2>{{ $t('lms::ui.auto_3adbdb3ac0') }} <i data-lucide="folder"></i></h2><strong data-category-summary>{{ $selectedCategory ?: $t('lms::ui.admin_course_unassigned') }}</strong><button class="lms-btn lms-btn-ghost lms-full" type="button" data-focus-category>{{ $t('lms::ui.auto_13a12d45b2') }}</button></section>
                        <section class="lms-card lms-builder-side-card" data-readiness-card><h2>{{ $t('lms::ui.auto_d8cfa70e66') }} <i data-lucide="badge-check"></i></h2><p class="lms-side-label"><strong data-ready-count>0</strong>{{ $t('lms::ui.auto_595ac46062') }}</p><ul class="lms-ready-list" data-ready-list><li data-ready-item="title">{{ $t('lms::ui.auto_d8136f6a93') }}</li><li data-ready-item="short_description">{{ $t('lms::ui.auto_c1069a4808') }}</li><li data-ready-item="overview">{{ $t('lms::ui.auto_3b878279a0') }}</li><li data-ready-item="description">{{ $t('lms::ui.auto_d87023beec') }}</li><li data-ready-item="category">{{ $t('lms::ui.auto_3adbdb3ac0') }}</li><li data-ready-item="instructor">{{ $t('lms::ui.auto_07f8bcc8ac') }}</li><li data-ready-item="image">{{ $t('lms::ui.auto_cd1f613eb3') }}</li><li data-ready-item="curriculum">{{ $t('lms::ui.auto_5edfc078b3') }}</li></ul></section>
<section class="lms-card lms-builder-side-card"><h2>{{ $t('lms::ui.auto_8f77b675d8') }} <i data-lucide="chart-no-axes-column-increasing"></i></h2><dl><div><dt>{{ $t('lms::ui.auto_3562c1c160') }}</dt><dd data-summary-units>{{ $units->count() }}</dd></div><div><dt>{{ $t('lms::ui.auto_62bdcf6be3') }}</dt><dd data-summary-lessons>{{ $lessons->count() }}</dd></div><div><dt>{{ $t('lms::ui.auto_fd747cf709') }}</dt><dd data-summary-duration>{{ intdiv($lessons->sum(fn($lesson) => ($lesson['duration_hours'] * 60) + $lesson['duration_minutes']), 60) }}h {{ $lessons->sum(fn($lesson) => ($lesson['duration_hours'] * 60) + $lesson['duration_minutes']) % 60 }}m</dd></div><div><dt>{{ $t('lms::ui.auto_18607cfaf5') }}</dt><dd>{{ $enrolledStudentCount ?? 0 }}</dd></div></dl>@if($coursePreviewUrl)<a class="lms-btn lms-btn-ghost lms-full" href="{{ $coursePreviewUrl }}"><i data-lucide="chevron-left"></i> {{ $t('lms::ui.auto_5d5cd268b8') }}</a>@endif</section>
                    </aside>
                </div>
<div class="lms-steps-footer" data-steps-footer><button type="button" class="lms-btn lms-btn-ghost" data-step-back><i data-lucide="arrow-left"></i> {{ $t('lms::ui.auto_0557fa923d') }}</button><span class="lms-step-label" data-step-label>{{ $t('lms::ui.auto_00fc07833f') }}</span><span class="lms-step-message" data-step-message></span><button type="button" class="lms-btn lms-btn-primary" data-step-next>{{ $t('lms::ui.auto_10ac3d0425') }} <i data-lucide="arrow-right"></i></button></div>
            </form>
        </div>

        <div class="lms-builder-modal" data-lesson-modal hidden aria-hidden="true">
            <div class="lms-builder-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lesson-modal-title">
                <header><h2 id="lesson-modal-title">{{ $t('lms::ui.auto_f9062fcfa6') }}</h2><button class="lms-icon-button" type="button" data-close-lesson><i data-lucide="x"></i></button></header>
                <form data-lesson-form enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="lesson_id" data-lesson-id><input type="hidden" name="course_id" data-lesson-course><input type="hidden" name="lesson_type" value="text"><input type="hidden" name="status" value="draft"><input type="hidden" name="visibility" value="enrolled"><input type="hidden" name="pdf_media_url" value="" data-modal-pdf-media-url><input type="hidden" name="pdf_media_name" value="" data-modal-pdf-media-name>
                    <section><h3>{{ $t('lms::ui.auto_83c64d5bf5') }}</h3><div class="lms-builder-form-grid two"><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_caa7b385e2') }} <span>*</span></label><input class="lms-input" name="title" required data-lesson-title></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_19b80f00ab') }} <span>*</span></label><input class="lms-input" name="slug" required data-lesson-slug><p class="lms-help">{{ $t('lms::ui.auto_26cac3a228') }}</p></div></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_36e3402e4d') }} <span>*</span></label><textarea class="lms-textarea" name="description" maxlength="500" required></textarea></div></section>
                    <section><div class="lms-section-title-inline"><h3>{{ $t('lms::ui.auto_8a8bc0eaaa') }}</h3><span>{{ $t('lms::ui.auto_18f9de5b5c') }}</span></div>
                        <div class="lms-content-block"><h4><i data-lucide="video"></i> {{ $t('lms::ui.auto_7a215ffe71') }}</h4><div class="lms-video-source"><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_cf26c0d5b7') }}</label><input class="lms-input" name="video_url" type="url" placeholder="{{ $t('lms::ui.auto_239d68c786') }}"></div><button class="lms-btn lms-btn-ghost" type="button" data-open-media="video"><i data-lucide="play-square"></i> {{ $t('lms::ui.auto_48792feb3e') }}</button></div><div class="lms-selected-files lms-selected-media-line" data-selected-video-media hidden></div><label class="lms-check"><input type="checkbox" name="free_preview" value="1"><span>{{ $t('lms::ui.auto_ed99789f58') }}</span></label><p class="lms-help">{{ $t('lms::ui.auto_1055e98ad0') }}</p></div>
                        <div class="lms-content-block"><h4><i data-lucide="align-left"></i> {{ $t('lms::ui.auto_52e79d5273') }}</h4><textarea name="content" data-lesson-editor></textarea><div class="lms-editor-media-actions"><button class="lms-btn lms-btn-ghost" type="button" data-insert-editor-image><i data-lucide="image"></i> {{ $t('lms::ui.auto_2e5628ef94') }}</button><button class="lms-btn lms-btn-ghost" type="button" data-open-media="editor-file"><i data-lucide="images"></i> {{ $t('lms::ui.auto_ce98486dbe') }}</button></div><div class="lms-selected-files lms-selected-media-line" data-editor-media-list hidden></div></div>
                        <div class="lms-content-block"><h4><i data-lucide="file-text"></i> {{ $t('lms::ui.admin_course_pdf_files') }}</h4><p class="lms-help">{{ $t('lms::ui.auto_8fa35a3dfa') }}</p><div class="lms-file-actions"><button class="lms-btn lms-btn-ghost" type="button" data-open-media="attachment"><i data-lucide="gallery-horizontal"></i> {{ $t('lms::ui.auto_48792feb3e') }}</button></div><div class="lms-selected-files" data-selected-files><span>{{ $t('lms::ui.auto_b21e5fa4fd') }}</span></div><div data-media-attachment-inputs></div><div data-removed-attachment-inputs></div></div>
                    </section>
                    <div class="lms-modal-columns">
                        <section><h3>{{ $t('lms::ui.auto_915815e4e0') }}</h3><div class="lms-duration-row"><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_926e3f8f9c') }} <span>*</span></label><div><input class="lms-input" name="duration_hours" type="number" min="0" max="999" value="0" required><span>{{ $t('lms::ui.auto_6a7e731616') }}</span><input class="lms-input" name="duration_minutes" type="number" min="0" max="59" value="30" required><span>{{ $t('lms::ui.auto_f670ea66cf') }}</span></div></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_d7a509e49a') }} <span>*</span></label><select class="lms-select" name="availability" required data-modal-availability>@foreach($lessonFormOptions['availability'] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select></div><div class="lms-field" data-modal-prerequisite-field hidden><label class="lms-label">{{ $t('lms::ui.auto_6932d675e8') }} <span>*</span></label><select class="lms-select" name="prerequisite_lesson_id" data-modal-prerequisite></select><p class="lms-help">{{ $t('lms::ui.auto_8f7edabe29') }}</p></div><div class="lms-field" data-modal-schedule-field hidden><label class="lms-label">{{ $t('lms::ui.auto_5619859087') }} <span>*</span></label><input class="lms-input" type="datetime-local" name="publish_date" data-modal-schedule></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_7004d1fb24') }} <span>*</span></label><select class="lms-select" name="access" required>@foreach($lessonFormOptions['access'] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select></div></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_3f06d3ad6b') }} <span>*</span></label><div class="lms-inline-options"><label><input type="radio" name="completion_requirement" value="manual" checked> {{ $t('lms::ui.auto_60091140eb') }}</label><label><input type="radio" name="completion_requirement" value="auto"> {{ $t('lms::ui.auto_33961092ee') }}</label></div></div></section>
                        <section><h3>{{ $t('lms::ui.auto_5d03795271') }}</h3><div class="lms-builder-form-grid two"><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_62851e02bb') }} <span>*</span></label><select class="lms-select" name="course_selector" disabled><option>{{ $course?->title ?: $t('lms::ui.admin_course_current_draft') }}</option></select></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_e1e594dd1b') }} <span>*</span></label><select class="lms-select" name="module_title" required data-module-select></select></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_160b56abf1') }} <span>*</span></label><input class="lms-input" name="lesson_order" type="number" min="1" value="1" required></div><div class="lms-field"><label class="lms-label">{{ $t('lms::ui.auto_7b00ad8649') }} <span>*</span></label><select class="lms-select" name="author_id" required>@foreach($lessonAuthors as $author)<option value="{{ $author->id }}" @selected($author->id === auth()->id())>{{ $author->name }}</option>@endforeach</select></div></div><input type="hidden" name="module_order" value="1" data-module-order></section>
                    </div>
                    <div class="lms-modal-errors" data-lesson-errors hidden></div>
                    <footer><button class="lms-btn lms-btn-ghost" type="button" data-close-lesson>{{ $t('lms::ui.auto_ea4788705e') }}</button><button class="lms-btn lms-btn-primary" type="submit" data-save-lesson><i data-lucide="plus"></i> <span>{{ $t('lms::ui.auto_82ec46fa1f') }}</span></button></footer>
                </form>
            </div>
        </div>

        <div class="lms-builder-modal lms-media-picker" data-media-modal hidden aria-hidden="true"><div class="lms-builder-modal-dialog" role="dialog" aria-modal="true"><header><div><h2>{{ $t('lms::ui.auto_f38e6f393f') }} <small style="color:#8b93a3;font-size:11px;font-weight:600;margin-inline-start:6px">v{{ json_decode((string) @file_get_contents(base_path('modules/lms/module.json')), true)['version'] ?? '' }}</small></h2><p data-media-description>{{ $t('lms::ui.auto_5a0e74fdd4') }}</p></div><button class="lms-icon-button" type="button" data-close-media><i data-lucide="x"></i></button></header><form class="lms-media-upload-panel" data-media-upload-form enctype="multipart/form-data"><div><strong>{{ $t('lms::ui.auto_75eac6d96c') }}</strong><span>{{ $t('lms::ui.auto_35de73c2d4') }}</span></div><input class="lms-input" type="text" name="title" placeholder="{{ $t('lms::ui.auto_6097f5f7cc') }}" data-media-upload-title><label class="lms-btn lms-btn-ghost"><i data-lucide="upload"></i> {{ $t('lms::ui.auto_cbe55e77ff') }}<span data-media-upload-filename style="font-size:12px;color:#6b7280;margin-inline-start:6px"></span><input type="file" name="media" hidden data-media-upload-file></label><button class="lms-btn lms-btn-primary" type="submit">{{ $t('lms::ui.auto_a3990024ce') }}</button><p data-media-upload-status></p></form><div data-media-alert class="lms-hidden" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#b91c1c;font-size:12.5px;margin:0 0 10px;padding:10px 12px"></div><div data-media-preview class="lms-hidden" style="align-items:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;display:flex;gap:12px;margin:0 0 10px;padding:10px"><img data-media-preview-img src="" alt="{{ $t('lms::ui.auto_a3619be17d') }}" style="border:1px solid #e2e8f0;border-radius:8px;max-height:120px;max-width:200px;object-fit:contain"><span style="color:#64748b;font-size:12px">{{ $t('lms::ui.auto_32c763a016') }}</span></div><div class="lms-media-picker-body" data-media-items><p>{{ $t('lms::ui.auto_0b7a62e592') }}</p></div></div></div>
    </div>

    <script>
    (() => {
        const root = document.querySelector('[data-lms-builder]');
        if (!root) return;
        const courseForm = root.querySelector('[data-course-form]');
        const isEditing = @json($isEditing);
        const initialCourseId = @json($course?->id);
        const storeCourseUrl = @json($courseStoreUrl);
        const curriculumUrlTemplate = @json($curriculumUrlTemplate);
        const lessonStoreUrl = @json($lessonStoreUrl);
        const lessonUpdateTemplate = @json($lessonUpdateUrlTemplate);
        const lessonDeleteTemplate = @json($lessonDeleteUrlTemplate);
        const mediaEndpoint = @json($mediaIndexUrl);
        const mediaStoreEndpoint = @json($mediaStoreUrl);
        const mediaDeleteEndpoint = @json($mediaDeleteUrl);
        const courseImageEndpoint = @json(route('lms.front.course-image.upload'));
        const mediaFileTemplate = @json($mediaFileTemplate);
        const mediaItemUrl = (item) => {
            const direct = item?.media_url || item?.url || item?.image_url || item?.src || '';
            if (direct) { return direct; }
            const rawPath = item?.path || item?.file_path || item?.relative_path || item?.file || '';
            return rawPath ? '/lms/media-file/' + String(rawPath).replace(/^\/+/, '') : '';
        };
        const proxiedMediaUrl = (value) => {
            const url = String(value || '');
            if (url === '') { return ''; }
            if (url.startsWith('/storage/')) { return mediaFileTemplate.replace('__PATH__', url.slice('/storage/'.length)); }
            return url;
        };
        const lessons = new Map((@json($lessons->all())).map((lesson) => [String(lesson.id), lesson]));
        const csrf = courseForm.querySelector('[name="_token"]').value;

        const T = {
            overviewPlaceholder: @json($t('lms::ui.builder_overview_placeholder')),
            outcomeTitlePlaceholder: @json($t('lms::ui.builder_outcome_title_placeholder')),
            outcomeTextPlaceholder: @json($t('lms::ui.builder_outcome_text_placeholder')),
            outcomeRemove: @json($t('lms::ui.builder_outcome_remove')),
            faqQuestionPlaceholder: @json($t('lms::ui.builder_faq_question_placeholder')),
            faqAnswerPlaceholder: @json($t('lms::ui.builder_faq_answer_placeholder')),
            faqRemove: @json($t('lms::ui.builder_faq_remove')),
            categoryAdded: @json($t('lms::ui.builder_category_added')),
            categoryHint: @json($t('lms::ui.builder_category_hint')),
            completeFields: @json($t('lms::ui.builder_complete_fields')),
            loadingMedia: @json($t('lms::ui.builder_loading_media')),
            mediaUnavailable: @json($t('lms::ui.builder_media_unavailable')),
            mediaLoadFailed: @json($t('lms::ui.builder_media_load_failed')),
            mediaFile: @json($t('lms::ui.builder_media_file')),
            mediaStudio: @json($t('lms::ui.builder_media_studio')),
            mediaStudioVideo: @json($t('lms::ui.builder_media_studio_video')),
            mediaEmpty: @json($t('lms::ui.builder_media_empty')),
            mediaDescCourse: @json($t('lms::ui.builder_media_desc_course')),
            mediaDescPromo: @json($t('lms::ui.builder_media_desc_promo')),
            mediaDescVideo: @json($t('lms::ui.builder_media_desc_video')),
            mediaDescEditor: @json($t('lms::ui.builder_media_desc_editor')),
            mediaDescEditorFile: @json($t('lms::ui.builder_media_desc_editor_file')),
            mediaDescAttachment: @json($t('lms::ui.builder_media_desc_attachment')),
            deleteImage: @json($t('lms::ui.builder_delete_image')),
            removeMedia: @json($t('lms::ui.builder_remove_media')),
            mediaDeleteConfirm: @json($t('lms::ui.builder_media_delete_confirm')),
            mediaDeleteFailed: @json($t('lms::ui.builder_media_delete_failed')),
            mediaDeleted: @json($t('lms::ui.builder_media_deleted')),
            pdf: @json($t('lms::ui.builder_pdf')),
            selectedCourseImage: @json($t('lms::ui.builder_selected_course_image')),
            selectedPrefix: @json($t('lms::ui.builder_selected_prefix')),
            selectedFilePrefix: @json($t('lms::ui.builder_selected_file_prefix')),
            pressUpload: @json($t('lms::ui.builder_press_upload')),
            noFileSelected: @json($t('lms::ui.builder_no_file_selected')),
            chooseFileFirst: @json($t('lms::ui.builder_choose_file_first')),
            chooseFileFirstShort: @json($t('lms::ui.builder_choose_file_first_short')),
            uploadingMedia: @json($t('lms::ui.builder_uploading_media')),
            imageUploaded: @json($t('lms::ui.builder_image_uploaded')),
            uploadedSelectedPrefix: @json($t('lms::ui.builder_uploaded_selected_prefix')),
            atTop: @json($t('lms::ui.builder_at_top')),
            noMediaReturned: @json($t('lms::ui.builder_no_media_returned')),
            enterTitle: @json($t('lms::ui.builder_enter_title')),
            draftFailed: @json($t('lms::ui.builder_draft_failed')),
            curriculumFailed: @json($t('lms::ui.builder_curriculum_failed')),
            curriculumSaved: @json($t('lms::ui.builder_curriculum_saved')),
            curriculumSaving: @json($t('lms::ui.builder_curriculum_saving')),
            curriculumSaveFailed: @json($t('lms::ui.builder_curriculum_save_failed')),
            dragUnavailable: @json($t('lms::ui.builder_drag_unavailable')),
            dragUnit: @json($t('lms::ui.builder_drag_unit')),
            unitTitle: @json($t('lms::ui.builder_unit_title')),
            deleteUnit: @json($t('lms::ui.builder_delete_unit')),
            newUnit: @json($t('lms::ui.builder_new_unit')),
            untitledUnit: @json($t('lms::ui.builder_untitled_unit')),
            deleteUnitLessonsFirst: @json($t('lms::ui.builder_delete_unit_lessons_first')),
            selectPrerequisite: @json($t('lms::ui.builder_select_prerequisite')),
            courseLessons: @json($t('lms::ui.builder_course_lessons')),
            editLesson: @json($t('lms::ui.builder_edit_lesson')),
            addNewLesson: @json($t('lms::ui.builder_add_new_lesson')),
            saveLesson: @json($t('lms::ui.builder_save_lesson')),
            addLesson: @json($t('lms::ui.builder_add_lesson')),
            noFiles: @json($t('lms::ui.builder_no_files')),
            lessonSaveFailed: @json($t('lms::ui.builder_lesson_save_failed')),
            deleteLessonConfirm: @json($t('lms::ui.builder_delete_lesson_confirm')),
        };
        let courseId = initialCourseId;
        let slugTouched = Boolean(root.querySelector('[data-course-slug]').value);
        let mediaTarget = 'course';

        const iconRefresh = () => window.lucide?.createIcons({ attrs: { 'stroke-width': 1.8 } });
        iconRefresh();

        const slugify = (value) => {
            const arabic = {'ا':'a','أ':'a','إ':'i','آ':'a','ب':'b','ت':'t','ث':'th','ج':'j','ح':'h','خ':'kh','د':'d','ذ':'th','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','و':'w','ي':'y','ى':'a','ة':'h'};
            return [...String(value)].map((char) => arabic[char] ?? char).join('').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 220);
        };
        const courseTitle = root.querySelector('[data-course-title]');
        const courseSlug = root.querySelector('[data-course-slug]');
        courseSlug.addEventListener('input', () => slugTouched = courseSlug.value.trim() !== '');
        courseTitle.addEventListener('input', () => { if (!slugTouched) courseSlug.value = slugify(courseTitle.value); });

        const shortCopy = root.querySelector('#short_description');
        const shortCount = root.querySelector('[data-short-count]');
        const updateShortCount = () => shortCount.textContent = String(shortCopy.value.length);
        shortCopy.addEventListener('input', updateShortCount); updateShortCount();

        const overviewCopy = root.querySelector('#overview');
        const overviewCount = root.querySelector('[data-overview-count]');
        const updateOverviewCount = () => { if (overviewCopy && overviewCount) { overviewCount.textContent = String(overviewCopy.value.length); } };
        overviewCopy?.addEventListener('input', updateOverviewCount); updateOverviewCount();

        const outcomeList = root.querySelector('[data-outcomes-list]');
        const faqList = root.querySelector('[data-faq-list]');
        let faqIndex = faqList.querySelectorAll('[data-faq-row]').length;
        let outcomeIndex = outcomeList.querySelectorAll('[data-outcome-row]').length;
        const bindRemoveRow = (row) => row.querySelector('[data-remove-row]')?.addEventListener('click', () => row.remove());
        root.querySelectorAll('[data-outcome-row], [data-faq-row]').forEach(bindRemoveRow);
        root.querySelector('[data-add-outcome]').addEventListener('click', () => {
            const index = outcomeIndex++;
            const row = document.createElement('div');
            row.className = 'lms-repeatable-row lms-learn-editor-row';
            row.dataset.outcomeRow = '';
            row.innerHTML = '<div><input class="lms-input" name="outcomes[' + index + '][title]" maxlength="255" placeholder="' + T.outcomeTitlePlaceholder + '"><textarea class="lms-textarea" name="outcomes[' + index + '][text]" maxlength="1000" placeholder="' + T.outcomeTextPlaceholder + '"></textarea></div><button class="lms-icon-button" type="button" data-remove-row aria-label="' + T.outcomeRemove + '"><i data-lucide="trash-2"></i></button>';
            outcomeList.appendChild(row);
            bindRemoveRow(row);
            iconRefresh();
            row.querySelector('input').focus();
        });
        root.querySelector('[data-add-faq]').addEventListener('click', () => {
            const index = faqIndex++;
            const row = document.createElement('div');
            row.className = 'lms-repeatable-row lms-faq-editor-row';
            row.dataset.faqRow = '';
            row.innerHTML = `<div><input class="lms-input" name="faqs[${index}][question]" maxlength="255" placeholder="${T.faqQuestionPlaceholder}"><textarea class="lms-textarea" name="faqs[${index}][answer]" maxlength="1000" placeholder="${T.faqAnswerPlaceholder}"></textarea></div><button class="lms-icon-button" type="button" data-remove-row aria-label="${T.faqRemove}"><i data-lucide="trash-2"></i></button>`;
            faqList.appendChild(row);
            bindRemoveRow(row);
            iconRefresh();
            row.querySelector('input').focus();
        });

        const insertPastedText = (editor, text) => {
            if (! text) { return; }
            const html = String(text).split(/\r?\n/).map(function (line) { return line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }).join('<br>');
            editor.insertContent(html);
        };
        const askForPastedText = (editor) => {
            const fallback = () => {
                if (! window.tinymce?.activeEditor) { return; }
                const manual = window.prompt(@json(__('lms::ui.editor_paste_prompt')));
                insertPastedText(editor, manual || '');
            };
            if (navigator.clipboard && navigator.clipboard.readText) {
                navigator.clipboard.readText().then(function (text) { if (text && String(text).trim() !== '') { insertPastedText(editor, text); } else { fallback(); } }).catch(fallback);
            } else {
                fallback();
            }
        };
        const editorLang = @json(app()->getLocale() === 'ar' ? 'ar' : 'en');
        const editorLangUrl = @json(app()->getLocale() === 'ar' ? asset('platform/vendor/tinymce/langs/ar.js') : null);
        const editorFont = editorLang === 'ar' ? 'Cairo,Inter,Arial,sans-serif' : 'Inter,Arial,sans-serif';
        const initEditor = (selector, height) => window.tinymce?.init({
            selector, height, menubar: false, branding: false, statusbar: true, resize: true,
            min_height: 220, max_height: 1200,
            language: editorLang,
            language_url: editorLangUrl || undefined,
            directionality: editorLang === 'ar' ? 'rtl' : 'ltr',
            plugins: 'link image table code codesample fullscreen wordcount media lists',
            toolbar: 'lms_paste | undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media table | blockquote code fullscreen',
            paste_data_images: true,
            smart_paste: true,
            content_style: 'body{font-family:' + editorFont + ';font-size:14px;line-height:1.6;padding:12px;user-select:text}',
            content_css: 'default',
            entity_encoding: 'raw',
            setup: (editor) => {
                editor.ui.registry.addButton('lms_paste', {
                    text: @json(__('lms::ui.editor_paste_text')),
                    icon: 'clipboard',
                    tooltip: @json(__('lms::ui.editor_paste_tooltip')),
                    onAction: () => askForPastedText(editor),
                });
            },
        });
        initEditor('[data-course-editor]', 320);
        initEditor('[data-lesson-editor]', 340);

        function syncChecklist() {
            const fieldValue = (selector) => (root.querySelector(selector)?.value || '').trim();
            const descriptionField = root.querySelector('#description');
            const descriptionEditor = descriptionField ? window.tinymce?.get(descriptionField.id) : null;
            const descriptionText = descriptionEditor ? descriptionEditor.getContent({ format: 'text' }).trim() : fieldValue('#description');
            const imageSelected = Boolean((root.querySelector('[data-course-media-url]')?.value || '').trim()) || Boolean(root.querySelector('[data-course-image-preview]'));
            const state = {
                title: fieldValue('#title') !== '',
                short_description: fieldValue('#short_description') !== '',
                overview: fieldValue('#overview') !== '',
                description: descriptionText !== '',
                category: fieldValue('#category') !== '',
                instructor: fieldValue('#instructor_id') !== '',
                image: imageSelected,
                curriculum: root.querySelectorAll('[data-unit]').length > 0,
            };
            let done = 0;
            Object.keys(state).forEach((key) => {
                const item = root.querySelector('[data-ready-item="' + key + '"]');
                if (!item) { return; }
                item.classList.toggle('is-done', Boolean(state[key]));
                if (state[key]) { done++; }
            });
            const counter = root.querySelector('[data-ready-count]');
            if (counter) { counter.textContent = String(done); }
        }
        const statusSelect = root.querySelector('[data-course-status]');
        const updateStatus = () => { const value = statusSelect.value; root.querySelector('[data-status-label]').textContent = (@json($statusLabels)[value] || (value.charAt(0).toUpperCase() + value.slice(1))); root.querySelector('[data-status-copy]').textContent = value === 'published' ? @json($t('lms::ui.admin_course_visible')) : @json($t('lms::ui.admin_course_not_visible')); root.querySelector('.lms-status-panel').classList.toggle('published', value === 'published'); };
        statusSelect.addEventListener('change', updateStatus); updateStatus();
        root.querySelector('[data-course-instructor]').addEventListener('change', (event) => root.querySelector('[data-instructor-summary]').textContent = event.target.value || @json($t('lms::ui.admin_course_unassigned')));
        const categoryField = root.querySelector('[data-course-category]');
        const syncCategorySummary = (event) => { root.querySelector('[data-category-summary]').textContent = (event.target.value || '').trim() || @json($t('lms::ui.admin_course_unassigned')); };
        categoryField?.addEventListener('change', syncCategorySummary);
        categoryField?.addEventListener('input', syncCategorySummary);
        const categoryNewField = root.querySelector('[data-course-category-new]');
        const categoryAddAction = root.querySelector('[data-course-category-add]');
        const categoryHint = root.querySelector('[data-category-hint]');
        const addCategoryToList = () => {
            const typed = (categoryNewField?.value || '').trim();
            if (typed === '') { categoryNewField?.focus(); return; }
            const select = root.querySelector('[data-course-category]');
            let option = Array.from(select.options).find((item) => item.value.toLowerCase() === typed.toLowerCase());
            if (! option) { option = new Option(typed, typed); select.add(option); }
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            categoryNewField.value = '';
            root.querySelector('[data-category-summary]').textContent = typed;
            if (categoryHint) {
                categoryHint.textContent = T.categoryAdded.replace(':category', typed);
                categoryHint.style.color = '#15803d';
                setTimeout(() => { categoryHint.textContent = T.categoryHint; categoryHint.style.color = ''; }, 4000);
            }
        };
        categoryAddAction?.addEventListener('click', addCategoryToList);
        categoryNewField?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); addCategoryToList(); } });
        categoryNewField?.addEventListener('blur', () => { if ((categoryNewField.value || '').trim() !== '') { addCategoryToList(); } });
        courseForm?.addEventListener('submit', () => { if ((categoryNewField?.value || '').trim() !== '') { addCategoryToList(); } });
        const currentCategoryValue = (root.querySelector('[data-course-category]')?.value || '').trim();
        if (currentCategoryValue !== '') {
            const categorySelect = root.querySelector('[data-course-category]');
            if (! Array.from(categorySelect.options).some((item) => item.value === currentCategoryValue)) {
                categorySelect.add(new Option(currentCategoryValue, currentCategoryValue));
                categorySelect.value = currentCategoryValue;
            }
        }
        categoryNewField?.addEventListener('input', () => {
            const typed = categoryNewField.value.trim();
            if (typed !== '') { root.querySelector('[data-category-summary]').textContent = typed; }
        });
        const programTypeField = root.querySelector('#program_type');
        const programTitleLabel = root.querySelector('[data-program-title-label]');
        const syncProgramTitle = () => { if (programTitleLabel && programTypeField) { programTitleLabel.textContent = programTypeField.value === 'diploma' ? @json($t('lms::ui.admin_course_diploma_title')) : @json($t('lms::ui.admin_course_title')); } };
        programTypeField?.addEventListener('change', syncProgramTitle);
        syncProgramTitle();
        const seatsUnlimitedBox = root.querySelector('[data-seats-unlimited]');
        const seatsInputField = root.querySelector('[data-seats-input]');
        const seatsTypeField = root.querySelector('[data-seats-limit-type]');
        const syncSeatsField = () => {
            const unlimited = !!(seatsUnlimitedBox && seatsUnlimitedBox.checked);
            if (seatsTypeField) { seatsTypeField.value = unlimited ? 'unlimited' : 'limited'; }
            if (seatsInputField) {
                seatsInputField.disabled = unlimited;
                if (unlimited) { seatsInputField.value = ''; }
                else if (! seatsInputField.value) { seatsInputField.value = '30'; }
            }
        };
        seatsUnlimitedBox?.addEventListener('change', syncSeatsField);
        syncSeatsField();
        root.addEventListener('input', syncChecklist);
        root.addEventListener('change', syncChecklist);
        const unitListObserver = root.querySelector('[data-unit-list]');
        if (unitListObserver && window.MutationObserver) { new MutationObserver(syncChecklist).observe(unitListObserver, { childList: true, subtree: true }); }
        syncChecklist();

        const stepsNav = root.querySelector('[data-steps-nav]');
        const stepTabs = Array.from(root.querySelectorAll('[data-step-tab]'));
        const stepLabel = root.querySelector('[data-step-label]');
        const stepMessage = root.querySelector('[data-step-message]');
        const stepBackButton = root.querySelector('[data-step-back]');
        const stepNextButton = root.querySelector('[data-step-next]');
        const totalSteps = 3;
        let activeStep = 1;

        const informationCard = root.querySelector('.lms-builder-main > .lms-builder-card');
        if (informationCard) {
            Array.from(informationCard.children).forEach((child) => {
                if (child.tagName === 'H2' || child.classList.contains('lms-card-heading-row')) { return; }
                if (child.matches('section.lms-course-about-editor, details')) { child.dataset.stepGroup = '2'; return; }
                child.dataset.stepGroup = '1';
            });
        }
        const curriculumCard = root.querySelector('[data-curriculum]');
        if (curriculumCard) { curriculumCard.dataset.stepGroup = '4'; }

        const fillReview = () => {
            const value = (selector) => (root.querySelector(selector)?.value || '').trim();
            const setText = (selector, text) => { const el = root.querySelector(selector); if (el) { el.textContent = text || '—'; } };
            const programSelect = root.querySelector('#program_type');
            const trainingSelect = root.querySelector('#training_mode');
            const currency = value('[name="currency"]') || 'JOD';
            setText('[data-review-title]', value('#title') || 'Untitled');
            setText('[data-review-program]', programSelect ? programSelect.options[programSelect.selectedIndex]?.textContent : '');
            setText('[data-review-category]', (root.querySelector('[data-category-summary]')?.textContent || '').trim());
            setText('[data-review-instructor]', (root.querySelector('[data-instructor-summary]')?.textContent || '').trim());
            setText('[data-review-training]', trainingSelect ? trainingSelect.options[trainingSelect.selectedIndex]?.textContent : '');
            const onlinePrice = value('[data-online-price]');
            const inPersonPrice = value('[data-in-person-price]');
            setText('[data-review-online]', onlinePrice !== '' ? onlinePrice + ' ' + currency : '—');
            setText('[data-review-inperson]', inPersonPrice !== '' ? inPersonPrice + ' ' + currency : '—');
            const unitCount = root.querySelectorAll('[data-unit-list] [data-unit]').length;
            const lessonCount = root.querySelectorAll('[data-unit-list] [data-lesson-id]').length;
            setText('[data-review-units]', unitCount + ' / ' + lessonCount);
        };

        const applyStep = (step) => {
            activeStep = Math.max(1, Math.min(totalSteps, step));
            stepTabs.forEach((tab) => tab.classList.toggle('is-active', Number(tab.dataset.stepTab) === activeStep));
            const groupStepMap = { '1': 1, '2': 2, '4': 3, '5': 3 };
            root.querySelectorAll('[data-step-group]').forEach((group) => {
                const mappedStep = groupStepMap[String(group.dataset.stepGroup)] || 3;
                group.classList.toggle('lms-step-hidden', mappedStep !== activeStep);
            });
            if (stepLabel) { stepLabel.textContent = @json($t('lms::ui.admin_step_label')).replace(':current', String(activeStep)).replace(':total', String(totalSteps)); }
            if (stepBackButton) { stepBackButton.disabled = activeStep === 1; }
            if (stepNextButton) { stepNextButton.disabled = activeStep === totalSteps; }
            if (stepMessage) { stepMessage.textContent = ''; }
            try { localStorage.setItem('lms-course-step', String(activeStep)); } catch (error) {}
            if (activeStep === 3) { fillReview(); }
            if (activeStep === 1 && window.tinymce?.editors) {
                window.tinymce.editors.forEach(function (editor) { try { editor.dispatch?.('ResizeEditor'); } catch (error) {} });
            }
        };

        stepTabs.forEach((tab) => tab.addEventListener('click', () => applyStep(Number(tab.dataset.stepTab))));
        root.querySelectorAll('[data-step-goto]').forEach((button) => button.addEventListener('click', () => applyStep(Number(button.dataset.stepGoto))));
        stepBackButton?.addEventListener('click', () => applyStep(activeStep - 1));
        stepNextButton?.addEventListener('click', () => applyStep(activeStep + 1));
        root.querySelector('[data-review-publish]')?.addEventListener('click', () => root.querySelector('button[name="action"][value="publish"]')?.click());
        root.querySelector('[data-review-save]')?.addEventListener('click', () => root.querySelector('button[name="action"][value="save"]')?.click());

        const hasFormErrors = stepsNav?.dataset.hasErrors === '1';
        let storedStep = 1;
        if (! hasFormErrors) { try { storedStep = Number(localStorage.getItem('lms-course-step') || '1'); } catch (error) { storedStep = 1; } }
        applyStep(storedStep || 1);

        const formErrors = @json($errors->getMessages());
        const errorFieldName = (key) => {
            const parts = String(key).split('.');

            if (parts.length === 1) { return key; }

            return parts[0] + parts.slice(1).map((part) => '[' + part + ']').join('');
        };
        const findErrorInput = (key) => {
            const names = [errorFieldName(key), key];

            for (let index = 0; index < names.length; index += 1) {
                try {
                    const found = root.querySelector('[name="' + names[index] + '"]');

                    if (found) { return found; }
                } catch (error) {}
            }

            if (/^[A-Za-z_][A-Za-z0-9_-]*$/.test(key)) {
                try { return root.querySelector('#' + key); } catch (error) {}
            }

            return null;
        };
        const applyFieldErrors = () => {
            const keys = Object.keys(formErrors || {});
            if (keys.length === 0) { return; }
            let firstField = null;

            keys.forEach((key) => {
                const input = findErrorInput(key);

                if (! input) { return; }

                const wrapper = input.closest('.lms-field') || input.closest('.lms-repeatable-row') || input;
                wrapper.classList.add('lms-has-error');

                const message = formErrors[key] && formErrors[key][0];

                if (message) {
                    const note = document.createElement('small');
                    note.className = 'lms-field-error';
                    note.textContent = message;
                    wrapper.appendChild(note);
                }

                if (! firstField) { firstField = input; }
            });

            if (! firstField) { return; }

            const stepHolder = firstField.closest('[data-step-group]');

            if (stepHolder) {
                const groupStep = { '1': 1, '2': 2, '4': 3, '5': 3 }[String(stepHolder.dataset.stepGroup)] || 1;
                applyStep(groupStep);
            }

            window.setTimeout(() => {
                firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstField.focus({ preventScroll: true });
            }, 150);
        };

        applyFieldErrors();

        const focusInvalidField = (field) => {
            const holder = field.closest('[data-step-group]');

            if (holder) {
                const groupStep = { '1': 1, '2': 2, '4': 3, '5': 3 }[String(holder.dataset.stepGroup)] || 1;
                applyStep(groupStep);
            }

            const wrapper = field.closest('.lms-field') || field;
            wrapper.classList.add('lms-has-error');

            const labelText = (wrapper.querySelector('.lms-label')?.textContent || field.name).replace('*', '').trim();

            if (stepMessage) {
                stepMessage.style.color = '#b91c1c';
                stepMessage.textContent = T.completeFields + ' ' + labelText;
            }

            window.setTimeout(() => {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus({ preventScroll: true });
            }, 120);
        };

        courseForm?.addEventListener('click', (event) => {
            const publishButton = event.target.closest('button[name="action"][value="publish"]');

            if (! publishButton) { return; }

            const invalid = Array.from(courseForm.elements).filter((element) => element.willValidate && ! element.checkValidity());

            if (invalid.length === 0) { return; }

            event.preventDefault();
            event.stopPropagation();
            focusInvalidField(invalid[0]);
        }, true);
        const trainingModeField = root.querySelector('[data-training-mode]');
        const onlinePriceField = root.querySelector('[data-online-price-field]');
        const inPersonPriceField = root.querySelector('[data-in-person-price-field]');
        const syncTrainingMode = () => {
            const mode = trainingModeField ? trainingModeField.value : 'online';
            const showOnline = mode === 'both';
            const showInPerson = mode === 'in_person' || mode === 'both';
            if (onlinePriceField) { onlinePriceField.classList.toggle('lms-hidden', ! showOnline); }
            if (inPersonPriceField) { inPersonPriceField.classList.toggle('lms-hidden', ! showInPerson); }
        };
        trainingModeField?.addEventListener('change', syncTrainingMode);
        syncTrainingMode();
        root.querySelector('[data-access-duration]').addEventListener('change', (event) => courseForm.querySelector('[name="access_duration"]').value = event.target.value);
        [['[data-focus-status]','#status'],['[data-focus-publish]','#publish_date'],['[data-focus-instructor]','#instructor_id'],['[data-focus-category]','#category']].forEach(([button, field]) => root.querySelector(button)?.addEventListener('click', () => root.querySelector(field)?.focus()));

        const mediaModal = root.querySelector('[data-media-modal]');
        const mediaItems = root.querySelector('[data-media-items]');
        const mediaAlert = root.querySelector('[data-media-alert]');
        const mediaPreview = root.querySelector('[data-media-preview]');
        const mediaPreviewImg = root.querySelector('[data-media-preview-img]');
        const showMediaPreview = (source) => {
            if (! mediaPreview || ! mediaPreviewImg) { return; }
            if (! source) { mediaPreview.classList.add('lms-hidden'); mediaPreviewImg.removeAttribute('src'); return; }
            mediaPreviewImg.src = source;
            mediaPreview.classList.remove('lms-hidden');
        };
        const setMediaAlert = (text, tone = 'error') => {
            if (! mediaAlert) { return; }
            mediaAlert.textContent = text || '';
            mediaAlert.classList.toggle('lms-hidden', ! text);
            mediaAlert.style.background = tone === 'success' ? '#ecfdf5' : (tone === 'info' ? '#eff6ff' : '#fef2f2');
            mediaAlert.style.borderColor = tone === 'success' ? '#6ee7b7' : (tone === 'info' ? '#93c5fd' : '#fca5a5');
            mediaAlert.style.color = tone === 'success' ? '#047857' : (tone === 'info' ? '#1d4ed8' : '#b91c1c');
        };
        let mediaCache = [];
        const mediaDescription = root.querySelector('[data-media-description]');
        const mediaUploadForm = root.querySelector('[data-media-upload-form]');
        const mediaUploadFile = root.querySelector('[data-media-upload-file]');
        mediaUploadFile?.addEventListener('change', () => { const nameLabel = root.querySelector('[data-media-upload-filename]'); if (nameLabel) nameLabel.textContent = mediaUploadFile.files?.[0]?.name || ''; });
        const mediaUploadTitle = root.querySelector('[data-media-upload-title]');
        const mediaUploadStatus = root.querySelector('[data-media-upload-status]');
        const escapeHtml = (value) => String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        const prepareModal = (modal) => {
            modal.querySelector('.lms-builder-modal-dialog')?.scrollTo({ top: 0 });
        };
        const syncModalLock = () => document.body.classList.toggle('lms-modal-open', Boolean(root.querySelector('.lms-builder-modal:not([hidden])')));
        const openMedia = async (target) => {
            mediaTarget = target; prepareModal(mediaModal); mediaModal.hidden = false; mediaModal.setAttribute('aria-hidden', 'false'); syncModalLock(); mediaItems.innerHTML = '<p>' + T.loadingMedia + '</p>'; mediaUploadForm.reset(); mediaUploadStatus.textContent = '';
            mediaDescription.textContent = target === 'course' ? T.mediaDescCourse : target === 'promo' ? T.mediaDescPromo : target === 'video' ? T.mediaDescVideo : target === 'editor' ? T.mediaDescEditor : target === 'editor-file' ? T.mediaDescEditorFile : T.mediaDescAttachment;
            mediaUploadFile.accept = target === 'course' || target === 'editor' ? 'image/*' : target === 'promo' || target === 'video' ? 'video/*' : 'image/*,video/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv';
            if (!mediaEndpoint) { mediaItems.innerHTML = '<p>' + T.mediaUnavailable + '</p>'; return; }
            const type = target === 'course' || target === 'editor' ? 'image' : target === 'promo' || target === 'video' ? 'video' : 'file';
            try { const endpoint = `${mediaEndpoint}${mediaEndpoint.includes('?') ? '&' : '?'}type=${type}`; const response = await fetch(endpoint, { headers: {Accept:'application/json'} }); const payload = await response.json(); if (!response.ok) throw new Error(); mediaCache = Array.isArray(payload) ? payload : (payload.items || payload.data || payload.files || payload.media || []); renderMedia(mediaCache); }
            catch { mediaItems.innerHTML = '<p>' + T.mediaLoadFailed + '</p>'; }
        };
        const closeMedia = () => { mediaModal.hidden = true; mediaModal.setAttribute('aria-hidden','true'); syncModalLock(); };
        const removeCourseImageButton = root.querySelector('[data-remove-course-image]');
        const builderHeader = root.querySelector('.lms-builder-header');
        const portalNav = document.querySelector('.ainpa-portal-nav');
        const syncStickyOffset = () => {
            const sticky = portalNav && getComputedStyle(portalNav).position === 'sticky';
            const offset = sticky ? portalNav.getBoundingClientRect().height : 0;
            document.documentElement.style.setProperty('--lms-sticky-top', Math.round(offset) + 'px');
        };
        const syncStuckState = () => {
            if (! builderHeader) { return; }
            const offset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--lms-sticky-top')) || 0;
            builderHeader.classList.toggle('is-stuck', builderHeader.getBoundingClientRect().top <= offset + 1);
        };
        syncStickyOffset();
        syncStuckState();
        window.addEventListener('resize', () => { syncStickyOffset(); syncStuckState(); });
        window.addEventListener('scroll', syncStuckState, { passive: true });
        removeCourseImageButton?.addEventListener('click', () => {
            root.querySelector('[data-course-media-url]').value = '';
            root.querySelector('[data-course-media-name]').value = '';
            root.querySelector('[data-course-image-preview]')?.remove();
            removeCourseImageButton.classList.add('lms-hidden');
        });
        const fileIcon = (item) => { const mime=String(item.mime_type||item.mime||item.type||'').toLowerCase(); const ext=String(item.extension||(item.name||item.url||'').split('.').pop()||'').toLowerCase(); if(mime.startsWith('video/')||['mp4','webm','mov','m4v','avi','mkv'].includes(ext))return 'video'; if(mime.includes('pdf')||ext==='pdf')return 'file-text'; if(mime.startsWith('image/')||['png','jpg','jpeg','webp','gif','svg','ico'].includes(ext))return 'image'; return 'file'; };
        const renderMedia = (items) => { mediaItems.innerHTML = items.length ? `<div class="lms-media-picker-grid">${items.map((item,index) => { const name=escapeHtml(item.title||item.name||T.mediaFile); const icon=fileIcon(item); const preview=icon==='image'?proxiedMediaUrl(item.thumbnail_url||item.image_url||item.media_url||mediaItemUrl(item)):''; return `<div class="lms-media-item"><button type="button" data-media-index="${index}">${preview ? `<img src="${escapeHtml(preview)}" alt="">` : `<i data-lucide="${icon}"></i>`}<span>${name}</span></button><button type="button" class="lms-media-delete" data-media-delete="${index}" title="${T.deleteImage}" aria-label="${T.deleteImage} ${name}">&times;</button></div>`; }).join('')}</div>` : '<p data-media-empty>'+T.mediaEmpty+'</p>'; mediaItems.querySelectorAll('[data-media-index]').forEach((button) => button.addEventListener('click', () => selectMedia(items[Number(button.dataset.mediaIndex)]))); mediaItems.querySelectorAll('[data-media-delete]').forEach((button) => button.addEventListener('click', async (event) => { event.preventDefault(); event.stopPropagation(); const item = items[Number(button.dataset.mediaDelete)]; const mediaUrl = item?.media_url || item?.url || item?.image_url || ''; if (! mediaUrl || ! mediaDeleteEndpoint) { return; } if (! window.confirm(T.mediaDeleteConfirm)) { return; } const data = new FormData(); data.append('_method', 'DELETE'); data.append('media_urls[]', mediaUrl); try { const response = await fetch(mediaDeleteEndpoint, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }); if (! response.ok) { throw new Error(T.mediaDeleteFailed); } mediaUploadStatus.textContent = T.mediaDeleted; await openMedia(mediaTarget); } catch (error) { mediaUploadStatus.textContent = error.message || T.mediaDeleteFailed; } })); iconRefresh(); };
        const appendSelectedMediaRow = (list, item, detail, onRemove) => { if(!list)return; list.hidden=false; const row=document.createElement('span'); row.className='lms-media-selected-chip'; const label=escapeHtml(item.title||item.name||T.mediaFile); const labelHtml=item.url?`<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline"><strong>${label}</strong></a>`:`<strong>${label}</strong>`; row.innerHTML=`<i data-lucide="${fileIcon(item)}"></i>${labelHtml}<small>${escapeHtml(detail||item.size||T.mediaStudio)}</small><button class="lms-icon-button" type="button" aria-label="${T.removeMedia}"><i data-lucide="x"></i></button>`; row.querySelector('button').addEventListener('click',()=>{onRemove?.();row.remove();if(!list.children.length)list.hidden=true;}); list.appendChild(row); iconRefresh(); };
        const addMediaAttachment = (item) => { const url=item.media_url||item.url||''; if(!url)return; const inputs=root.querySelector('[data-media-attachment-inputs]'); const list=root.querySelector('[data-selected-files]'); const index=`${Date.now()}-${inputs.children.length}`; const group=document.createElement('div'); group.hidden=true; [['url',url],['name',item.title||item.name||T.mediaFile],['size',item.size||''],['mime',item.mime_type||item.mime||item.type||'']].forEach(([key,value])=>{const input=document.createElement('input');input.type='hidden';input.name=`media_attachments[${index}][${key}]`;input.value=value;group.appendChild(input);}); if(fileIcon(item)==='file-text'){lessonForm.querySelector('[data-modal-pdf-media-url]').value=url;lessonForm.querySelector('[data-modal-pdf-media-name]').value=item.title||item.name||T.pdf;} inputs.appendChild(group); list.querySelector('span')?.remove(); appendSelectedMediaRow(list,item,item.size||T.mediaStudio,()=>{group.remove();if(lessonForm.querySelector('[data-modal-pdf-media-url]').value===url){lessonForm.querySelector('[data-modal-pdf-media-url]').value='';lessonForm.querySelector('[data-modal-pdf-media-name]').value='';}}); };
        const selectMedia = (item, keepOpen = false) => { const url=proxiedMediaUrl(mediaItemUrl(item)); if(!url){ if(mediaUploadStatus){ mediaUploadStatus.textContent='The file was received but no media URL was returned. Please report this message.'; } return; } if(mediaTarget==='course'){ root.querySelector('[data-course-media-url]').value=url; root.querySelector('[data-course-media-name]').value=item.name||''; let preview=root.querySelector('[data-course-image-preview]'); if(!preview){ preview=document.createElement('img'); preview.setAttribute('data-course-image-preview',''); preview.alt=T.selectedCourseImage; root.querySelector('[data-open-media="course"]').prepend(preview); } preview.src=url; preview.hidden=false; root.querySelector('[data-remove-course-image]')?.classList.remove('lms-hidden'); } else if(mediaTarget==='promo'){ root.querySelector('[data-promo-video-url]').value=url; root.querySelector('[data-promo-video-name]').textContent=`${T.selectedPrefix} ${item.title||item.name||T.mediaStudioVideo}`; } else if(mediaTarget==='video'){ const field=lessonForm.elements.namedItem('video_url'); field.value=url; const list=lessonForm.querySelector('[data-selected-video-media]'); list.innerHTML=''; appendSelectedMediaRow(list,item,item.size||T.mediaStudioVideo,()=>{field.value='';}); } else if(mediaTarget==='attachment'){ addMediaAttachment(item); } else { const embedId=`lms-media-${Date.now()}-${Math.random().toString(16).slice(2)}`; const editor=window.tinymce?.get(root.querySelector('[data-lesson-editor]').id||'')||window.tinymce?.activeEditor; const icon=fileIcon(item); const safeUrl=escapeHtml(url); const label=escapeHtml(item.alt_text||item.title||item.name||T.mediaFile); const html=icon==='image'?`<figure data-lms-media-embed="${embedId}"><img src="${safeUrl}" alt="${label}"></figure>`:icon==='video'?`<figure data-lms-media-embed="${embedId}"><video src="${safeUrl}" controls preload="metadata"></video><figcaption>${label}</figcaption></figure>`:`<p data-lms-media-embed="${embedId}"><a href="${safeUrl}" target="_blank" rel="noopener">${label}</a></p>`; editor?.insertContent(html); appendSelectedMediaRow(lessonForm.querySelector('[data-editor-media-list]'),item,'Inserted in lesson content',()=>{editor?.getBody()?.querySelector(`[data-lms-media-embed="${embedId}"]`)?.remove();editor?.save();}); } if (! keepOpen) { closeMedia(); } };
        root.querySelector('[data-open-media="course"]').addEventListener('click', () => openMedia('course'));
        root.querySelector('[data-insert-editor-image]').addEventListener('click', () => openMedia('editor'));
        root.querySelectorAll('[data-open-media="promo"], [data-open-media="video"], [data-open-media="editor-file"], [data-open-media="attachment"]').forEach((button) => button.addEventListener('click', () => openMedia(button.dataset.openMedia)));
        root.querySelectorAll('[data-close-media]').forEach((button) => button.addEventListener('click', closeMedia));
        mediaModal.addEventListener('click', (event) => { if (event.target === mediaModal) closeMedia(); });
        mediaUploadFile?.addEventListener('change', () => { const chosen = mediaUploadFile.files?.[0]; if (chosen) { setMediaAlert(T.selectedFilePrefix + ' ' + chosen.name + ' (' + Math.max(1, Math.round(chosen.size / 1024)) + ' KB) — ' + T.pressUpload, 'info'); if (String(chosen.type || '').startsWith('image/')) { showMediaPreview(URL.createObjectURL(chosen)); } else { showMediaPreview(''); } } else { setMediaAlert(T.noFileSelected, 'error'); showMediaPreview(''); } mediaItems.querySelector('[data-media-empty]')?.classList.toggle('lms-hidden', Boolean(chosen)); });
        mediaUploadForm.addEventListener('submit', async (event) => { event.preventDefault(); const file=mediaUploadFile.files?.[0]; if(!file){setMediaAlert(T.chooseFileFirst,'error');mediaUploadStatus.textContent=T.chooseFileFirstShort;return;} const isImageTarget=(mediaTarget==='course'||mediaTarget==='editor'); const attempts=isImageTarget?[{url:courseImageEndpoint,field:'image'},{url:mediaStoreEndpoint,field:'media'}]:[{url:mediaStoreEndpoint,field:'media'}]; mediaUploadStatus.style.color='#6b7280'; mediaUploadStatus.textContent=T.uploadingMedia; setMediaAlert(''); let lastError=''; for(const attempt of attempts){ if(!attempt.url){continue;} try{ const data=new FormData(); data.append(attempt.field,file); if(attempt.field==='media'){data.append('title',mediaUploadTitle.value||file.name.replace(/\.[^.]+$/,''));} const response=await fetch(attempt.url,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}); const raw=await response.text(); let payload={}; try{payload=JSON.parse(raw);}catch{payload={};} let uploaded=payload.media||payload.data||payload.file||null; if(!uploaded&&payload.url){uploaded={media_url:payload.url,image_url:payload.url,name:payload.name||file.name,title:payload.name||file.name,is_image:true};} if(!uploaded){lastError='HTTP '+response.status+' — '+(Object.values(payload.errors||{}).flat()[0]||payload.message||raw.slice(0,140).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()||T.noMediaReturned); continue;} mediaCache=[uploaded,...mediaCache.filter((entry)=>mediaItemUrl(entry)!==mediaItemUrl(uploaded))]; renderMedia(mediaCache); selectMedia(uploaded,true); if(mediaUploadStatus){mediaUploadStatus.style.color='#15803d';mediaUploadStatus.textContent=T.imageUploaded;} setMediaAlert(T.uploadedSelectedPrefix+' '+(uploaded.title||uploaded.name||file.name)+T.atTop,'success'); showMediaPreview(proxiedMediaUrl(mediaItemUrl(uploaded))); mediaUploadFile.value=''; return; }catch(error){ lastError=String((error&&error.message)||error); } } if(mediaUploadStatus){mediaUploadStatus.style.color='#b91c1c';mediaUploadStatus.textContent='Upload failed — '+(lastError||'unknown error');} setMediaAlert('Upload failed — '+(lastError||'unknown error')); });
        const ensureCourse = async () => {
            if (courseId) return courseId;
            window.tinymce?.triggerSave();
            if (!courseTitle.value.trim()) { courseTitle.classList.add('lms-needs-attention'); courseTitle.scrollIntoView({ behavior: 'smooth', block: 'center' }); courseTitle.focus({ preventScroll: true }); setTimeout(() => courseTitle.classList.remove('lms-needs-attention'), 2600); throw new Error(T.enterTitle); }
            const data = new FormData(courseForm); data.set('action','autosave'); data.delete('featured_image'); data.delete('promo_video');
            const response = await fetch(storeCourseUrl, {method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}); const result = await response.json();
            if (!response.ok) throw new Error(result.message || T.draftFailed);
            courseId = result.course_id; root.dataset.courseId = String(courseId); root.querySelector('[data-draft-course-id]').value = String(courseId); return courseId;
        };
        const unitList = root.querySelector('[data-unit-list]');
        const curriculumStatus = root.querySelector('[data-curriculum-status]');
        const curriculumUnits = () => [...unitList.querySelectorAll(':scope > [data-unit]')].map((unit,index) => ({key:unit.dataset.key,title:unit.querySelector('[data-unit-title]').value.trim(),previous_title:unit.dataset.previousTitle || unit.dataset.title,order:index+1}));
        const curriculumLessons = () => curriculumUnits().flatMap((unit) => {
            const unitElement = [...unitList.querySelectorAll(':scope > [data-unit]')][unit.order - 1];
            return [...unitElement.querySelectorAll('[data-unit-lessons] > [data-lesson-id]')].map((row,index) => ({id:Number(row.dataset.lessonId),module_title:unit.title,module_order:unit.order,lesson_order:index+1}));
        });
        const renderCurriculumOrder = () => {
            curriculumUnits().forEach((unit) => {
                const unitElement = [...unitList.querySelectorAll(':scope > [data-unit]')][unit.order - 1];
                unitElement.dataset.title=unit.title; unitElement.dataset.order=String(unit.order);
                [...unitElement.querySelectorAll('[data-unit-lessons] > [data-lesson-id]')].forEach((row,index) => {
                    row.querySelector('[data-lesson-order-number]').textContent=`${index+1}.`;
                    const lesson = lessons.get(String(row.dataset.lessonId));
                    if(lesson) Object.assign(lesson,{module_title:unit.title,module_order:unit.order,lesson_order:index+1});
                });
            });
        };
        const saveCurriculum = async () => { const id = await ensureCourse(); renderCurriculumOrder(); const units = curriculumUnits(); const lessonOrder=curriculumLessons(); const response = await fetch(curriculumUrlTemplate.replace('__COURSE__', id), {method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({units,lessons:lessonOrder})}); const result = await response.json(); if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || T.curriculumFailed); root.querySelectorAll('[data-unit]').forEach((unit,index) => {unit.dataset.previousTitle=units[index].title;unit.dataset.title=units[index].title;unit.dataset.order=String(index+1)}); renderCurriculumOrder(); curriculumStatus.textContent=T.curriculumSaved; root.querySelector('[data-summary-units]').textContent=String(units.length); return id; };
        let unitSortable = null;
        let lessonSortables = [];
        const persistCurriculumOrder = async () => { renderCurriculumOrder(); curriculumStatus.textContent=T.curriculumSaving; try{await saveCurriculum();}catch(error){curriculumStatus.textContent=error.message || T.curriculumSaveFailed;} };
        const initializeCurriculumSorting = () => {
            unitSortable?.destroy(); lessonSortables.forEach((sortable)=>sortable.destroy()); lessonSortables=[];
            if(typeof window.Sortable !== 'function'){curriculumStatus.textContent=T.dragUnavailable;return;}
            unitSortable=window.Sortable.create(unitList,{animation:160,handle:'.lms-unit-grip',draggable:'[data-unit]',ghostClass:'is-sorting',chosenClass:'is-dragging',onEnd:persistCurriculumOrder});
            unitList.querySelectorAll('[data-unit-lessons]').forEach((container)=>lessonSortables.push(window.Sortable.create(container,{group:'lms-course-lessons',animation:160,handle:'.lms-lesson-grip',draggable:'[data-lesson-id]',ghostClass:'is-sorting',chosenClass:'is-dragging',onEnd:persistCurriculumOrder})));
        };
        const createUnitElement = (title, order) => { const article=document.createElement('article'); article.className='lms-unit'; article.dataset.unit=''; article.dataset.key=`unit-${Date.now()}`; article.dataset.title=title; article.dataset.previousTitle=''; article.dataset.order=String(order); article.innerHTML=`<header><span class="lms-unit-grip" title="${T.dragUnit}" aria-label="${T.dragUnit}"><i data-lucide="grip-vertical"></i></span><button type="button" class="lms-unit-toggle" data-toggle-unit><i data-lucide="chevron-up"></i></button><input value="${title.replace(/"/g,'&quot;')}" aria-label="${T.unitTitle}" data-unit-title><button class="lms-icon-button" type="button" title="${T.deleteUnit}" data-delete-unit><i data-lucide="trash-2"></i></button></header><div class="lms-unit-lessons" data-unit-lessons><button class="lms-add-unit-lesson" type="button" data-add-lesson-to-unit><i data-lucide="plus"></i> ${T.addLesson}</button></div>`; return article; };
        root.querySelector('[data-add-unit]').addEventListener('click', async () => { root.querySelector('[data-curriculum-empty]')?.remove(); const order=unitList.querySelectorAll(':scope > [data-unit]').length+1; const unit=createUnitElement(T.newUnit.replace(':number', String(order)),order); unitList.append(unit); initializeCurriculumSorting(); iconRefresh(); unit.querySelector('[data-unit-title]').select(); try{await saveCurriculum();}catch(error){curriculumStatus.textContent=error.message;} });
        root.querySelector('[data-unit-list]').addEventListener('change', async (event) => { if (!event.target.matches('[data-unit-title]')) return; if (!event.target.value.trim()) event.target.value=T.untitledUnit; try{await saveCurriculum();}catch(error){root.querySelector('[data-curriculum-status]').textContent=error.message;} });
        root.querySelector('[data-unit-list]').addEventListener('click', async (event) => { const unit=event.target.closest('[data-unit]'); if (!unit) return; if(event.target.closest('[data-toggle-unit]')) unit.classList.toggle('collapsed'); if(event.target.closest('[data-delete-unit]')){if(unit.querySelector('[data-lesson-id]')){root.querySelector('[data-curriculum-status]').textContent=T.deleteUnitLessonsFirst;return;}unit.remove();try{await saveCurriculum();}catch(error){root.querySelector('[data-curriculum-status]').textContent=error.message;}} if(event.target.closest('[data-add-lesson-to-unit]')) openLessonModal(null,unit.dataset.title); });

        const lessonModal = root.querySelector('[data-lesson-modal]'); const lessonForm=root.querySelector('[data-lesson-form]'); const moduleSelect=root.querySelector('[data-module-select]'); const lessonErrors=root.querySelector('[data-lesson-errors]');
        const lessonAvailability=lessonForm.querySelector('[data-modal-availability]'); const prerequisiteField=lessonForm.querySelector('[data-modal-prerequisite-field]'); const prerequisiteSelect=lessonForm.querySelector('[data-modal-prerequisite]'); const scheduleField=lessonForm.querySelector('[data-modal-schedule-field]'); const scheduleInput=lessonForm.querySelector('[data-modal-schedule]');
        const refreshLessonPrerequisites = (preferred='') => { const current=String(root.querySelector('[data-lesson-id]').value||''); const candidates=[...lessons.values()].filter((item)=>String(item.id)!==current).sort((a,b)=>(Number(a.module_order||1)-Number(b.module_order||1))||(Number(a.lesson_order||1)-Number(b.lesson_order||1))||(Number(a.id)-Number(b.id))); prerequisiteSelect.innerHTML='<option value="">'+T.selectPrerequisite+'</option>'+candidates.map((item)=>`<option value="${item.id}">${escapeHtml(`${item.module_title||T.courseLessons} · ${item.lesson_order||1}. ${item.title}`)}</option>`).join(''); if(candidates.some((item)=>String(item.id)===String(preferred)))prerequisiteSelect.value=String(preferred); };
        const syncLessonAvailability = () => { const needsPrevious=lessonAvailability.value==='after_previous'; const scheduled=lessonAvailability.value==='scheduled'; prerequisiteField.hidden=!needsPrevious; prerequisiteSelect.required=needsPrevious; if(!needsPrevious)prerequisiteSelect.value=''; scheduleField.hidden=!scheduled; scheduleInput.required=scheduled; if(!scheduled)scheduleInput.value=''; };
        const nextLessonOrder = (moduleTitle) => { const unit=[...unitList.querySelectorAll(':scope > [data-unit]')].find((item)=>item.querySelector('[data-unit-title]').value.trim()===moduleTitle); return (unit?.querySelectorAll('[data-unit-lessons] > [data-lesson-id]').length || 0)+1; };
        const refreshModules = (selected='') => { const units=curriculumUnits(); moduleSelect.innerHTML=units.map((unit) => `<option value="${unit.title.replace(/"/g,'&quot;')}" data-order="${unit.order}" ${unit.title===selected?'selected':''}>${unit.title}</option>`).join(''); if(!units.length) { const dm=@json(__('lms::ui.admin_lesson_default_module')); moduleSelect.innerHTML=`<option value="${dm}" data-order="1">${dm}</option>`; } moduleSelect.dispatchEvent(new Event('change')); };
        moduleSelect.addEventListener('change', () => { root.querySelector('[data-module-order]').value = moduleSelect.selectedOptions[0]?.dataset.order || '1'; if(!root.querySelector('[data-lesson-id]').value) lessonForm.elements.namedItem('lesson_order').value=String(nextLessonOrder(moduleSelect.value)); });
        const openLessonModal = (lesson=null, module='') => { lessonForm.reset(); lessonForm.querySelectorAll('[data-selected-video-media], [data-editor-media-list]').forEach((list)=>{list.innerHTML='';list.hidden=true;}); lessonForm.querySelector('[data-media-attachment-inputs]').innerHTML=''; lessonForm.querySelector('[data-removed-attachment-inputs]').innerHTML=''; root.querySelector('[data-selected-files]').innerHTML='<span>'+T.noFiles+'</span>'; window.tinymce?.get(root.querySelector('[data-lesson-editor]').id || '')?.setContent(''); lessonErrors.hidden=true; root.querySelector('[data-lesson-id]').value=lesson?.id||''; root.querySelector('[data-lesson-course]').value=courseId ? String(courseId) : ''; root.querySelector('#lesson-modal-title').textContent=lesson?T.editLesson:T.addNewLesson; root.querySelector('[data-save-lesson] span').textContent=lesson?T.saveLesson:T.addLesson; refreshModules(lesson?.module_title||module); if(lesson){Object.entries(lesson).forEach(([name,value])=>{const field=lessonForm.elements.namedItem(name);if(!field||value===null)return;if(field instanceof RadioNodeList){[...field].forEach((radio)=>radio.checked=radio.value===value);}else if(field.type==='checkbox')field.checked=Boolean(value);else if(field.type!=='file')field.value=value;});window.tinymce?.get(root.querySelector('[data-lesson-editor]').id || '')?.setContent(lesson.content||''); const filesList=root.querySelector('[data-selected-files]'); const removedBox=root.querySelector('[data-removed-attachment-inputs]'); filesList.innerHTML=''; const savedFiles=(lesson.attachments||[]); if(!savedFiles.length){filesList.innerHTML='<span>'+T.noFiles+'</span>';} else {filesList.hidden=false; savedFiles.forEach((file)=>{ const sizeLabel=file.size?`${Math.max(1,Math.round(Number(file.size)/1024))} KB`:'Saved'; appendSelectedMediaRow(filesList,{name:file.file_name||'Attachment',mime:file.mime||'',size:sizeLabel,url:file.url||''},sizeLabel,()=>{ if(!file.path)return; const input=document.createElement('input'); input.type='hidden'; input.name='remove_attachment_paths[]'; input.value=file.path; (removedBox||lessonForm).appendChild(input); }); }); }} refreshLessonPrerequisites(lesson?.prerequisite_lesson_id||''); syncLessonAvailability(); prepareModal(lessonModal); lessonModal.hidden=false;lessonModal.setAttribute('aria-hidden','false');syncModalLock();iconRefresh();setTimeout(()=>root.querySelector('[data-lesson-title]').focus(),30); };
        const closeLesson = () => {lessonModal.hidden=true;lessonModal.setAttribute('aria-hidden','true');syncModalLock();};
        root.querySelectorAll('[data-close-lesson]').forEach((button)=>button.addEventListener('click',closeLesson)); lessonModal.addEventListener('click',(event)=>{if(event.target===lessonModal)closeLesson();});
        lessonAvailability.addEventListener('change',syncLessonAvailability);
        root.querySelector('[data-add-lesson]')?.addEventListener('click',()=>openLessonModal());
        root.querySelectorAll('[data-edit-lesson]').forEach((button)=>button.addEventListener('click',()=>openLessonModal(lessons.get(button.dataset.editLesson))));
        root.querySelector('[data-lesson-title]').addEventListener('input',(event)=>{const slug=root.querySelector('[data-lesson-slug]');if(!slug.dataset.touched)slug.value=slugify(event.target.value);}); root.querySelector('[data-lesson-slug]').addEventListener('input',(event)=>event.target.dataset.touched='1');
        lessonForm.addEventListener('submit',async(event)=>{event.preventDefault();window.tinymce?.triggerSave();lessonErrors.hidden=true;const id=root.querySelector('[data-lesson-id]').value;const button=root.querySelector('[data-save-lesson]');button.disabled=true;try{await ensureCourse();root.querySelector('[data-lesson-course]').value=String(courseId);const data=new FormData(lessonForm);data.set('course_id',String(courseId));data.set('module_order',moduleSelect.selectedOptions[0]?.dataset.order||'1');data.set('action',statusSelect.value==='published'?'publish':'draft');if(id)data.set('_method','PUT');const url=id?lessonUpdateTemplate.replace('__LESSON__',id):lessonStoreUrl;const response=await fetch(url,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||T.lessonSaveFailed);window.location.assign(@json($courseEditUrlTemplate).replace('__COURSE__',String(courseId)));}catch(error){lessonErrors.textContent=error.message;lessonErrors.hidden=false;}finally{button.disabled=false;}});
        root.querySelectorAll('[data-delete-lesson]').forEach((button)=>button.addEventListener('click',async()=>{if(!confirm(T.deleteLessonConfirm))return;const lessonId=button.dataset.deleteLesson;const response=await fetch(lessonDeleteTemplate.replace('__LESSON__',lessonId),{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});if(response.ok){button.closest('[data-lesson-id]').remove();lessons.delete(String(lessonId));root.querySelector('[data-summary-lessons]').textContent=String(root.querySelectorAll('[data-lesson-id]').length);await persistCurriculumOrder();} }));
        renderCurriculumOrder();
        initializeCurriculumSorting();

        const createTabs = root.querySelector('[data-create-tabs]');
        if (createTabs) {
            createTabs.querySelectorAll('[data-create-locale]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const locale = link.dataset.createLocale;
                    if (locale === @json($locale)) { return; }
                    const title = root.querySelector('[data-course-title]')?.value.trim();
                    if (!title) {
                        alert(@json($t('lms::ui.admin_course_title_required')));
                        return;
                    }
                    const switchLocale = root.querySelector('[data-switch-locale]');
                    const switchAction = root.querySelector('[data-switch-action]');
                    if (!switchLocale || !switchAction) { return; }
                    switchLocale.value = locale;
                    switchAction.disabled = false;
                    root.querySelector('[data-course-form]').requestSubmit();
                });
            });
        }
    })();
    </script>
    @if ($isInstructorEditor)
        </div>
    @endif
</x-dynamic-component>
