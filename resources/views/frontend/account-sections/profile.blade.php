@php
    $status = session('status');
@endphp

<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('core.account.profile') }}</h2>
            <p>{{ __('core.account.profile_hint') }}</p>
        </div>
    </div>

    @if ($status === 'profile-updated')
        <div class="ainpa-alert ainpa-alert-success" style="margin:0 0 1rem">
            {{ __('core.account.profile_saved') }}
        </div>
    @elseif ($status === 'password-updated')
        <div class="ainpa-alert ainpa-alert-success" style="margin:0 0 1rem">
            {{ __('core.account.password_saved') }}
        </div>
    @endif

    <div class="account-profile-forms" style="display:grid;gap:1.25rem">
        @include('profile.partials.update-profile-information-form', ['user' => $user])
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</section>
