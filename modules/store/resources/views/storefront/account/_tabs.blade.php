<nav class="z4-account-tabs" aria-label="{{ __('store::ui.store_auto_418da7fdbb') }}">
    <a href="{{ route('store.account.orders.index') }}" @class(['is-active' => request()->routeIs('store.account.orders.*')])>{{ __('store::ui.store_auto_f915661604') }}</a>
    <a href="{{ route('store.account.addresses.index') }}" @class(['is-active' => request()->routeIs('store.account.addresses.*')])>{{ __('store::ui.store_auto_0511155ff1') }}</a>
    <a href="{{ route('store.account.personal') }}" @class(['is-active' => request()->routeIs('store.account.personal')])>{{ __('store::ui.store_auto_61f1667518') }}</a>
    <a href="{{ route('front.account') }}">{{ __('store::ui.store_auto_ea23e02696') }}</a>
</nav>
