@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $editorMode = $editorMode ?? 'admin';
    $isInstructorEditor = $editorMode === 'instructor';
    $layoutComponent = $isInstructorEditor ? 'frontend-layout' : 'app-layout';
    $isEditing = isset($lesson) && $lesson;
    $locale = $locale ?? 'en';
    $locales = $locales ?? [];
    $formLocale = $isInstructorEditor ? app()->getLocale() : $locale;
    $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
    $pageTitle = $isEditing ? $t('lms::ui.admin_lesson_edit_title') : $t('lms::ui.admin_lesson_add_title');
    $formAction = $isInstructorEditor
        ? ($isEditing ? route('lms.front.instructor.lessons.update', $lesson) : route('lms.front.instructor.lessons.store'))
        : ($isEditing ? route('admin.plugins.lms.lessons.update', $lesson) : route('admin.plugins.lms.lessons.store'));
    $lessonListUrl = $isInstructorEditor ? route('lms.front.instructor.lessons') : route('admin.plugins.lms.lessons');
    $mediaIndexUrl = $isInstructorEditor ? route('lms.front.media.index', [], false) : route('admin.plugins.lms.media.index', [], false);
    $mediaStoreUrl = $isInstructorEditor ? route('lms.front.media.store', [], false) : route('admin.plugins.lms.media.store', [], false);
    $selectedCourse = (string) old('course_id', $isEditing ? $lesson->course_id : ($defaultCourseId ?? ''));
    $selectedModule = (string) old('module_title', $isEditing ? $lesson->module_title : '');
    $selectedModuleOrder = (int) old('module_order', $isEditing ? max(1, (int) $lesson->module_order) : 1);
    $selectedStatus = (string) old('status', $isEditing ? $lesson->status : 'draft');
    $selectedVisibility = (string) old('visibility', $isEditing ? $lesson->visibility : 'enrolled');
    $selectedCompletion = (string) old('completion_requirement', $isEditing ? $lesson->completion_requirement : 'manual');
    $selectedAvailability = (string) old('availability', $isEditing ? $lesson->availability : 'immediate');
    $selectedPrerequisite = (string) old('prerequisite_lesson_id', $isEditing ? $lesson->prerequisite_lesson_id : '');
    $selectedAuthor = (string) old('author_id', $isEditing ? $lesson->created_by : auth()->id());
    $modulesByCourse = $formOptions['modulesByCourse'] ?? [];
    $moduleOrdersByCourse = $formOptions['moduleOrdersByCourse'] ?? [];
    $existingAttachments = collect($isEditing ? ($lesson->attachments ?: []) : [])->values();
    $preservedResources = old('resources', $isEditing ? ($lesson->resources ?: []) : []);
    $videoFileName = $isEditing && $lesson->video_path ? basename((string) $lesson->video_path) : null;
    $publishDate = old('publish_date', $isEditing && $lesson->publish_date ? $lesson->publish_date->format('Y-m-d H:i:s') : '');
@endphp

