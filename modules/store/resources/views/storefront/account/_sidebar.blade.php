@php
    $accountUser = auth()->user();
    $accountInitial = mb_strtoupper(mb_substr(trim((string) $accountUser->name), 0, 1));
@endphp

@once
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
@endonce

<aside class="z4-account-sidebar" aria-label="{{ __('store::ui.store-account-navigation') }}">
    <div class="z4-account-identity">
        <span class="z4-account-avatar" aria-hidden="true">
            @if(($customerProfile->avatar_path ?? null) !== null)
                <img src="{{ route('store.account.avatar', ['v' => strtotime((string) $customerProfile->updated_at)]) }}" alt="">
            @else
                {{ $accountInitial }}
            @endif
        </span>
        <strong>{{ $accountUser->name }}</strong>
        <small>{{ $accountUser->email }}</small>
    </div>

    <nav class="z4-account-menu">
        <a href="{{ route('store.account.overview') }}" @class(['is-active' => request()->routeIs('store.account.overview')])>
            <span class="material-symbols-outlined" aria-hidden="true">dashboard</span>
            <span>{{ __('store::ui.overview') }}</span>
        </a>
        <a href="{{ route('store.account.orders.index') }}" @class(['is-active' => request()->routeIs('store.account.orders.*')])>
            <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
            <span>{{ __('store::ui.my-purchases') }}</span>
        </a>
        <a href="{{ route('store.account.addresses.index') }}" @class(['is-active' => request()->routeIs('store.account.addresses.*')])>
            <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
            <span>{{ __('store::ui.addresses') }}</span>
        </a>
        <a href="{{ route('store.account.personal') }}" @class(['is-active' => request()->routeIs('store.account.personal')])>
            <span class="material-symbols-outlined" aria-hidden="true">shield</span>
            <span>{{ __('store::ui.profile-security') }}</span>
        </a>
        <a href="{{ route('front.account') }}">
            <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            <span>{{ __('store::ui.back-to-my-account') }}</span>
        </a>
    </nav>

    <form class="z4-account-logout" method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit">
            <span class="material-symbols-outlined" aria-hidden="true">logout</span>
            <span>{{ __('store::ui.log-out') }}</span>
        </button>
    </form>
</aside>
