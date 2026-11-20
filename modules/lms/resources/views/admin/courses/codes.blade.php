@php
    $statusLabels = [
        'available' => 'Available',
        'used' => 'Used',
        'void' => 'Voided',
    ];
    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<x-app-layout>
    <div class="lms-course-shell lms-codes-dashboard" data-lms-codes-page>
        <div class="lms-course-container">
            <div class="lms-dashboard-heading">
                <div>
                    <a class="lms-back-link" href="{{ route('admin.plugins.lms.courses') }}">{{ __('lms::ui.auto_aebc0de88e') }}</a>
                    <h1>{{ __('lms::ui.auto_32d94da553') }}</h1>
                    <p>{{ __('lms::ui.auto_6e5e5f4894') }}</p>
                </div>
                <a href="{{ route('admin.plugins.lms.courses.create') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_a5a0f5c174') }}</a>
            </div>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            @if ($viewErrors->any())
                <div class="lms-alert lms-alert-error">{{ $viewErrors->first() }}</div>
            @endif

            <section class="lms-stat-grid" aria-label="{{ __('lms::ui.auto_6ab6d4f512') }}">
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-purple">#</div>
                    <div>
                        <span>{{ __('lms::ui.auto_b8305c5b53') }}</span>
                        <strong>{{ number_format($stats['total']) }}</strong>
                        <small>{{ __('lms::ui.auto_e2fa538867') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-green">✓</div>
                    <div>
                        <span>{{ __('lms::ui.auto_ed52461091') }}</span>
                        <strong>{{ number_format($stats['available']) }}</strong>
                        <small>{{ __('lms::ui.auto_b2fdab230a') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-orange">↳</div>
                    <div>
                        <span>{{ __('lms::ui.auto_e140ca4de9') }}</span>
                        <strong>{{ number_format($stats['used']) }}</strong>
                        <small>{{ __('lms::ui.auto_2fdf1d34fe') }}</small>
                    </div>
                </article>
                <article class="lms-stat-card">
                    <div class="lms-stat-icon lms-stat-blue">U</div>
                    <div>
                        <span>{{ __('lms::ui.auto_0bd23aac7b') }}</span>
                        <strong>{{ number_format($stats['enrollments']) }}</strong>
                        <small>{{ __('lms::ui.auto_426223c6ea') }}</small>
                    </div>
                </article>
            </section>

            <section class="lms-code-generator-card">
                <form method="POST" action="{{ route('admin.plugins.lms.courses.codes.store') }}" class="lms-code-generator-form">
                    @csrf
                    <label>
                        <span>{{ __('lms::ui.auto_62851e02bb') }}</span>
                        <select class="lms-select" name="course_id" required>
                            <option value="">{{ __('lms::ui.auto_96427a1d2d') }}</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected((int) old('course_id', $selectedCourseId) === (int) $course->id)>
                                    {{ $course->title }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>{{ __('lms::ui.auto_a4560ca70f') }}</span>
                        <input class="lms-input" name="quantity" type="number" min="1" max="5000" value="{{ old('quantity', 10) }}" required>
                    </label>
                    <button type="submit" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_6ff087069e') }}</button>
                </form>
            </section>

            <form method="GET" action="{{ route('admin.plugins.lms.courses.codes') }}" class="lms-filter-card lms-code-filter-card">
                <label>
                    <span>{{ __('lms::ui.auto_3730c98664') }}</span>
                    <select class="lms-select" name="course_id">
                        <option value="">{{ __('lms::ui.auto_96eb5855af') }}</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((int) $selectedCourseId === (int) $course->id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="lms-filter-actions">
                    <button type="submit" class="lms-btn lms-btn-primary">{{ __('lms::ui.auto_d7778d0c64') }}</button>
                    <a href="{{ route('admin.plugins.lms.courses.codes') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_526d688f37') }}</a>
                </div>
            </form>

            <section class="lms-table-card">
                <div class="lms-table-toolbar">
                    <strong>{{ __('lms::ui.auto_32d94da553') }}</strong>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span data-codes-selection hidden style="font-size:12px;font-weight:700;color:#8b0000;background:#fff1ef;border:1px solid #f0d5d2;border-radius:999px;padding:3px 10px">0</span>
                        <a href="{{ route('admin.plugins.lms.courses.codes.export', array_filter(['course_id' => $selectedCourseId ?: null])) }}"
                           data-codes-export
                           data-export-base="{{ route('admin.plugins.lms.courses.codes.export') }}"
                           data-export-course="{{ $selectedCourseId ?: '' }}"
                           class="lms-btn lms-btn-ghost" style="display:none;align-items:center;gap:6px">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> {{ __('lms::ui.export_codes') }}
                        </a>
                    </div>
                </div>

                <div class="lms-table-scroll">
                    <table class="lms-dashboard-table lms-codes-table">
                        <thead>
                            <tr>
                                <th style="width:38px"><input type="checkbox" data-codes-all aria-label="{{ __('lms::ui.select_all') }}"></th>
                                <th>{{ __('lms::ui.auto_ca0dbad92a') }}</th>
                                <th>{{ __('lms::ui.auto_3730c98664') }}</th>
                                <th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th>
                                <th>{{ __('lms::ui.auto_ac559bde00') }}</th>
                                <th>{{ __('lms::ui.auto_a9f06e4ecd') }}</th>
                                <th>{{ __('lms::ui.auto_0eceeb4586') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($codes as $code)
                                <tr>
                                    <td><input type="checkbox" data-code-select value="{{ $code->id }}" aria-label="{{ $code->code }}"></td>
                                    <td>
                                        <button type="button" class="lms-code-copy" data-copy-code="{{ $code->code }}" title="{{ __('lms::ui.auto_7bbbb4dbf1') }}">
                                            {{ $code->code }}
                                        </button>
                                    </td>
                                    <td>
                                        <strong>{{ $code->course?->title ?: 'Deleted course' }}</strong>
                                        <small>{{ __('lms::ui.auto_63fdc39981') }}</small>
                                    </td>
                                    <td><span class="lms-status-badge lms-code-status-{{ $code->status }}">{{ $statusLabels[$code->status] ?? ucfirst($code->status) }}</span></td>
                                    <td>
    @if ($code->used_by)
        @php($linkedUser = \App\Models\User::query()->find($code->used_by))
        <a class="lms-user-link" href="{{ route('admin.users.index', ['q' => $linkedUser?->email ?? (string) $code->used_by]) }}" target="_blank" rel="noopener" title="{{ $linkedUser ? $linkedUser->name.' — '.$linkedUser->email : '' }}">#USER-{{ $code->used_by }}</a>
    @else
        —
    @endif
</td>
                                    <td>{{ $code->used_at?->translatedFormat('M d, Y h:i A') ?: '—' }}</td>
                                    <td>{{ $code->created_at?->translatedFormat('M d, Y h:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="lms-empty-state">
                                            <strong>{{ __('lms::ui.auto_4ccfb5450e') }}</strong>
                                            <span>{{ __('lms::ui.auto_99e0ddd350') }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="lms-table-footer">
                    <x-platform-pagination :paginator="$codes" />
                </div>
            </section>

            @if ($canManageIssuers)
                <section class="lms-card" data-code-issuers-panel style="margin-top:18px">
                    <div class="lms-card-heading-row">
                        <div>
                            <h2>{{ __('lms::ui.code_issuers') }}</h2>
                            <p>{{ __('lms::ui.code_issuers_hint') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.plugins.lms.courses.codes.issuers.store') }}" data-issuer-form style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
                        @csrf
                        <input type="hidden" name="user_id" value="" data-issuer-user-id>
                        <label style="flex:1 1 260px;min-width:220px;position:relative">
                            <span>{{ __('lms::ui.auto_b2dea6f867') }}</span>
                            <input class="lms-input" type="search" autocomplete="off"
                                   placeholder="{{ __('lms::ui.auto_cd6a675740') }}"
                                   data-issuer-search
                                   data-endpoint="{{ route('admin.plugins.lms.courses.codes.issuers.search', [], false) }}">
                            <div class="lms-user-picker-results" data-issuer-results hidden
                                 style="position:absolute;z-index:20;inset-inline:0;top:100%;margin-top:4px;background:#fff;border:1px solid #e5d5d5;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.08);max-height:240px;overflow:auto"></div>
                        </label>
                        <button type="submit" class="lms-btn lms-btn-primary">{{ __('lms::ui.grant_access') }}</button>
                    </form>

                    <table class="lms-dashboard-table" style="width:100%;table-layout:auto">
                            <thead>
                                <tr>
                                    <th style="width:42%">{{ __('lms::ui.issuer_name') }}</th>
                                    <th style="width:43%">{{ __('lms::ui.issuer_email') }}</th>
                                    <th style="width:15%;text-align:end">{{ __('lms::ui.issuer_action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($codeIssuers as $issuer)
                                    <tr>
                                        <td><strong>{{ $issuer->name }}</strong></td>
                                        <td>{{ $issuer->email }}</td>
                                        <td style="text-align:end">
                                            <form method="POST" action="{{ route('admin.plugins.lms.courses.codes.issuers.destroy', $issuer) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="lms-btn lms-btn-ghost" style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;width:auto;min-height:34px;padding:6px 12px;font-size:12px;font-weight:700;line-height:1;color:#b91c1c;border:1px solid #f3c4c4;background:#fff;border-radius:8px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> {{ __('lms::ui.revoke_access') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3"><div class="lms-empty-state">{{ __('lms::ui.code_issuers_empty') }}</div></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                </section>
            @endif

        </div>
    </div>

    <script>
        const copyCodeToClipboard = async (code) => {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(code);
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = code;
            textarea.setAttribute('readonly', '');
            textarea.style.cssText = 'left:-9999px;position:fixed;top:-9999px;';
            document.body.append(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();

            if (!copied) throw new Error('Copy failed');
        };

        document.querySelectorAll('[data-copy-code]').forEach((button) => {
            button.addEventListener('click', async () => {
                const code = button.dataset.copyCode || '';
                const originalLabel = button.textContent.trim();
                try {
                    await copyCodeToClipboard(code);
                    button.classList.add('is-copied');
                    button.textContent = 'Copied';
                    setTimeout(() => {
                        button.classList.remove('is-copied');
                        button.textContent = originalLabel;
                    }, 1200);
                } catch (error) {
                    button.textContent = 'Copy failed';
                    setTimeout(() => { button.textContent = originalLabel; }, 1200);
                }
            });
        });
    </script>
    <script>
        // codesSelectionInit: تحديد فردي/جماعي + تصدير المحدد
        (() => {
            const page = document.querySelector('[data-lms-codes-page]');
            if (!page) return;

            const allBox = page.querySelector('[data-codes-all]');
            const rowBoxes = () => Array.from(page.querySelectorAll('[data-code-select]'));
            const exportLink = page.querySelector('[data-codes-export]');
            const counter = page.querySelector('[data-codes-selection]');
            const base = exportLink?.dataset.exportBase || '';
            const courseId = exportLink?.dataset.exportCourse || '';

            const sync = () => {
                const boxes = rowBoxes();
                const ids = boxes.filter((box) => box.checked).map((box) => box.value);

                if (allBox) {
                    allBox.checked = ids.length > 0 && ids.length === boxes.length;
                    allBox.indeterminate = ids.length > 0 && ids.length < boxes.length;
                }

                if (counter) {
                    counter.hidden = ids.length === 0;
                    counter.textContent = ids.length;
                }

                if (exportLink) {
                    exportLink.style.display = ids.length ? 'inline-flex' : 'none';

                    const url = new URL(base, window.location.origin);
                    if (courseId) url.searchParams.set('course_id', courseId);
                    if (ids.length) url.searchParams.set('ids', ids.join(','));
                    else url.searchParams.delete('ids');
                    exportLink.setAttribute('href', url.toString());
                }
            };

            allBox?.addEventListener('change', () => {
                rowBoxes().forEach((box) => { box.checked = allBox.checked; });
                sync();
            });

            rowBoxes().forEach((box) => box.addEventListener('change', sync));
            sync();
        })();
    </script>
    <script>
        // codeIssuersSearch: بحث ومنح صلاحية إصدار الأكواد
        (() => {
            const form = document.querySelector('[data-issuer-form]');
            if (!form) return;

            const input = form.querySelector('[data-issuer-search]');
            const hidden = form.querySelector('[data-issuer-user-id]');
            const results = document.querySelector('[data-issuer-results]');
            const endpoint = input.dataset.endpoint;
            let timer = null;

            const hide = () => { results.hidden = true; results.innerHTML = ''; };

            const load = async () => {
                const url = new URL(endpoint, window.location.origin);
                if (input.value.trim()) url.searchParams.set('search', input.value.trim());

                try {
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                    const payload = await response.json();
                    const items = payload.data || [];

                    results.innerHTML = items.length
                        ? items.map((user) => `<button type="button" class="lms-user-result" data-id="${user.id}" data-name="${user.name}" data-email="${user.email}" style="display:block;width:100%;text-align:start;padding:8px 10px;border:0;background:#fff;cursor:pointer"><strong>${user.name}</strong><small style="display:block;color:#6b7280">${user.email}</small></button>`).join('')
                        : '<div style="padding:8px 10px;color:#6b7280">No matching users.</div>';

                    results.hidden = false;

                    results.querySelectorAll('[data-id]').forEach((button) => {
                        button.addEventListener('click', () => {
                            hidden.value = button.dataset.id;
                            input.value = `${button.dataset.name} — ${button.dataset.email}`;
                            hide();
                        });
                    });
                } catch (error) {
                    hide();
                }
            };

            input.addEventListener('input', () => {
                hidden.value = '';
                clearTimeout(timer);
                timer = setTimeout(load, 220);
            });
            input.addEventListener('focus', load);
            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== input) hide();
            });

            form.addEventListener('submit', (event) => {
                if (!hidden.value) {
                    event.preventDefault();
                    input.focus();
                }
            });
        })();
    </script>
</x-app-layout>
