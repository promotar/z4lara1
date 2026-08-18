<x-guest-layout>
    <div class="ainpa-auth-heading">
        <h1>Create Your Account</h1>
        <p>Join INPA and become part of our global artistic network.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="ainpa-auth-form ainpa-auth-form--register">
        @csrf

        <div class="ainpa-auth-name-grid">
            <label class="ainpa-auth-field" for="first_name">
                <span>First Name</span>
                <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    value="{{ old('first_name') }}"
                    required
                    autofocus
                    autocomplete="given-name"
                    placeholder="Enter your first name"
                >
                <x-input-error :messages="$errors->get('first_name')" class="ainpa-auth-error" />
            </label>

            <label class="ainpa-auth-field" for="last_name">
                <span>Last Name</span>
                <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    value="{{ old('last_name') }}"
                    required
                    autocomplete="family-name"
                    placeholder="Enter your last name"
                >
                <x-input-error :messages="$errors->get('last_name')" class="ainpa-auth-error" />
            </label>
        </div>

        <label class="ainpa-auth-field" for="email">
            <span>Email Address</span>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                placeholder="Enter your email address"
            >
            <x-input-error :messages="$errors->get('email')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="phone">
            <span>Phone Number <small>(optional)</small></span>
            <input
                id="phone"
                type="tel"
                name="phone"
                value="{{ old('phone') }}"
                autocomplete="tel"
                inputmode="tel"
                placeholder="Enter your phone number"
            >
            <x-input-error :messages="$errors->get('phone')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password">
            <span>Password</span>
            <span class="ainpa-auth-password">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-field" for="password_confirmation">
            <span>Confirm Password</span>
            <span class="ainpa-auth-password">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm your password"
                >
                <button type="button" data-auth-toggle-password aria-label="Show password" aria-controls="password_confirmation">Show</button>
            </span>
            <x-input-error :messages="$errors->get('password_confirmation')" class="ainpa-auth-error" />
        </label>

        <label class="ainpa-auth-check ainpa-auth-check--terms" for="terms">
            <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))>
            <span>
                I agree to the <a href="{{ url('/pages/privacy-policy') }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
            </span>
        </label>
        <x-input-error :messages="$errors->get('terms')" class="ainpa-auth-error" />

        <button type="submit" class="ainpa-auth-submit">Create Account</button>

        <div class="ainpa-auth-divider"><span>or</span></div>

        <p class="ainpa-auth-switch">
            Already have an account?
            <a href="{{ route('login') }}">Sign In</a>
        </p>
    </form>
</x-guest-layout>
