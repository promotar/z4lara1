<x-guest-layout>
    <div class="ainpa-auth-heading">
        <p class="ainpa-auth-kicker">Welcome back</p>
        <h1>Sign In</h1>
        <p>Access your INPA account and continue managing your artistic profile.</p>
    </div>

    <x-auth-session-status class="ainpa-auth-status ainpa-auth-status--success" :status="session('status')" />

    @if (session('google_login_error'))
        <div class="google-login-alert" role="alert">{{ session('google_login_error') }}</div>
    @endif

    @if ($googleLoginAvailable ?? false)
        <a class="google-login-primary" href="{{ route('google-login.auth.redirect', array_filter(['return_to' => $returnTo ?? null])) }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.55h3.24c1.9-1.75 2.98-4.33 2.98-7.42Z"/>
                <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.35l-3.24-2.55c-.9.6-2.05.96-3.38.96-2.6 0-4.81-1.76-5.6-4.13H3.05v2.63A10 10 0 0 0 12 22Z"/>
                <path fill="#FBBC05" d="M6.4 13.93A6.02 6.02 0 0 1 6.08 12c0-.67.12-1.33.32-1.93V7.44H3.05A10 10 0 0 0 2 12c0 1.64.39 3.2 1.05 4.56l3.35-2.63Z"/>
                <path fill="#EA4335" d="M12 5.94c1.47 0 2.79.5 3.82 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.95 5.44l3.35 2.63C7.19 7.7 9.4 5.94 12 5.94Z"/>
            </svg>
            <span>{{ $googleLoginButtonText ?? 'Continue with Google' }}</span>
        </a>

        <div class="google-login-divider"><span>or continue with email</span></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="ainpa-auth-form">
        @csrf
        @if ($returnTo ?? null)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <label class="ainpa-auth-field" for="email">
            <span>Email Address</span>
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
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span>Password</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <div class="ainpa-auth-row">
            <label class="ainpa-auth-check" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="ainpa-auth-submit">Sign In</button>

        <div class="ainpa-auth-divider"><span>or</span></div>

        <p class="ainpa-auth-switch">
            Don’t have an account?
            <a href="{{ route('register') }}">Create Account</a>
        </p>
    </form>
</x-guest-layout>
