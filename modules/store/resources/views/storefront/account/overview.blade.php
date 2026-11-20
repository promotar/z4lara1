<x-frontend-layout>
    @php
        $latestStatus = $latestOrder ? \Modules\Store\Domain\Orders\OrderStatus::from($latestOrder->status) : null;
        $countryNames = app(\Modules\Store\Application\Shipping\ShippingRegionCatalog::class)->countries();
        $storeDate = app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class);
    @endphp
    <div class="z4sf z4-account z4-account-v2" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <main class="z4sf-container z4-account-shell-v2">
            @include('store::storefront.account._alerts')
            <nav class="z4-account-breadcrumb" aria-label="{{ __('store::ui.breadcrumb') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.home') }}</a><span aria-hidden="true">›</span><span>{{ __('store::ui.store-account') }}</span>
            </nav>
            <h1 class="z4-account-title">{{ __('store::ui.store-account') }}</h1>

            <div class="z4-account-frame">
                @include('store::storefront.account._sidebar')
                <div class="z4-account-content">
                    <section class="z4-account-section">
                        <header class="z4-account-section-heading">
                            <div><p>{{ __('store::ui.account-overview') }}</p><h2>{{ __('store::ui.store_auto_bcdda5d45a', ['name' => auth()->user()->name]) }}</h2></div>
                            <a class="z4-account-primary-button" href="{{ route('store.index') }}">{{ __('store::ui.continue-shopping') }}</a>
                        </header>

                        <div class="z4-account-overview-grid">
                            <a href="{{ route('store.account.orders.index') }}" class="z4-account-overview-card">
                                <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
                                <div><strong>{{ $ordersCount }}</strong><span>{{ __('store::ui.purchases') }}</span></div>
                                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                            </a>
                            <a href="{{ route('store.account.addresses.index') }}" class="z4-account-overview-card">
                                <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
                                <div><strong>{{ $addressesCount }}</strong><span>{{ __('store::ui.saved-addresses') }}</span></div>
                                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </section>

                    <section class="z4-account-section">
                        <header class="z4-account-section-heading is-compact"><div><p>{{ __('store::ui.latest-activity') }}</p><h2>{{ __('store::ui.most-recent-order') }}</h2></div></header>
                        @if($latestOrder)
                            <div class="z4-account-overview-order">
                                <div><small>{{ __('store::ui.order') }}</small><strong>{{ $latestOrder->order_number }}</strong></div>
                                <div><small>{{ __('store::ui.placed') }}</small><strong>{{ $storeDate->format($latestOrder->placed_at, 'M d, Y') }}</strong></div>
                                <div><small>{{ __('store::ui.total') }}</small><strong>{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $latestOrder->total_minor, $latestOrder->currency) }}</strong></div>
                                <span class="z4-account-status-badge is-{{ $latestStatus->value }}">{{ $latestStatus->accountLabel() }}</span>
                                <a href="{{ route('store.account.orders.show', $latestOrder->public_id) }}">{{ __('store::ui.view-details') }}</a>
                            </div>
                        @else
                            <div class="z4-account-empty-v2"><span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span><strong>{{ __('store::ui.no-purchases-yet') }}</strong><p>{{ __('store::ui.your-first-order-will-appear-here') }}</p></div>
                        @endif
                    </section>

                    <section class="z4-account-section z4-account-saved-address">
                        <span class="z4-account-address-icon material-symbols-outlined" aria-hidden="true">location_on</span>
                        @if($defaultAddress)
                            <div>
                                <small>{{ __('store::ui.saved-address') }}</small>
                                <strong>{{ $defaultAddress->recipient_name }}</strong>
                                <p>{{ collect([$defaultAddress->street, $defaultAddress->city, $defaultAddress->governorate, $countryNames[$defaultAddress->country_code] ?? $defaultAddress->country_code])->filter()->implode(', ') }}</p>
                                <span>{{ $defaultAddress->phone }}</span>
                            </div>
                        @else
                            <div><small>{{ __('store::ui.saved-address') }}</small><strong>{{ __('store::ui.no-address-saved') }}</strong><p>{{ __('store::ui.add-an-address-to-make-checkout-faster') }}</p></div>
                        @endif
                        <a href="{{ route('store.account.addresses.index') }}">{{ $defaultAddress ? __('store::ui.store_auto_461eb72772') : __('store::ui.store_auto_2332136d14') }}</a>
                    </section>
                </div>
            </div>
        </main>
    </div>
</x-frontend-layout>
