<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800" data-i18n="core.translations.title">{{ __('core.translations.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500" data-i18n="core.translations.subtitle">{{ __('core.translations.subtitle') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @php
                $coreFiles = array_values(array_filter($files, fn ($file) => $file['module'] === null && $file['group'] === 'text'));
                $moduleFiles = array_values(array_filter($files, fn ($file) => $file['module'] !== null && $file['group'] === 'text'));
                $modules = collect($moduleFiles)->groupBy('module');
            @endphp

            <div id="language-files" class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.translations.available">{{ __('core.translations.available') }}</h3>
                <p class="mt-1 text-sm text-gray-600" data-i18n="core.translations.hint">{{ __('core.translations.hint') }}</p>
            </div>

            <div class="tr-tabs" role="tablist" aria-label="{{ __('core.translations.title') }}">
                <button type="button" class="tr-tab is-active" data-target="core" role="tab">
                    {{ __('core.translations.core') }}
                    <span class="tr-tab__count">{{ count($coreFiles) }}</span>
                </button>

                @foreach ($modules as $moduleSlug => $rows)
                    <button type="button" class="tr-tab" data-target="module-{{ $moduleSlug }}" role="tab">
                        {{ $moduleLabels[$moduleSlug] ?? $moduleSlug }}
                        <span class="tr-tab__count">{{ count($rows) }}</span>
                    </button>
                @endforeach
            </div>

            <section class="tr-panel is-active" data-panel="core">
                <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('core.translations.core') }}</h3>
                        <span class="text-xs text-gray-500">{{ count($coreFiles) }} {{ __('core.translations.files_count') }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">{{ __('core.translations.locale') }}</th>
                                    <th class="px-6 py-3">{{ __('core.translations.file') }}</th>
                                    <th class="px-6 py-3">{{ __('core.translations.keys') }}</th>
                                    <th class="px-6 py-3">{{ __('core.translations.filled') }}</th>
                                    <th class="px-6 py-3">{{ __('core.translations.missing') }}</th>
                                    <th class="px-6 py-3">{{ __('core.translations.updated') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('core.translations.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($coreFiles as $file)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <span class="tr-locale">{{ $file['locale'] }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-mono text-sm text-gray-900">{{ $file['group'] === 'json' ? $file['locale'].'.json' : ($file['group'] === 'text' ? 'core.txt' : $file['group'].'.php') }}</div>
                                            <div class="mt-0.5 text-xs text-gray-400">{{ $file['path'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700">{{ $file['keys'] }}</td>
                                        <td class="px-6 py-4 text-gray-700">{{ $file['filled'] }}</td>
                                        <td class="px-6 py-4"><span class="tr-badge {{ $file['missing'] > 0 ? 'tr-badge--warn' : 'tr-badge--ok' }}">{{ $file['missing'] }}</span></td>
                                        <td class="px-6 py-4 text-xs text-gray-500">
                                            {{ $file['updated'] ? \Illuminate\Support\Carbon::createFromTimestamp($file['updated'])->format('Y-m-d H:i') : '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('admin.translations.edit', ['locale' => $file['locale'], 'group' => $file['group']]) }}"
                                               class="tr-btn" style="background:#111827 !important;color:#ffffff !important;border:1px solid #111827 !important;text-decoration:none !important"
                                               data-i18n="core.translations.edit">{{ __('core.translations.edit') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">{{ __('core.translations.no_files') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @include('admin.translations._add-language', ['module' => null, 'moduleLabel' => null])
            


            </section>

            @foreach ($modules as $moduleSlug => $rows)
                <section class="tr-panel" data-panel="module-{{ $moduleSlug }}">
                    <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ $moduleLabels[$moduleSlug] ?? $moduleSlug }}</h3>
                                <p class="mt-0.5 font-mono text-xs text-gray-400">{{ $moduleSlug }}</p>
                            </div>
                            <span class="text-xs text-gray-500">{{ count($rows) }} {{ __('core.translations.files_count') }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-6 py-3">{{ __('core.translations.locale') }}</th>
                                        <th class="px-6 py-3">{{ __('core.translations.file') }}</th>
                                        <th class="px-6 py-3">{{ __('core.translations.keys') }}</th>
                                        <th class="px-6 py-3">{{ __('core.translations.filled') }}</th>
                                        <th class="px-6 py-3">{{ __('core.translations.missing') }}</th>
                                        <th class="px-6 py-3">{{ __('core.translations.updated') }}</th>
                                        <th class="px-6 py-3 text-right">{{ __('core.translations.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($rows as $file)
                                        <tr>
                                            <td class="px-6 py-4"><span class="tr-locale">{{ $file['locale'] }}</span></td>
                                            <td class="px-6 py-4">
                                                <div class="font-mono text-sm text-gray-900">{{ $file['locale'] }}/{{ $file['group'] === 'text' ? 'core.txt' : $file['group'].'.php' }}</div>
                                                <div class="mt-0.5 text-xs text-gray-400">{{ $file['path'] }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">{{ $file['keys'] }}</td>
                                            <td class="px-6 py-4 text-gray-700">{{ $file['filled'] }}</td>
                                            <td class="px-6 py-4"><span class="tr-badge {{ $file['missing'] > 0 ? 'tr-badge--warn' : 'tr-badge--ok' }}">{{ $file['missing'] }}</span></td>
                                            <td class="px-6 py-4 text-xs text-gray-500">
                                                {{ $file['updated'] ? \Illuminate\Support\Carbon::createFromTimestamp($file['updated'])->format('Y-m-d H:i') : '—' }}
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="{{ route('admin.translations.edit', ['locale' => $file['locale'], 'group' => $file['group'], 'module' => $file['module']]) }}"
                                                   class="tr-btn" style="background:#111827 !important;color:#ffffff !important;border:1px solid #111827 !important;text-decoration:none !important"
                                                   data-i18n="core.translations.edit">{{ __('core.translations.edit') }}</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">{{ __('core.translations.no_files') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                

                @include('admin.translations._add-language', ['module' => $moduleSlug, 'moduleLabel' => $moduleLabels[$moduleSlug] ?? $moduleSlug])
            </section>
            @endforeach
        </div>
    </div>

    @once
        <style>
            .tr-tabs { display: flex; flex-wrap: wrap; gap: .35rem; border-bottom: 1px solid #e5e7eb; }
            .tr-tab {
                border: 1px solid transparent; border-bottom: 0; background: transparent;
                color: #475569; font: inherit; font-size: .875rem; font-weight: 600;
                padding: .65rem 1rem; border-radius: .5rem .5rem 0 0; cursor: pointer;
            }
            .tr-tab:hover { background: #f1f5f9; }
            .tr-tab.is-active { background: #fff; border-color: #e5e7eb; color: #b91c1c; }
            .tr-tab__count {
                display: inline-block; margin-inline-start: .4rem; border-radius: 999px;
                background: #f1f5f9; color: #475569; font-size: .7rem; padding: .1rem .45rem;
            }
            .tr-tab.is-active .tr-tab__count { background: #fee2e2; color: #b91c1c; }
            .tr-panel { display: none; }
            .tr-panel.is-active { display: block; }
            .tr-btn {
                display: inline-flex; align-items: center; justify-content: center;
                border-radius: .375rem; padding: .4rem .8rem;
                font-size: .75rem; font-weight: 700;
            }
            .tr-btn:hover { background: #b91c1c !important; border-color: #b91c1c !important; color: #fff !important; }
            .tr-badge { display: inline-block; border-radius: 999px; padding: .15rem .55rem; font-size: .72rem; font-weight: 700; }
            .tr-badge--ok { background: #dcfce7; color: #15803d; }
            .tr-badge--warn { background: #fef3c7; color: #b45309; }
            .tr-locale { display: inline-block; border-radius: .3rem; background: #f1f5f9; color: #334155; font-family: ui-monospace, monospace; font-size: .72rem; padding: .15rem .45rem; }
        </style>
    @endonce

    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('.tr-tab'));
            const panels = Array.from(document.querySelectorAll('.tr-panel'));

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
                    panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.panel === tab.dataset.target));
                });
            });
        })();
    </script>
</x-app-layout>

