<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin</h2>
            <span class="text-sm text-gray-500">Super Admin</span>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <section class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </section>
            @endif

            @if ($unregisteredRoutes !== [])
                <section class="bg-yellow-50 border border-yellow-200 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-yellow-900">Unregistered Routes Detected</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-yellow-900">
                                    <tr>
                                        <th class="py-2 pr-4">Name</th>
                                        <th class="py-2 pr-4">Method</th>
                                        <th class="py-2 pr-4">URI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-yellow-200">
                                    @foreach ($unregisteredRoutes as $route)
                                        <tr>
                                            <td class="py-2 pr-4 font-mono">{{ $route['name'] }}</td>
                                            <td class="py-2 pr-4">{{ $route['method'] }}</td>
                                            <td class="py-2 pr-4 font-mono">{{ $route['uri'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @endif

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="{ activeTab: @js($activeTab) }">
                <div class="border-b border-gray-200 px-6 pt-6">
                    <nav class="flex flex-wrap gap-2" aria-label="Admin tabs">
                        <button type="button" @click="activeTab = 'functions'" :class="activeTab === 'functions' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Functions
                        </button>
                        <button type="button" @click="activeTab = 'hooks'" :class="activeTab === 'hooks' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Hooks
                        </button>
                        <button type="button" @click="activeTab = 'routes'" :class="activeTab === 'routes' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Routes
                        </button>
                        <a href="{{ route('admin.documentation.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                            Docs
                        </a>
                        <button type="button" @click="activeTab = 'reports'" :class="activeTab === 'reports' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Reports
                        </button>
                        <button type="button" @click="activeTab = 'success'" :class="activeTab === 'success' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Success Log
                        </button>
                        <button type="button" @click="activeTab = 'errors'" :class="activeTab === 'errors' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Error Log
                        </button>
                        <button type="button" @click="activeTab = 'live-log'" :class="activeTab === 'live-log' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" class="rounded-md px-4 py-2 text-sm font-semibold">
                            Live Log
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <div x-show="activeTab === 'functions'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Registered Functions</h3>
                            <span class="text-sm text-gray-500">{{ count($functions) }} items</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($functions as $name => $definition)
                                <div class="border rounded-md p-3">
                                    <div class="font-mono text-sm text-gray-900">{{ $name }}</div>
                                    <div class="text-xs text-gray-500">{{ $definition['description'] ?? 'Registered function' }}</div>
                                    <div class="mt-1 text-xs text-green-700">{{ $definition['status'] ?? 'active' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeTab === 'hooks'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Registered Hooks</h3>
                            <span class="text-sm text-gray-500">{{ count($hooks) }} items</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($hooks as $name => $definition)
                                <div class="border rounded-md p-3">
                                    <div class="font-mono text-sm text-gray-900">{{ $name }}</div>
                                    <div class="text-xs text-gray-500">{{ $definition['description'] ?? 'Registered hook' }}</div>
                                    <div class="mt-1 text-xs text-gray-600">{{ $definition['type'] ?? 'hook' }} / {{ $definition['status'] ?? 'active' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeTab === 'routes'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Registered Routes</h3>
                            <span class="text-sm text-gray-500">{{ count($routes) }} items</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($routes as $name => $definition)
                                <div class="border rounded-md p-3">
                                    <div class="font-mono text-sm text-gray-900">{{ $name }}</div>
                                    <div class="text-xs text-gray-500">{{ $definition['uri'] ?? '' }}</div>
                                    <div class="mt-1 text-xs text-gray-600">{{ implode('|', $definition['methods'] ?? []) }} / {{ $definition['status'] ?? 'active' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeTab === 'reports'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Implementation Reports</h3>
                            <span class="text-sm text-gray-500">{{ count($reports) }} reports</span>
                        </div>
                        @if ($reports === [])
                            <p class="text-sm text-gray-500">No implementation reports found.</p>
                        @else
                            <div class="space-y-2 rounded-md border border-gray-200 bg-gray-100 p-2">
                                @foreach ($reports as $report)
                                    @php $isOpen = $selectedReport && $selectedReport['name'] === $report['name']; @endphp
                                    <section class="overflow-hidden rounded-md border border-gray-200 {{ $isOpen ? 'bg-blue-50/60' : ($loop->even ? 'bg-slate-50' : 'bg-white') }}">
                                        <a href="{{ $isOpen ? route('admin.platform-registry.index', ['tab' => 'reports']) : route('admin.platform-registry.index', ['tab' => 'reports', 'report' => $report['name']]) }}" class="flex min-h-20 items-center justify-between gap-6 px-5 py-4 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="truncate font-mono text-sm font-semibold text-gray-900">{{ $report['name'] }}</div>
                                                <div class="mt-3 flex flex-wrap gap-3 text-xs">
                                                    <span class="rounded-md bg-white px-2.5 py-1 text-gray-600 shadow-sm">{{ $report['modified_at'] }}</span>
                                                    <span class="rounded-md bg-white px-2.5 py-1 text-gray-600 shadow-sm">{{ $report['size'] }}</span>
                                                </div>
                                            </div>
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border {{ $isOpen ? 'border-blue-200 bg-blue-100 text-blue-700' : 'border-gray-200 bg-gray-50 text-gray-600' }} text-lg font-semibold">
                                                {{ $isOpen ? '-' : '+' }}
                                            </span>
                                        </a>

                                        @if ($isOpen)
                                            <div class="border-t border-blue-100 bg-white">
                                                <pre class="max-h-[40rem] overflow-auto whitespace-pre-wrap border-l-4 border-blue-200 p-5 text-sm leading-6 text-gray-900">{{ $selectedReport['content'] }}</pre>
                                            </div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        @endif

                    </div>

                    <div
                        x-show="activeTab === 'live-log'"
                        x-cloak
                        x-data="{
                            logs: [],
                            sources: [],
                            generatedAt: null,
                            loading: false,
                            paused: false,
                            error: null,
                            timer: null,
                            async load() {
                                this.loading = true;
                                this.error = null;

                                try {
                                    const response = await fetch(@js($liveLogUrl) + '?limit=500', {
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                    });

                                    if (! response.ok) {
                                        throw new Error('Live log request failed with status ' + response.status);
                                    }

                                    const payload = await response.json();
                                    this.logs = payload.logs || [];
                                    this.sources = payload.sources || [];
                                    this.generatedAt = payload.generated_at || null;

                                    this.$nextTick(() => {
                                        if (this.$refs.panel && ! this.paused) {
                                            this.$refs.panel.scrollTop = this.$refs.panel.scrollHeight;
                                        }
                                    });
                                } catch (exception) {
                                    this.error = exception.message || 'Live log could not be loaded.';
                                } finally {
                                    this.loading = false;
                                }
                            },
                            start() {
                                this.paused = false;
                                this.load();

                                if (! this.timer) {
                                    this.timer = setInterval(() => {
                                        if (! this.paused) {
                                            this.load();
                                        }
                                    }, 2000);
                                }
                            },
                            stop() {
                                if (this.timer) {
                                    clearInterval(this.timer);
                                    this.timer = null;
                                }
                            },
                            togglePause() {
                                this.paused = ! this.paused;
                            }
                        }"
                        x-init="$watch('activeTab', value => value === 'live-log' ? start() : stop()); if (activeTab === 'live-log') start();"
                    >
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">Live Log</h3>
                                <p class="mt-1 text-sm text-gray-500">Last 500 log lines from Laravel and active plugin log folders. Auto-refreshes every 2 seconds.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600" x-text="generatedAt ? 'Updated ' + generatedAt : 'Waiting for first load'"></span>
                                <button type="button" @click="togglePause()" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="paused ? 'Resume' : 'Pause'"></button>
                                <button type="button" @click="load()" :disabled="loading" class="rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span x-text="loading ? 'Refreshing...' : 'Refresh now'"></span>
                                </button>
                            </div>
                        </div>

                        <template x-if="error">
                            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>
                        </template>

                        <div class="mb-4 grid gap-3 md:grid-cols-4">
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lines</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="logs.length"></div>
                            </div>
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sources</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="sources.length"></div>
                            </div>
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</div>
                                <div class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-semibold" :class="paused ? 'bg-yellow-50 text-yellow-700' : 'bg-green-50 text-green-700'" x-text="paused ? 'Paused' : 'Live'"></div>
                            </div>
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Limit</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">500</div>
                            </div>
                        </div>

                        <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Detected Log Sources</div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="source in sources" :key="source.source + source.file + source.path">
                                    <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700" :title="source.path">
                                        <span x-text="source.source"></span>
                                        <span class="text-gray-400">/</span>
                                        <span x-text="source.file"></span>
                                    </span>
                                </template>
                                <template x-if="sources.length === 0">
                                    <span class="text-sm text-gray-500">No readable log sources found yet.</span>
                                </template>
                            </div>
                        </div>

                        <div x-ref="panel" class="max-h-[42rem] overflow-auto rounded-md bg-gray-950 p-4 font-mono text-xs leading-6 text-gray-100">
                            <template x-if="logs.length === 0">
                                <div class="text-gray-400">No log entries found.</div>
                            </template>
                            <template x-for="(entry, index) in logs" :key="index + entry.source + entry.file + entry.line">
                                <div class="grid gap-2 border-b border-white/5 py-1 md:grid-cols-[9rem_8rem_6rem_minmax(0,1fr)]">
                                    <span class="text-gray-500" x-text="entry.timestamp || '-'"></span>
                                    <span class="truncate text-blue-300" :title="entry.path" x-text="entry.source"></span>
                                    <span class="uppercase" :class="{
                                        'text-red-300': ['error', 'critical', 'alert', 'emergency'].includes(entry.level),
                                        'text-yellow-300': ['warning', 'warn'].includes(entry.level),
                                        'text-green-300': entry.level === 'info',
                                        'text-gray-400': ! entry.level
                                    }" x-text="entry.level || 'log'"></span>
                                    <span class="whitespace-pre-wrap break-words" x-text="entry.message || entry.line"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="activeTab === 'success'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Success Log</h3>
                            <span class="text-sm text-gray-500">{{ count($successLogs) }} entries</span>
                        </div>
                        <pre class="max-h-[32rem] overflow-auto rounded bg-gray-950 p-4 text-xs text-green-200">{{ $successLogs === [] ? 'No success log entries.' : implode(PHP_EOL, $successLogs) }}</pre>
                    </div>

                    <div x-show="activeTab === 'errors'" x-cloak>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Error Log</h3>
                            <span class="text-sm text-gray-500">{{ count($errorLogs) }} entries</span>
                        </div>
                        <pre class="max-h-[32rem] overflow-auto rounded bg-gray-950 p-4 text-xs text-red-200">{{ $errorLogs === [] ? 'No error log entries.' : implode(PHP_EOL, $errorLogs) }}</pre>
                    </div>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
