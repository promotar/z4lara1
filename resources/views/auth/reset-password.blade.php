<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <h1>Reset Password</h1>
        <p>Create a new secure password for your INPA account.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="ainpa-auth-form ainpa-auth-form--password">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label class="ainpa-auth-field" for="email">
            <span>Email Address</span>
            <span class="ainpa-auth-input-wrap">
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter your email address"
                >
            </span>
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span>New Password</span>
            <span class="ainpa-auth-input-wrap ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a new password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password_confirmation">
            <span>Confirm Password</span>
            <span class="ainpa-auth-input-wrap ainpa-auth-password">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm your new password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password_confirmation">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password_confirmation')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit">Reset Password</button>

        <div class="ainpa-auth-divider"><span>or</span></div>

        <p class="ainpa-auth-switch ainpa-auth-switch--back">
            <a href="{{ route('login') }}">
                <span aria-hidden="true">←</span>
                Back to Sign In
            </a>
        </p>
    </form>
</x-guest-layout>
