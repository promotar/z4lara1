<x-app-layout>
    <style>
        body .lms-student-delete-modal[hidden] { display: none !important; }
        body .lms-student-delete-modal:not([hidden]) { align-items: center !important; background: rgba(23, 32, 51, .52) !important; display: flex !important; inset: 0 !important; justify-content: center !important; padding: 24px !important; position: fixed !important; z-index: 99999 !important; }
        body .lms-student-delete-modal .lms-student-delete-modal-panel { background: #fff !important; border: 1px solid #eadfdb !important; border-radius: 18px !important; box-shadow: 0 24px 70px rgba(40, 18, 14, .28) !important; max-width: 460px !important; padding: 30px !important; text-align: center !important; width: min(460px, 100%) !important; }
        body .lms-student-delete-modal .lms-student-delete-modal-icon { align-items: center; background: #fff0ee; border-radius: 50%; color: #b42318; display: inline-flex; font-size: 21px; font-weight: 800; height: 46px; justify-content: center; margin-bottom: 14px; width: 46px; }
        body .lms-student-delete-modal h2 { color: var(--ainpa-admin-text, #172033); font-size: 23px; margin: 0 0 10px; }
        body .lms-student-delete-modal p { color: #687386; font-size: 14px; line-height: 1.6; margin: 0; }
        body .lms-student-delete-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 26px; }
        body .lms-student-delete-modal .lms-btn-danger { background: #b42318 !important; border-color: #b42318 !important; color: #fff !important; }
        @media (max-width: 560px) { body .lms-student-delete-modal:not([hidden]) { padding: 16px !important; } body .lms-student-delete-modal .lms-student-delete-modal-panel { padding: 24px 20px !important; } body .lms-student-delete-modal-actions { flex-direction: column-reverse; } body .lms-student-delete-modal-actions .lms-btn { width: 100%; } }
        body .lms-student-bulk-bar:not([hidden]) { align-items: center !important; display: flex !important; gap: 16px !important; justify-content: space-between !important; padding: 16px 18px !important; }
        body .lms-student-bulk-actions { align-items: center !important; display: flex !important; gap: 8px !important; }
        body .lms-student-bulk-select { background: #fff !important; border: 1px solid #e4d9d5 !important; border-radius: 9px !important; color: var(--ainpa-admin-text, #172033) !important; font: inherit !important; min-height: 40px !important; min-width: 175px !important; padding: 0 34px 0 12px !important; }
        body .lms-student-bulk-apply { align-items: center !important; background: var(--ainpa-admin-accent, #9a0000) !important; border: 1px solid var(--ainpa-admin-accent, #9a0000) !important; border-radius: 9px !important; color: #fff !important; cursor: pointer !important; display: inline-flex !important; font: inherit !important; font-weight: 700 !important; gap: 7px !important; height: 40px !important; justify-content: center !important; padding: 0 14px !important; }
        body .lms-student-bulk-apply svg { fill: none !important; height: 18px !important; stroke: currentColor !important; stroke-linecap: round !important; stroke-linejoin: round !important; stroke-width: 2.5 !important; width: 18px !important; }
        body .lms-student-bulk-apply:disabled { background: #f2efed !important; border-color: #e2dbd8 !important; color: #9b918e !important; cursor: not-allowed !important; }
        body .lms-student-bulk-cancel[hidden] { display: none !important; }
        body .lms-student-bulk-cancel { background: #fff !important; border: 1px solid #d9c9c5 !important; border-radius: 9px !important; color: #6b514c !important; cursor: pointer !important; font: inherit !important; font-weight: 700 !important; height: 40px !important; padding: 0 14px !important; }
        body .lms-student-bulk-cancel:hover, body .lms-student-bulk-cancel:focus-visible { background: color-mix(in srgb, var(--ainpa-admin-accent, #fff4f2) 8%, #fff) !important; border-color: var(--ainpa-admin-accent, #9a0000) !important; color: color-mix(in srgb, var(--ainpa-admin-accent, #790000) 78%, #000) !important; }
        @media (max-width: 680px) { body .lms-student-bulk-bar:not([hidden]) { align-items: stretch !important; flex-direction: column !important; } body .lms-student-bulk-actions { width: 100% !important; } body .lms-student-bulk-select { flex: 1; min-width: 0 !important; } }
        body .lms-people-table tbody tr:last-child .lms-actions-menu > div { bottom: 42px !important; top: auto !important; }
        body .lms-actions-menu > div > a, body .lms-actions-menu > div > form, body .lms-actions-menu > div > button { margin-bottom: 8px !important; }
        body .lms-actions-menu > div > :last-child { margin-bottom: 0 !important; }
        body .lms-people-table-card { background: transparent !important; border: 0 !important; box-shadow: none !important; }
        html body:has(.z4-admin-sidebar) main table.lms-people-table, html body:has(.dashboard-sidebar) main table.lms-people-table, html body:has(.ainpa-sidebar) main table.lms-people-table { background: transparent !important; }
        html body:has(.z4-admin-sidebar) main .lms-people-table tbody tr, html body:has(.dashboard-sidebar) main .lms-people-table tbody tr, html body:has(.ainpa-sidebar) main .lms-people-table tbody tr { background: transparent !important; }
        body .lms-people-table th:first-child { border-top-left-radius: 18px !important; }
        body .lms-people-table th:last-child { border-top-right-radius: 18px !important; }
        body .lms-people-table tbody tr:last-child td:first-child { border-bottom-left-radius: 18px !important; }
        body .lms-people-table tbody tr:last-child td:last-child { border-bottom-right-radius: 18px !important; }
        body .lms-status-stack { align-items: flex-start; display: inline-flex; flex-direction: column; gap: 5px; }
        body .lms-invitation-pending { color: #9a6300; font-size: 11px; font-weight: 700; }
        body .lms-password-field { display: block; position: relative; }
        body .lms-password-field .lms-input { padding-right: 46px !important; }
        body .lms-password-toggle { align-items: center; background: transparent; border: 0; color: #687386; cursor: pointer; display: inline-flex; height: 38px; justify-content: center; padding: 0; position: absolute; right: 7px; top: 50%; transform: translateY(-50%); width: 38px; }
        body .lms-password-toggle:hover, body .lms-password-toggle:focus-visible { color: var(--ainpa-admin-accent, #9a0000); outline: none; transform: translateY(-50%) !important; }
        body .lms-password-toggle svg { fill: none; height: 20px; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2; width: 20px; }
        body .lms-password-toggle .lms-eye-slash { display: none; }
        body .lms-password-toggle[aria-pressed="true"] .lms-eye-slash { display: block; }
        body .lms-student-name-link { color: inherit !important; text-decoration: none !important; border-bottom: 1px dashed transparent !important; }
        body .lms-student-name-link:hover, body .lms-student-name-link:focus-visible { color: var(--ainpa-admin-accent, #9a0000) !important; border-bottom-color: currentColor !important; text-decoration: none !important; }
    </style>
    <div class="lms-course-shell lms-people-page" data-lms-people-page>
        <div class="lms-course-container">
            <header class="lms-people-header"><div><span class="lms-eyebrow">{{ __('lms::ui.admin_people_eyebrow') }}</span><h1>{{ __('lms::ui.auto_aba064f896') }}</h1><p>{{ __('lms::ui.auto_5dcbd81af0') }}</p></div><a href="{{ route('admin.plugins.lms.students.create') }}" class="lms-btn lms-btn-primary"><span>+</span> {{ __('lms::ui.auto_62a88a4ad9') }}</a></header>
            @if(session('status'))<div class="lms-alert lms-alert-success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>@endif

            <form method="GET" class="lms-people-filters" aria-label="{{ __('lms::ui.auto_c4f21cf486') }}"><label class="lms-search-field"><span>&#8981;</span><input name="search" value="{{ request('search') }}" placeholder="{{ __('lms::ui.auto_4d9ecbb184') }}"></label><select name="course_id" class="lms-select" aria-label="{{ __('lms::ui.auto_3730c98664') }}"><option value="">{{ __('lms::ui.auto_96eb5855af') }}</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string) request('course_id') === (string) $course->id)>{{ $course->title }}</option>@endforeach</select><select name="status" class="lms-select" aria-label="{{ __('lms::ui.auto_ec53a8c4f0') }}"><option value="">{{ __('lms::ui.auto_a775fc840b') }}</option><option value="pending" @selected(request('status') === 'pending')>{{ __('lms::ui.auto_2d13df6f8b') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('lms::ui.auto_4d3d769b81') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('lms::ui.auto_3cab03c00d') }}</option></select><button class="lms-btn lms-btn-secondary">{{ __('lms::ui.auto_bf73617f18') }}</button>@if(request()->hasAny(['search', 'course_id', 'status']))<a href="{{ route('admin.plugins.lms.students') }}" class="lms-text-link">{{ __('lms::ui.auto_526d688f37') }}</a>@endif</form>

            <form id="studentBulkForm" method="POST" action="{{ route('admin.plugins.lms.students.bulk') }}">@csrf<input type="hidden" name="bulk_action" data-student-bulk-action></form>
            <section class="lms-student-bulk-bar" data-student-bulk-bar hidden aria-live="polite"><div><strong data-student-bulk-count>{{ __('lms::ui.auto_d89d1b8adf') }}</strong><span>{{ __('lms::ui.auto_0d18d35d2c') }}</span></div><div class="lms-student-bulk-actions"><select class="lms-student-bulk-select" data-student-bulk-select aria-label="{{ __('lms::ui.auto_d2b9cbf9a1') }}"><option value="">{{ __('lms::ui.auto_df62326d15') }}</option><option value="activate">{{ __('lms::ui.auto_a13367a8e2') }}</option><option value="deactivate">{{ __('lms::ui.auto_109fec0682') }}</option><option value="delete">{{ __('lms::ui.auto_f2a6c498fb') }}</option></select><button type="button" class="lms-student-bulk-cancel" data-student-bulk-cancel hidden>{{ __('lms::ui.auto_ea4788705e') }}</button><button type="button" class="lms-student-bulk-apply" data-student-bulk-apply aria-label="{{ __('lms::ui.auto_ee0b046b86') }}" title="{{ __('lms::ui.auto_ee0b046b86') }}" disabled><span>{{ __('lms::ui.auto_9639e32cab') }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg></button></div></section>

            <section class="lms-people-table-card"><div class="lms-people-loading" hidden aria-live="polite"><span></span> {{ __('lms::ui.auto_048e26df02') }}</div><div class="lms-table-scroll"><table class="lms-people-table"><thead><tr><th class="lms-select-column"><input type="checkbox" data-student-select-all aria-label="{{ __('lms::ui.auto_22eb40541d') }}"></th><th>{{ __('lms::ui.auto_f5c0a1c938') }}</th><th>{{ __('lms::ui.auto_f1f0a2b7d6') }}</th><th>{{ __('lms::ui.auto_46b5f8c58b') }}</th><th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th><th>{{ __('lms::ui.auto_97ce2ccb5d') }}</th><th><span class="sr-only">{{ __('lms::ui.auto_06df33001c') }}</span></th></tr></thead><tbody>
                @forelse($students as $student)
                    @php($avatar = $student->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->avatar_path) : null)
                    <tr>
                        <td class="lms-select-column" data-label="Select"><input form="studentBulkForm" type="checkbox" name="student_ids[]" value="{{ $student->id }}" data-student-row-checkbox aria-label="{{ __('lms::ui.auto_e2e0e8977c') }}"></td>
                        <td data-label="Student"><div class="lms-person"><span class="lms-person-avatar">@if($avatar)<img src="{{ $avatar }}" alt="">@else{{ strtoupper(\Illuminate\Support\Str::substr($student->name, 0, 1)) }}@endif</span><span><strong><a class="lms-student-name-link" href="{{ route('admin.plugins.lms.students.courses', $student) }}" title="{{ __('lms::ui.auto_8858553aa8') }}">{{ $student->name }}</a></strong><small>{{ $student->email }}</small></span></div></td>
                        <td data-label="Enrolled Courses"><div class="lms-course-title-list">@forelse($student->enrolled_course_titles as $courseTitle)<span>{{ $courseTitle }}</span>@empty<span class="is-muted">{{ __('lms::ui.auto_36b165817b') }}</span>@endforelse<small>{{ __('lms::ui.auto_95b8fd9b17') }}</small></div></td>
                        <td data-label="Progress"><div class="lms-progress-cell"><span><i style="width:{{ $student->progress_percent }}%"></i></span><b>{{ $student->progress_percent }}%</b></div></td>
                        <td data-label="Status"><span class="lms-status-stack"><span class="lms-state lms-state-{{ $student->status }}"><i></i>{{ ucfirst($student->status) }}</span>@if($student->invitation_pending)<small class="lms-invitation-pending">{{ __('lms::ui.auto_bdc7ef0d4b') }}</small>@endif</span></td>
                        <td data-label="Registration Date">{{ $student->registered_at?->translatedFormat('d M Y') ?: '&mdash;' }}</td>
                        <td class="lms-row-actions"><details class="lms-actions-menu"><summary aria-label="{{ __('lms::ui.auto_34f7ca50ae') }}">&bull;&bull;&bull;</summary><div>@if($student->status === 'pending')<a href="{{ route('admin.plugins.lms.students.create') }}">{{ __('lms::ui.auto_41446ec462') }}</a><form method="POST" action="{{ route('admin.plugins.lms.students.status', $student) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="active"><button>{{ __('lms::ui.auto_a13367a8e2') }}</button></form>@else<a href="{{ route('admin.plugins.lms.students.show', $student) }}">{{ __('lms::ui.auto_87e25e2a18') }}</a><a href="{{ route('admin.plugins.lms.students.courses', $student) }}">View Courses &amp; Progress</a>@if($student->invitation_pending)<button type="button" data-student-temporary-password data-temporary-password-url="{{ route('admin.plugins.lms.students.temporary-password', $student) }}" data-student-name="{{ $student->name }}">{{ __('lms::ui.auto_e7130e32a5') }}</button>@endif<form method="POST" action="{{ route('admin.plugins.lms.students.status', $student) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $student->status === 'active' ? 'inactive' : 'active' }}"><button>{{ $student->status === 'active' ? 'Deactivate' : 'Activate' }}</button></form>@endif<button type="button" class="lms-danger-text" data-student-delete data-student-name="{{ $student->name }}" data-delete-url="{{ route('admin.plugins.lms.students.destroy', $student) }}">{{ __('lms::ui.auto_5ebd3f5948') }}</button></div></details></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="lms-empty-state"><span class="lms-empty-icon">S</span><strong>{{ __('lms::ui.auto_e35e43238d') }}</strong><p>{{ __('lms::ui.auto_58c0ef280d') }}</p><a href="{{ route('admin.plugins.lms.students.create') }}" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_62a88a4ad9') }}</a></div></td></tr>
                @endforelse
            </tbody></table></div>
                <x-platform-pagination :paginator="$students" page-name="page" />
            </section>
        </div>
    </div>

    <div class="lms-student-delete-modal" data-student-delete-modal hidden aria-hidden="true"><div class="lms-student-delete-modal-panel" role="dialog" aria-modal="true" aria-labelledby="student-delete-title" aria-describedby="student-delete-description"><div class="lms-student-delete-modal-icon" aria-hidden="true">!</div><h2 id="student-delete-title">{{ __('lms::ui.auto_ac71addb0d') }}</h2><p id="student-delete-description">{{ __('lms::ui.auto_44ba543ffc') }} <strong data-student-delete-name></strong>{{ __('lms::ui.auto_49c1964d88') }}</p><form method="POST" data-student-delete-form>@csrf @method('DELETE')<div class="lms-student-delete-modal-actions"><button type="button" class="lms-btn lms-btn-ghost" data-student-delete-cancel>{{ __('lms::ui.auto_ea4788705e') }}</button><button type="submit" class="lms-btn lms-btn-danger">{{ __('lms::ui.auto_5ebd3f5948') }}</button></div></form></div></div>
    <div class="lms-student-delete-modal" data-student-temporary-password-modal hidden aria-hidden="true"><div class="lms-student-delete-modal-panel" role="dialog" aria-modal="true" aria-labelledby="student-temporary-password-title" aria-describedby="student-temporary-password-description"><div class="lms-student-delete-modal-icon" aria-hidden="true">&#128273;</div><h2 id="student-temporary-password-title">{{ __('lms::ui.auto_e7130e32a5') }}</h2><p id="student-temporary-password-description">{{ __('lms::ui.auto_da856e8941') }} <strong data-student-temporary-password-name></strong>{{ __('lms::ui.auto_fb65b48168') }}</p><form method="POST" data-student-temporary-password-form>@csrf<div class="lms-form-grid" style="margin-top:22px;text-align:left"><label><span>{{ __('lms::ui.auto_6f8a3ff480') }}</span><span class="lms-password-field"><input class="lms-input" type="password" name="password" minlength="8" autocomplete="new-password" required data-student-temporary-password-input><button type="button" class="lms-password-toggle" data-password-visibility aria-label="{{ __('lms::ui.auto_a1cc7ba89c') }}" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/><path class="lms-eye-slash" d="m4 4 16 16"/></svg></button></span></label><label><span>{{ __('lms::ui.auto_4c231e0da3') }}</span><span class="lms-password-field"><input class="lms-input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required><button type="button" class="lms-password-toggle" data-password-visibility aria-label="{{ __('lms::ui.auto_a1cc7ba89c') }}" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/><path class="lms-eye-slash" d="m4 4 16 16"/></svg></button></span></label></div><div class="lms-student-delete-modal-actions"><button type="button" class="lms-btn lms-btn-ghost" data-student-temporary-password-cancel>{{ __('lms::ui.auto_ea4788705e') }}</button><button type="submit" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_e18fb05f11') }}</button></div></form></div></div>
    <div class="lms-student-delete-modal" data-student-bulk-delete-modal hidden aria-hidden="true"><div class="lms-student-delete-modal-panel" role="dialog" aria-modal="true" aria-labelledby="students-bulk-delete-title" aria-describedby="students-bulk-delete-description"><div class="lms-student-delete-modal-icon" aria-hidden="true">!</div><h2 id="students-bulk-delete-title">{{ __('lms::ui.auto_ba4609b3cb') }}</h2><p id="students-bulk-delete-description">{{ __('lms::ui.auto_44ba543ffc') }} <strong data-student-bulk-delete-count></strong>{{ __('lms::ui.auto_d84ba13a9d') }}</p><div class="lms-student-delete-modal-actions"><button type="button" class="lms-btn lms-btn-ghost" data-student-bulk-delete-cancel>{{ __('lms::ui.auto_ea4788705e') }}</button><button type="button" class="lms-btn lms-btn-danger" data-student-bulk-delete-confirm>{{ __('lms::ui.auto_c13af79d63') }}</button></div></div></div>

    <script>
        (() => {
            const deleteModal = document.querySelector('[data-student-delete-modal]');
            const deleteForm = document.querySelector('[data-student-delete-form]');
            const deleteName = document.querySelector('[data-student-delete-name]');
            const deleteCancel = document.querySelector('[data-student-delete-cancel]');
            const deleteTriggers = document.querySelectorAll('[data-student-delete]');
            const temporaryPasswordModal = document.querySelector('[data-student-temporary-password-modal]');
            const temporaryPasswordForm = document.querySelector('[data-student-temporary-password-form]');
            const temporaryPasswordName = document.querySelector('[data-student-temporary-password-name]');
            const temporaryPasswordInput = document.querySelector('[data-student-temporary-password-input]');
            const temporaryPasswordCancel = document.querySelector('[data-student-temporary-password-cancel]');
            const temporaryPasswordTriggers = document.querySelectorAll('[data-student-temporary-password]');
            const bulkForm = document.querySelector('#studentBulkForm');
            const bulkAction = document.querySelector('[data-student-bulk-action]');
            const bulkBar = document.querySelector('[data-student-bulk-bar]');
            const bulkCount = document.querySelector('[data-student-bulk-count]');
            const selectAll = document.querySelector('[data-student-select-all]');
            const rowCheckboxes = document.querySelectorAll('[data-student-row-checkbox]');
            const bulkActionSelect = document.querySelector('[data-student-bulk-select]');
            const bulkActionCancel = document.querySelector('[data-student-bulk-cancel]');
            const bulkApply = document.querySelector('[data-student-bulk-apply]');
            const bulkDeleteModal = document.querySelector('[data-student-bulk-delete-modal]');
            const bulkDeleteCancel = document.querySelector('[data-student-bulk-delete-cancel]');
            const bulkDeleteConfirm = document.querySelector('[data-student-bulk-delete-confirm]');
            const bulkDeleteCount = document.querySelector('[data-student-bulk-delete-count]');
            const actionMenus = document.querySelectorAll('.lms-actions-menu');
            let focusedElement;

            const openModal = (modal, focusTarget) => { modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('lms-modal-open'); focusTarget?.focus(); };
            const closeModal = (modal) => { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); if (deleteModal?.hidden && temporaryPasswordModal?.hidden && bulkDeleteModal?.hidden) document.body.classList.remove('lms-modal-open'); focusedElement?.focus(); };

            if (deleteModal && deleteForm && deleteName && deleteCancel) {
                deleteTriggers.forEach((trigger) => trigger.addEventListener('click', () => { focusedElement = trigger; deleteForm.action = trigger.dataset.deleteUrl; deleteName.textContent = trigger.dataset.studentName; openModal(deleteModal, deleteCancel); }));
                deleteCancel.addEventListener('click', () => closeModal(deleteModal));
                deleteModal.addEventListener('click', (event) => { if (event.target === deleteModal) closeModal(deleteModal); });
            }

            if (temporaryPasswordModal && temporaryPasswordForm && temporaryPasswordName && temporaryPasswordCancel) {
                temporaryPasswordTriggers.forEach((trigger) => trigger.addEventListener('click', () => { focusedElement = trigger; temporaryPasswordForm.action = trigger.dataset.temporaryPasswordUrl; temporaryPasswordName.textContent = trigger.dataset.studentName; temporaryPasswordForm.reset(); openModal(temporaryPasswordModal, temporaryPasswordInput); }));
                temporaryPasswordCancel.addEventListener('click', () => closeModal(temporaryPasswordModal));
                temporaryPasswordModal.addEventListener('click', (event) => { if (event.target === temporaryPasswordModal) closeModal(temporaryPasswordModal); });
            }
            document.querySelectorAll('[data-password-visibility]').forEach((toggle) => toggle.addEventListener('click', () => { const input = toggle.closest('.lms-password-field')?.querySelector('input'); if (!input) return; const showing = input.type === 'password'; input.type = showing ? 'text' : 'password'; toggle.setAttribute('aria-pressed', showing ? 'true' : 'false'); toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password'); }));

            const updateBulkState = () => {
                const selected = Array.from(rowCheckboxes).filter((checkbox) => checkbox.checked);
                const total = rowCheckboxes.length;
                bulkBar.hidden = selected.length === 0;
                bulkCount.textContent = `${selected.length} student${selected.length === 1 ? '' : 's'} selected`;
                if (selectAll) { selectAll.checked = total > 0 && selected.length === total; selectAll.indeterminate = selected.length > 0 && selected.length < total; }
                if (bulkApply) bulkApply.disabled = selected.length === 0 || !bulkActionSelect?.value;
                if (bulkActionCancel) bulkActionCancel.hidden = !bulkActionSelect?.value;
            };

            selectAll?.addEventListener('change', () => { rowCheckboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; }); updateBulkState(); });
            rowCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateBulkState));
            bulkActionSelect?.addEventListener('change', updateBulkState);
            bulkActionCancel?.addEventListener('click', () => { bulkActionSelect.value = ''; rowCheckboxes.forEach((checkbox) => { checkbox.checked = false; }); updateBulkState(); });
            bulkApply?.addEventListener('click', () => { const action = bulkActionSelect?.value; if (!action) return; if (action === 'delete') { const selectedCount = Array.from(rowCheckboxes).filter((checkbox) => checkbox.checked).length; bulkDeleteCount.textContent = `${selectedCount} selected student${selectedCount === 1 ? '' : 's'}`; focusedElement = bulkApply; openModal(bulkDeleteModal, bulkDeleteCancel); return; } bulkAction.value = action; bulkForm.requestSubmit(); });
            bulkDeleteCancel?.addEventListener('click', () => { if (bulkActionSelect?.value === 'delete') bulkActionSelect.value = ''; updateBulkState(); closeModal(bulkDeleteModal); });
            bulkDeleteModal?.addEventListener('click', (event) => { if (event.target === bulkDeleteModal) closeModal(bulkDeleteModal); });
            bulkDeleteConfirm?.addEventListener('click', () => { bulkAction.value = 'delete'; bulkForm.requestSubmit(); });
            actionMenus.forEach((menu) => menu.addEventListener('toggle', () => { if (menu.open) actionMenus.forEach((otherMenu) => { if (otherMenu !== menu) otherMenu.open = false; }); }));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { if (deleteModal && !deleteModal.hidden) closeModal(deleteModal); if (temporaryPasswordModal && !temporaryPasswordModal.hidden) closeModal(temporaryPasswordModal); if (bulkDeleteModal && !bulkDeleteModal.hidden) closeModal(bulkDeleteModal); actionMenus.forEach((menu) => { menu.open = false; }); } });
            updateBulkState();
        })();
    </script>
</x-app-layout>
