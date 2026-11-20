@php($storeName = app(\Modules\Store\Application\Settings\StoreSettingsService::class)->current()['store_name'])

<div class="z4sf-nav-wrap">
    <div class="z4sf-container z4sf-nav">
        <a class="z4sf-brand" href="{{ route('store.index') }}" aria-label="{{ $storeName }}">
            <span>{{ $storeName }}</span>
            <strong>{{ __('store::ui.store_auto_fdb0c388de') }}</strong>
        </a>

        <form class="z4sf-search" action="{{ route('store.search') }}" method="get" role="search">
            <label class="z4sf-visually-hidden" for="z4-store-search">{{ __('store::ui.store_auto_437ac95a91') }}</label>
            <input id="z4-store-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('store::ui.store_auto_9335132bda') }}">
            <button type="submit">{{ __('store::ui.store_auto_13348442cc') }}</button>
        </form>

        <nav class="z4sf-nav-links" aria-label="{{ __('store::ui.store_auto_a956035448') }}">
            <a href="{{ route('store.index') }}" @class(['is-active' => request()->routeIs('store.index')])>{{ __('store::ui.store_auto_068f80c751') }}</a>
            @if (\Illuminate\Support\Facades\Route::has('exhibitions.artworks.index'))
                <a href="{{ route('exhibitions.artworks.index') }}" @class(['is-active' => request()->routeIs('exhibitions.artworks.*')])>{{ __('store::ui.store_auto_44eac6ddda') }}</a>
            @endif
            @auth
                <a href="{{ route('store.account.orders.index') }}" @class(['is-active' => request()->routeIs('store.account.*')])>{{ __('store::ui.store_auto_2cb9de35d6') }}</a>
            @endauth
            <a class="z4sf-cart-link" href="{{ route('store.cart.index') }}">
                {{ __('store::ui.store_auto_a85eba4c6c') }}
                <span data-store-cart-count aria-label="{{ __('store::ui.store_auto_58ba302397', ['count' => $cartCount]) }}">{{ $cartCount }}</span>
            </a>
        </nav>
    </div>
</div>

@if (session('store_success') || $errors->any())
    <div class="z4sf-container z4sf-alerts" aria-live="polite">
        @if (session('store_success'))
            <div class="z4sf-alert is-success">{{ session('store_success') }}</div>
        @endif
        @if ($errors->any())
            <div class="z4sf-alert is-error">
                <strong>{{ __('store::ui.store_auto_f817eca49d') }}</strong>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
