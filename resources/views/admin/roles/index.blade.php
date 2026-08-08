<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title">Roles & Permissions</h2>
    </x-slot>

    <div class="ainpa-admin-page">
        <div
            class="ainpa-page-container admin-access-page"
            x-data="{ createOpen: @js(old('_form') === 'create'), open: @js(str_starts_with((string) old('_form'), 'update-') ? str_replace('update-', '', (string) old('_form')) : null) }"
            @keydown.escape.window="createOpen = false"
        >
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-success">{{ session('status') }}</div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="ainpa-alert ainpa-alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="admin-access-toolbar">
                <div>
                    <h3 class="admin-access-title">Roles</h3>
                    <p class="admin-access-subtitle">Create roles, rename existing roles, and assign registered permissions.</p>
                </div>
                <button type="button" class="ainpa-button ainpa-button-primary" @click="createOpen = true">
                    Create Role
                </button>
            </div>

            <section class="admin-access-card">
                <div class="admin-access-card-header">
                    <h3 class="admin-access-card-title">Registered Roles</h3>
                </div>

                <div class="admin-access-accordion">
                    @foreach ($roles as $role)
                        <article class="admin-access-accordion-item">
                            <button
                                type="button"
                                class="admin-access-accordion-button"
                                @click="open = open === '{{ $role->id }}' ? null : '{{ $role->id }}'"
                                :aria-expanded="(open === '{{ $role->id }}').toString()"
                            >
                                <span class="admin-access-entity">
                                    <span class="admin-access-entity-name">{{ $role->name }}</span>
                                    <span class="admin-access-entity-meta">{{ $role->users_count }} assigned users</span>
                                </span>
                                <span class="admin-access-count">{{ $role->permissions->count() }} permissions</span>
                            </button>

                            <div x-show="open === '{{ $role->id }}'" x-cloak class="admin-access-panel">
                                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="admin-access-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form" value="update-{{ $role->id }}">

                                    <label class="admin-field">
                                        <span class="admin-field-label">Role name</span>
                                        <input
                                            name="name"
                                            class="admin-input"
                                            value="{{ old('_form') === 'update-'.$role->id ? old('name', $role->name) : $role->name }}"
                                            required
                                        >
                                        <span class="admin-field-hint">Renaming a role changes its platform role key. Keep system role names stable unless this is intentional.</span>
                                    </label>

                                    @include('admin.roles._permission-fields', [
                                        'selectedPermissions' => old('_form') === 'update-'.$role->id
                                            ? old('permissions', $role->permissions->pluck('name')->all())
                                            : $role->permissions->pluck('name')->all(),
                                    ])

                                    <div class="admin-access-actions">
                                        <button type="submit" class="ainpa-button ainpa-button-primary">Save Role</button>
                                    </div>
                                </form>

                                <div class="admin-danger-zone">
                                    @if ($role->name === 'super-admin')
                                        <p class="admin-access-note">The super-admin role is protected and cannot be deleted.</p>
                                    @elseif ($role->users_count > 0)
                                        <p class="admin-access-note">
                                            This role is assigned to {{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }}. Remove it from users before deleting it.
                                        </p>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.roles.destroy', $role) }}"
                                            onsubmit="return confirm('Delete this role permanently? This cannot be undone.')"
                                            class="admin-danger-form"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ainpa-button ainpa-button-danger">Delete Role</button>
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
                                <h3 id="create-role-title" class="admin-modal-title">Create Role</h3>
                                <p class="admin-modal-subtitle">Name the role and select the permissions it should receive.</p>
                            </div>
                            <button type="button" class="admin-modal-close" @click="createOpen = false">
                                Close
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.roles.store') }}" class="admin-modal-form">
                            @csrf
                            <input type="hidden" name="_form" value="create">

                            <div class="admin-modal-body">
                                <label class="admin-field">
                                    <span class="admin-field-label">Role name</span>
                                    <input name="name" value="{{ old('_form') === 'create' ? old('name') : '' }}" class="admin-input" required>
                                </label>

                                @include('admin.roles._permission-fields', [
                                    'selectedPermissions' => old('_form') === 'create' ? old('permissions', []) : [],
                                ])
                            </div>

                            <div class="admin-modal-footer">
                                <button type="button" class="admin-modal-cancel" @click="createOpen = false">
                                    Cancel
                                </button>
                                <button type="submit" class="ainpa-button ainpa-button-primary">Create Role</button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
