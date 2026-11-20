@push('styles')
    <style>
        html body:has(.z4-admin-sidebar) main form button.seo-audit-resolve[type="submit"] {
            align-items: center !important;
            background: #fff1f0 !important;
            border: 1px solid #efc9c5 !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            color: #9a0000 !important;
            display: inline-flex !important;
            height: 28px !important;
            justify-content: center !important;
            min-height: 0 !important;
            padding: 0 !important;
            transform: none !important;
            width: 28px !important;
        }

        html body:has(.z4-admin-sidebar) main form button.seo-audit-resolve[type="submit"]:hover {
            background: #ffe4df !important;
            border-color: #dfa9a3 !important;
            color: #7a0000 !important;
            transform: none !important;
        }
    </style>
@endpush

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.audit') }}</h2>
            <form method="POST" action="{{ route('admin.plugins.seo.audit.run') }}">
                @csrf
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.run_audit') }}</button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.last_audit') }}</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900">{{ $lastRun ?: __('seo::ui.never') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.urls_checked') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $lastUrls }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.errors') }}</p>
                    <p class="mt-2 text-3xl font-bold text-red-600">{{ $lastErrors }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.warnings') }}</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600">{{ $lastWarnings }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">Recent runs</h3>
                        <p class="mt-1 text-sm text-gray-500">Last 5 audit executions.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-md border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">URLs</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Records</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Errors</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Warnings</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Duration</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse (($runs ?? collect()) as $run)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ optional($run->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $run->status === 'completed' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $run->status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $run->urls }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $run->records }}</td>
                                    <td class="px-4 py-3 font-semibold text-red-600">{{ $run->errors }}</td>
                                    <td class="px-4 py-3 font-semibold text-amber-600">{{ $run->warnings }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ number_format($run->duration_ms / 1000, 2) }}s</td>
                                </tr>
                                @php($checked = json_decode((string) ($run->checked_urls ?? ''), true) ?: [])
                                @if ($checked !== [])
                                    <tr class="bg-gray-50/60">
                                        <td colspan="7" class="px-4 py-2">
                                            <details class="checked-urls">
                                                <summary class="cursor-pointer text-xs font-semibold text-gray-600">Checked URLs ({{ count($checked) }})</summary>
                                                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                                    @foreach ($checked as $url => $status)
                                                        <li class="flex flex-wrap items-center gap-2">
                                                            <span class="rounded px-1.5 py-0.5 font-mono text-[11px] {{ is_int($status) && $status < 400 ? 'bg-green-50 text-green-700' : (is_int($status) && $status < 500 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">{{ is_int($status) ? $status : $status }}</span>
                                                            <span class="break-all">{{ $url }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No audit runs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.issues_by_type') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.issues_by_type_help') }}</p>
                    </div>
                    @if ($type !== '')
                        <a href="{{ route('admin.plugins.seo.audit.index') }}" class="text-sm font-semibold text-gray-700 underline">{{ __('seo::ui.reset') }}</a>
                    @endif
                </div>

                <div class="mt-4 overflow-hidden rounded-md border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.issue_type') }}</th>
                                <th class="px-3 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.affected_records') }}</th>
                                <th class="px-3 py-3 text-end font-semibold text-gray-600">{{ __('seo::ui.review') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($issueSummaries as $summary)
                                <tr>
                                    <td class="px-3 py-3 text-gray-700">{{ __('seo::ui.audit_types.'.$summary->type) }}</td>
                                    <td class="px-3 py-3 font-semibold text-gray-900">{{ $summary->total }}</td>
                                    <td class="px-3 py-3 text-end">
                                        <a href="{{ route('admin.plugins.seo.audit.index', ['type' => $summary->type]) }}" class="font-semibold text-gray-700 hover:text-gray-950">{{ __('seo::ui.review') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">{{ __('seo::ui.no_issues') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.plugins.seo.audit.index') }}" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="audit-search" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.search') }}</label>
                    <input id="audit-search" name="search" value="{{ $search }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="audit-status" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.status') }}</label>
                    <select id="audit-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('seo::ui.all') }}</option>
                        <option value="open" @selected($status === 'open')>{{ __('seo::ui.open') }}</option>
                        <option value="resolved" @selected($status === 'resolved')>{{ __('seo::ui.resolved') }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.filter') }}</button>
                    <a href="{{ route('admin.plugins.seo.audit.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.reset') }}</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($issues === null)
                    <p class="px-4 py-6 text-sm text-gray-500">{{ __('seo::ui.no_records') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.issue_type') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.content_url') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.message') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.status') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('seo::ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($issues as $issue)
                                    <tr>
                                        <td class="max-w-xs px-4 py-3 text-xs text-gray-600">{{ __('seo::ui.audit_types.'.$issue->type) }}</td>
                                        <td class="max-w-sm px-4 py-3 font-mono text-xs text-gray-800">
                                            @if ($issue->url)
                                                <a href="{{ $issue->url }}" target="_blank" rel="noopener" class="underline">{{ $issue->url }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $issue->message }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $issue->status === 'open' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">{{ $issue->status }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="flex justify-end gap-3">
                                                @if ($issue->admin_url)
                                                    <a href="{{ $issue->admin_url }}" target="_blank" rel="noopener" class="font-semibold text-gray-700 hover:text-gray-950">{{ __('seo::ui.review') }}</a>
                                                @endif
                                                @if ($issue->status === 'open')
                                                    <form method="POST" action="{{ route('admin.plugins.seo.audit.resolve', $issue) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="seo-audit-resolve" title="{{ __('seo::ui.resolve') }}" aria-label="{{ __('seo::ui.resolve') }}">
                                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M20 6 9 17l-5-5"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('seo::ui.no_issues') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3">{{ $issues->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
