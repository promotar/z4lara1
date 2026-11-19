<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title" data-i18n="core.users.title">{{ __('core.users.title') }}</h2>
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

            <div class="admin-access-toolbar admin-user-toolbar">
                <div class="admin-user-toolbar-actions">
                    <form method="GET" action="{{ route('admin.users.index') }}" class="admin-user-search" x-ref="userSearchForm">
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            class="admin-input"
                            placeholder="{{ __('core.users.search_placeholder') }}"
                            autocomplete="off"
                            aria-label="{{ __('core.users.search_aria') }}"
                            @if ($search !== '') autofocus @endif
                            @input.debounce.400ms="$refs.userSearchForm.requestSubmit()"
                        >
                        <button type="submit" class="ainpa-button" data-i18n="core.users.search">{{ __('core.users.search') }}</button>
                        @if ($search !== '')
                            <a href="{{ route('admin.users.index') }}" class="admin-user-search-clear" data-i18n="core.users.clear">{{ __('core.users.clear') }}</a>
                        @endif
                    </form>
                    <button type="button" class="ainpa-button ainpa-button-primary" @click="createOpen = true" data-i18n="core.users.new">
                        {{ __('core.users.new') }}
                    </button>
                </div>
            </div>

            <section class="admin-access-card">
                <div class="admin-access-card-header admin-user-list-header">
                    <h3 class="admin-access-card-title" data-i18n="core.users.accounts">{{ __('core.users.accounts') }}</h3>
                    <p class="admin-user-results">
                        {{ __('core.users.account_count', ['count' => number_format($users->total())]) }}
                    </p>
                </div>

                <div class="admin-access-accordion">
                    @forelse ($users as $managedUser)
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
                                    @if ($managedUser->phone)<span class="admin-user-phone">{{ $managedUser->phone }}</span>@endif
                                </span>
                                <span class="admin-access-count">
                                    {{ __('core.users.roles_count', ['count' => $managedUser->roles->count()]) }}
                                    · {{ $managedUser->email_verified_at ? __('core.users.active') : __('core.users.pending_activation') }}
                                </span>
                            </button>

                            <div x-show="open === '{{ $managedUser->id }}'" x-cloak class="admin-access-panel">
                                <form
                                    id="admin-user-update-{{ $managedUser->id }}"
                                    method="POST"
                                    action="{{ route('admin.users.update', $managedUser) }}"
                                    class="admin-access-form"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form" value="update-{{ $managedUser->id }}">

                                    <div class="admin-access-form-grid">
                                        <label class="admin-field">
                                            <span class="admin-field-label" data-i18n="core.users.name">{{ __('core.users.name') }}</span>
                                            <input name="name" value="{{ old('_form') === 'update-'.$managedUser->id ? old('name', $managedUser->name) : $managedUser->name }}" class="admin-input" required>
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label" data-i18n="core.users.email">{{ __('core.users.email') }}</span>
                                            <input name="email" type="email" value="{{ old('_form') === 'update-'.$managedUser->id ? old('email', $managedUser->email) : $managedUser->email }}" class="admin-input" required>
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label" data-i18n="core.users.new_password">{{ __('core.users.new_password') }}</span>
                                            <input name="password" type="password" class="admin-input" autocomplete="new-password">
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label" data-i18n="core.users.phone">{{ __('core.users.phone') }}</span>
                                            <input name="phone" type="tel" value="{{ old('_form') === 'update-'.$managedUser->id ? old('phone', $managedUser->phone) : $managedUser->phone }}" class="admin-input" autocomplete="tel">
                                        </label>

                                        <label class="admin-field">
                                            <span class="admin-field-label" data-i18n="core.users.confirm_new_password">{{ __('core.users.confirm_new_password') }}</span>
                                            <input name="password_confirmation" type="password" class="admin-input" autocomplete="new-password">
                                        </label>
                                    </div>

                                    <div class="admin-access-stack">
                                        <div>
                                            <div class="admin-field-label" data-i18n="core.users.roles">{{ __('core.users.roles') }}</div>
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
                                    </div>

                                </form>

                                <div class="admin-user-action-bar">
                                    <div class="admin-user-action-status">
                                        @if ($managedUser->email_verified_at)
                                            <span class="admin-user-status-dot" aria-hidden="true"></span>
                                            <p class="admin-access-note">
                                                {{ __('core.users.active_since', ['date' => $managedUser->email_verified_at->format('Y-m-d H:i')]) }}
                                            </p>
                                        @else
                                            <span class="admin-user-status-dot is-pending" aria-hidden="true"></span>
                                            <p class="admin-access-note" data-i18n="core.users.verification_pending">
                                                {{ __('core.users.verification_pending') }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="admin-user-action-buttons">
                                        <button
                                            type="submit"
                                            form="admin-user-update-{{ $managedUser->id }}"
                                            class="ainpa-button ainpa-button-primary"
                                            data-i18n="core.users.save"
                                        >{{ __('core.users.save') }}</button>

                                        @unless ($managedUser->email_verified_at)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.users.verify-email', $managedUser) }}"
                                                class="admin-user-inline-form"
                                                onsubmit="return confirm('{{ __('core.users.verify_confirm') }}')"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="ainpa-button ainpa-button-success" data-i18n="core.users.activate">{{ __('core.users.activate') }}</button>
                                            </form>
                                        @endunless

                                        @if (! auth()->user()->is($managedUser))
                                            <form
                                                method="POST"
                                                action="{{ route('admin.users.destroy', $managedUser) }}"
                                                class="admin-user-inline-form"
                                                onsubmit="return confirm('{{ __('core.users.delete_confirm') }}')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ainpa-button ainpa-button-danger" data-i18n="core.users.delete">{{ __('core.users.delete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="admin-access-note" data-i18n="core.users.empty">{{ __('core.users.empty') }}</div>
                    @endforelse
                </div>

                @if ($users->hasPages())
                    <x-platform-pagination :paginator="$users" />
                @endif
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
                                <h3 id="create-user-title" class="admin-modal-title" data-i18n="core.users.new">{{ __('core.users.new') }}</h3>
                            </div>
                            <button type="button" class="admin-modal-close" @click="createOpen = false" data-i18n="core.common.close">
                                {{ __('core.common.close') }}
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.users.store') }}" class="admin-modal-form">
                            @csrf
                            <input type="hidden" name="_form" value="create">

                            <div class="admin-modal-body">
                                <div class="admin-access-form-grid">
                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.users.name">{{ __('core.users.name') }}</span>
                                        <input name="name" value="{{ old('_form') === 'create' ? old('name') : '' }}" class="admin-input" required>
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.users.email">{{ __('core.users.email') }}</span>
                                        <input name="email" type="email" value="{{ old('_form') === 'create' ? old('email') : '' }}" class="admin-input" required>
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.users.password">{{ __('core.users.password') }}</span>
                                        <input name="password" type="password" class="admin-input" required autocomplete="new-password">
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.users.phone">{{ __('core.users.phone') }}</span>
                                        <input name="phone" type="tel" value="{{ old('_form') === 'create' ? old('phone') : '' }}" class="admin-input" autocomplete="tel">
                                    </label>

                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.users.confirm_password">{{ __('core.users.confirm_password') }}</span>
                                        <input name="password_confirmation" type="password" class="admin-input" required autocomplete="new-password">
                                    </label>
                                </div>

                                <div class="admin-access-stack">
                                    <div>
                                        <div class="admin-field-label" data-i18n="core.users.roles">{{ __('core.users.roles') }}</div>
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
                                </div>
                            </div>

                            <div class="admin-modal-footer">
                                <button type="button" class="admin-modal-cancel" @click="createOpen = false" data-i18n="core.common.cancel">
                                    {{ __('core.common.cancel') }}
                                </button>
                                <button type="submit" class="ainpa-button ainpa-button-primary" data-i18n="core.users.create">{{ __('core.users.create') }}</button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
