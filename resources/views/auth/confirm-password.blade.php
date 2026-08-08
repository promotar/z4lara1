<x-guest-layout>
    <div class="ainpa-auth-heading ainpa-auth-heading--password">
        <p class="ainpa-auth-kicker">Security check</p>
        <h1>Confirm Password</h1>
        <p>This is a secure area. Enter your password to continue.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="ainpa-auth-form ainpa-auth-form--password ainpa-auth-form--compact">
        @csrf

        <label class="ainpa-auth-field" for="password">
            <span>Password</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <button type="submit" class="ainpa-auth-submit">Confirm and Continue</button>
    </form>
</x-guest-layout>
