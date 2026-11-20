<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight" data-i18n="core.backups.title">{{ __('core.backups.title') }}</h2>
        <p class="mt-1 text-sm text-gray-500" data-i18n="core.backups.subtitle">{{ __('core.backups.subtitle') }}</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- What the backup contains ------------------------------------------------ --}}
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-700">DB</span>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('core.backups.db_title') }}</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li>• {{ __('core.backups.db_all_tables') }}</li>
                        <li>• {{ __('core.backups.db_dynamic') }}</li>
                        <li>• {{ __('core.backups.db_current', ['size' => $liveDatabase['size'], 'tables' => $liveDatabase['tables']]) }}</li>
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">{{ __('core.backups.db_format') }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">&lt;/&gt;</span>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('core.backups.code_title') }}</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li>• {{ __('core.backups.code_includes') }}</li>
                        <li>• {{ __('core.backups.code_assets') }}</li>
                        <li>• {{ __('core.backups.code_excludes') }}</li>
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">{{ __('core.backups.code_format') }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.backups.checkpoints">{{ __('core.backups.checkpoints') }}</h3>
                            <p class="mt-1 text-sm text-gray-500" data-i18n="core.backups.hint">{{ __('core.backups.hint') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.backups.store') }}">
                            @csrf
                            <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-i18n="core.backups.create">
                                {{ __('core.backups.create') }}
                            </button>
                        </form>
                    </div>
                </div>

                @if ($backups === [])
                    <div class="px-6 py-10 text-center text-sm text-gray-600" data-i18n="core.backups.empty">{{ __('core.backups.empty') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3" data-i18n="core.backups.taken_at">{{ __('core.backups.taken_at') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.status">{{ __('core.backups.status') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.db_title">{{ __('core.backups.db_title') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.code_title">{{ __('core.backups.code_title') }}</th>
                                    <th class="px-6 py-3 text-right" data-i18n="core.backups.actions">{{ __('core.backups.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td class="px-6 py-4 align-top">
                                            <div class="font-semibold text-gray-900">#{{ $backup['id'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $backup['created_at'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $backup['checkpoint_label'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 align-top">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-green-100 text-green-700' => $backup['status'] === 'completed',
                                                'bg-yellow-100 text-yellow-800' => $backup['status'] === 'pending',
                                                'bg-red-100 text-red-700' => $backup['status'] === 'failed',
                                            ])>
                                                {{ ucfirst($backup['status']) }}
                                            </span>
                                            @if ($backup['error'] !== '')
                                                <div class="mt-2 max-w-xs text-xs text-red-600">{{ $backup['error'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 align-top text-gray-600">
                                            @if ($backup['database']['exists'])
                                                <div class="font-semibold text-gray-900">{{ $backup['database']['size'] }}</div>
                                                <div class="text-xs text-gray-500">{{ trans_choice('core.backups.tables_count', $backup['database']['tables'], ['count' => $backup['database']['tables']]) }}</div>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 align-top text-gray-600">
                                            @if ($backup['code']['exists'])
                                                <div class="font-semibold text-gray-900">{{ $backup['code']['size'] }}</div>
                                                <div class="text-xs text-gray-500">{{ trans_choice('core.backups.files_count', $backup['code']['files'], ['count' => $backup['code']['files']]) }}</div>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 align-top">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @if ($backup['database']['exists'])
                                                    <a href="{{ route('admin.backups.download', [$backup['id'], 'db']) }}"
                                                       class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                        {{ __('core.backups.download_db') }}
                                                    </a>
                                                @endif
                                                @if ($backup['code']['exists'])
                                                    <a href="{{ route('admin.backups.download', [$backup['id'], 'code']) }}"
                                                       class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                        {{ __('core.backups.download_code') }}
                                                    </a>
                                                @endif
                                                @if (! $backup['database']['exists'] && ! $backup['code']['exists'])
                                                    <span class="text-xs text-gray-400">{{ __('core.backups.no_files') }}</span>
                                                @endif
                                                <form method="POST" action="{{ route('admin.backups.destroy', $backup['id']) }}" onsubmit="return confirm(@js(__('core.backups.confirm.remove')));">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-i18n="core.backups.remove">
                                                        {{ __('core.backups.remove') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
