<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Admin / Docs</h2>
                <p class="mt-1 text-sm text-gray-500">Documentation tasks, implementation logs, plugin standards, reports, and errors.</p>
            </div>
            <a href="{{ route('admin.platform-registry.index') }}" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Admin</a>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ tab: 'tasks' }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 overflow-x-auto rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                <nav class="flex gap-2 text-sm font-semibold">
                    <a href="{{ route('admin.platform-registry.index') }}" class="whitespace-nowrap rounded-md bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">Admin</a>
                    <span class="whitespace-nowrap rounded-md bg-gray-900 px-4 py-2 text-white">Docs</span>
                    @foreach ([
                        'tasks' => 'Tasks',
                        'implementation' => 'Implementation Log',
                        'standards' => 'Plugin Standards',
                        'registry' => 'Registry',
                        'reports' => 'Reports',
                        'errors' => 'Errors',
                    ] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="whitespace-nowrap rounded-md px-4 py-2">{{ $label }}</button>
                    @endforeach
                </nav>
            </div>

            <section x-show="tab === 'tasks'" x-cloak class="space-y-4">
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Add Documentation Task</h3>
                    <form method="POST" action="{{ route('admin.documentation.tasks.store') }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_10rem_auto]">
                        @csrf
                        <input name="title" required class="rounded-md border-gray-300 text-sm" placeholder="Task title">
                        <input name="sort_order" type="number" min="0" max="999999" class="rounded-md border-gray-300 text-sm" placeholder="Order">
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Add</button>
                        <textarea name="details" class="md:col-span-3 rounded-md border-gray-300 text-sm" rows="3" placeholder="Details"></textarea>
                    </form>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Task</th>
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
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
                                        <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $task->completed_at ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">{{ $task->completed_at ? 'Done' : 'Open' }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Save</button>
                                        </form>
                                            <form method="POST" action="{{ route('admin.documentation.tasks.toggle', $task->id) }}">@csrf @method('PATCH')<button class="rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Toggle</button></form>
                                            <form method="POST" action="{{ route('admin.documentation.tasks.destroy', $task->id) }}" onsubmit="return confirm('Delete this task?')">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section x-show="tab === 'implementation'" x-cloak class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Implementation Log</h3>
                <div class="mt-4 overflow-x-auto rounded-md border">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3">Operation</th><th class="px-4 py-3">Target</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Message</th><th class="px-4 py-3">Time</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($operationLogs as $log)
                                <tr><td class="px-4 py-3 font-mono text-xs">{{ $log->operation_type }}</td><td class="px-4 py-3">{{ $log->target_type }} / {{ $log->target_slug }}</td><td class="px-4 py-3">{{ $log->status }}</td><td class="px-4 py-3">{{ $log->message }}</td><td class="px-4 py-3">{{ $log->created_at }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No operation logs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section x-show="tab === 'standards'" x-cloak class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Plugin Standard</h3>
                    <div class="mt-4 space-y-2 text-sm text-gray-700">
                        <p>Every plugin must be uploaded as a ZIP and contain a root <span class="font-mono">module.json</span>.</p>
                        <p>Required fields: <span class="font-mono">name, slug, version, provider</span>.</p>
                        <p>Optional standard fields: <span class="font-mono">provider_file, routes, permissions, menus, hooks, functions, assets, docs</span>.</p>
                        <p>Admin routes default to <span class="font-mono">admin/plugins/{slug}</span>.</p>
                        <p>Functions and hooks must be declared in the manifest before they are considered registered.</p>
                    </div>
                </div>
                <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Manifest Example</h3>
                    <pre class="mt-4 max-h-[34rem] overflow-auto rounded-md bg-gray-950 p-4 text-xs leading-5 text-gray-100">{{ $manifestExample }}</pre>
                </div>
            </section>

            <section x-show="tab === 'registry'" x-cloak class="grid gap-4 xl:grid-cols-3">
                @foreach ([['Functions', $functions], ['Hooks', $hooks], ['Routes', $routes]] as [$title, $items])
                    <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900">{{ $title }}</h3><span class="text-sm text-gray-500">{{ count($items) }}</span></div>
                        <div class="mt-4 max-h-[36rem] space-y-2 overflow-auto">
                            @foreach ($items as $name => $definition)
                                <div class="rounded-md border border-gray-100 p-3"><div class="break-all font-mono text-xs text-gray-900">{{ $name }}</div><div class="mt-1 text-xs text-gray-500">{{ $definition['description'] ?? ($definition['uri'] ?? '') }}</div><div class="mt-1 text-xs {{ ($definition['status'] ?? 'active') === 'active' ? 'text-green-700' : 'text-yellow-700' }}">{{ $definition['status'] ?? 'active' }} {{ isset($definition['plugin']) ? ' / '.$definition['plugin'] : '' }}</div></div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if ($unregisteredRoutes !== [])
                    <div class="rounded-md border border-yellow-200 bg-yellow-50 p-6 shadow-sm xl:col-span-3">
                        <h3 class="font-semibold text-yellow-950">Unregistered Routes</h3>
                        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><tbody>@foreach ($unregisteredRoutes as $route)<tr class="border-t border-yellow-200"><td class="py-2 pr-4 font-mono">{{ $route['name'] }}</td><td class="py-2 pr-4">{{ $route['method'] }}</td><td class="py-2 font-mono">{{ $route['uri'] }}</td></tr>@endforeach</tbody></table></div>
                    </div>
                @endif
            </section>

            <section x-show="tab === 'reports'" x-cloak class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Implementation Reports</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($reports as $report)
                        <div class="rounded-md border border-gray-100 p-4">
                            <div class="break-all font-mono text-sm font-semibold text-gray-900">{{ $report['name'] }}</div>
                            <div class="mt-2 text-xs text-gray-500">{{ $report['modified_at'] }} / {{ $report['size'] }}</div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ $report['view_url'] }}" target="_blank" class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                    Open
                                </a>
                                <a href="{{ $report['download_url'] }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Download
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No reports found.</p>
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
