<x-app-layout>
    <x-slot name="header">
        <div class="ainpa-page-toolbar">
            <h2 class="ainpa-page-title">Plugins</h2>
            <a href="{{ route('admin.plugins.create') }}" class="ainpa-button ainpa-button-primary">Install Plugin</a>
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

            <div class="ainpa-card ainpa-table-card">
                <table class="ainpa-data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Installed</th>
                            <th>Settings</th>
                            <th>Path</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plugins as $plugin)
                            <tr>
                                <td class="ainpa-table-primary">{{ $plugin->name }}</td>
                                <td><span class="ainpa-code">{{ $plugin->slug }}</span></td>
                                <td>{{ $plugin->version }}</td>
                                <td>
                                    <span class="ainpa-status-badge ainpa-status-{{ $plugin->status === 'active' ? 'active' : 'inactive' }}">
                                        {{ $plugin->status }}
                                    </span>
                                    @if ($plugin->isCore())
                                        <span class="ainpa-status-badge ainpa-status-active" title="Required by the platform and protected from deactivation or uninstall.">core</span>
                                    @endif
                                    @if ($plugin->isDefaultAdminTheme())
                                        <span class="ainpa-status-badge ainpa-status-active" title="Default fallback admin theme. Another admin theme can replace it, but it cannot be directly disabled.">default admin theme</span>
                                    @elseif ($plugin->isAdminTheme())
                                        <span class="ainpa-status-badge ainpa-status-inactive" title="Only one admin theme can be active at a time.">admin theme</span>
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
                                            <button class="ainpa-button ainpa-button-danger ainpa-button-compact">Deactivate</button>
                                        </form>
                                    @elseif ($plugin->status === 'active' && $plugin->isDefaultAdminTheme())
                                        <button class="ainpa-button ainpa-button-compact" type="button" disabled title="The default admin theme is restored automatically when no custom admin theme is active.">Default theme</button>
                                    @elseif ($plugin->status === 'active')
                                        <button class="ainpa-button ainpa-button-compact" type="button" disabled title="Core plugins are required by the platform.">Core plugin</button>
                                    @else
                                        <form method="POST" action="{{ route('admin.plugins.activate', $plugin->slug) }}" class="ainpa-action-form">
                                            @csrf @method('PATCH')
                                            <button class="ainpa-button ainpa-button-success ainpa-button-compact">Activate</button>
                                        </form>
                                        @unless ($plugin->isCore())
                                            <form method="POST" action="{{ route('admin.plugins.destroy', $plugin->slug) }}" class="ainpa-action-form" onsubmit="if (this.dataset.submitted === 'true') return false; if (!confirm('Permanently delete {{ $plugin->slug }}? All plugin data, database tables, settings, permissions, assets, source files, and package archives will be deleted. This cannot be undone.')) return false; this.dataset.submitted = 'true'; const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Deleting...';">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="purge_confirmation" value="{{ $plugin->slug }}">
                                                <button type="submit" class="ainpa-button ainpa-button-danger ainpa-button-compact">Purge / Delete data</button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="ainpa-empty-table">No plugins installed yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
