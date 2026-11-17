<x-guest-layout>
    <div class="ainpa-auth-heading">
        <h1 data-i18n="core.auth.register_title">{{ __('core.auth.register_title') }}</h1>
        <p data-i18n="core.auth.register_subtitle">{{ __('core.auth.register_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="ainpa-auth-form ainpa-auth-form--register">
        @csrf

        <div class="ainpa-auth-name-grid">
            <label class="ainpa-auth-field" for="first_name">
                <span data-i18n="core.auth.first_name">{{ __('core.auth.first_name') }}</span>
                <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    value="{{ old('first_name') }}"
                    required
                    autofocus
                    autocomplete="given-name"
                    placeholder="{{ __('core.auth.first_name_placeholder') }}"
                >
                <x-input-error :messages="$errors->get('first_name')" class="ainpa-auth-error" />
            </label>

            <label class="ainpa-auth-field" for="last_name">
                <span data-i18n="core.auth.last_name">{{ __('core.auth.last_name') }}</span>
                <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    value="{{ old('last_name') }}"
                    required
                    autocomplete="family-name"
                    placeholder="{{ __('core.auth.last_name_placeholder') }}"
                >
                <x-input-error :messages="$errors->get('last_name')" class="ainpa-auth-error" />
            </label>
        </div>

        <label class="ainpa-auth-field" for="email">
            <span data-i18n="core.auth.email">{{ __('core.auth.email') }}</span>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                placeholder="{{ __('core.auth.email_placeholder') }}"
            >
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="phone">
            <span>
                <span data-i18n="core.auth.phone">{{ __('core.auth.phone') }}</span>
                <small data-i18n="core.auth.optional">{{ __('core.auth.optional') }}</small>
            </span>
            <input
                id="phone"
                type="tel"
                name="phone"
                value="{{ old('phone') }}"
                autocomplete="tel"
                inputmode="tel"
                placeholder="{{ __('core.auth.phone_placeholder') }}"
            >
            <x-input-error :messages="$errors->get('phone')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span data-i18n="core.auth.password">{{ __('core.auth.password') }}</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('core.auth.create_password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password_confirmation">
            <span data-i18n="core.auth.confirm_password">{{ __('core.auth.confirm_password') }}</span>
            <span class="ainpa-auth-password">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('core.auth.confirm_password_placeholder') }}"
                >
                <button type="button" data-auth-toggle-password aria-label="{{ __('core.auth.show_password') }}" aria-controls="password_confirmation">{{ __('core.auth.show') }}</button>
            </span>
            <x-input-error :messages="$errors->get('password_confirmation')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-check ainpa-auth-check--terms" for="terms">
            <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))>
            <span>
                <span data-i18n="core.auth.terms_agree">{{ __('core.auth.terms_agree') }}</span>
                <a href="{{ url('/pages/privacy-policy') }}" target="_blank" rel="noopener noreferrer" data-i18n="core.auth.privacy_policy">{{ __('core.auth.privacy_policy') }}</a>
            </span>
        </label>
        <x-input-error :messages="$errors->get('terms')" class="ainpa-auth-error" />

        <button type="submit" class="ainpa-auth-submit" data-i18n="core.auth.create_account">{{ __('core.auth.create_account') }}</button>

        <div class="ainpa-auth-divider"><span data-i18n="core.common.or">{{ __('core.common.or') }}</span></div>

        <p class="ainpa-auth-switch">
            <span data-i18n="core.auth.already_account">{{ __('core.auth.already_account') }}</span>
            <a href="{{ route('login') }}" data-i18n="core.auth.sign_in">{{ __('core.auth.sign_in') }}</a>
        </p>
    </form>
</x-guest-layout>
