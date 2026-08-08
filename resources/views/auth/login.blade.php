<x-guest-layout>
    <div class="ainpa-auth-heading">
        <p class="ainpa-auth-kicker">Welcome back</p>
        <h1>Sign In</h1>
        <p>Access your INPA account and continue managing your artistic profile.</p>
    </div>

    <x-auth-session-status class="ainpa-auth-status ainpa-auth-status--success" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="ainpa-auth-form">
        @csrf

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
