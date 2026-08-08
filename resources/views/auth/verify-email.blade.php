<x-guest-layout>
    <div class="ainpa-auth-verify">
        <div class="ainpa-auth-verify-graphic" aria-hidden="true">
            <span class="ainpa-auth-envelope">
                <span></span>
            </span>
            <strong>✓</strong>
        </div>

        <div class="ainpa-auth-heading">
            <h1>Check Your Email</h1>
            <p>
                We’ve sent a verification link to
                <strong>{{ auth()->user()?->email }}</strong>
            </p>
            <p>Please check your inbox and click the link to activate your account.</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="ainpa-auth-status ainpa-auth-status--success">
                A new verification link has been sent to your email address.
            </div>
        @endif

        <div class="ainpa-auth-help">
            <strong>Didn’t receive the email?</strong>
            <p>Check your spam folder or click the button below to resend the verification email.</p>
        </div>

        <form method="POST" action="{{ route('verification.send') }}" class="ainpa-auth-form">
            @csrf
            <button type="submit" class="ainpa-auth-submit ainpa-auth-submit--outline">Resend Email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="ainpa-auth-logout">
            @csrf
            <button type="submit">Back to Sign In</button>
        </form>
    </div>
</x-guest-layout>
