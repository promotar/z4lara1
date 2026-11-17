<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <h1 data-i18n="core.auth.forgot_title">{{ __('core.auth.forgot_title') }}</h1>
        <p data-i18n="core.auth.forgot_subtitle">{{ __('core.auth.forgot_subtitle') }}</p>
    </div>

    <x-auth-session-status class="ainpa-auth-status ainpa-auth-status--success" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="ainpa-auth-form ainpa-auth-form--password">
        @csrf

        <label class="ainpa-auth-field" for="email">
            <span data-i18n="core.auth.email">{{ __('core.auth.email') }}</span>
            <span class="ainpa-auth-input-wrap">
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
            </span>
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit" data-i18n="core.auth.send_reset_link">{{ __('core.auth.send_reset_link') }}</button>

        <div class="ainpa-auth-divider"><span data-i18n="core.common.or">{{ __('core.common.or') }}</span></div>

        <p class="ainpa-auth-switch ainpa-auth-switch--back">
            <a href="{{ route('login') }}">
                <span aria-hidden="true">←</span>
                <span data-i18n="core.auth.back_to_sign_in">{{ __('core.auth.back_to_sign_in') }}</span>
            </a>
        </p>
    </form>
</x-guest-layout>
