<x-frontend-layout>
    @php($countryNames = app(\Modules\Store\Application\Shipping\ShippingRegionCatalog::class)->countries())
    <div class="z4sf z4-account z4-account-v2" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <main class="z4sf-container z4-account-shell-v2">
            @include('store::storefront.account._alerts')
            <nav class="z4-account-breadcrumb" aria-label="{{ __('store::ui.breadcrumb') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.home') }}</a><span aria-hidden="true">›</span><span>{{ __('store::ui.store-account') }}</span><span aria-hidden="true">›</span><span>{{ __('store::ui.addresses') }}</span>
            </nav>
            <h1 class="z4-account-title">{{ __('store::ui.store-account') }}</h1>

            <div class="z4-account-frame">
                @include('store::storefront.account._sidebar')
                <div class="z4-account-content">
                    <section class="z4-account-section">
                        <header class="z4-account-section-heading">
                            <div><p>{{ __('store::ui.delivery-details') }}</p><h2>{{ __('store::ui.addresses') }}</h2><span>{{ __('store::ui.manage-addresses-used-for-future-purchases') }}</span></div>
                        </header>

                        <div class="z4-address-grid-v2">
                            @forelse($addresses as $address)
                                <article class="z4-account-address-card">
                                    <header>
                                        <span class="z4-account-address-icon material-symbols-outlined" aria-hidden="true">location_on</span>
                                        <div><small>{{ $address->label }}</small><h3>{{ $address->recipient_name }}</h3></div>
                                        @if($address->is_default)<span class="z4-account-default-badge">{{ __('store::ui.default') }}</span>@endif
                                    </header>
                                    <address>{{ collect([$address->street, $address->city, $address->governorate, $countryNames[$address->country_code] ?? $address->country_code])->filter()->implode(', ') }}</address>
                                    <p>{{ $address->phone }}</p>
                                    <details>
                                        <summary>{{ __('store::ui.edit-address') }}</summary>
                                        @include('store::storefront.account._address-form', ['action'=>route('store.account.addresses.update',$address->public_id),'method'=>'PUT','address'=>$address,'button'=>__('store::ui.store_auto_938a685edf')])
                                    </details>
                                    <div class="z4-address-actions">
                                        @unless($address->is_default)<form method="post" action="{{ route('store.account.addresses.default',$address->public_id) }}">@csrf @method('PATCH')<button class="z4-account-secondary-button">{{ __('store::ui.make-default') }}</button></form>@endunless
                                        <form method="post" action="{{ route('store.account.addresses.destroy',$address->public_id) }}">@csrf @method('DELETE')<button class="z4-account-danger-button">{{ __('store::ui.delete') }}</button></form>
                                    </div>
                                </article>
                            @empty
                                <div class="z4-account-empty-v2"><span class="material-symbols-outlined" aria-hidden="true">location_on</span><strong>{{ __('store::ui.no-addresses-saved') }}</strong><p>{{ __('store::ui.add-an-address-to-make-checkout-faster') }}</p></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="z4-account-section">
                        <header class="z4-account-section-heading is-compact"><div><p>{{ __('store::ui.new-destination') }}</p><h2>{{ __('store::ui.add-an-address') }}</h2></div></header>
                        @include('store::storefront.account._address-form', ['action'=>route('store.account.addresses.store'),'method'=>'POST','address'=>null,'button'=>__('store::ui.store_auto_56ed5ee343')])
                    </section>
                </div>
            </div>
        </main>
    </div>
</x-frontend-layout>
