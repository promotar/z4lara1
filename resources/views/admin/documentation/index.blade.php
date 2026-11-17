<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800" data-i18n="core.docs.title">{{ __('core.docs.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500" data-i18n="core.docs.subtitle">{{ __('core.docs.subtitle') }}</p>
            </div>
            <a href="{{ route('admin.platform-registry.index') }}" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-i18n="core.docs.tab_admin">{{ __('core.docs.tab_admin') }}</a>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ tab: @js($activeDocumentationTab) }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 overflow-x-auto rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                <nav class="flex gap-2 text-sm font-semibold">
                    <a href="{{ route('admin.platform-registry.index') }}" class="whitespace-nowrap rounded-md bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200" data-i18n="core.docs.tab_admin">{{ __('core.docs.tab_admin') }}</a>
                    <span class="whitespace-nowrap rounded-md bg-gray-900 px-4 py-2 text-white" data-i18n="core.docs.tab_docs">{{ __('core.docs.tab_docs') }}</span>
                    @foreach ([
                        'tasks' => __('core.docs.tab_tasks'),
                        'implementation' => __('core.docs.tab_implementation'),
                        'standards' => __('core.docs.tab_standards'),
                        'registry' => __('core.docs.tab_registry'),
                        'reports' => __('core.docs.tab_reports'),
                        'errors' => __('core.docs.tab_errors'),
                    ] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="whitespace-nowrap rounded-md px-4 py-2">{{ $label }}</button>
                    @endforeach
                </nav>
            </div>

            <section x-show="tab === 'tasks'" x-cloak class="space-y-4">
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.docs.add_task">{{ __('core.docs.add_task') }}</h3>
                    <form method="POST" action="{{ route('admin.documentation.tasks.store') }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_10rem_auto]">
                        @csrf
                        <input name="title" required class="rounded-md border-gray-300 text-sm" placeholder="{{ __('core.docs.task_title') }}">
                        <input name="sort_order" type="number" min="0" max="999999" class="rounded-md border-gray-300 text-sm" placeholder="{{ __('core.docs.order') }}">
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-i18n="core.docs.add">{{ __('core.docs.add') }}</button>
                        <textarea name="details" class="md:col-span-3 rounded-md border-gray-300 text-sm" rows="3" placeholder="{{ __('core.docs.details') }}"></textarea>
                    </form>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-3" data-i18n="core.docs.task">{{ __('core.docs.task') }}</th>
                                <th class="px-4 py-3" data-i18n="core.docs.order">{{ __('core.docs.order') }}</th>
                                <th class="px-4 py-3" data-i18n="core.docs.status">{{ __('core.docs.status') }}</th>
                                <th class="px-4 py-3 text-right" data-i18n="core.docs.actions">{{ __('core.docs.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tasks as $task)
                                <tr>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('admin.documentation.tasks.update', $task->id) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <input name="title" value="{{ $task->title }}" class="w-full rounded-md border-gray-300 text-sm font-semibold">
                                            <textarea name="details" rows="2" class="w-full rounded-md border-gray-300 text-sm">{{ $task->details }}</textarea>
                                    </td>
                                    <td class="px-4 py-3 align-top"><input name="sort_order" type="number" value="{{ $task->sort_order }}" class="w-24 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-4 py-3 align-top">
                                        <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $task->completed_at ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">{{ $task->completed_at ? __('core.docs.done') : __('core.docs.open') }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-i18n="core.common.save">{{ __('core.common.save') }}</button>
                                        </form>
                                            <form method="POST" action="{{ route('admin.documentation.tasks.toggle', $task->id) }}">@csrf @method('PATCH')<button class="rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50" data-i18n="core.docs.toggle">{{ __('core.docs.toggle') }}</button></form>
                                            <form method="POST" action="{{ route('admin.documentation.tasks.destroy', $task->id) }}" onsubmit="return confirm('{{ __('core.docs.delete_confirm') }}')">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-i18n="core.common.delete">{{ __('core.common.delete') }}</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-platform-pagination :paginator="$tasks" page-name="tasks_page" />
            </section>

            <section x-show="tab === 'implementation'" x-cloak class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.docs.impl_log">{{ __('core.docs.impl_log') }}</h3>
                <div class="mt-4 overflow-x-auto rounded-md border">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3" data-i18n="core.docs.operation">{{ __('core.docs.operation') }}</th><th class="px-4 py-3" data-i18n="core.docs.target">{{ __('core.docs.target') }}</th><th class="px-4 py-3" data-i18n="core.docs.status">{{ __('core.docs.status') }}</th><th class="px-4 py-3" data-i18n="core.docs.message">{{ __('core.docs.message') }}</th><th class="px-4 py-3" data-i18n="core.docs.time">{{ __('core.docs.time') }}</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($operationLogs as $log)
                                <tr><td class="px-4 py-3 font-mono text-xs">{{ $log->operation_type }}</td><td class="px-4 py-3">{{ $log->target_type }} / {{ $log->target_slug }}</td><td class="px-4 py-3">{{ $log->status }}</td><td class="px-4 py-3">{{ $log->message }}</td><td class="px-4 py-3">{{ $log->created_at }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500" data-i18n="core.docs.no_logs">{{ __('core.docs.no_logs') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-platform-pagination :paginator="$operationLogs" page-name="operations_page" />
            </section>

            <section x-show="tab === 'standards'" x-cloak class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.docs.plugin_standard">{{ __('core.docs.plugin_standard') }}</h3>
                    <div class="mt-4 space-y-2 text-sm text-gray-700">
                        <p data-i18n="core.docs.zip_note">{{ __('core.docs.zip_note') }} <span class="font-mono">module.json</span>.</p>
                        <p data-i18n="core.docs.required_fields">{{ __('core.docs.required_fields') }} <span class="font-mono">name, slug, version, provider</span>.</p>
                        <p data-i18n="core.docs.optional_fields">{{ __('core.docs.optional_fields') }} <span class="font-mono">provider_file, routes, permissions, menus, hooks, functions, assets, docs</span>.</p>
                        <p data-i18n="core.docs.admin_routes_note">{{ __('core.docs.admin_routes_note') }} <span class="font-mono">admin/plugins/{slug}</span>.</p>
                        <p data-i18n="core.docs.manifest_note">{{ __('core.docs.manifest_note') }}</p>
                    </div>
                </div>
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.docs.manifest_example">{{ __('core.docs.manifest_example') }}</h3>
                    <pre class="mt-4 max-h-[34rem] overflow-auto rounded-md bg-gray-950 p-4 text-xs leading-5 text-gray-100">{{ $manifestExample }}</pre>
                </div>
            </section>

            <section x-show="tab === 'registry'" x-cloak class="grid gap-4 xl:grid-cols-3">
                @foreach ([['Functions', $functions], ['Hooks', $hooks], ['Routes', $routes]] as [$title, $items])
                    <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900" data-i18n="core.docs.{{ strtolower($title) }}">{{ __('core.docs.'.strtolower($title)) }}</h3><span class="text-sm text-gray-500">{{ count($items) }}</span></div>
                        <div class="mt-4 max-h-[36rem] space-y-2 overflow-auto">
                            @foreach ($items as $name => $definition)
                                <div class="rounded-md border border-gray-100 p-3"><div class="break-all font-mono text-xs text-gray-900">{{ $name }}</div><div class="mt-1 text-xs text-gray-500">{{ $definition['description'] ?? ($definition['uri'] ?? '') }}</div><div class="mt-1 text-xs {{ ($definition['status'] ?? 'active') === 'active' ? 'text-green-700' : 'text-yellow-700' }}">{{ $definition['status'] ?? 'active' }} {{ isset($definition['plugin']) ? ' / '.$definition['plugin'] : '' }}</div></div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if ($unregisteredRoutes !== [])
                    <div class="rounded-md border border-yellow-200 bg-yellow-50 p-6 shadow-sm xl:col-span-3">
                        <h3 class="font-semibold text-yellow-950" data-i18n="core.docs.unregistered">{{ __('core.docs.unregistered') }}</h3>
                        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><tbody>@foreach ($unregisteredRoutes as $route)<tr class="border-t border-yellow-200"><td class="py-2 pr-4 font-mono">{{ $route['name'] }}</td><td class="py-2 pr-4">{{ $route['method'] }}</td><td class="py-2 font-mono">{{ $route['uri'] }}</td></tr>@endforeach</tbody></table></div>
                    </div>
                @endif
            </section>

            <section x-show="tab === 'reports'" x-cloak class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.docs.reports">{{ __('core.docs.reports') }}</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($reports as $report)
                        <div class="rounded-md border border-gray-100 p-4">
                            <div class="break-all font-mono text-sm font-semibold text-gray-900">{{ $report['name'] }}</div>
                            <div class="mt-2 text-xs text-gray-500">{{ $report['modified_at'] }} / {{ $report['size'] }}</div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ $report['view_url'] }}" target="_blank" class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50" data-i18n="core.docs.open">
                                    {{ __('core.docs.open') }}
                                </a>
                                <a href="{{ $report['download_url'] }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-i18n="core.docs.download">
                                    {{ __('core.docs.download') }}
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500" data-i18n="core.docs.no_reports">{{ __('core.docs.no_reports') }}</p>
                    @endforelse
                </div>
            </section>

            <section x-show="tab === 'errors'" x-cloak class="grid gap-4 lg:grid-cols-3">
                @foreach ([['Platform Success', $successLogs, 'text-green-700'], ['Platform Errors', $errorLogs, 'text-red-700'], ['Laravel Log Tail', $laravelErrors, 'text-gray-700']] as [$title, $lines, $tone])
                    <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold {{ $tone }}">{{ $title }}</h3>
                        <pre class="mt-4 max-h-[34rem] overflow-auto whitespace-pre-wrap rounded-md bg-gray-950 p-4 text-xs leading-5 text-gray-100">{{ implode("\n", $lines) }}</pre>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
</x-app-layout>
