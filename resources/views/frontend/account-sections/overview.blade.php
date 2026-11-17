<div class="ainpa-account-overview">
    <header class="ainpa-account-content-header">
        <div>
            <p class="ainpa-account-kicker" data-i18n="core.account.kicker">{{ __('core.account.kicker') }}</p>
            <h1 data-i18n="core.account.title">{{ __('core.account.title') }}</h1>
            <p data-i18n="core.account.subtitle">{{ __('core.account.subtitle') }}</p>
        </div>
        <span class="ainpa-account-status-pill" data-i18n="core.account.verified">{{ __('core.account.verified') }}</span>
    </header>

    <div class="ainpa-account-detail-grid">
        <article>
            <span data-i18n="core.account.name">{{ __('core.account.name') }}</span>
            <strong>{{ $user->name }}</strong>
        </article>
        <article>
            <span data-i18n="core.account.email">{{ __('core.account.email') }}</span>
            <strong dir="auto">{{ $user->email }}</strong>
        </article>
        <article>
            <span data-i18n="core.account.access">{{ __('core.account.access') }}</span>
            <strong data-i18n="{{ $canAccessDashboard ? 'core.account.access_admin' : 'core.account.access_website' }}">
                {{ $canAccessDashboard ? __('core.account.access_admin') : __('core.account.access_website') }}
            </strong>
        </article>
    </div>

    @if ($user->roles->isNotEmpty())
        <div class="ainpa-account-role-list" aria-label="{{ __('core.account.assigned_roles') }}">
            @foreach ($user->roles as $role)
                <span data-i18n="core.enums.roles.{{ $role->name }}">
                    {{ \App\Platform\Core\Localization\Translation::label('core.enums.roles.'.$role->name, $role->name) }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="ainpa-account-content-actions">
        <a href="{{ route('front.account', ['section' => 'profile']) }}" class="ainpa-account-button ainpa-account-button--primary" data-i18n="core.account.edit_profile">{{ __('core.account.edit_profile') }}</a>
        @if ($canAccessDashboard)
            <a href="{{ route('dashboard') }}" class="ainpa-account-button" data-i18n="core.account.open_dashboard">{{ __('core.account.open_dashboard') }}</a>
        @endif
    </div>
</div>
