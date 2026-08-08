<x-app-layout>
    <x-slot name="header">
        <div class="ainpa-page-toolbar">
            <div>
                <h2 class="ainpa-page-title">Review Plugin Update</h2>
                <p class="text-sm text-gray-600">Confirm the package before replacing the installed plugin files.</p>
            </div>
            <a href="{{ route('admin.plugins.create') }}" class="ainpa-button">Cancel</a>
        </div>
    </x-slot>

    @php
        $old = $pending['old'];
        $new = $pending['new'];
        $versionCompare = (int) ($pending['version_compare'] ?? 0);
        $versionLabel = $versionCompare > 0 ? 'Upgrade' : ($versionCompare === 0 ? 'Same version reinstall' : 'Downgrade');
        $versionClass = $versionCompare >= 0 ? 'ainpa-status-active' : 'ainpa-status-inactive';
    @endphp

    <div class="ainpa-admin-page">
        <div class="ainpa-page-container">
            @if ($errors->any())
                <div class="ainpa-alert ainpa-alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="ainpa-card p-6 space-y-6">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    This is an update, not an uninstall. The platform will disable the old runtime, move old module files out of
                    <span class="ainpa-code">modules/{{ $old['slug'] }}</span>, install the new package, run only pending/new migrations,
                    and preserve existing plugin database data.
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <section class="rounded-md border border-gray-200 p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Installed Plugin</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div><dt class="font-semibold">Name</dt><dd>{{ $old['name'] }}</dd></div>
                            <div><dt class="font-semibold">Slug</dt><dd><span class="ainpa-code">{{ $old['slug'] }}</span></dd></div>
                            <div><dt class="font-semibold">Version</dt><dd>{{ $old['version'] }}</dd></div>
                            <div><dt class="font-semibold">Owner / Author</dt><dd>{{ $old['author'] ?: 'Not provided' }}</dd></div>
                            <div><dt class="font-semibold">Provider</dt><dd><span class="ainpa-code">{{ $old['provider'] }}</span></dd></div>
                            <div><dt class="font-semibold">Status</dt><dd>{{ $old['status'] }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-md border border-gray-200 p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Uploaded Package</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div><dt class="font-semibold">Name</dt><dd>{{ $new['name'] }}</dd></div>
                            <div><dt class="font-semibold">Slug</dt><dd><span class="ainpa-code">{{ $new['slug'] }}</span></dd></div>
                            <div><dt class="font-semibold">Version</dt><dd>{{ $new['version'] }}</dd></div>
                            <div><dt class="font-semibold">Owner / Author</dt><dd>{{ $new['author'] ?: 'Not provided' }}</dd></div>
                            <div><dt class="font-semibold">Provider</dt><dd><span class="ainpa-code">{{ $new['provider'] }}</span></dd></div>
                            <div><dt class="font-semibold">Version Check</dt><dd><span class="ainpa-status-badge {{ $versionClass }}">{{ $versionLabel }}</span></dd></div>
                        </dl>
                    </section>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <section class="rounded-md border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-900">Installed Migrations</h3>
                        @if ($old['migrations'])
                            <ul class="mt-3 space-y-1 text-xs text-gray-700">
                                @foreach ($old['migrations'] as $migration)
                                    <li><span class="ainpa-code">{{ $migration }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-gray-500">No migration files found.</p>
                        @endif
                    </section>

                    <section class="rounded-md border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-900">Uploaded Migrations</h3>
                        @if ($new['migrations'])
                            <ul class="mt-3 space-y-1 text-xs text-gray-700">
                                @foreach ($new['migrations'] as $migration)
                                    <li><span class="ainpa-code">{{ $migration }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-gray-500">No migration files found.</p>
                        @endif
                    </section>
                </div>

                @if ($versionCompare < 0)
                    <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        Warning: the uploaded package version is lower than the installed version. Continue only if this is an intentional rollback package.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.plugins.update.confirm', $token) }}" class="flex items-center gap-3">
                    @csrf
                    <x-primary-button>Confirm Update</x-primary-button>
                    <a href="{{ route('admin.plugins.create') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
