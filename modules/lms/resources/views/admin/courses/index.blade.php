@php
    $statusLabels = [
        'published' => __('lms::ui.admin_status_published'),
        'scheduled' => __('lms::ui.admin_status_scheduled'),
        'draft' => __('lms::ui.admin_status_draft'),
    ];
    $categoryTone = [
        'Interior Design' => 'purple',
        'Business' => 'orange',
        'Business Management' => 'orange',
        'Marketing' => 'blue',
        'Digital Art' => 'blue',
        'Personal Growth' => 'green',
        'Fine Art' => 'green',
        'Photography' => 'blue',
    ];
    $isDraftView = request()->query('status') === 'draft';
@endphp

<x-app-layout>
    <div class="lms-course-shell lms-courses-dashboard lms-courses-page" data-lms-courses-page>
        <div class="lms-course-container">
            <div class="lms-dashboard-heading">
                <div>
                    <h1>{{ __('lms::ui.auto_389433c98f') }}</h1>
                    <p>{{ __('lms::ui.auto_8515c6d2da') }}</p>
                </div>
                <div class="lms-heading-actions">
                    <a href="{{ route('admin.plugins.lms.courses.codes') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_0cfcee28ba') }}</a>
                    <a href="{{ route('admin.plugins.lms.courses.create') }}" class="lms-btn lms-btn-primary lms-add-course-btn">
                        <span aria-hidden="true">+</span>
                        {{ __('lms::ui.auto_a5a0f5c174') }}
                    </a>
                </div>
            </div>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>
            @endif

            <section class="lms-stat-grid" aria-label="{{ __('lms::ui.auto_831b632c66') }}">
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-purple">▤</div>
                    <div>
                        <span>{{ __('lms::ui.auto_8caaae88bf') }}</span>
                        <strong>{{ number_format($stats['total']) }}</strong>
                        <small>{{ __('lms::ui.auto_0dfcce7527') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-green">✓</div>
                    <div>
                        <span>{{ __('lms::ui.auto_d37db27c1a') }}</span>
                        <strong>{{ number_format($stats['published']) }}</strong>
                        <small>{{ __('lms::ui.auto_0dfcce7527') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-orange">▧</div>
                    <div>
                        <span>{{ __('lms::ui.auto_c3a281fd97') }}</span>
                        <strong>{{ number_format($stats['draft']) }}</strong>
                        <small>{{ __('lms::ui.auto_0dfcce7527') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-blue">◌</div>
                    <div>
                        <span>{{ __('lms::ui.auto_44da05124b') }}</span>
                        <strong>{{ number_format($stats['students']) }}</strong>
                        <small>{{ __('lms::ui.auto_75d37c6cbf') }}</small>
                    </div>
                </article>
            </section>

            <form method="GET" action="{{ route('admin.plugins.lms.courses') }}" class="lms-filter-card">
                <div class="lms-search-field">
                    <input class="lms-input" name="search" value="{{ request('search') }}" placeholder="{{ __('lms::ui.auto_576228493e') }}">
                    <span aria-hidden="true">⌕</span>
                </div>

                <label>
                    <span>{{ __('lms::ui.auto_3adbdb3ac0') }}</span>
                    <select class="lms-select" name="category">
                        <option value="">{{ __('lms::ui.auto_a6a2a55bea') }}</option>
                        @foreach ($filters['categories'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('lms::ui.auto_07f8bcc8ac') }}</span>
                    <select class="lms-select" name="instructor">
                        <option value="">{{ __('lms::ui.auto_4527ad2f0f') }}</option>
                        @foreach ($filters['instructors'] as $instructor)
                            <option value="{{ $instructor }}" @selected(request('instructor') === $instructor)>{{ $instructor }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('lms::ui.auto_ec53a8c4f0') }}</span>
                    <select class="lms-select" name="status">
                        <option value="">{{ __('lms::ui.auto_a775fc840b') }}</option>
                        @foreach ($filters['statuses'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('lms::ui.auto_a1fa277792') }}</span>
                    <select class="lms-select" name="type">
                        <option value="">{{ __('lms::ui.auto_90b2f7433d') }}</option>
                        @foreach ($filters['types'] as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ __('lms::ui.admin_pricing_'.$type) }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('lms::ui.auto_39b9e051f9') }}</span>
                    <select class="lms-select" name="date_range">
                        @foreach ($filters['dateRanges'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('date_range', '') === $value)>{{ $value === '' ? __('lms::ui.admin_range_all') : __('lms::ui.admin_range_'.$value) }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="lms-filter-actions">
                    <button type="submit" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_9639e32cab') }}</button>
                    <a href="{{ route('admin.plugins.lms.courses') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_54319fb8e4') }}</a>
                </div>
            </form>

            <form id="lms-courses-bulk-form" method="POST" action="{{ route('admin.plugins.lms.courses.bulk') }}" data-bulk-submit>
                @csrf
            </form>

            <section class="lms-table-card" data-bulk-form>
                <div class="lms-table-toolbar">
                    <select class="lms-select" name="bulk_action" aria-label="{{ __('lms::ui.auto_d2b9cbf9a1') }}" form="lms-courses-bulk-form">
                        <option value="">{{ __('lms::ui.auto_bfab8bcc21') }}</option>
                        <option value="publish">{{ __('lms::ui.auto_df3c217224') }}</option>
                        <option value="draft">{{ __('lms::ui.auto_dc0e577735') }}</option>
                        <option value="delete">{{ __('lms::ui.auto_d3b206d196') }}</option>
                    </select>
                    <button type="submit" form="lms-courses-bulk-form" class="lms-btn lms-btn-ghost" data-bulk-apply disabled>{{ __('lms::ui.auto_9639e32cab') }}</button>
                </div>

                <div class="lms-table-scroll-hint" role="status">
                    <span aria-hidden="true">↔</span>
                    {{ __('lms::ui.auto_32828401f4') }}
                </div>
                <div class="lms-table-scroll">
                    <table class="lms-dashboard-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" data-select-all aria-label="{{ __('lms::ui.auto_f6ae2a67a9') }}"></th>
                                <th>{{ __('lms::ui.auto_3d5e610cc8') }}</th>
                                <th>{{ __('lms::ui.auto_3adbdb3ac0') }}</th>
                                <th>{{ __('lms::ui.auto_44749712db') }}</th>
                                <th>{{ __('lms::ui.auto_aba064f896') }}</th>
                                <th>{{ __('lms::ui.auto_07f8bcc8ac') }}</th>
                                <th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th>
                                <th>{{ __('lms::ui.auto_06df33001c') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courses as $course)
                                @php
                                    $tone = $categoryTone[$course->category] ?? 'purple';
                                    $status = (string) $course->status;
                                    $imageUrl = $course->featured_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($course->featured_image_path) : null;
                                    $studentCount = (int) ($course->enrollments_count ?? 0);
                                @endphp
                                <tr>
                                    <td><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" form="lms-courses-bulk-form" data-row-checkbox aria-label="{{ __('lms::ui.auto_6386034f37') }}"></td>
                                    <td>
                                        <div class="lms-course-cell">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="{{ $course->title }}">
                                            @else
                                                <div class="lms-course-thumb-fallback">{{ strtoupper(\Illuminate\Support\Str::substr($course->title, 0, 2)) }}</div>
                                            @endif
                                            <div>
                                                <a class="lms-course-title-link" href="{{ route('admin.plugins.lms.courses.edit', $course) }}">{{ $course->title }}</a>
                                                <small>{{ __('lms::ui.auto_9f9a582a11') }}</small>
                                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">
                                                    @foreach ($locales as $code => $name)
                                                        <a href="{{ route('admin.plugins.lms.courses.edit', ['course' => $course, 'locale' => $code]) }}"
                                                           title="{{ $name }}"
                                                           style="border:1px solid #dfe3ea;border-radius:4px;background:#fff;padding:1px 6px;color:#7c1d1d;font-size:10px;font-weight:700;text-decoration:none">
                                                            {{ strtoupper($code) }} {{ $code === 'en' ? '•' : (($course->translations->firstWhere('locale', $code)) ? '✓' : '+') }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                                <div class="lms-course-hover-actions" aria-label="{{ __('lms::ui.auto_e43fe61481') }}">
                                                    <a class="lms-course-hover-action" href="{{ route('admin.plugins.lms.courses.edit', $course) }}" aria-label="{{ __('lms::ui.auto_2cfefe8013') }}" title="{{ __('lms::ui.auto_7dce122004') }}">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13 7 4 4"/></svg>
                                                    </a>
                                                    @if ($status === 'published')
                                                        <a class="lms-course-hover-action" href="{{ route('lms.front.courses.show', ['course' => $course->slug], false) }}" aria-label="{{ __('lms::ui.auto_e56d461535') }}" title="{{ __('lms::ui.auto_4351cfebe4') }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                        </a>
                                                    @else
                                                        <a class="lms-course-hover-action" href="{{ route('admin.plugins.lms.courses.preview', $course) }}" aria-label="{{ __('lms::ui.auto_0f7c638a09') }}" title="{{ __('lms::ui.auto_31fde7b05a') }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                        </a>
                                                    @endif
                                                    @if ($status !== 'published')
                                                        <form method="POST" action="{{ route('admin.plugins.lms.courses.status', $course) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="published">
                                                            <button type="submit" class="lms-course-hover-action" aria-label="{{ __('lms::ui.auto_ba186145e2') }}" title="{{ __('lms::ui.auto_110a4b01be') }}">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($status !== 'draft')
                                                        <form method="POST" action="{{ route('admin.plugins.lms.courses.status', $course) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="draft">
                                                            <button type="submit" class="lms-course-hover-action" aria-label="{{ __('lms::ui.auto_ede5a80a24') }}" title="{{ __('lms::ui.auto_f03ab16cd5') }}">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.plugins.lms.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="lms-course-hover-action lms-course-hover-action-danger" aria-label="{{ __('lms::ui.auto_d29c5acc12') }}" title="{{ __('lms::ui.auto_f2a6c498fb') }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 13h10l1-13"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="lms-category-pill lms-category-{{ $tone }}">{{ $course->category ?: 'Uncategorized' }}</span>
                                    </td>
                                    <td>
                                        <strong class="lms-date-main">{{ $course->created_at?->translatedFormat('M d, Y') }}</strong>
                                        <small>{{ $course->created_at?->translatedFormat('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $studentCount }}</strong>
                                        <small>{{ __('lms::ui.auto_75d37c6cbf') }}</small>
                                    </td>
                                    <td>
                                        <div class="lms-instructor-cell">
                                            <div class="lms-avatar">{{ strtoupper(\Illuminate\Support\Str::substr($course->instructor_name ?: 'IN', 0, 1)) }}</div>
                                            <div>
                                                <strong>{{ $course->instructor_name ?: 'Unassigned' }}</strong>
                                                <small>{{ $course->instructor_title ?: __('lms::ui.admin_role_instructor') }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="lms-status-badge lms-status-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>@if($course->review_status === 'pending')<small class="lms-review-required">{{ __('lms::ui.auto_8c657bb4f2') }}</small>@endif</td>
                                    <td>
                                        <details class="lms-actions-menu" data-action-menu>
                                            <summary aria-label="{{ __('lms::ui.auto_d694737491') }}">
                                                <span aria-hidden="true">•••</span>
                                            </summary>
                                            <div class="lms-actions-menu-panel" role="menu">
                                                <a role="menuitem" href="{{ route('admin.plugins.lms.courses.edit', $course) }}">{{ __('lms::ui.auto_7dce122004') }}</a>
                                                @if ($status === 'published')
                                                    <a role="menuitem" href="{{ route('lms.front.courses.show', ['course' => $course->slug], false) }}">{{ __('lms::ui.auto_4351cfebe4') }}</a>
                                                @else
                                                    <a role="menuitem" href="{{ route('admin.plugins.lms.courses.preview', $course) }}">{{ __('lms::ui.auto_31fde7b05a') }}</a>
                                                @endif
                                                @if ($status !== 'published')
                                                    <form method="POST" action="{{ route('admin.plugins.lms.courses.status', $course) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="published">
                                                        <button type="submit" role="menuitem">{{ __('lms::ui.auto_110a4b01be') }}</button>
                                                    </form>
                                                @endif
                                                @if ($status !== 'draft')
                                                    <form method="POST" action="{{ route('admin.plugins.lms.courses.status', $course) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="draft">
                                                        <button type="submit" class="lms-course-menu-action" role="menuitem" style="background: transparent !important; background-image: none !important; box-shadow: none !important; color: #1f2a44 !important; -webkit-text-fill-color: #1f2a44 !important;">{{ __('lms::ui.auto_f03ab16cd5') }}</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('admin.plugins.lms.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="lms-danger-text lms-course-menu-action" role="menuitem" style="background: transparent !important; background-image: none !important; box-shadow: none !important; color: #1f2a44 !important; -webkit-text-fill-color: #1f2a44 !important;">{{ __('lms::ui.auto_f2a6c498fb') }}</button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="lms-empty-state">
                                            <strong>{{ __('lms::ui.auto_613df65060') }}</strong>
                                            <span>{{ __('lms::ui.auto_6632322f32') }}</span>
                                            <a href="{{ route('admin.plugins.lms.courses.create') }}" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_a5a0f5c174') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="lms-table-footer">
                    <label class="lms-per-page">
                        <span>{{ __('lms::ui.auto_498f79c4c5') }}</span>
                        <select class="lms-select" name="per_page" data-per-page>
                            @foreach ([10, 25, 50] as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                        <span>{{ __('lms::ui.auto_4f5d5f295d') }}</span>
                    </label>

                    <x-platform-pagination :paginator="$courses" />
                </div>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const page = document.querySelector('[data-lms-courses-page]');
            if (!page) return;

            const lmsSidebar = document.querySelector('.z4-admin-sidebar');
            const addCourseNavLink = lmsSidebar?.querySelector('a[href$="/admin/plugins/lms/courses/create"]');
            const allCoursesNavLink = lmsSidebar?.querySelector('a[href$="/admin/plugins/lms/courses"]');
            const isDraftCoursesView = @json($isDraftView);

            if (addCourseNavLink && !lmsSidebar.querySelector('[data-lms-draft-courses-link]')) {
                const draftCoursesNavLink = addCourseNavLink.cloneNode(true);
                draftCoursesNavLink.href = @json(route('admin.plugins.lms.courses', ['status' => 'draft']));
                draftCoursesNavLink.dataset.lmsDraftCoursesLink = 'true';
                draftCoursesNavLink.textContent = @json(__('lms::ui.admin_draft_courses'));

                if (isDraftCoursesView) {
                    allCoursesNavLink?.classList.remove('active');
                    allCoursesNavLink?.removeAttribute('aria-current');
                    draftCoursesNavLink.classList.add('active');
                    draftCoursesNavLink.setAttribute('aria-current', 'page');
                }

                addCourseNavLink.before(draftCoursesNavLink);
            }

            const selectAll = page.querySelector('[data-select-all]');
            const checkboxes = [...page.querySelectorAll('[data-row-checkbox]')];
            const bulkButton = page.querySelector('[data-bulk-apply]');
            const bulkSelect = page.querySelector('[name="bulk_action"]');

            const updateBulkState = () => {
                const checked = checkboxes.filter((box) => box.checked).length;
                if (bulkButton) bulkButton.disabled = checked === 0 || !bulkSelect?.value;
                if (selectAll) {
                    selectAll.checked = checked > 0 && checked === checkboxes.length;
                    selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
                }
            };

            selectAll?.addEventListener('change', () => {
                checkboxes.forEach((box) => { box.checked = selectAll.checked; });
                updateBulkState();
            });
            checkboxes.forEach((box) => box.addEventListener('change', updateBulkState));
            bulkSelect?.addEventListener('change', updateBulkState);

            page.querySelector('[data-bulk-submit]')?.addEventListener('submit', (event) => {
                if (!bulkSelect?.value || checkboxes.every((box) => !box.checked)) {
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
