@php
    $statusLabels = [
        'published' => __('lms::ui.admin_course_status_published'),
        'scheduled' => __('lms::ui.admin_course_status_scheduled'),
        'draft' => __('lms::ui.admin_course_status_draft'),
        'archived' => __('lms::ui.admin_status_archived'),
    ];

    $typeLabels = [
        'video' => __('lms::ui.admin_lesson_type_video'),
        'text' => __('lms::ui.admin_lesson_type_text'),
        'pdf' => __('lms::ui.admin_lesson_type_pdf'),
        'audio' => __('lms::ui.admin_lesson_type_audio'),
        'live' => __('lms::ui.admin_lesson_type_live'),
    ];

    $typeTone = [
        'video' => 'blue',
        'text' => 'orange',
        'pdf' => 'green',
        'audio' => 'purple',
        'live' => 'gray',
    ];

    $isDraftView = request()->routeIs('admin.plugins.lms.lessons.drafts');
@endphp

<x-app-layout>
    <style>
        .lms-lessons-dashboard .lms-lesson-actions-menu .lms-actions-menu-panel .lms-lesson-menu-action {
            appearance: none;
            background: color-mix(in srgb, var(--ainpa-admin-accent, #fffafa) 5%, #fff) !important;
            background-image: none !important;
            border: 1px solid transparent !important;
            box-shadow: none !important;
            color: var(--ainpa-admin-accent, #9a0000) !important;
        }

        .lms-lessons-dashboard .lms-lesson-actions-menu .lms-actions-menu-panel .lms-lesson-menu-action:hover,
        .lms-lessons-dashboard .lms-lesson-actions-menu .lms-actions-menu-panel .lms-lesson-menu-action:focus-visible {
            background: #fbe6e2 !important;
            border-color: #ebc5bc !important;
            color: color-mix(in srgb, var(--ainpa-admin-accent, #790000) 78%, #000) !important;
            outline: none;
        }
    </style>
    <div class="lms-course-shell lms-lessons-dashboard" data-lms-lessons-page>
        <div class="lms-course-container">
            <div class="lms-dashboard-heading">
                <div>
                    <h1>{{ $isDraftView ? __('lms::ui.admin_draft_lessons_title') : __('lms::ui.admin_lessons_title') }}</h1>
                    <p>{{ $isDraftView ? __('lms::ui.admin_draft_lessons_desc') : __('lms::ui.admin_lessons_desc') }}</p>
                </div>
                <a href="{{ route('admin.plugins.lms.lessons.create') }}" class="lms-btn lms-btn-primary lms-add-course-btn">
                    <span aria-hidden="true">+</span>
                    {{ __('lms::ui.auto_f9062fcfa6') }}
                </a>
            </div>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ $isDraftView ? route('admin.plugins.lms.lessons.drafts') : route('admin.plugins.lms.lessons') }}" class="lms-lessons-filter-card">
                <div class="lms-lessons-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="lms-input" name="search" value="{{ request('search') }}" placeholder="{{ __('lms::ui.auto_a6091aeca6') }}">
                </div>

                <label>
                    <span class="lms-sr-only">{{ __('lms::ui.auto_3730c98664') }}</span>
                    <select class="lms-select" name="course_id">
                        <option value="">{{ __('lms::ui.auto_96eb5855af') }}</option>
                        @foreach ($filters['courses'] as $course)
                            <option value="{{ $course->id }}" @selected((string) request('course_id') === (string) $course->id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="lms-sr-only">{{ __('lms::ui.auto_a517747c3d') }}</span>
                    <select class="lms-select" name="author">
                        <option value="">{{ __('lms::ui.auto_a0042ca389') }}</option>
                        @foreach ($filters['authors'] as $author)
                            <option value="{{ $author }}" @selected(request('author') === $author)>{{ $author }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="lms-sr-only">{{ __('lms::ui.auto_ec53a8c4f0') }}</span>
                    <select class="lms-select" name="status">
                        <option value="">{{ __('lms::ui.auto_a775fc840b') }}</option>
                        @foreach ($filters['statuses'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="lms-sr-only">{{ __('lms::ui.auto_6b46ae4842') }}</span>
                    <select class="lms-select" name="sort">
                        @foreach ($filters['sorts'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="lms-lessons-filter-actions">
                    <button type="submit" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_f3f43e30c8') }}</button>
                    <a href="{{ $isDraftView ? route('admin.plugins.lms.lessons.drafts') : route('admin.plugins.lms.lessons') }}" class="lms-link-button">{{ __('lms::ui.auto_526d688f37') }}</a>
                </div>
            </form>

            <form id="lessonBulkForm" method="POST" action="{{ route('admin.plugins.lms.lessons.bulk') }}" data-bulk-form>
                @csrf
            </form>

            <section class="lms-table-card lms-lessons-card">
                <div class="lms-lessons-scroll-hint" role="status">
                    <span aria-hidden="true">↔</span>
                    {{ __('lms::ui.auto_32828401f4') }}
                </div>
                <div class="lms-table-scroll">
                    <table class="lms-dashboard-table lms-lessons-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" data-select-all aria-label="{{ __('lms::ui.auto_e73f80c8d1') }}"></th>
                                <th>{{ __('lms::ui.auto_b78a322350') }}</th>
                                <th>{{ __('lms::ui.auto_3730c98664') }}</th>
                                <th>{{ __('lms::ui.auto_7b00ad8649') }}</th>
                                <th>{{ __('lms::ui.auto_44749712db') }}</th>
                                <th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th>
                                <th>{{ __('lms::ui.auto_06df33001c') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lessons as $lesson)
                                @php
                                    $status = (string) $lesson->status;
                                    $type = (string) $lesson->lesson_type;
                                    $tone = $typeTone[$type] ?? 'gray';
                                    $imageUrl = $lesson->featured_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($lesson->featured_image_path) : null;
                                    $author = $lesson->author?->name ?: 'Unassigned';
                                    $authorTitle = 'Lesson author';
                                @endphp
                                <tr>
                                    <td><input form="lessonBulkForm" type="checkbox" name="lesson_ids[]" value="{{ $lesson->id }}" data-row-checkbox aria-label="{{ __('lms::ui.auto_edee91e788') }}"></td>
                                    <td>
                                        <div class="lms-lesson-title-cell">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="{{ $lesson->title }}">
                                            @else
                                                <div class="lms-lesson-thumb lms-lesson-thumb-{{ $tone }}">
                                                    <span>{{ strtoupper(\Illuminate\Support\Str::substr($typeLabels[$type] ?? 'L', 0, 1)) }}</span>
                                                </div>
                                            @endif
                                            <div>
                                                <a class="lms-lesson-title-link" href="{{ route('admin.plugins.lms.lessons.edit', $lesson) }}">{{ $lesson->title }}</a>
                                                <small>{{ __('lms::ui.auto_aa8ec0a2d7') }}</small>
                                                <div class="lms-lesson-hover-actions" aria-label="{{ __('lms::ui.auto_dbf0df90c5') }}">
                                                    <a class="lms-lesson-hover-action" href="{{ route('admin.plugins.lms.lessons.edit', $lesson) }}" aria-label="{{ __('lms::ui.auto_4ae6a9c3ac') }}" title="{{ __('lms::ui.auto_7dce122004') }}">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13 7 4 4"/></svg>
                                                    </a>
                                                    @if ($status !== 'published')
                                                        <form method="POST" action="{{ route('admin.plugins.lms.lessons.status', $lesson) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="published">
                                                            <button type="submit" class="lms-lesson-hover-action" aria-label="{{ __('lms::ui.auto_cd13c4bd3e') }}" title="{{ __('lms::ui.auto_110a4b01be') }}">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($status !== 'draft')
                                                        <form method="POST" action="{{ route('admin.plugins.lms.lessons.status', $lesson) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="draft">
                                                            <button type="submit" class="lms-lesson-hover-action" aria-label="{{ __('lms::ui.auto_5c15ec8f80') }}" title="{{ __('lms::ui.auto_f03ab16cd5') }}">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.plugins.lms.lessons.destroy', $lesson) }}" onsubmit="return confirm('Delete this lesson?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="lms-lesson-hover-action lms-lesson-hover-action-danger" aria-label="{{ __('lms::ui.auto_4bd1339d8b') }}" title="{{ __('lms::ui.auto_f2a6c498fb') }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 13h10l1-13"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $lesson->course?->title ?: 'Unassigned course' }}</td>
                                    <td>
                                        <div class="lms-instructor-cell">
                                            <div class="lms-avatar">{{ strtoupper(\Illuminate\Support\Str::substr($author, 0, 1)) }}</div>
                                            <div>
                                                <strong>{{ $author }}</strong>
                                                <small>{{ $authorTitle }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="lms-date-main">{{ $lesson->created_at?->translatedFormat('M j, Y') }}</strong>
                                        <small>{{ $lesson->module_title }}</small>
                                    </td>
                                    <td><span class="lms-status-badge lms-status-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>@if($lesson->review_status === 'pending')<small class="lms-review-required">{{ __('lms::ui.auto_8c657bb4f2') }}</small>@endif</td>
                                    <td>
                                        <details class="lms-actions-menu lms-lesson-actions-menu" data-action-menu>
                                            <summary aria-label="{{ __('lms::ui.auto_070fd38030') }}"><span aria-hidden="true">•••</span></summary>
                                            <div class="lms-actions-menu-panel" role="menu">
                                                <a class="lms-lesson-menu-action" role="menuitem" href="{{ route('admin.plugins.lms.lessons.edit', $lesson) }}">{{ __('lms::ui.auto_7dce122004') }}</a>
                                                @if ($status !== 'published')
                                                    <form method="POST" action="{{ route('admin.plugins.lms.lessons.status', $lesson) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="published">
                                                        <button type="submit" class="lms-lesson-menu-action" role="menuitem">{{ __('lms::ui.auto_110a4b01be') }}</button>
                                                    </form>
                                                @endif
                                                @if ($status !== 'draft')
                                                    <form method="POST" action="{{ route('admin.plugins.lms.lessons.status', $lesson) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="draft">
                                                        <button type="submit" class="lms-lesson-menu-action" role="menuitem">{{ __('lms::ui.auto_f03ab16cd5') }}</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('admin.plugins.lms.lessons.destroy', $lesson) }}" onsubmit="return confirm('Delete this lesson?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="lms-danger-text lms-lesson-menu-action" role="menuitem">{{ __('lms::ui.auto_f2a6c498fb') }}</button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="lms-empty-state">
                                            <strong>{{ __('lms::ui.auto_b3e01dd1c7') }}</strong>
                                            <span>{{ __('lms::ui.auto_1369dda592') }}</span>
                                            <a href="{{ route('admin.plugins.lms.lessons.create') }}" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_f9062fcfa6') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="lms-lesson-bulk-bar" data-bulk-bar>
                <div class="lms-bulk-select-summary">
                    <button type="button" class="lms-mini-select" data-select-toggle aria-label="{{ __('lms::ui.auto_2585f9f979') }}">
                        <span data-select-icon>□</span>
                    </button>
                    <strong data-selected-summary hidden><span data-selected-count>0</span> {{ __('lms::ui.auto_9252bc14a1') }}</strong>
                </div>
                <button form="lessonBulkForm" type="submit" name="bulk_action" value="publish" class="lms-btn lms-btn-success" data-bulk-action data-bulk-option hidden disabled>{{ __('lms::ui.auto_110a4b01be') }}</button>
                <button form="lessonBulkForm" type="submit" name="bulk_action" value="draft" class="lms-btn lms-btn-warning" data-bulk-action data-bulk-option hidden disabled>{{ __('lms::ui.auto_d8fe120448') }}</button>
                <div class="lms-bulk-course-change" data-bulk-option hidden>
                    <select form="lessonBulkForm" class="lms-select" name="target_course_id" data-course-target>
                        <option value="">{{ __('lms::ui.auto_182acc5e47') }}</option>
                        @foreach ($filters['courses'] as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                    <button form="lessonBulkForm" type="submit" name="bulk_action" value="change_course" class="lms-btn lms-btn-ghost" data-bulk-action disabled>{{ __('lms::ui.auto_9639e32cab') }}</button>
                </div>
                <button form="lessonBulkForm" type="submit" name="bulk_action" value="delete" class="lms-btn lms-btn-danger" data-bulk-action data-bulk-option hidden disabled>{{ __('lms::ui.auto_f2a6c498fb') }}</button>
                <button type="button" class="lms-clear-selection" data-clear-selection data-bulk-option hidden>{{ __('lms::ui.auto_82ce4fe964') }}</button>
            </div>

            <div class="lms-lessons-footer">
                <label class="lms-per-page">
                    <select class="lms-select" name="per_page" data-per-page>
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span>{{ __('lms::ui.auto_4f5d5f295d') }}</span>
                </label>

                <x-platform-pagination :paginator="$lessons" />
            </div>
        </div>
    </div>

    <script>
        (() => {
            const page = document.querySelector('[data-lms-lessons-page]');
            if (!page) return;

            const lmsSidebar = document.querySelector('.z4-admin-sidebar');
            const addLessonNavLink = lmsSidebar?.querySelector('a[href$="/admin/plugins/lms/lessons/create"]');
            const allLessonsNavLink = lmsSidebar?.querySelector('a[href$="/admin/plugins/lms/lessons"]');
            const isDraftLessonsView = @json($isDraftView);


            const selectAll = page.querySelector('[data-select-all]');
            const checkboxes = [...page.querySelectorAll('[data-row-checkbox]')];
            const actions = [...page.querySelectorAll('[data-bulk-action]')];
            const selectedCount = page.querySelector('[data-selected-count]');
            const selectedSummary = page.querySelector('[data-selected-summary]');
            const bulkOptions = [...page.querySelectorAll('[data-bulk-option]')];
            const selectIcon = page.querySelector('[data-select-icon]');
            const clearSelection = page.querySelector('[data-clear-selection]');
            const toggleSelection = page.querySelector('[data-select-toggle]');
            const bulkForm = page.querySelector('[data-bulk-form]');

            const updateBulkState = () => {
                const checked = checkboxes.filter((box) => box.checked).length;
                actions.forEach((button) => { button.disabled = checked === 0; });
                if (selectedCount) selectedCount.textContent = checked;
                if (selectedSummary) selectedSummary.hidden = checked === 0;
                bulkOptions.forEach((option) => { option.hidden = checked === 0; });
                if (selectIcon) selectIcon.textContent = checked > 0 && checked === checkboxes.length ? '✓' : '□';
                if (selectAll) {
                    selectAll.checked = checked > 0 && checked === checkboxes.length;
                    selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
                }
            };

            const setAll = (checked) => {
                checkboxes.forEach((box) => { box.checked = checked; });
                updateBulkState();
            };

            selectAll?.addEventListener('change', () => setAll(selectAll.checked));
            toggleSelection?.addEventListener('click', () => setAll(checkboxes.some((box) => !box.checked)));
            clearSelection?.addEventListener('click', () => setAll(false));
            checkboxes.forEach((box) => box.addEventListener('change', updateBulkState));

            bulkForm?.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                const checked = checkboxes.filter((box) => box.checked).length;
                if (checked === 0) {
                    event.preventDefault();
                    return;
                }
                if (submitter?.value === 'change_course') {
                    const target = page.querySelector('[data-course-target]');
                    if (!target?.value) {
                        event.preventDefault();
                    }
                }
                if (submitter?.value === 'delete' && !confirm('Delete selected lessons?')) {
                    event.preventDefault();
                }
            });

            page.querySelector('[data-per-page]')?.addEventListener('change', (event) => {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', event.target.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });

            const closeActionMenus = (except = null) => {
                page.querySelectorAll('[data-action-menu][open]').forEach((menu) => {
                    if (menu !== except) menu.removeAttribute('open');
                });
            };

            page.querySelectorAll('[data-action-menu]').forEach((menu) => {
                menu.addEventListener('toggle', () => {
                    if (menu.open) closeActionMenus(menu);
                });
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element) || !target.closest('[data-action-menu]')) closeActionMenus();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeActionMenus();
            });

            updateBulkState();
        })();
    </script>
</x-app-layout>
