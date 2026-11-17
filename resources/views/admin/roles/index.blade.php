<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title" data-i18n="core.roles.title">{{ __('core.roles.title') }}</h2>
    </x-slot>

    <div class="ainpa-admin-page">
        <div
            class="ainpa-page-container admin-access-page"
            x-data="{
                createOpen: @js(old('_form') === 'create'),
                openRoles: @js(str_starts_with((string) old('_form'), 'update-') ? [str_replace('update-', '', (string) old('_form'))] : []),
                toggleRole(roleId) {
                    this.openRoles = this.openRoles.includes(roleId)
                        ? this.openRoles.filter((id) => id !== roleId)
                        : [...this.openRoles, roleId];
                },
            }"
            @keydown.escape.window="createOpen = false; openRoles = []"
        >
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-success">{{ session('status') }}</div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="ainpa-alert ainpa-alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="admin-access-toolbar">
                <div>
                    <h3 class="admin-access-title" data-i18n="core.roles.heading">{{ __('core.roles.heading') }}</h3>
                    <p class="admin-access-subtitle" data-i18n="core.roles.subtitle">{{ __('core.roles.subtitle') }}</p>
                </div>
                <button type="button" class="ainpa-button ainpa-button-primary" @click="createOpen = true" data-i18n="core.roles.create">
                    {{ __('core.roles.create') }}
                </button>
            </div>

            <section class="admin-access-card">
                <div class="admin-access-card-header">
                    <h3 class="admin-access-card-title" data-i18n="core.roles.registered">{{ __('core.roles.registered') }}</h3>
                </div>

                <div class="admin-access-accordion">
                    @foreach ($roles as $role)
                        <article class="admin-access-accordion-item">
                            <button
                                type="button"
                                class="admin-access-accordion-button"
                                @click="toggleRole('{{ $role->id }}')"
                                :aria-expanded="openRoles.includes('{{ $role->id }}').toString()"
                                aria-controls="role-panel-{{ $role->id }}"
                            >
                                <span class="admin-access-entity">
                                    <span class="admin-access-entity-name">{{ $role->name }}</span>
                                    <span class="admin-access-entity-meta">{{ __('core.roles.assigned_users', ['count' => $role->users_count]) }}</span>
                                </span>
                                <span class="admin-access-count admin-permission-accordion-meta">
                                    <span>{{ __('core.roles.permissions_count', ['count' => $role->permissions->count()]) }}</span>
                                    <span class="admin-permission-chevron" :class="{ 'is-open': openRoles.includes('{{ $role->id }}') }" aria-hidden="true"></span>
                                </span>
                            </button>

                            <div id="role-panel-{{ $role->id }}" x-show="openRoles.includes('{{ $role->id }}')" x-transition.opacity.duration.150ms x-cloak class="admin-access-panel">
                                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="admin-access-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form" value="update-{{ $role->id }}">

                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.roles.name">{{ __('core.roles.name') }}</span>
                                        <input
                                            name="name"
                                            class="admin-input"
                                            value="{{ old('_form') === 'update-'.$role->id ? old('name', $role->name) : $role->name }}"
                                            required
                                        >
                                        <span class="admin-field-hint" data-i18n="core.roles.name_hint">{{ __('core.roles.name_hint') }}</span>
                                    </label>

                                    @include('admin.roles._permission-fields', [
                                        'accordionScope' => 'role-'.$role->id,
                                        'selectedPermissions' => old('_form') === 'update-'.$role->id
                                            ? old('permissions', $role->permissions->pluck('name')->all())
                                            : $role->permissions->pluck('name')->all(),
                                    ])

                                    <div class="admin-access-actions">
                                        <button type="submit" class="ainpa-button ainpa-button-primary" data-i18n="core.roles.save">{{ __('core.roles.save') }}</button>
                                    </div>
                                </form>

                                <div class="admin-danger-zone">
                                    @if ($role->name === 'super-admin')
                                        <p class="admin-access-note" data-i18n="core.roles.protected">{{ __('core.roles.protected') }}</p>
                                    @elseif ($role->users_count > 0)
                                        <p class="admin-access-note" data-i18n="core.roles.assigned_warning">
                                            {{ __('core.roles.assigned_warning', ['count' => $role->users_count]) }}
                                        </p>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.roles.destroy', $role) }}"
                                            onsubmit="return confirm('{{ __('core.roles.delete_confirm') }}')"
                                            class="admin-danger-form"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ainpa-button ainpa-button-danger" data-i18n="core.roles.delete">{{ __('core.roles.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <template x-teleport="body">
                <div
                    x-show="createOpen"
                    x-cloak
                    x-transition.opacity
                    class="admin-modal-overlay"
                    aria-modal="true"
                    role="dialog"
                    aria-labelledby="create-role-title"
                >
                    <div class="admin-modal-backdrop" @click="createOpen = false"></div>

                    <section class="admin-modal-panel" x-transition.scale.origin.center>
                        <div class="admin-modal-header">
                            <div>
                                <h3 id="create-role-title" class="admin-modal-title" data-i18n="core.roles.create">{{ __('core.roles.create') }}</h3>
                                <p class="admin-modal-subtitle" data-i18n="core.roles.create_hint">{{ __('core.roles.create_hint') }}</p>
                            </div>
                            <button type="button" class="admin-modal-close" @click="createOpen = false" data-i18n="core.common.close">
                                {{ __('core.common.close') }}
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.roles.store') }}" class="admin-modal-form">
                            @csrf
                            <input type="hidden" name="_form" value="create">

                            <div class="admin-modal-body">
                                <label class="admin-field">
                                    <span class="admin-field-label" data-i18n="core.roles.name">{{ __('core.roles.name') }}</span>
                                    <input name="name" value="{{ old('_form') === 'create' ? old('name') : '' }}" class="admin-input" required>
                                </label>

                                @include('admin.roles._permission-fields', [
                                    'accordionScope' => 'create-role',
                                    'selectedPermissions' => old('_form') === 'create' ? old('permissions', []) : [],
                                ])
                            </div>

                            <div class="admin-modal-footer">
                                <button type="button" class="admin-modal-cancel" @click="createOpen = false" data-i18n="core.common.cancel">
                                    {{ __('core.common.cancel') }}
                                </button>
                                <button type="submit" class="ainpa-button ainpa-button-primary" data-i18n="core.roles.create">{{ __('core.roles.create') }}</button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
