<x-guest-layout>
    <div class="ainpa-auth-heading">
        <p class="ainpa-auth-kicker">{.auth.welcome_back}</p>
        <h1>{.auth.sign_in}</h1>
        <p>{.auth.login_subtitle}</p>
    </div>

    <x-auth-session-status class="ainpa-auth-status ainpa-auth-status--success" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="ainpa-auth-form">
        @csrf
        @if ($returnTo ?? null)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <label class="ainpa-auth-field" for="email">
            <span data-i18n="core.auth.email">{{ __('core.auth.email') }}</span>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="{{ __('core.auth.email_placeholder') }}"
            >
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span data-i18n="core.auth.password">{{ __('core.auth.password') }}</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('core.auth.password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <div class="ainpa-auth-row">
            <label class="ainpa-auth-check" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span data-i18n="core.auth.remember_me">{{ __('core.auth.remember_me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" data-i18n="core.auth.forgot_password">{{ __('core.auth.forgot_password') }}</a>
            @endif
        </div>

        <button type="submit" class="ainpa-auth-submit">{.auth.sign_in}</button>

        <div class="ainpa-auth-divider"><span data-i18n="core.common.or">{{ __('core.common.or') }}</span></div>

        <p class="ainpa-auth-switch">
            <span>{.auth.no_account}</span>
            <a href="{{ route('register') }}">{.auth.create_account}</a>
        </p>
    </form>
</x-guest-layout>
