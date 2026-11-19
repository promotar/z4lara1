<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <p class="ainpa-auth-kicker" data-i18n="core.auth.security_check">{{ __('core.auth.security_check') }}</p>
        <h1 data-i18n="core.auth.confirm_title">{{ __('core.auth.confirm_title') }}</h1>
        <p data-i18n="core.auth.confirm_subtitle">{{ __('core.auth.confirm_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="ainpa-auth-form ainpa-auth-form--password ainpa-auth-form--compact">
        @csrf

        <label class="ainpa-auth-field" for="password">
            <span data-i18n="core.auth.password">{{ __('core.auth.password') }}</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="{{ __('core.auth.password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit" data-i18n="core.auth.confirm_continue">{{ __('core.auth.confirm_continue') }}</button>
    </form>
</x-guest-layout>
