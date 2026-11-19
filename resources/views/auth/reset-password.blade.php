<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <h1 data-i18n="core.auth.reset_title">{{ __('core.auth.reset_title') }}</h1>
        <p data-i18n="core.auth.reset_subtitle">{{ __('core.auth.reset_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="ainpa-auth-form ainpa-auth-form--password">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label class="ainpa-auth-field" for="email">
            <span data-i18n="core.auth.email">{{ __('core.auth.email') }}</span>
            <span class="ainpa-auth-input-wrap">
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="{{ __('core.auth.email_placeholder') }}"
                >
            </span>
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span data-i18n="core.auth.new_password">{{ __('core.auth.new_password') }}</span>
            <span class="ainpa-auth-input-wrap ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('core.auth.new_password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password_confirmation">
            <span data-i18n="core.auth.confirm_password">{{ __('core.auth.confirm_password') }}</span>
            <span class="ainpa-auth-input-wrap ainpa-auth-password">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('core.auth.confirm_new_password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password_confirmation">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password_confirmation')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit" data-i18n="core.auth.reset_submit">{{ __('core.auth.reset_submit') }}</button>

        <div class="ainpa-auth-divider"><span data-i18n="core.common.or">{{ __('core.common.or') }}</span></div>

        <p class="ainpa-auth-switch ainpa-auth-switch--back">
            <a href="{{ route('login') }}">
                <span aria-hidden="true">←</span>
                <span data-i18n="core.auth.back_to_sign_in">{{ __('core.auth.back_to_sign_in') }}</span>
            </a>
        </p>
    </form>
</x-guest-layout>
