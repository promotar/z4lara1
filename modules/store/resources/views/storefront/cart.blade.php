<x-frontend-layout>
    <div class="z4sf" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}" data-z4-cart-page>
        @include('store::storefront.partials.nav')

        <div class="z4sf-container z4sf-flow-page" role="main">
            <div
                class="z4sf-cart-feedback is-error"
                role="alert"
                aria-live="assertive"
                data-cart-feedback
                @unless($errors->has('cart')) hidden @endunless
            >@error('cart'){{ $message }}@enderror</div>
            <nav class="z4sf-breadcrumbs" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_fdb0c388de') }}</a><span>/</span><span aria-current="page">{{ __('store::ui.store_auto_a85eba4c6c') }}</span>
            </nav>
            <header class="z4sf-flow-heading" data-cart-heading tabindex="-1">
                <p>{{ __('store::ui.store_auto_7f3098e9ea') }}</p>
                <h1>{{ __('store::ui.store_auto_f373b40397') }}</h1>
                <span>{{ __('store::ui.store_auto_58ba302397', ['count' => $cart['count']]) }}</span>
            </header>

            @if ($cart['items']->isEmpty())
                @include('store::storefront.partials.empty-state', [
                    'title' => __('store::ui.store_auto_a2a24f4120'),
                    'message' => __('store::ui.store_auto_11159978a7'),
                ])
            @else
                <div class="z4sf-cart-layout">
                    <section class="z4sf-cart-items" aria-label="{{ __('store::ui.store_auto_ea5d589c05') }}">
                        @foreach ($cart['items'] as $item)
                            <article class="z4sf-cart-item">
                                <a class="z4sf-cart-item__image" href="{{ route('store.products.show', $item['product_slug']) }}">
                                    @if ($item['image_url'])<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($item['image_url']) }}" alt="{{ $item['image_alt'] }}">@endif
                                </a>
                                <div class="z4sf-cart-item__details">
                                    <h2><a href="{{ route('store.products.show', $item['product_slug']) }}">{{ $item['product_name'] }}</a></h2>
                                    @if($item['artist_display_name'])<p>{{ __('store::ui.store_auto_4047c5607d') }}: <strong>{{ $item['artist_display_name'] }}</strong></p>@endif
                                    @if($item['edition_type'])<p>{{ $item['edition_type'] === 'original' ? __('store::ui.store_auto_3ecb365ac8') : __(ucwords(str_replace('_', ' ', $item['edition_type']))) }}</p>@endif
                                    @if($item['frame_cost_included'] && $item['packaging_cost_included'])<small>{{ __('store::ui.store_auto_6261843409') }}</small>@endif
                                    @if ($item['variant_name'])<p>{{ $item['variant_name'] }}</p>@endif
                                    @if ($item['sku'])<small>{{ __('store::ui.store_auto_6665e37610') }}: {{ $item['sku'] }}</small>@endif
                                    <strong>{{ $item['price_formatted'] }}</strong>
                                    @if($cart['prices_include_tax'])<small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>@endif
                                </div>
                                <form class="z4sf-cart-item__quantity" method="post" action="{{ route('store.cart.items.update', $item['variant_public_id']) }}" data-cart-ajax data-cart-success="{{ __('store::ui.store_auto_cbf21b8d78') }}">
                                    @csrf @method('PATCH')
                                    <label for="cart-qty-{{ $item['variant_public_id'] }}">{{ __('store::ui.store_auto_694e8d1f2e') }}</label>
                                    <input id="cart-qty-{{ $item['variant_public_id'] }}" type="number" name="quantity" min="1" max="{{ $item['maximum_quantity'] }}" value="{{ $item['quantity'] }}">
                                    <button type="submit">{{ __('store::ui.store_auto_06933067aa') }}</button>
                                </form>
                                <div class="z4sf-cart-item__total">
                                    <strong>{{ $item['line_total_formatted'] }}</strong>
                                    <form method="post" action="{{ route('store.cart.items.destroy', $item['variant_public_id']) }}" data-cart-ajax data-cart-success="{{ __('store::ui.store_auto_d36eb9937b') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit">{{ __('store::ui.store_auto_1063e38cb5') }}</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                        <div class="z4sf-cart-actions">
                            <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_300225ee95') }}</a>
                            <form method="post" action="{{ route('store.cart.clear') }}" data-cart-ajax data-cart-success="{{ __('store::ui.store_auto_4315dec844') }}">@csrf @method('DELETE')<button type="submit">{{ __('store::ui.store_auto_fde58e2717') }}</button></form>
                        </div>
                    </section>

                    <aside class="z4sf-order-summary">
                        <h2>{{ __('store::ui.store_auto_f1d3b424cd') }}</h2>
                        @if($cart['coupon_error'])
                            <div class="z4sf-coupon-message is-error" role="alert">{{ __($cart['coupon_error']) }}</div>
                        @endif
                        @if($cart['coupon'])
                            <div class="z4sf-coupon-applied">
                                <span><small>{{ __('store::ui.store_auto_adb6e651c4') }}</small><strong>{{ $cart['coupon']['code'] }}</strong></span>
                                <form method="post" action="{{ route('store.cart.coupon.remove') }}" data-cart-ajax data-cart-success="{{ __('store::ui.store_auto_99fc6f8acd') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit">{{ __('store::ui.store_auto_807fd4c186') }}</button>
                                </form>
                            </div>
                        @else
                            <form class="z4sf-coupon-form" method="post" action="{{ route('store.cart.coupon.apply') }}" data-cart-ajax data-cart-success="{{ __('store::ui.store_auto_5b0f41bf43') }}">
                                @csrf
                                <label for="coupon-code">{{ __('store::ui.store_auto_5290e2630e') }}</label>
                                <div>
                                    <input id="coupon-code" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="64" autocomplete="off" required>
                                    <button type="submit">{{ __('store::ui.store_auto_9639e32cab') }}</button>
                                </div>
                                @error('coupon')<small class="z4sf-coupon-error" role="alert">{{ $message }}</small>@enderror
                            </form>
                        @endif
                        <dl>
                            <div><dt>{{ __('store::ui.store_auto_2194aaec8c') }} @if($cart['prices_include_tax'])<small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>@endif</dt><dd>{{ $cart['subtotal_formatted'] }}</dd></div>
                            @if($cart['discount_minor'] > 0)
                                <div class="is-discount"><dt>{{ __('store::ui.store_auto_43954c9da8') }}</dt><dd>− {{ $cart['discount_formatted'] }}</dd></div>
                            @endif
                            @if($cart['tax_minor'] > 0 && ! $cart['prices_include_tax'])
                                <div><dt>{{ $cart['tax_name'] }}</dt><dd>{{ $cart['tax_formatted'] }}</dd></div>
                            @endif
                            <div><dt>{{ __('store::ui.store_auto_065ab3a28c') }}</dt><dd>{{ $cart['shipping_formatted'] }}</dd></div>
                            <div class="is-total"><dt>{{ __('store::ui.store_auto_96b0141273') }}</dt><dd>{{ $cart['total_formatted'] }}</dd></div>
                        </dl>
                        @if($cart['shipping_supported'])
                            <div class="z4sf-shipping-quote">
                                <strong>{{ $cart['shipping_zone_name'] }}</strong>
                                @if($cart['shipping_estimate'])<span>{{ __('store::ui.store_auto_7e30feabb6') }}: {{ $cart['shipping_estimate'] }}</span>@endif
                                @if($cart['shipping_free_reason'])<span>{{ __('store::ui.store_auto_eeb3cc1571') }}</span>@elseif($cart['shipping_free_remaining_formatted'])<span>{{ __('store::ui.store_auto_77f2dd0b03', ['amount' => $cart['shipping_free_remaining_formatted']]) }}</span>@endif
                            </div>
                        @elseif($cart['shipping_resolved'])
                            <div class="z4sf-shipping-quote is-error">{{ __($cart['shipping_message']) }}</div>
                        @endif
                        <p>{{ __('store::ui.store_auto_82cb878b43') }}</p>
                        <a class="z4sf-btn z4sf-btn-dark" href="{{ route('store.checkout.index') }}">{{ __('store::ui.store_auto_7e0bf6d677') }}</a>
                    </aside>
                </div>
            @endif
        </div>
    </div>

    <script>
    (() => {
        if (window.z4StoreCartAjaxInitialized) return;
        window.z4StoreCartAjaxInitialized = true;

        const failureMessage = @json(__('store::ui.store_auto_2a56cd429b'));

        const showFeedback = (root, message, isError = false) => {
            const feedback = root?.querySelector('[data-cart-feedback]');
            if (!feedback) return;
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.classList.toggle('is-error', isError);
            feedback.classList.toggle('is-success', !isError);
        };

        document.addEventListener('submit', async (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : null;
            if (!form?.matches('[data-cart-ajax]')) return;

            const root = form.closest('[data-z4-cart-page]');
            if (!root) return;

            event.preventDefault();
            if (root.dataset.cartBusy === '1') return;

            root.dataset.cartBusy = '1';
            root.classList.add('is-updating');
            root.setAttribute('aria-busy', 'true');

            const submitter = event.submitter instanceof HTMLButtonElement
                ? event.submitter
                : form.querySelector('button[type="submit"]');
            const wasDisabled = submitter?.disabled ?? false;
            if (submitter) {
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');
            }

            try {
                const response = await fetch(form.action, {
                    method: (form.getAttribute('method') || 'post').toUpperCase(),
                    body: new FormData(form),
                    credentials: 'same-origin',
                    redirect: 'follow',
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const html = await response.text();
                if (!response.ok) throw new Error(`Cart request failed with ${response.status}`);

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRoot = parsed.querySelector('[data-z4-cart-page]');
                if (!nextRoot) throw new Error('Cart response did not contain the cart page.');

                const successMessage = form.dataset.cartSuccess || @json(__('store::ui.store_auto_cbf21b8d78'));
                root.replaceWith(nextRoot);

                if (parsed.title) document.title = parsed.title;

                const serverError = nextRoot.querySelector(
                    '[data-cart-feedback]:not([hidden]), .z4sf-coupon-error, .z4sf-coupon-message.is-error'
                );
                if (serverError) {
                    serverError.setAttribute('tabindex', '-1');
                    serverError.focus({ preventScroll: true });
                    return;
                }

                showFeedback(nextRoot, successMessage);
                nextRoot.querySelector('[data-cart-heading]')?.focus({ preventScroll: true });
            } catch (error) {
                root.classList.remove('is-updating');
                root.removeAttribute('aria-busy');
                delete root.dataset.cartBusy;
                showFeedback(root, failureMessage, true);
            } finally {
                if (submitter?.isConnected) {
                    submitter.disabled = wasDisabled;
                    submitter.removeAttribute('aria-busy');
                }
                if (root.isConnected) {
                    root.classList.remove('is-updating');
                    root.removeAttribute('aria-busy');
                    delete root.dataset.cartBusy;
                }
            }
        });
    })();
    </script>
</x-frontend-layout>
