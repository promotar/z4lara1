<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title">Users</h2>
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
                    <h3 class="admin-access-title">Users</h3>
                    <p class="admin-access-subtitle">Create users, update account access, and assign roles or direct permissions.</p>
                </div>
                <button type="button" class="ainpa-button ainpa-button-primary" @click="createOpen = true">
                    Create User
                </button>
            </div>

            <section class="admin-access-card">
                <div class="admin-access-card-header">
                    <h3 class="admin-access-card-title">Registered Users</h3>
                </div>

                <div class="admin-access-accordion">
                    @foreach ($users as $managedUser)
                        <article class="admin-access-accordion-item">
                            <button
                                type="button"
                                class="admin-access-accordion-button"
                                @click="open = open === '{{ $managedUser->id }}' ? null : '{{ $managedUser->id }}'"
                                :aria-expanded="(open === '{{ $managedUser->id }}').toString()"
                            >
                                <span class="admin-access-entity">
                                    <span class="admin-access-entity-name">{{ $managedUser->name }}</span>
                                    <span class="admin-access-entity-meta">{{ $managedUser->email }}</span>
                                </span>
                                <span class="admin-access-count">
                                    {{ $managedUser->roles->count() }} roles / {{ $managedUser->permissions->count() }} direct permissions
                                    · {{ $managedUser->email_verified_at ? 'Email verified' : 'Email not verified' }}
                                </span>
                            </button>

                            <div x-show="open === '{{ $managedUser->id }}'" x-cloak class="admin-access-panel">
                                <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="admin-access-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form" value="update-{{ $managedUser->id }}">

                                    <div class="admin-access-form-grid">
                                        <label class="admin-field">
                                            <span class="admin-field-label">Name</span>
                                            <input name="name" value="{{ old('_form') === 'update-'.$managedUser->id ? old('name', $managedUser->name) : $managedUser->name }}" class="admin-input" required>
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label">Email</span>
                                            <input name="email" type="email" value="{{ old('_form') === 'update-'.$managedUser->id ? old('email', $managedUser->email) : $managedUser->email }}" class="admin-input" required>
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label">New Password</span>
                                            <input name="password" type="password" class="admin-input" autocomplete="new-password">
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label">Confirm New Password</span>
                                            <input name="password_confirmation" type="password" class="admin-input" autocomplete="new-password">
                                        </label>
                                    </div>

                                    <div class="admin-access-stack">
                                        <div>
                                            <div class="admin-field-label">Roles</div>
                                            <div class="admin-option-grid admin-option-grid-compact">
                                                @foreach ($roles as $role)
                                                    <label class="admin-option">
                                                        <input
                                                            type="checkbox"
                                                            name="roles[]"
                                                            value="{{ $role->name }}"
                                                            @checked(in_array($role->name, old('_form') === 'update-'.$managedUser->id ? old('roles', $managedUser->roles->pluck('name')->all()) : $managedUser->roles->pluck('name')->all(), true))
                                                        >
                                                        <span>{{ $role->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <div class="admin-field-label">Direct Permissions</div>
                                            <div class="admin-option-grid">
                                                @foreach ($permissions as $permission)
                                                    <label class="admin-option">
                                                        <input
                                                            type="checkbox"
                                                            name="permissions[]"
                                                            value="{{ $permission->name }}"
                                                            @checked(in_array($permission->name, old('_form') === 'update-'.$managedUser->id ? old('permissions', $managedUser->permissions->pluck('name')->all()) : $managedUser->permissions->pluck('name')->all(), true))
                                                        >
                                                        <span>{{ $permission->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    <div class="admin-access-actions">
                                        <button type="submit" class="ainpa-button ainpa-button-primary">Save User</button>
                                    </div>
                                </form>

                                <div class="admin-access-actions">
                                    @if ($managedUser->email_verified_at)
                                        <p class="admin-access-note">
                                            Email verified on {{ $managedUser->email_verified_at->format('Y-m-d H:i') }}.
                                        </p>
                                    @else
                                        <div>
                                            <p class="admin-access-note">
                                                This email is not verified. An administrator can approve it without requiring the user to open a verification email.
                                            </p>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.users.verify-email', $managedUser) }}"
                                                onsubmit="return confirm('Verify this user email as an administrator?')"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="ainpa-button ainpa-button-primary">Verify Email as Admin</button>
                                            </form>
                                        </div>
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
                    aria-labelledby="create-user-title"
                >
                    <div class="admin-modal-backdrop" @click="createOpen = false"></div>

                    <section class="admin-modal-panel" x-transition.scale.origin.center>
                        <div class="admin-modal-header">
                            <div>
                                <h3 id="create-user-title" class="admin-modal-title">Create User</h3>
                                <p class="admin-modal-subtitle">Create the account and assign roles or direct permissions.</p>
                            </div>
                            <button type="button" class="admin-modal-close" @click="createOpen = false">
                                Close
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.users.store') }}" class="admin-modal-form">
                            @csrf
                            <input type="hidden" name="_form" value="create">

                            <div class="admin-modal-body">
                                <div class="admin-access-form-grid">
                                    <label class="admin-field">
                                        <span class="admin-field-label">Name</span>
                                        <input name="name" value="{{ old('_form') === 'create' ? old('name') : '' }}" class="admin-input" required>
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label">Email</span>
                                        <input name="email" type="email" value="{{ old('_form') === 'create' ? old('email') : '' }}" class="admin-input" required>
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label">Password</span>
                                        <input name="password" type="password" class="admin-input" required autocomplete="new-password">
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label">Confirm Password</span>
                                        <input name="password_confirmation" type="password" class="admin-input" required autocomplete="new-password">
                                    </label>
                                </div>

                                <div class="admin-access-stack">
                                    <div>
                                        <div class="admin-field-label">Roles</div>
                                        <div class="admin-option-grid admin-option-grid-compact">
                                            @foreach ($roles as $role)
                                                <label class="admin-option">
                                                    <input
                                                        type="checkbox"
                                                        name="roles[]"
                                                        value="{{ $role->name }}"
                                                        @checked(in_array($role->name, old('_form') === 'create' ? old('roles', []) : [], true))
                                                    >
                                                    <span>{{ $role->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <div class="admin-field-label">Direct Permissions</div>
                                        <div class="admin-option-grid">
                                            @foreach ($permissions as $permission)
                                                <label class="admin-option">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permission->name }}"
                                                        @checked(in_array($permission->name, old('_form') === 'create' ? old('permissions', []) : [], true))
                                                    >
                                                    <span>{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-modal-footer">
                                <button type="button" class="admin-modal-cancel" @click="createOpen = false">
                                    Cancel
                                </button>
                                <button type="submit" class="ainpa-button ainpa-button-primary">Create User</button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
