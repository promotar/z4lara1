<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <h1>Forgot Password?</h1>
        <p>Enter your email address and we’ll send you a link to reset your password.</p>
    </div>

    <x-auth-session-status class="ainpa-auth-status ainpa-auth-status--success" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="ainpa-auth-form ainpa-auth-form--password">
        @csrf

        <label class="ainpa-auth-field" for="email">
            <span>Email Address</span>
            <span class="ainpa-auth-input-wrap">
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter your email address"
                >
            </span>
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit">Send Reset Link</button>

        <div class="ainpa-auth-divider"><span>or</span></div>

        <p class="ainpa-auth-switch ainpa-auth-switch--back">
            <a href="{{ route('login') }}">
                <span aria-hidden="true">←</span>
                Back to Sign In
            </a>
        </p>
    </form>
</x-guest-layout>
