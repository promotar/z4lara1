<x-frontend-layout>
    <div class="z4sf" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        @include('store::storefront.partials.nav')

        <div class="z4sf-container z4sf-confirmation" role="main">
            <section class="z4sf-confirmation__hero">
                <span>{{ __('store::ui.store_auto_0958fcb29b') }}</span>
                <p>{{ __('store::ui.store_auto_b2c895d11a', ['name' => $order['customer_name']]) }}</p>
                <h1>{{ __('store::ui.store_auto_91e0655143') }}</h1>
                <div>{{ __('store::ui.store_auto_4049d979b8') }} <strong>{{ $order['order_number'] }}</strong></div>
            </section>

            <div class="z4sf-confirmation__grid">
                <section class="z4sf-form-card">
                    <div class="z4sf-form-card__heading"><span>01</span><div><p>{{ __('store::ui.store_auto_a240fa2792') }}</p><h2>{{ __('store::ui.store_auto_f5d74ea753') }}</h2></div></div>
                    <div class="z4sf-review-items">
                        @foreach ($order['items'] as $item)
                            <article>
                                @if ($item['image_url'])<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($item['image_url']) }}" alt="">@endif
                                <div><strong>{{ $item['product_name'] }}</strong>@if ($item['variant_name'])<span>{{ $item['variant_name'] }}</span>@endif<span>{{ __('store::ui.store_auto_694e8d1f2e') }}: {{ $item['quantity'] }}</span></div>
                                <b>{{ $item['line_total_formatted'] }}</b>
                            </article>
                        @endforeach
                    </div>
                    <dl class="z4sf-confirmation__totals">
                        <div><dt>{{ __('store::ui.store_auto_2194aaec8c') }} @if($order['prices_include_tax'])<small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>@endif</dt><dd>{{ $order['subtotal_formatted'] }}</dd></div>
                        @if($order['discount_minor'] > 0)
                            <div><dt>{{ __('store::ui.store_auto_adb6e651c4') }} ({{ $order['coupon_code'] }})</dt><dd>− {{ $order['discount_formatted'] }}</dd></div>
                        @endif
                        @if($order['tax_minor'] > 0 && ! $order['prices_include_tax'])<div><dt>{{ $order['tax_name'] }}</dt><dd>{{ $order['tax_formatted'] }}</dd></div>@endif
                        <div><dt>{{ __('store::ui.store_auto_065ab3a28c') }}</dt><dd>{{ $order['shipping_formatted'] }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_96b0141273') }}</dt><dd>{{ $order['total_formatted'] }}</dd></div>
                    </dl>
                </section>

                <aside class="z4sf-form-card">
                    <div class="z4sf-form-card__heading"><span>02</span><div><p>{{ __('store::ui.store_auto_065ab3a28c') }}</p><h2>{{ __('store::ui.store_auto_ea7d9b60cc') }}</h2></div></div>
                    <dl class="z4sf-confirmation__details">
                        <div><dt>{{ __('store::ui.store_auto_1f8261d174') }}</dt><dd>{{ $order['customer_phone'] }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_57d056ed09') }}</dt><dd>{{ $order['city'] }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_dd7bf230fd') }}</dt><dd>{{ $order['address'] }}</dd></div>
                        @if($order['shipping_zone_name'])<div><dt>{{ __('store::ui.store_auto_eade0db541') }}</dt><dd>{{ $order['shipping_zone_name'] }}</dd></div>@endif
                        @if($order['shipping_estimate'])<div><dt>{{ __('store::ui.store_auto_7e30feabb6') }}</dt><dd>{{ $order['shipping_estimate'] }}</dd></div>@endif
                        <div><dt>{{ __('store::ui.store_auto_c453a4b8e8') }}</dt><dd>{{ $order['payment_label'] }}</dd></div>
                    </dl>
                    @if($order['payment_confirmation_message'])<p>{{ $order['payment_confirmation_message'] }}</p>@endif
                    <p>{{ __('store::ui.store_auto_bf81c7d7ff') }}</p>
                    <div class="z4sf-confirmation__actions">
                        <a class="z4sf-btn z4sf-btn-dark" href="{{ route('store.index') }}">{{ __('store::ui.store_auto_300225ee95') }}</a>
                        <a class="z4sf-btn z4sf-btn-outline" href="{{ route('store.account.orders.index') }}">{{ __('store::ui.store_auto_d95cf4ab2c') }}</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-frontend-layout>
