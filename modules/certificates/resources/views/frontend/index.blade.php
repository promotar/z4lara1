@extends('certificates::frontend.layout')
@section('title', 'Certificate Management')
@section('content')
    <div class="manage-head">
        <h1>{{ __('certificates::ui.certificates') }}</h1>
        <a class="account-section-action" href="{{ route('certificates.manage.create', [], false) }}">{{ __('certificates::ui.issue_certificate') }}</a>
    </div>

    @if(session('status'))<div class="manage-notice">{{ session('status') }}</div>@endif

    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <a href="{{ route('certificates.manage.index', [], false) }}" class="manage-tab{{ $view === 'all' ? ' is-active' : '' }}">All ({{ $counts['all'] }})</a>
        <a href="{{ route('certificates.manage.index', ['view' => 'trash'], false) }}" class="manage-tab{{ $view === 'trash' ? ' is-active' : '' }}">Trash ({{ $counts['trash'] }})</a>
    </div>

    <form method="GET" action="{{ route('certificates.manage.index', [], false) }}" class="manage-search">
        @if($view === 'trash')<input type="hidden" name="view" value="trash">@endif
        <label class="sr-only" for="manage-search-input">{{ __('certificates::ui.name_or_number') }}</label>
        <input id="manage-search-input" type="search" name="q" value="{{ $search }}" placeholder="{{ __('certificates::ui.name_or_number') }}" maxlength="255" autocomplete="off">
        <button type="submit">{{ __('certificates::ui.search') }}</button>
        @if($search !== '')
            <a class="manage-link" href="{{ route('certificates.manage.index', $view === 'trash' ? ['view' => 'trash'] : [], false) }}">{{ __('certificates::ui.clear_search') }}</a>
        @endif
    </form>

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        @if($view === 'all')
            <form id="portal-bulk-form" method="POST" action="{{ route('certificates.manage.bulk-destroy', [], false) }}">
                @csrf @method('DELETE')
                <span data-bulk-actions hidden><button type="submit" class="manage-danger" data-bulk-label="{{ __('certificates::ui.move_to_trash') }}" onclick="return confirm(@js(__('certificates::ui.confirm_move_selected')));">{{ __('certificates::ui.move_to_trash') }}</button></span>
            </form>
        @else
            <form id="portal-bulk-form" method="POST" action="{{ route('certificates.manage.bulk-restore', [], false) }}">
                @csrf
                <span data-bulk-actions hidden><button type="submit" data-bulk-label="{{ __('certificates::ui.restore_selected') }}">{{ __('certificates::ui.restore_selected') }}</button></span>
            </form>
            <form method="POST" action="{{ route('certificates.manage.trash.empty', [], false) }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_empty_trash_portal')));">
                @csrf @method('DELETE')
                <span data-bulk-actions hidden><button type="submit" class="manage-danger">{{ __('certificates::ui.empty_trash') }}</button></span>
            </form>
        @endif
    </div>

    @if($certificates->count())
        <div style="overflow-x:auto">
            <table class="manage-table">
                <thead><tr>
                    <th style="width:44px"><input type="checkbox" id="portal-select-all" aria-label="{{ __('certificates::ui.select_all') }}"></th>
                    <th>{{ __('certificates::ui.name') }}</th><th>{{ __('certificates::ui.date') }}</th><th>{{ __('certificates::ui.slug') }}</th><th>{{ __('certificates::ui.actions') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($certificates as $certificate)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $certificate->id }}" form="portal-bulk-form" class="portal-row-check" aria-label="{{ __('certificates::ui.select_named', ['name' => $certificate->name]) }}"></td>
                            <td>{{ $certificate->name }}</td>
                            <td>{{ $certificate->date?->format('Y-m-d') }}</td>
                            <td dir="ltr">{{ $certificate->slug }}</td>
                            <td>
                                @if($view === 'all')
                                    <a class="manage-link" style="margin:0" href="{{ route('certificates.manage.edit', $certificate, false) }}">{{ __('certificates::ui.edit') }}</a>
                                    <form method="POST" action="{{ route('certificates.manage.destroy', $certificate, false) }}" style="display:inline" onsubmit="return confirm(@js(__('certificates::ui.confirm_move_one')));">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="manage-danger-inline">{{ __('certificates::ui.delete') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('certificates.manage.restore', $certificate, false) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="manage-link-inline">{{ __('certificates::ui.restore') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('certificates.manage.force-destroy', $certificate, false) }}" style="display:inline" onsubmit="return confirm(@js(__('certificates::ui.confirm_delete_one')));">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="manage-danger-inline">{{ __('certificates::ui.delete_permanently') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px">{{ $certificates->links() }}</div>
    @else
        <div class="manage-empty">{{ $view === 'trash' ? 'The trash is empty.' : 'No matching certificates.' }}</div>
    @endif

    <script>
        (function () {
            var all = document.getElementById('portal-select-all');
            var rows = function () { return Array.prototype.slice.call(document.querySelectorAll('.portal-row-check')); };
            var sync = function () {
                var selected = rows().filter(function (cb) { return cb.checked; }).length;
                document.querySelectorAll('[data-bulk-actions]').forEach(function (box) {
                    box.hidden = selected === 0;
                    box.querySelectorAll('[data-bulk-label]').forEach(function (button) {
                        button.textContent = button.getAttribute('data-bulk-label') + (selected > 0 ? ' (' + selected + ')' : '');
                    });
                });
                if (all) { all.checked = selected > 0 && selected === rows().length; }
            };
            if (all) {
                all.addEventListener('change', function () {
                    rows().forEach(function (cb) { cb.checked = all.checked; });
                    sync();
                });
            }
            rows().forEach(function (cb) { cb.addEventListener('change', sync); });
            sync();
        })();
    </script>
@endsection
