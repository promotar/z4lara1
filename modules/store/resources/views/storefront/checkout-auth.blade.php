<x-frontend-layout>
    <div class="z4sf" data-z4-store data-checkout-auth-gate dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        @include('store::storefront.partials.nav')

        <div class="z4sf-container z4sf-flow-page z4sf-checkout-page" role="main">
            <nav class="z4sf-breadcrumbs" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_fdb0c388de') }}</a><span>/</span><a href="{{ route('store.cart.index') }}">{{ __('store::ui.store_auto_a85eba4c6c') }}</a><span>/</span><span aria-current="page">{{ __('store::ui.store_auto_6ff063fbc8') }}</span>
            </nav>
            <header class="z4sf-flow-heading">
                <p>{{ __('store::ui.store_auto_a60f1ae8ca') }}</p>
                <h1>{{ __('store::ui.store_auto_6ff063fbc8') }}</h1>
                <span>{{ __('store::ui.store_auto_5987377a8b') }}</span>
            </header>

            <div class="z4sf-checkout">
                <section class="z4sf-form-card" aria-labelledby="checkout-account-title">
                    <div class="z4sf-form-card__heading">
                        <span>01</span>
                        <div><p>{{ __('store::ui.store_auto_08bd40c754') }}</p><h2 id="checkout-account-title">{{ __('store::ui.store_auto_7b9deb3b61') }}</h2></div>
                    </div>
                    <p>{{ __('store::ui.store_auto_f18470bb6b') }}</p>
                    <p>{{ __('store::ui.store_auto_b9f7516551') }}</p>
                    <div class="z4sf-confirmation__actions">
                        <a class="z4sf-btn z4sf-btn-dark" href="{{ route('login') }}" target="_blank" rel="noopener" data-checkout-auth-link>{{ __('store::ui.store_auto_b6d4223e60') }}</a>
                        <a class="z4sf-btn z4sf-btn-outline" href="{{ route('register') }}" target="_blank" rel="noopener" data-checkout-auth-link>{{ __('store::ui.store_auto_42369faa6a') }}</a>
                    </div>
                    <p aria-live="polite" data-checkout-auth-status>{{ __('store::ui.store_auto_67db8a121b') }}</p>
                </section>

                <aside class="z4sf-order-summary z4sf-checkout-summary" aria-labelledby="checkout-review-title">
                    <h2 id="checkout-review-title">{{ __('store::ui.store_auto_a05313c186') }}</h2>
                    <div class="z4sf-review-items">
                        @foreach ($cart['items'] as $item)
                            <article>
                                @if ($item['image_url'])<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($item['image_url']) }}" alt="">@endif
                                <div><strong>{{ $item['product_name'] }}</strong><span>{{ __('store::ui.store_auto_694e8d1f2e') }}: {{ $item['quantity'] }}</span></div>
                                <b>{{ $item['line_total_formatted'] }}</b>
                            </article>
                        @endforeach
                    </div>
                    <dl>
                        <div><dt>{{ __('store::ui.store_auto_2194aaec8c') }}</dt><dd>{{ $cart['subtotal_formatted'] }}</dd></div>
                        @if($cart['discount_minor'] > 0)<div><dt>{{ __('store::ui.store_auto_43954c9da8') }}</dt><dd>− {{ $cart['discount_formatted'] }}</dd></div>@endif
                        <div class="is-total"><dt>{{ __('store::ui.store_auto_96b0141273') }}</dt><dd>{{ $cart['total_formatted'] }}</dd></div>
                    </dl>
                    <a class="z4sf-summary-edit" href="{{ route('store.cart.index') }}">{{ __('store::ui.store_auto_0ee374d5fc') }}</a>
                </aside>
            </div>
        </div>

        <script>
        (() => {
            const gate = document.querySelector('[data-checkout-auth-gate]');
            if (!gate) return;

            const waitingKey = 'z4-store-checkout-auth-waiting';
            gate.querySelectorAll('[data-checkout-auth-link]').forEach((link) => {
                link.addEventListener('click', () => sessionStorage.setItem(waitingKey, '1'));
            });

            const resumeCheckout = () => {
                if (sessionStorage.getItem(waitingKey) !== '1') return;
                sessionStorage.removeItem(waitingKey);
                window.location.reload();
            };

            window.addEventListener('focus', () => window.setTimeout(resumeCheckout, 500));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') window.setTimeout(resumeCheckout, 500);
            });
        })();
        </script>
    </div>
</x-frontend-layout>
