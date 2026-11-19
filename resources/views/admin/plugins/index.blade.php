@push('styles')
    <style>
        .plugins-status-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem;border-bottom:1px solid #efe7e5;padding-bottom:.15rem}
        .plugins-status-tab{display:inline-flex;align-items:center;gap:.45rem;padding:.5rem .95rem;border-radius:.6rem .6rem 0 0;
            font-size:.85rem;font-weight:800;color:#7a6f6d;text-decoration:none;border:1px solid transparent;border-bottom:none}
        .plugins-status-tab:hover{background:#fbf5f4;color:#8a1110}
        .plugins-status-tab.is-active{background:#fff;color:#8a1110;border-color:#efe7e5;position:relative;top:1px}
        .plugins-status-tab b{min-width:1.5rem;text-align:center;border-radius:999px;background:#f6e7e6;color:#8a1110;
            font-size:.72rem;font-weight:800;padding:.05rem .45rem}
        .plugins-status-tab.is-active b{background:#8a1110;color:#fff}
        .ainpa-bulk-toolbar{display:flex;flex-wrap:wrap;gap:.65rem;align-items:center;justify-content:space-between;
            background:#fff;border:1px solid #ece6e4;border-radius:.85rem;padding:.7rem .95rem;margin:0 0 1rem}
        .ainpa-bulk-toolbar__group{display:flex;flex-wrap:wrap;gap:.55rem;align-items:center}
        .ainpa-bulk-toolbar__title{font-weight:800;font-size:.85rem;color:#6b0f0f;letter-spacing:.01em}
        .ainpa-bulk-select-all{display:inline-flex;gap:.45rem;align-items:center;font-size:.82rem;font-weight:700;color:#4b4b4b}
        .ainpa-bulk-toolbar__group[hidden]{display:none}
        .ainpa-bulk-count{min-width:1.7rem;text-align:center;border-radius:999px;background:#f6e7e6;color:#8a1110;
            font-size:.78rem;font-weight:800;padding:.12rem .5rem}
        .ainpa-bulk-check{width:16px;height:16px;cursor:pointer;accent-color:#9a0000}
        .ainpa-bulk-check:disabled{cursor:not-allowed;opacity:.35}
        .ainpa-bulk-col{width:36px;text-align:center}
    </style>
@endpush

<x-app-layout>
    <x-slot name="header">
        <div class="ainpa-page-toolbar">
            <h2 class="ainpa-page-title" data-i18n="core.plugins.title">{{ __('core.plugins.title') }}</h2>
            <a href="{{ route('admin.plugins.create') }}" class="ainpa-button ainpa-button-primary" data-i18n="core.plugins.install">{{ __('core.plugins.install') }}</a>
        </div>
    </x-slot>

    <div class="ainpa-admin-page">
        <div class="ainpa-page-container">
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="ainpa-alert ainpa-alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="plugins-status-tabs" role="tablist">
                @php($tabs = [
                    'all' => __('core.plugins.tab_all'),
                    'active' => __('core.plugins.tab_active'),
                    'disabled' => __('core.plugins.tab_disabled'),
                ])
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('admin.plugins.index', $key === 'all' ? [] : ['status' => $key]) }}"
                       class="plugins-status-tab {{ ($statusFilter ?? 'all') === $key ? 'is-active' : '' }}"
                       role="tab" aria-selected="{{ ($statusFilter ?? 'all') === $key ? 'true' : 'false' }}">
                        <span>{{ $label }}</span>
                        <b>{{ $statusCounts[$key] ?? 0 }}</b>
                    </a>
                @endforeach
            </div>
            <form id="plugins-bulk-form" method="POST" action="{{ route('admin.plugins.bulk.deactivate') }}" class="ainpa-bulk-toolbar">
                @csrf
                <div class="ainpa-bulk-toolbar__group">
                    <span class="ainpa-bulk-toolbar__title" data-i18n="core.plugins.bulk_title">{{ __('core.plugins.bulk_title') }}</span>
                    <label class="ainpa-bulk-select-all">
                        <input type="checkbox" id="plugins-bulk-all" class="ainpa-bulk-check">
                        <span data-i18n="core.plugins.bulk_select_all">{{ __('core.plugins.bulk_select_all') }}</span>
                    </label>
                    <span class="ainpa-bulk-count" id="plugins-bulk-count">0</span>
                </div>
                <div class="ainpa-bulk-toolbar__group" id="plugins-bulk-actions" hidden>
                    <button type="submit" formaction="{{ route('admin.plugins.bulk.deactivate') }}"
                            class="ainpa-button ainpa-button-compact" data-bulk-action="deactivate" disabled
                            data-i18n="core.plugins.bulk_deactivate">{{ __('core.plugins.bulk_deactivate') }}</button>
                    <button type="submit" formaction="{{ route('admin.plugins.bulk.purge') }}"
                            class="ainpa-button ainpa-button-danger ainpa-button-compact" data-bulk-action="purge" disabled
                            data-i18n="core.plugins.bulk_purge">{{ __('core.plugins.bulk_purge') }}</button>
                </div>
                <input type="hidden" name="bulk_confirmation" id="plugins-bulk-confirmation" value="">
            </form>

            <div class="ainpa-card ainpa-table-card">
                <table class="ainpa-data-table">
                    <thead>
                        <tr>
                            <th class="ainpa-bulk-col"></th>
                            <th data-i18n="core.plugins.name">{{ __('core.plugins.name') }}</th>
                            <th data-i18n="core.plugins.slug">{{ __('core.plugins.slug') }}</th>
                            <th data-i18n="core.plugins.version">{{ __('core.plugins.version') }}</th>
                            <th data-i18n="core.plugins.status">{{ __('core.plugins.status') }}</th>
                            <th data-i18n="core.plugins.installed">{{ __('core.plugins.installed') }}</th>
                            <th data-i18n="core.plugins.settings">{{ __('core.plugins.settings') }}</th>
                            <th data-i18n="core.plugins.path">{{ __('core.plugins.path') }}</th>
                            <th data-i18n="core.plugins.actions">{{ __('core.plugins.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plugins as $plugin)
                            @php($bulkLocked = $plugin->isCore() || $plugin->isDefaultAdminTheme())
                            <tr>
                                <td class="ainpa-bulk-col">
                                    <input type="checkbox" class="ainpa-bulk-check" form="plugins-bulk-form"
                                           name="plugins[]" value="{{ $plugin->slug }}" data-status="{{ $plugin->status }}"
                                           @disabled($bulkLocked)
                                           title="{{ $bulkLocked ? __('core.plugins.bulk_locked') : $plugin->slug }}">
                                </td>
                                <td class="ainpa-table-primary">{{ $plugin->name }}</td>
                                <td><span class="ainpa-code">{{ $plugin->slug }}</span></td>
                                <td>{{ $plugin->version }}</td>
                                <td>
                                    <span class="ainpa-status-badge ainpa-status-{{ $plugin->status === 'active' ? 'active' : 'inactive' }}">
                                        {{ $plugin->status }}
                                    </span>
                                    @if ($plugin->isCore())
                                        <span class="ainpa-status-badge ainpa-status-active" title="{{ __('core.plugins.required_title') }}" data-i18n="core.plugins.core">{{ __('core.plugins.core') }}</span>
                                    @endif
                                    @if ($plugin->isDefaultAdminTheme())
                                        <span class="ainpa-status-badge ainpa-status-active" title="{{ __('core.plugins.default_fallback_title') }}" data-i18n="core.plugins.default_admin_theme">{{ __('core.plugins.default_admin_theme') }}</span>
                                    @elseif ($plugin->isAdminTheme())
                                        <span class="ainpa-status-badge ainpa-status-inactive" title="{{ __('core.plugins.one_admin_title') }}" data-i18n="core.plugins.admin_theme">{{ __('core.plugins.admin_theme') }}</span>
                                    @endif
                                </td>
                                <td class="ainpa-table-muted">
                                    {{ optional($plugin->installed_at)->format('Y-m-d H:i') ?? 'Not installed' }}
                                </td>
                                <td>
                                    @php($settings = $plugin->admin_settings_link)
                                    @if ($settings['available'])
                                        <a href="{{ $settings['url'] }}" class="ainpa-table-link">
                                            {{ $settings['label'] }}
                                        </a>
                                    @else
                                        <div class="ainpa-table-note">
                                            <span class="ainpa-table-note-title">{{ $settings['label'] }}</span>
                                            <div>{{ $settings['note'] }}</div>
                                            @if ($settings['route'])
                                                <div class="ainpa-code">{{ $settings['route'] }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="ainpa-table-path">{{ $plugin->installed_path }}</td>
                                <td class="ainpa-table-actions">
                                    @if ($plugin->status === 'active' && ! $plugin->isCore() && ! $plugin->isDefaultAdminTheme())
                                        <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin->slug) }}" class="ainpa-action-form">
                                            @csrf @method('PATCH')
                                            <button class="ainpa-button ainpa-button-danger ainpa-button-compact" data-i18n="core.plugins.deactivate">{{ __('core.plugins.deactivate') }}</button>
                                        </form>
                                    @elseif ($plugin->status === 'active' && $plugin->isDefaultAdminTheme())
                                        <button class="ainpa-button ainpa-button-compact" type="button" disabled title="{{ __('core.plugins.restored_title') }}" data-i18n="core.plugins.default_theme">{{ __('core.plugins.default_theme') }}</button>
                                    @elseif ($plugin->status === 'active')
                                        <button class="ainpa-button ainpa-button-compact" type="button" disabled title="{{ __('core.plugins.core_required_title') }}" data-i18n="core.plugins.core_plugin">{{ __('core.plugins.core_plugin') }}</button>
                                    @else
                                        <form method="POST" action="{{ route('admin.plugins.activate', $plugin->slug) }}" class="ainpa-action-form">
                                            @csrf @method('PATCH')
                                            <button class="ainpa-button ainpa-button-success ainpa-button-compact" data-i18n="core.plugins.activate">{{ __('core.plugins.activate') }}</button>
                                        </form>
                                        @unless ($plugin->isCore())
                                            <form method="POST" action="{{ route('admin.plugins.destroy', $plugin->slug) }}" class="ainpa-action-form" onsubmit="if (this.dataset.submitted === 'true') return false; if (!confirm(@js(__('core.plugins.confirm.purge', ['slug' => $plugin->slug])))) return false; this.dataset.submitted = 'true'; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = @js(__('core.plugins.deleting'));">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="purge_confirmation" value="{{ $plugin->slug }}">
                                                <button type="submit" class="ainpa-button ainpa-button-danger ainpa-button-compact" data-i18n="core.plugins.purge">{{ __('core.plugins.purge') }}</button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="ainpa-empty-table" data-i18n="core.plugins.empty">{{ __('core.plugins.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <x-platform-pagination :paginator="$plugins" />
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('plugins-bulk-form');
            if (!form) { return; }

            var all = document.getElementById('plugins-bulk-all');
            var counter = document.getElementById('plugins-bulk-count');
            var confirmation = document.getElementById('plugins-bulk-confirmation');
            var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.ainpa-bulk-check[form="plugins-bulk-form"]')); };
            var buttons = function () { return Array.prototype.slice.call(form.querySelectorAll('button[data-bulk-action]')); };
            var selected = function () { return boxes().filter(function (b) { return b.checked; }); };

            function refresh() {
                var count = selected().length;
                if (counter) { counter.textContent = String(count); }
                buttons().forEach(function (b) { b.disabled = count === 0; });
                var actions = document.getElementById('plugins-bulk-actions');
                if (actions) { actions.hidden = count === 0; }
            }

            if (all) {
                all.addEventListener('change', function () {
                    boxes().forEach(function (b) { if (!b.disabled) { b.checked = all.checked; } });
                    refresh();
                });
            }
            boxes().forEach(function (b) { b.addEventListener('change', refresh); });

            form.addEventListener('submit', function (event) {
                var picked = selected();
                if (picked.length === 0) { event.preventDefault(); return; }

                var action = (event.submitter && event.submitter.dataset.bulkAction) || 'deactivate';
                var names = picked.map(function (b) { return b.value; }).join(', ');

                if (action === 'purge') {
                    var active = picked.filter(function (b) { return b.dataset.status === 'active'; })
                        .map(function (b) { return b.value; });

                    if (active.length) {
                        window.alert(
                            'لا يمكن حذف بلجن مفعّل. عطّله أولاً:\n\n' + active.join(', ')
                        );
                        event.preventDefault();
                        return;
                    }

                    var ok = window.confirm(
                        @js(__('core.plugins.confirm.bulk_purge_prefix')) + picked.length + @js(__('core.plugins.confirm.bulk_purge_suffix')) + '\n\n' + names +
                        @js(__('core.plugins.confirm.bulk_purge_detail'))
                    );
                    if (!ok) { event.preventDefault(); return; }
                    if (confirmation) { confirmation.value = 'PURGE'; }
                } else {
                    if (!window.confirm(@js(__('core.plugins.confirm.bulk_deactivate_prefix')) + picked.length + @js(__('core.plugins.confirm.bulk_purge_suffix')) + '\n\n' + names)) {
                        event.preventDefault();
                        return;
                    }
                    if (confirmation) { confirmation.value = ''; }
                }

                buttons().forEach(function (b) { b.disabled = true; });
            });

            refresh();
        })();
    </script>
</x-app-layout>
