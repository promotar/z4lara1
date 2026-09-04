<div class="ainpa-account-overview">
    <header class="ainpa-account-content-header">
        <div>
            <p class="ainpa-account-kicker">Account</p>
            <h1>My Account</h1>
            <p>Manage your identity and use the services available to your account.</p>
        </div>
        <span class="ainpa-account-status-pill">Verified</span>
    </header>

    <div class="ainpa-account-detail-grid">
        <article><span>Name</span><strong>{{ $user->name }}</strong></article>
        <article><span>Email</span><strong dir="auto">{{ $user->email }}</strong></article>
        <article><span>Access</span><strong>{{ $canAccessDashboard ? 'Administration and website' : 'Website account' }}</strong></article>
    </div>

    @if ($user->roles->isNotEmpty())
        <div class="ainpa-account-role-list" aria-label="Assigned roles">
            @foreach ($user->roles as $role)<span>{{ $role->name }}</span>@endforeach
        </div>
    @endif

    <div class="ainpa-account-content-actions">
        <a href="{{ route('profile.edit') }}" class="ainpa-account-button ainpa-account-button--primary">Edit profile</a>
        @if ($canAccessDashboard)<a href="{{ route('dashboard') }}" class="ainpa-account-button">Open dashboard</a>@endif
    </div>
</div>
