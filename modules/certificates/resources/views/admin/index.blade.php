<x-app-layout>
    @include('certificates::admin.styles')
    <style>
        .cert-tabs{display:flex;flex-wrap:wrap;gap:8px}
        .cert-tabs .cert-tab{display:inline-flex;align-items:center;min-height:38px;padding:0 18px;border:1px solid color-mix(in srgb, var(--ainpa-admin-accent, #d5c4dd) 22%, #fff);border-radius:8px;background:#fff;color:var(--ainpa-admin-accent, #5b2e86)!important;font-weight:800;text-decoration:none;line-height:1}
        .cert-tabs .cert-tab.is-active{background:var(--ainpa-admin-text, #111827)!important;border-color:var(--ainpa-admin-text, #111827)!important;color:#ffffff!important}
        .cert-admin-list{overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
        .cert-admin-list table{width:100%;border-collapse:collapse}
        .cert-admin-list th,.cert-admin-list td{padding:12px 16px;text-align:left;vertical-align:middle;border-bottom:1px solid #f0f0f2;white-space:nowrap}
        .cert-admin-list td.cert-name{white-space:normal;min-width:180px}
        .cert-admin-list thead th{background:#f8f7f9;color:#374151;font-size:13px;font-weight:800}
        .cert-admin-list tbody tr:last-child td{border-bottom:0}
        .cert-admin-list .cert-toggle{-webkit-appearance:none!important;appearance:none!important;display:inline-grid!important;place-content:center!important;width:20px!important;height:20px!important;min-width:20px!important;margin:0!important;border:1.5px solid #b9c9dc!important;border-radius:6px!important;background:#ffffff!important;cursor:pointer;vertical-align:middle!important;box-shadow:none!important}
        .cert-admin-list .cert-toggle:checked{background:#d71913!important;border-color:#b3130f!important}
        .cert-admin-list .cert-toggle::before{content:"";width:10px;height:10px;transform:scale(0);background:#ffffff;clip-path:polygon(14% 50%,0 64%,39% 100%,100% 18%,84% 4%,36% 68%)}
        .cert-admin-list .cert-toggle:checked::before{transform:scale(1)}
        .cert-admin-list .cert-actions{display:flex;flex-wrap:nowrap;align-items:center;gap:16px;white-space:nowrap}
        .cert-admin-list .cert-action-btn{display:inline!important;background:transparent!important;background-image:none!important;border:0!important;border-radius:0!important;box-shadow:none!important;padding:0!important;min-height:0!important;width:auto!important;color:#b91c1c!important;font-weight:700;text-decoration:underline;cursor:pointer;font-size:inherit;line-height:1.4}
        .cert-admin-list .cert-action-btn:hover{background:transparent!important;color:#991b1b!important}
    </style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3" dir="ltr">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('certificates::ui.certificates') }}</h2>
            <a href="{{ route('admin.plugins.certificates.create') }}" class="certificates-action rounded-md bg-gray-900 px-4 py-2 text-white">{{ __('certificates::ui.add_certificate') }}</a>
        </div>
    </x-slot>
    <div class="py-8" dir="ltr">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<p role="status" class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</p>@endif

            <div class="cert-tabs">
                <a href="{{ route('admin.plugins.certificates.index') }}" class="cert-tab{{ $view === 'all' ? ' is-active' : '' }}" style="color:{{ $view === 'all' ? '#ffffff' : 'var(--ainpa-admin-accent, #5b2e86)' }}!important;background:{{ $view === 'all' ? 'var(--ainpa-admin-text, #111827)' : '#ffffff' }}!important;border-color:{{ $view === 'all' ? 'var(--ainpa-admin-text, #111827)' : 'color-mix(in srgb, var(--ainpa-admin-accent, #d5c4dd) 22%, #fff)' }}!important">All ({{ $counts['all'] }})</a>
                <a href="{{ route('admin.plugins.certificates.index', ['view' => 'trash']) }}" class="cert-tab{{ $view === 'trash' ? ' is-active' : '' }}" style="color:{{ $view === 'trash' ? '#ffffff' : 'var(--ainpa-admin-accent, #5b2e86)' }}!important;background:{{ $view === 'trash' ? 'var(--ainpa-admin-text, #111827)' : '#ffffff' }}!important;border-color:{{ $view === 'trash' ? 'var(--ainpa-admin-text, #111827)' : 'color-mix(in srgb, var(--ainpa-admin-accent, #d5c4dd) 22%, #fff)' }}!important">Trash ({{ $counts['trash'] }})</a>
            </div>

            <form method="GET" action="{{ route('admin.plugins.certificates.index') }}" class="flex flex-wrap gap-3">
                @if($view === 'trash')<input type="hidden" name="view" value="trash">@endif
                <label class="sr-only" for="certificate-admin-search">{{ __('certificates::ui.name_or_number') }}</label>
                <input id="certificate-admin-search" name="q" value="{{ $search }}" placeholder="{{ __('certificates::ui.name_or_number') }}" maxlength="255" autocomplete="off" class="rounded-md border-gray-300" dir="auto">
                <button class="certificates-action rounded-md bg-gray-900 px-4 py-2 text-white">{{ __('certificates::ui.search') }}</button>
                <a href="{{ route('admin.plugins.certificates.index', $view === 'trash' ? ['view' => 'trash'] : []) }}" class="px-4 py-2 underline">{{ __('certificates::ui.clear') }}</a>
            </form>

            <div class="flex flex-wrap gap-3">
                @if($view === 'all')
                    <form id="cert-bulk-form" method="POST" action="{{ route('admin.plugins.certificates.bulk-destroy') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="certificates-action rounded-md bg-gray-900 px-4 py-2 text-white" onclick="return confirm(@js(__('certificates::ui.confirm_move_selected')));">{{ __('certificates::ui.move_to_trash') }}</button>
                    </form>
                @else
                    <form id="cert-bulk-form" method="POST" action="{{ route('admin.plugins.certificates.bulk-restore') }}">
                        @csrf
                        <button type="submit" class="certificates-action rounded-md bg-gray-900 px-4 py-2 text-white">{{ __('certificates::ui.restore_selected') }}</button>
                        <button type="submit" formaction="{{ route('admin.plugins.certificates.bulk-force-destroy') }}" name="_method" value="DELETE" class="ml-2 rounded-md border border-red-300 bg-white px-4 py-2 text-red-700" onclick="return confirm(@js(__('certificates::ui.confirm_delete_selected')));">{{ __('certificates::ui.delete_permanently') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.plugins.certificates.trash.empty') }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_empty_trash_admin')));">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 bg-white px-4 py-2 text-red-700">{{ __('certificates::ui.empty_trash') }}</button>
                    </form>
                @endif
            </div>

            <div class="cert-admin-list">
                <table>
                    <thead><tr>
                        <th style="width:52px"><input type="checkbox" id="cert-select-all" class="cert-toggle" aria-label="{{ __('certificates::ui.select_all') }}"></th>
                        <th>{{ __('certificates::ui.name') }}</th><th>{{ __('certificates::ui.date') }}</th><th>{{ __('certificates::ui.slug') }}</th>
                        @if($view === 'all')<th>{{ __('certificates::ui.image') }}</th>@endif
                        <th>{{ __('certificates::ui.actions') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($certificates as $certificate)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $certificate->id }}" form="cert-bulk-form" class="cert-toggle cert-row-check" aria-label="{{ __('certificates::ui.select_named', ['name' => $certificate->name]) }}"></td>
                                <td class="cert-name">{{ $certificate->name }}</td>
                                <td><time datetime="{{ $certificate->date->toDateString() }}">{{ $certificate->date->format('Y-m-d') }}</time></td>
                                <td dir="ltr">{{ $certificate->slug }}</td>
                                @if($view === 'all')
                                    <td><a href="{{ $certificate->publicUrl() }}" target="_blank" rel="noopener noreferrer">{{ __('certificates::ui.view_image') }}</a></td>
                                @endif
                                <td>
                                    <div class="cert-actions">
                                        @if($view === 'all')
                                            <a href="{{ route('admin.plugins.certificates.edit', $certificate) }}">{{ __('certificates::ui.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.plugins.certificates.destroy', $certificate) }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_move_one')));">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="cert-action-btn" style="color:#b91c1c!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;min-height:0!important;text-decoration:underline">Delete</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.plugins.certificates.restore', $certificate) }}">
                                                @csrf
                                                <button type="submit" class="cert-action-btn" style="color:#15803d!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;min-height:0!important;text-decoration:underline">{{ __('certificates::ui.restore') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.plugins.certificates.force-destroy', $certificate) }}" onsubmit="return confirm(@js(__('certificates::ui.confirm_delete_one')));">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="cert-action-btn" style="color:#b91c1c!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;min-height:0!important;text-decoration:underline">{{ __('certificates::ui.delete_permanently') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $view === 'all' ? 6 : 5 }}" style="padding:32px;text-align:center;color:var(--ainpa-admin-muted, #6b7280)">{{ $view === 'trash' ? 'The trash is empty.' : 'No matching certificates.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $certificates->links() }}
        </div>
    </div>
    <script>
        (function () {
            var all = document.getElementById('cert-select-all');
            if (!all) { return; }
            all.addEventListener('change', function () {
                document.querySelectorAll('.cert-row-check').forEach(function (cb) { cb.checked = all.checked; });
            });
        })();
    </script>
</x-app-layout>
