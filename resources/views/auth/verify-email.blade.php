<x-guest-layout>
    <div class="ainpa-auth-verify">
        <div class="ainpa-auth-verify-graphic" aria-hidden="true">
            <span class="ainpa-auth-envelope">
                <span></span>
            </span>
            <strong>✓</strong>
        </div>

        <div class="ainpa-auth-heading">
            <h1 data-i18n="core.auth.verify_title">{{ __('core.auth.verify_title') }}</h1>
            <p>
                <span data-i18n="core.auth.verify_sent_to">{{ __('core.auth.verify_sent_to') }}</span>
                <strong>{{ auth()->user()?->email }}</strong>
            </p>
            <p data-i18n="core.auth.verify_inbox">{{ __('core.auth.verify_inbox') }}</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="ainpa-auth-status ainpa-auth-status--success" data-i18n="core.auth.verify_resent">
                {{ __('core.auth.verify_resent') }}
            </div>
        @endif

        <div class="ainpa-auth-help">
            <strong data-i18n="core.auth.verify_no_email">{{ __('core.auth.verify_no_email') }}</strong>
            <p data-i18n="core.auth.verify_spam">{{ __('core.auth.verify_spam') }}</p>
        </div>

        <form method="POST" action="{{ route('verification.send') }}" class="ainpa-auth-form">
            @csrf
            <button type="submit" class="ainpa-auth-submit ainpa-auth-submit--outline" data-i18n="core.auth.resend_email">{{ __('core.auth.resend_email') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="ainpa-auth-logout">
            @csrf
            <button type="submit" data-i18n="core.auth.back_to_sign_in">{{ __('core.auth.back_to_sign_in') }}</button>
        </form>
    </div>
</x-guest-layout>