<x-dynamic-component :component="$layoutComponent">
    @if ($isInstructorEditor)
        <link rel="stylesheet" href="{{ asset('platform/plugins/lms/admin/course-form.css') }}?v={{ filemtime(base_path('modules/lms/resources/assets/admin/course-form.css')) }}">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

    @if ($isInstructorEditor)
        <div class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
            @include('lms::frontend.portal-nav', ['portal' => 'instructor', 'portalAccountName' => $instructor->name])
    @endif

    <div class="lms-course-shell lms-builder lms-lesson-compact" data-lms-lesson-compact>
        <div class="lms-lesson-compact-container">
            <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" data-lesson-form novalidate>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="switch_locale" value="" data-lesson-switch-locale>
                <input type="hidden" name="action" value="save_draft" data-lesson-switch-action disabled>
                @if ($isEditing)
                    @method('PUT')
                @else
                    <input type="hidden" name="draft_lesson_id" value="">
                @endif
                <input type="hidden" name="lesson_type" value="{{ old('lesson_type', $isEditing ? $lesson->lesson_type : 'text') }}" data-lesson-type>
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
                <input type="hidden" name="visibility" value="{{ $selectedVisibility }}">
                <input type="hidden" name="module_order" value="{{ $selectedModuleOrder }}" data-module-order>
                <input type="hidden" name="pdf_media_url" value="{{ old('pdf_media_url') }}" data-pdf-media-url>
                <input type="hidden" name="pdf_media_name" value="{{ old('pdf_media_name') }}" data-pdf-media-name>

                @foreach ($preservedResources as $resourceIndex => $resource)
                    <input type="hidden" name="resources[{{ $resourceIndex }}][file_name]" value="{{ $resource['file_name'] ?? '' }}">
                    <input type="hidden" name="resources[{{ $resourceIndex }}][type]" value="{{ $resource['type'] ?? '' }}">
                    <input type="hidden" name="resources[{{ $resourceIndex }}][size]" value="{{ $resource['size'] ?? '' }}">
                    <input type="hidden" name="resources[{{ $resourceIndex }}][download]" value="{{ !empty($resource['download']) ? 1 : 0 }}">
                @endforeach

                <article class="lms-lesson-editor-card">
                    <header class="lms-lesson-editor-header">
                        <div>
                            <h1>{{ $pageTitle }}</h1>
                            <p>{{ $isEditing ? $t('lms::ui.admin_lesson_update_desc') : $t('lms::ui.admin_lesson_create_desc') }}</p>
                        </div>
                        <a class="lms-icon-button" href="{{ $lessonListUrl }}" aria-label="{{ $t('lms::ui.auto_0aceaec80b') }}" title="{{ $t('lms::ui.auto_493f7c013e') }}">
                            <i data-lucide="x"></i>
                        </a>
                    </header>

                    @if (count($locales) > 1)
                        <div class="lms-lesson-lang-tabs" style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px" @if(! $isEditing) data-lesson-create-tabs @endif>
                            @foreach ($locales as $code => $name)
                                @php $tabTranslation = $isEditing ? $lesson->translations->firstWhere('locale', $code) : null; @endphp
                                <a href="{{ $isEditing ? ($isInstructorEditor ? route('lms.front.instructor.lessons.edit', ['lesson' => $lesson, 'locale' => $code]) : route('admin.plugins.lms.lessons.edit', ['lesson' => $lesson, 'locale' => $code])) : '#' }}"
                                   @if(! $isEditing) data-lesson-locale="{{ $code }}" @endif
                                   style="display:inline-flex;align-items:center;gap:6px;border:1px solid {{ $locale === $code ? '#7c1d1d' : '#dfe3ea' }}!important;border-radius:7px;background:{{ $locale === $code ? '#7c1d1d' : '#fff' }}!important;color:{{ $locale === $code ? '#fff' : '#344054' }}!important;padding:7px 13px;font-size:12px;font-weight:800;text-decoration:none">
                                    {{ $name }}
                                    <span style="font-size:10px;opacity:.8">{{ $code === 'en' ? '' : ($tabTranslation ? '?' : '+') }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="lms-alert lms-alert-error lms-lesson-compact-alert">
                            <strong>{{ $t('lms::ui.auto_2d7d932a4d') }}</strong>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="lms-alert lms-alert-success lms-lesson-compact-alert">{{ session('status') }}</div>
                    @endif
                    @if ($courses->isEmpty())
                        <div class="lms-alert lms-alert-error lms-lesson-compact-alert">{{ $t('lms::ui.auto_489eacf4f5') }}</div>
                    @endif

                    <section class="lms-lesson-editor-section">
                        <h2>{{ $t('lms::ui.auto_83c64d5bf5') }}</h2>
                        <div class="lms-builder-form-grid two">
                            <div class="lms-field">
                                <label class="lms-label" for="lesson-title">{{ $t('lms::ui.auto_caa7b385e2') }} <span>*</span></label>
                                <input class="lms-input" id="lesson-title" name="title" type="text" value="{{ old('title', $isEditing ? $lesson->title : '') }}" required data-title-input>
                                @error('title')<p class="lms-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="lms-field">
                                <label class="lms-label" for="lesson-slug">{{ $t('lms::ui.auto_19b80f00ab') }} <span>*</span></label>
                                <input class="lms-input" id="lesson-slug" name="slug" type="text" value="{{ old('slug', $isEditing ? $lesson->slug : '') }}" required data-slug-input>
                                <p class="lms-help">{{ $t('lms::ui.auto_26cac3a228') }}</p>
                                @error('slug')<p class="lms-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="lms-field">
                            <label class="lms-label" for="lesson-description">{{ $t('lms::ui.auto_36e3402e4d') }} <span>*</span></label>
                            <textarea class="lms-textarea lms-lesson-short-description" id="lesson-description" name="description" maxlength="500" required data-short-description placeholder="{{ $t('lms::ui.admin_lesson_desc_placeholder') }}">{{ old('description', $isEditing ? $lesson->description : '') }}</textarea>
                            <div class="lms-counter"><span data-description-count>0</span>/500</div>
                        </div>
                    </section>

                    <section class="lms-lesson-editor-section">
                        <div class="lms-section-title-inline">
                            <h2>{{ $t('lms::ui.auto_8a8bc0eaaa') }}</h2>
                            <span>{{ $t('lms::ui.auto_18f9de5b5c') }}</span>
                        </div>

                        <div class="lms-content-block">
                            <h3><i data-lucide="video"></i> {{ $t('lms::ui.auto_7a215ffe71') }}</h3>
                            <div class="lms-video-source">
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-video-url">{{ $t('lms::ui.auto_cf26c0d5b7') }}</label>
                                    <input class="lms-input" id="lesson-video-url" name="video_url" type="url" value="{{ old('video_url', $isEditing ? $lesson->video_url : '') }}" placeholder="{{ $t('lms::ui.auto_239d68c786') }}" data-video-url>
                                </div>
                                <button class="lms-btn lms-btn-ghost" type="button" data-open-media="video"><i data-lucide="play-square"></i> {{ $t('lms::ui.auto_ce98486dbe') }}</button>
                            </div>
                            <div class="lms-file-actions"><span class="lms-selected-file-name" data-video-file-name>{{ $videoFileName ? 'Saved video: '.$videoFileName : '' }}</span></div>
                            <div class="lms-selected-files lms-selected-media-line" data-selected-video-media hidden></div>
                            <label class="lms-check"><input type="checkbox" name="free_preview" value="1" @checked(old('free_preview', $isEditing ? $lesson->free_preview : true))><span>{{ $t('lms::ui.auto_ed99789f58') }}</span></label>
                            <p class="lms-help">{{ $t('lms::ui.auto_1055e98ad0') }}</p>
                        </div>

                        <div class="lms-content-block">
                            <h3><i data-lucide="align-left"></i> {{ $t('lms::ui.auto_52e79d5273') }}</h3>
                            <textarea name="content" data-lesson-editor>{{ old('content', $isEditing ? $lesson->content : '') }}</textarea>
                            <div class="lms-editor-media-actions">
                                <button class="lms-btn lms-btn-ghost" type="button" data-open-media="editor"><i data-lucide="image"></i> {{ $t('lms::ui.auto_2e5628ef94') }}</button>
                                <button class="lms-btn lms-btn-ghost" type="button" data-open-media="editor-file"><i data-lucide="images"></i> {{ $t('lms::ui.auto_ce98486dbe') }}</button>
                            </div>
                            <div class="lms-selected-files lms-selected-media-line" data-editor-media-list hidden></div>
                        </div>

                        <div class="lms-content-block">
                            <h3><i data-lucide="file-text"></i> {{ $t('lms::ui.admin_lesson_files_title') }}</h3>
                            <p class="lms-help">{{ $t('lms::ui.auto_2a6bb529bf') }}</p>
                            <div class="lms-file-actions">
                                <button class="lms-btn lms-btn-ghost" type="button" data-open-media="attachment"><i data-lucide="gallery-horizontal"></i> {{ $t('lms::ui.auto_48792feb3e') }}</button>
                            </div>
                            <div class="lms-selected-files" data-selected-files>
                                @forelse ($existingAttachments as $attachmentIndex => $attachment)
                                    @php
                                        $attachmentPath = (string) ($attachment['path'] ?? '');
                                        $attachmentName = $attachment['file_name'] ?? basename($attachmentPath ?: 'attachment');
                                        $attachmentUrl = $attachmentPath !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($attachmentPath) : '#';
                                    @endphp
                                    <div class="lms-compact-file-row" data-existing-file>
                                        <i data-lucide="{{ str_ends_with(strtolower($attachmentPath), '.pdf') ? 'file-text' : 'file' }}"></i>
                                        <strong style="display:flex;align-items:center;gap:6px;min-width:0">
                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $attachmentName }}</a>
                                            <a class="lms-icon-button" href="{{ $attachmentUrl }}" target="_blank" rel="noopener" title="Preview file" aria-label="Preview {{ $attachmentName }}" style="border:0;flex:0 0 22px;height:22px;width:22px"><i data-lucide="eye" style="height:16px;width:16px"></i></a>
                                        </strong>
                                        <span>{{ isset($attachment['size']) ? number_format(((int) $attachment['size']) / 1024, 0).' KB' : 'Saved' }}</span>
                                        <button class="lms-icon-button" type="button" data-remove-existing-file data-path="{{ $attachmentPath }}" aria-label="{{ $t('lms::ui.auto_07e5f87e38') }}"><i data-lucide="x"></i></button>
                                    </div>
                                @empty
                                    <span data-empty-files>{{ $t('lms::ui.auto_b21e5fa4fd') }}</span>
                                @endforelse
                            </div>
                            <div data-media-attachment-inputs></div>
                            <div data-removed-attachment-inputs></div>
                        </div>
                    </section>

                    <div class="lms-lesson-editor-columns">
                        <section class="lms-lesson-editor-section">
                            <h2>{{ $t('lms::ui.auto_915815e4e0') }}</h2>
                            <div class="lms-lesson-settings-row">
                                <div class="lms-field">
                                    <label class="lms-label">{{ $t('lms::ui.auto_926e3f8f9c') }} <span>*</span></label>
                                    <div class="lms-duration-row compact">
                                        <input class="lms-input" name="duration_hours" type="number" min="0" max="999" value="{{ old('duration_hours', $isEditing ? $lesson->duration_hours : 0) }}" required><span>{{ $t('lms::ui.auto_6a7e731616') }}</span>
                                        <input class="lms-input" name="duration_minutes" type="number" min="0" max="59" value="{{ old('duration_minutes', $isEditing ? $lesson->duration_minutes : 30) }}" required><span>{{ $t('lms::ui.auto_f670ea66cf') }}</span>
                                    </div>
                                </div>
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-availability">{{ $t('lms::ui.auto_d7a509e49a') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-availability" name="availability" required>
                                        @foreach ($formOptions['availability'] as $option)
                                            <option value="{{ $option['value'] }}" @selected($selectedAvailability === $option['value'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="lms-field" data-prerequisite-field @if($selectedAvailability !== 'after_previous') hidden @endif>
                                    <label class="lms-label" for="lesson-prerequisite">{{ $t('lms::ui.auto_6932d675e8') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-prerequisite" name="prerequisite_lesson_id" data-prerequisite-select data-selected="{{ $selectedPrerequisite }}"></select>
                                    <p class="lms-help">{{ $t('lms::ui.auto_e94a54665f') }}</p>
                                </div>
                                <div class="lms-field" data-schedule-field @if($selectedAvailability !== 'scheduled') hidden @endif>
                                    <label class="lms-label" for="lesson-publish-date">{{ $t('lms::ui.auto_5619859087') }} <span>*</span></label>
                                    <input class="lms-input" id="lesson-publish-date" type="datetime-local" name="publish_date" value="{{ $publishDate ? \Illuminate\Support\Carbon::parse($publishDate)->format('Y-m-d\\TH:i') : '' }}" data-schedule-input>
                                </div>
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-access">{{ $t('lms::ui.auto_7004d1fb24') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-access" name="access" required>
                                        @foreach ($formOptions['access'] as $option)
                                            <option value="{{ $option['value'] }}" @selected(old('access', $isEditing ? $lesson->access : 'enrolled') === $option['value'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lms-field">
                                <label class="lms-label">{{ $t('lms::ui.auto_3f06d3ad6b') }} <span>*</span></label>
                                <div class="lms-inline-options">
                                    <label><input type="radio" name="completion_requirement" value="manual" @checked($selectedCompletion === 'manual')> {{ $t('lms::ui.auto_60091140eb') }}</label>
                                    <label><input type="radio" name="completion_requirement" value="content" @checked($selectedCompletion !== 'manual')> {{ $t('lms::ui.auto_33961092ee') }}</label>
                                </div>
                            </div>
                        </section>

                        <section class="lms-lesson-editor-section">
                            <h2>{{ $t('lms::ui.auto_5d03795271') }}</h2>
                            <div class="lms-builder-form-grid two">
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-course">{{ $t('lms::ui.auto_62851e02bb') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-course" name="course_id" required data-course-select>
                                        <option value="">{{ $t('lms::ui.auto_cb743c15ba') }}</option>
                                        @foreach ($courses as $courseOption)
                                            <option value="{{ $courseOption->id }}" @selected($selectedCourse === (string) $courseOption->id)>{{ $courseOption->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-module">{{ $t('lms::ui.auto_e1e594dd1b') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-module" name="module_title" required data-module-select></select>
                                </div>
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-order">{{ $t('lms::ui.auto_160b56abf1') }} <span>*</span></label>
                                    <input class="lms-input" id="lesson-order" name="lesson_order" type="number" min="1" max="10000" value="{{ old('lesson_order', $isEditing ? $lesson->lesson_order : 1) }}" required>
                                    <p class="lms-help">{{ $t('lms::ui.auto_cb1dcc4129') }}</p>
                                </div>
                                <div class="lms-field">
                                    <label class="lms-label" for="lesson-author">{{ $t('lms::ui.auto_7b00ad8649') }} <span>*</span></label>
                                    <select class="lms-select" id="lesson-author" name="author_id" required>
                                        @forelse ($authors as $author)
                                            <option value="{{ $author->id }}" @selected($selectedAuthor === (string) $author->id)>{{ $author->name }}</option>
                                        @empty
                                            <option value="{{ auth()->id() }}" selected>{{ optional(auth()->user())->name ?: 'Current administrator' }}</option>
                                        @endforelse
                                    </select>
                                    <p class="lms-help">{{ $t('lms::ui.auto_e0fe47b340') }}</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <footer class="lms-lesson-editor-footer">
                        <a class="lms-btn lms-btn-ghost" href="{{ $lessonListUrl }}">{{ $t('lms::ui.auto_ea4788705e') }}</a>
                        @if ($selectedStatus !== 'published')
                            <button class="lms-btn lms-btn-ghost" type="submit" name="action" value="save_draft" formnovalidate><i data-lucide="save"></i> {{ $t('lms::ui.auto_5778e182c5') }}</button>
                        @endif
                        <button class="lms-btn lms-btn-primary" type="submit" name="action" value="{{ $isEditing ? 'save' : 'publish' }}"><i data-lucide="{{ $isEditing ? 'save' : 'plus' }}"></i> {{ $isEditing ? $t('lms::ui.admin_lesson_save_changes') : $t('lms::ui.admin_lesson_add') }}</button>
                    </footer>
                </article>
            </form>
        </div>

        <div class="lms-builder-modal lms-media-picker" data-media-modal hidden aria-hidden="true">
            <div class="lms-builder-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lesson-media-title">
                <header>
                    <div><h2 id="lesson-media-title">{{ $t('lms::ui.auto_f38e6f393f') }}</h2><p data-media-description>{{ $t('lms::ui.auto_d0f474a8f4') }}</p></div>
                    <button class="lms-icon-button" type="button" data-close-media aria-label="{{ $t('lms::ui.auto_8949ac07e0') }}"><i data-lucide="x"></i></button>
                </header>
                <form class="lms-media-upload-panel" data-media-upload-form enctype="multipart/form-data">
                    <div>
                        <strong>{{ $t('lms::ui.auto_75eac6d96c') }}</strong>
                        <span>{{ $t('lms::ui.auto_6d22dfa8ab') }}</span>
                    </div>
                    <input class="lms-input" type="text" name="title" placeholder="{{ $t('lms::ui.auto_6097f5f7cc') }}" data-media-upload-title>
                    <label class="lms-btn lms-btn-ghost">
                        <i data-lucide="upload"></i> {{ $t('lms::ui.auto_cbe55e77ff') }}
                        <span data-media-upload-filename style="font-size:12px;color:#6b7280;margin-inline-start:6px"></span>
                        <input type="file" name="media" hidden data-media-upload-file>
                    </label>
                    <button class="lms-btn lms-btn-primary" type="submit" data-media-upload-submit>{{ $t('lms::ui.auto_a3990024ce') }}</button>
                    <p data-media-upload-status></p>
                </form>
                <div class="lms-media-picker-body" data-media-items><p>{{ $t('lms::ui.auto_0b7a62e592') }}</p></div>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const root = document.querySelector('[data-lms-lesson-compact]');
        if (!root) return;

        const form = root.querySelector('[data-lesson-form]');
        const csrf = form.querySelector('[name="_token"]').value;
        const mediaEndpoint = @json($mediaIndexUrl);
        const mediaStoreEndpoint = @json($mediaStoreUrl);
        const modulesByCourse = @json($modulesByCourse);
        const moduleOrdersByCourse = @json($moduleOrdersByCourse);
        const prerequisiteLessonsByCourse = @json($formOptions['prerequisiteLessonsByCourse'] ?? []);
        const currentLessonId = @json($isEditing ? (int) $lesson->id : null);
        const initialPrerequisite = @json($selectedPrerequisite);
        const initialModule = @json($selectedModule);
        const initialModuleOrder = @json($selectedModuleOrder);
        let mediaTarget = 'attachment';
        let slugTouched = root.querySelector('[data-slug-input]').value.trim() !== '';

        const icons = () => window.lucide?.createIcons({attrs: {'stroke-width': 1.8}});
        const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        const slugify = (value) => {
            const arabic = {'ا':'a','أ':'a','إ':'i','آ':'a','ب':'b','ت':'t','ث':'th','ج':'j','ح':'h','خ':'kh','د':'d','ذ':'th','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','و':'w','ي':'y','ى':'a','ة':'h'};
            return [...String(value)].map((char) => arabic[char] ?? char).join('').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 220);
        };
        const formatSize = (value) => {
            const size = Number(value || 0);
            if (!size) return @json(__('lms::ui.editor_file_ready'));
            return size >= 1048576 ? `${(size / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(size / 1024))} KB`;
        };

        icons();
        const editorLang = @json(app()->getLocale() === 'ar' ? 'ar' : 'en');
        const editorLangUrl = @json(app()->getLocale() === 'ar' ? asset('platform/vendor/tinymce/langs/ar.js') : null);
        const editorFont = editorLang === 'ar' ? 'Cairo,Inter,Arial,sans-serif' : 'Inter,Arial,sans-serif';
        window.tinymce?.init({
            selector: '[data-lesson-editor]',
            height: 235,
            menubar: false,
            branding: false,
            statusbar: true,
            language: editorLang,
            language_url: editorLangUrl || undefined,
            directionality: editorLang === 'ar' ? 'rtl' : 'ltr',
            plugins: 'link image table code codesample fullscreen wordcount media autoresize lists',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor | alignleft aligncenter alignright | bullist numlist | blockquote | link image media table | code fullscreen',
            content_style: 'body{font-family:' + editorFont + ';font-size:14px;line-height:1.6;padding:12px}',
        });

        const title = root.querySelector('[data-title-input]');
        const slug = root.querySelector('[data-slug-input]');
        slug.addEventListener('input', () => { slugTouched = slug.value.trim() !== ''; });
        title.addEventListener('input', () => { if (!slugTouched) slug.value = slugify(title.value); });

        const description = root.querySelector('[data-short-description]');
        const descriptionCount = root.querySelector('[data-description-count]');
        const updateDescriptionCount = () => { descriptionCount.textContent = String(description.value.length); };
        description.addEventListener('input', updateDescriptionCount);
        updateDescriptionCount();

        const courseSelect = root.querySelector('[data-course-select]');
        const moduleSelect = root.querySelector('[data-module-select]');
        const moduleOrder = root.querySelector('[data-module-order]');
        const availabilitySelect = root.querySelector('#lesson-availability');
        const prerequisiteField = root.querySelector('[data-prerequisite-field]');
        const prerequisiteSelect = root.querySelector('[data-prerequisite-select]');
        const scheduleField = root.querySelector('[data-schedule-field]');
        const scheduleInput = root.querySelector('[data-schedule-input]');
        const refreshPrerequisites = (preferred = '') => {
            const courseId = String(courseSelect.value || '');
            const lessons = (prerequisiteLessonsByCourse[courseId] || []).filter((item) => Number(item.id) !== Number(currentLessonId));
            prerequisiteSelect.innerHTML = '<option value="">Select the required lesson</option>' + lessons.map((item) => `<option value="${item.id}">${escapeHtml(`${item.module || 'Course Lessons'} · ${item.order}. ${item.title}`)}</option>`).join('');
            if (preferred && lessons.some((item) => String(item.id) === String(preferred))) prerequisiteSelect.value = String(preferred);
        };
        const syncAvailabilityFields = () => {
            const afterPrevious = availabilitySelect.value === 'after_previous';
            const scheduled = availabilitySelect.value === 'scheduled';
            prerequisiteField.hidden = !afterPrevious;
            prerequisiteSelect.required = afterPrevious;
            if (!afterPrevious) prerequisiteSelect.value = '';
            scheduleField.hidden = !scheduled;
            scheduleInput.required = scheduled;
            if (!scheduled) scheduleInput.value = '';
        };
        const refreshModules = (preferred = '') => {
            const courseId = String(courseSelect.value || '');
            const modules = [...(modulesByCourse[courseId] || [])];
            if (preferred && !modules.includes(preferred)) modules.unshift(preferred);
            if (!modules.length) modules.push(@json(__('lms::ui.admin_lesson_default_module')));
            moduleSelect.innerHTML = modules.map((module) => `<option value="${escapeHtml(module)}">${escapeHtml(module)}</option>`).join('');
            moduleSelect.value = preferred && modules.includes(preferred) ? preferred : modules[0];
            moduleOrder.value = String(moduleOrdersByCourse[courseId]?.[moduleSelect.value] || initialModuleOrder || 1);
        };
        courseSelect.addEventListener('change', () => { refreshModules(''); refreshPrerequisites(''); });
        moduleSelect.addEventListener('change', () => {
            moduleOrder.value = String(moduleOrdersByCourse[String(courseSelect.value)]?.[moduleSelect.value] || 1);
        });
        refreshModules(initialModule);
        refreshPrerequisites(initialPrerequisite);
        availabilitySelect.addEventListener('change', syncAvailabilityFields);
        syncAvailabilityFields();

        const selectedFiles = root.querySelector('[data-selected-files]');
        const mediaInputs = root.querySelector('[data-media-attachment-inputs]');
        const removedInputs = root.querySelector('[data-removed-attachment-inputs]');
        const clearEmptyState = () => root.querySelector('[data-empty-files]')?.remove();
        const appendFileRow = (name, size, icon = 'file', removable = true, linkedInputs = null, targetList = selectedFiles, onRemove = null, url = '') => {
            if (targetList === selectedFiles) {
                clearEmptyState();
            } else {
                targetList.hidden = false;
            }
            const row = document.createElement('div');
            row.className = 'lms-compact-file-row';
            row.innerHTML = `<i data-lucide="${icon}"></i><strong style="display:flex;align-items:center;gap:6px;min-width:0">${url ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(name)}</a><a class="lms-icon-button" href="${escapeHtml(url)}" target="_blank" rel="noopener" title="Preview file" style="border:0;flex:0 0 22px;height:22px;width:22px"><i data-lucide="eye" style="height:16px;width:16px"></i></a>` : escapeHtml(name)}</strong><span>${escapeHtml(size)}</span>${removable ? '<button class="lms-icon-button" type="button" data-remove-new-file aria-label="Remove file"><i data-lucide="x"></i></button>' : ''}`;
            row.querySelector('[data-remove-new-file]')?.addEventListener('click', () => {
                linkedInputs?.remove();
                onRemove?.();
                row.remove();
                if (!targetList.children.length) {
                    if (targetList === selectedFiles) {
                        selectedFiles.innerHTML = '<span data-empty-files>No files selected.</span>';
                    } else {
                        targetList.hidden = true;
                    }
                }
            });
            targetList.appendChild(row);
            icons();
            return row;
        };

        root.querySelectorAll('[data-remove-existing-file]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_attachment_paths[]';
                input.value = button.dataset.path || '';
                removedInputs.appendChild(input);
                button.closest('[data-existing-file]')?.remove();
                if (!selectedFiles.children.length) selectedFiles.innerHTML = '<span data-empty-files>No files selected.</span>';
            });
        });

        const mediaModal = root.querySelector('[data-media-modal]');
        const mediaItems = root.querySelector('[data-media-items]');
        const mediaDescription = root.querySelector('[data-media-description]');
        const mediaUploadForm = root.querySelector('[data-media-upload-form]');
        const mediaUploadFile = root.querySelector('[data-media-upload-file]');
        mediaUploadFile?.addEventListener('change', () => { const nameLabel = root.querySelector('[data-media-upload-filename]'); if (nameLabel) nameLabel.textContent = mediaUploadFile.files?.[0]?.name || ''; });
        const mediaUploadTitle = root.querySelector('[data-media-upload-title]');
        const mediaUploadStatus = root.querySelector('[data-media-upload-status]');
        const closeMedia = () => { mediaModal.hidden = true; mediaModal.setAttribute('aria-hidden', 'true'); };
        const lessonEditor = () => window.tinymce?.get(root.querySelector('[data-lesson-editor]')?.id || '') || window.tinymce?.editors?.[0] || null;
        const fileIcon = (item) => {
            const mime = String(item.mime_type || item.mime || item.type || '').toLowerCase();
            const extension = String(item.extension || (item.name || item.url || '').split('.').pop() || '').toLowerCase();
            if (mime.startsWith('video/') || ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'].includes(extension)) return 'video';
            if (mime.includes('pdf') || extension === 'pdf') return 'file-text';
            if (mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico'].includes(extension)) return 'image';
            return 'file';
        };
        const renderSelectedRow = (container, label, detail, icon, onRemove) => {
            if (!container) return null;
            container.hidden = false;
            const row = appendFileRow(label, detail, icon, true, null, container);
            row.querySelector('[data-remove-new-file]')?.addEventListener('click', () => {
                onRemove?.();
                if (!container.children.length) container.hidden = true;
            }, {once: true});
            return row;
        };
        const selectMedia = (item) => {
            const url = item.media_url || item.url || item.image_url || '';
            const name = item.title || item.name || 'Media file';
            if (!url) return;

            if (mediaTarget === 'video') {
                root.querySelector('[data-video-url]').value = url;
                root.querySelector('[data-video-file-name]').textContent = `Selected from Media Studio: ${name}`;
                const videoList = root.querySelector('[data-selected-video-media]');
                videoList.innerHTML = '';
                renderSelectedRow(videoList, name, item.size || 'Media Studio video', 'video', () => {
                    root.querySelector('[data-video-url]').value = '';
                    root.querySelector('[data-video-file-name]').textContent = '';
                });
            } else if (mediaTarget === 'editor' || mediaTarget === 'editor-file') {
                const embedId = `lms-media-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                const icon = fileIcon(item);
                const editor = lessonEditor();
                const label = escapeHtml(name);
                const safeUrl = escapeHtml(url);
                const html = icon === 'image'
                    ? `<figure data-lms-media-embed="${embedId}"><img src="${safeUrl}" alt="${label}"></figure>`
                    : icon === 'video'
                        ? `<figure data-lms-media-embed="${embedId}"><video src="${safeUrl}" controls preload="metadata"></video><figcaption>${label}</figcaption></figure>`
                        : `<p data-lms-media-embed="${embedId}"><a href="${safeUrl}" target="_blank" rel="noopener">${label}</a></p>`;
                editor?.insertContent(html);
                renderSelectedRow(root.querySelector('[data-editor-media-list]'), name, item.size || 'Inserted in lesson content', icon, () => {
                    const node = editor?.getBody()?.querySelector(`[data-lms-media-embed="${embedId}"]`);
                    node?.remove();
                    editor?.save();
                });
            } else {
                if (fileIcon(item) === 'file-text') {
                    root.querySelector('[data-pdf-media-url]').value = url;
                    root.querySelector('[data-pdf-media-name]').value = name;
                }
                const index = `${Date.now()}-${mediaInputs.children.length}`;
                const inputGroup = document.createElement('div');
                inputGroup.hidden = true;
                inputGroup.dataset.mediaInputGroup = index;
                [['url', url], ['name', name], ['size', item.size || ''], ['mime', item.mime || item.mime_type || '']].forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `media_attachments[${index}][${key}]`;
                    input.value = value;
                    inputGroup.appendChild(input);
                });
                mediaInputs.appendChild(inputGroup);
                appendFileRow(name, item.size || 'Media Studio', fileIcon(item), true, inputGroup, selectedFiles, null, url);
            }
            closeMedia();
        };
        const renderMedia = (items) => {
            if (!items.length) { mediaItems.innerHTML = '<div class="lms-media-empty">No matching files found in Media Studio.</div>'; return; }
            mediaItems.innerHTML = `<div class="lms-media-picker-grid">${items.map((item, index) => {
                const preview = item.thumbnail_url || item.image_url || '';
                const name = item.title || item.name || 'Media file';
                const icon = fileIcon(item);
                return `<button type="button" data-media-index="${index}">${preview ? `<img src="${escapeHtml(preview)}" alt="">` : `<i data-lucide="${icon}"></i>`}<span>${escapeHtml(name)}</span></button>`;
            }).join('')}</div>`;
            mediaItems.querySelectorAll('[data-media-index]').forEach((button) => button.addEventListener('click', () => selectMedia(items[Number(button.dataset.mediaIndex)])));
            icons();
        };
        const openMedia = async (target) => {
            mediaTarget = target;
            mediaDescription.textContent = target === 'video' ? 'Choose or upload a video, then it will be inserted immediately into the lesson video field.' : target === 'editor' ? 'Choose or upload an image to insert into the lesson content.' : target === 'editor-file' ? 'Choose or upload any media file to insert into the lesson content.' : 'Choose or upload a PDF or file for this lesson.';
            mediaUploadFile.accept = target === 'video' ? 'video/*' : target === 'editor' ? 'image/*' : target === 'editor-file' ? 'image/*,video/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv' : 'application/pdf,.pdf,image/*,video/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv';
            mediaUploadForm.reset();
            mediaUploadStatus.textContent = '';
            mediaModal.hidden = false;
            mediaModal.setAttribute('aria-hidden', 'false');
            mediaItems.innerHTML = '<p>Loading media...</p>';
            if (!mediaEndpoint) { mediaItems.innerHTML = '<div class="lms-media-empty">Media Studio is not available.</div>'; return; }
            const type = target === 'video' ? 'video' : target === 'editor' ? 'image' : 'file';
            try {
                const endpoint = `${mediaEndpoint}${mediaEndpoint.includes('?') ? '&' : '?'}type=${type}`;
                const response = await fetch(endpoint, {headers: {Accept: 'application/json'}});
                const payload = await response.json();
                if (!response.ok) throw new Error('media-load-failed');
                renderMedia(Array.isArray(payload.items) ? payload.items : []);
            } catch (error) {
                mediaItems.innerHTML = '<div class="lms-media-empty">Could not load Media Studio files.</div>';
            }
        };
        mediaUploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!mediaStoreEndpoint) {
                mediaUploadStatus.textContent = 'Media upload route is not available.';
                return;
            }
            const file = mediaUploadFile.files?.[0];
            if (!file) {
                mediaUploadStatus.textContent = 'Choose a file first.';
                return;
            }
            const data = new FormData();
            data.append('media', file);
            data.append('title', mediaUploadTitle.value || file.name.replace(/\.[^.]+$/, ''));
            mediaUploadStatus.textContent = 'Uploading media...';
            try {
                const response = await fetch(mediaStoreEndpoint, {
                    method: 'POST',
                    body: data,
                    headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf},
                });
                const payload = await response.json();
                if (!response.ok || !payload.media) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Media upload failed.');
                mediaUploadStatus.textContent = 'Uploaded. Inserting media...';
                selectMedia(payload.media);
            } catch (error) {
                mediaUploadStatus.textContent = error.message || 'Media upload failed.';
            }
        });
        root.querySelectorAll('[data-open-media]').forEach((button) => button.addEventListener('click', () => openMedia(button.dataset.openMedia)));
        root.querySelector('[data-close-media]').addEventListener('click', closeMedia);
        mediaModal.addEventListener('click', (event) => { if (event.target === mediaModal) closeMedia(); });

        form.addEventListener('submit', () => {
            window.tinymce?.triggerSave();
            const hasVideo = root.querySelector('[data-video-url]').value.trim();
            const hasPdf = root.querySelector('[data-pdf-media-url]').value.trim();
            root.querySelector('[data-lesson-type]').value = hasVideo ? 'video' : hasPdf ? 'pdf' : 'text';
        });
    })();
    </script>
    @if ($isInstructorEditor)
        </div>
    @endif

    @if (! $isEditing && ! $isInstructorEditor)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.querySelector('[data-lesson-form]');
            var tabs = document.querySelector('[data-lesson-create-tabs]');
            if (!form || !tabs) { return; }
            tabs.querySelectorAll('[data-lesson-locale]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    var locale = link.dataset.lessonLocale;
                    if (locale === @json($locale)) { return; }
                    var title = document.getElementById('lesson-title');
                    if (!title || !title.value.trim()) {
                        alert(@json(__('lms::ui.admin_lesson_title_required')));
                        return;
                    }
                    form.querySelector('[data-lesson-switch-locale]').value = locale;
                    form.querySelector('[data-lesson-switch-action]').disabled = false;
                    form.requestSubmit();
                });
            });
        });
    </script>
    @endif
</x-dynamic-component>
