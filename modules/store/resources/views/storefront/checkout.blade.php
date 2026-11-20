<x-frontend-layout>
    @php
        $defaultAddress = $savedAddresses->firstWhere('is_default', true) ?? $savedAddresses->first();
        $defaultCountry = $defaultAddress?->country_code
            ?? (array_key_exists('JO', $shippingCountries) ? 'JO' : array_key_first($shippingCountries));
        $selectedCountry = strtoupper((string) old('country_code', $defaultCountry));
        if (! array_key_exists($selectedCountry, $shippingCountries)) {
            $selectedCountry = (string) array_key_first($shippingCountries);
        }
        $selectedGovernorate = (string) old('governorate', $defaultAddress?->governorate);
    @endphp
    <div class="z4sf" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        @include('store::storefront.partials.nav')

        <div class="z4sf-container z4sf-flow-page z4sf-checkout-page" role="main">
            <nav class="z4sf-breadcrumbs" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_fdb0c388de') }}</a><span>/</span><a href="{{ route('store.cart.index') }}">{{ __('store::ui.store_auto_a85eba4c6c') }}</a><span>/</span><span aria-current="page">{{ __('store::ui.store_auto_6ff063fbc8') }}</span>
            </nav>
            <header class="z4sf-flow-heading">
                <p>{{ __('store::ui.store_auto_a60f1ae8ca') }}</p>
                <h1>{{ __('store::ui.store_auto_6ff063fbc8') }}</h1>
                <span>{{ __('store::ui.store_auto_469ded86e4') }}</span>
            </header>

            <form class="z4sf-checkout" method="post" action="{{ route('store.checkout.store') }}" data-shipping-checkout data-quote-url="{{ route('store.checkout.shipping-quote') }}">
                @csrf
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                @if($cart['coupon'])
                    <input type="hidden" name="coupon_code" value="{{ $cart['coupon']['code'] }}">
                @endif
                <div class="z4sf-checkout__forms">
                    @if($savedAddresses->isNotEmpty())
                    <section class="z4sf-form-card" aria-labelledby="saved-address-title">
                        <div class="z4sf-form-card__heading"><span>00</span><div><p>{{ __('store::ui.store_auto_08bd40c754') }}</p><h2 id="saved-address-title">{{ __('store::ui.store_auto_c14511d1fc') }}</h2></div></div>
                        <div class="z4-account-address-choice">
                            @foreach($savedAddresses as $savedAddress)
                                <label>
                                    <input type="radio" name="saved_address_public_id" value="{{ $savedAddress->public_id }}" @checked(old('saved_address_public_id', $defaultAddress?->public_id) === $savedAddress->public_id)>
                                    <span><strong>{{ $savedAddress->label }}</strong><small>{{ collect([$savedAddress->governorate,$savedAddress->city,$savedAddress->area,$savedAddress->street])->filter()->implode('، ') }}</small><small>{{ $savedAddress->recipient_name }} · {{ $savedAddress->phone }}</small></span>
                                </label>
                            @endforeach
                            <a href="{{ route('store.account.addresses.index') }}">{{ __('store::ui.store_auto_ad07e00131') }}</a>
                        </div>
                        <p>{{ __('store::ui.store_auto_a94e7a3b56') }}</p>
                    </section>
                    @endif

                    <section class="z4sf-form-card" aria-labelledby="customer-details-title">
                        <div class="z4sf-form-card__heading"><span>01</span><div><p>{{ __('store::ui.store_auto_ce26601dac') }}</p><h2 id="customer-details-title">{{ __('store::ui.store_auto_ed616fc7d3') }}</h2></div></div>
                        <div class="z4sf-form-grid">
                            <label class="z4sf-field is-wide"><span>{{ __('store::ui.store_auto_f11b368cdd') }} *</span><input name="customer_name" value="{{ old('customer_name', $defaultAddress?->recipient_name ?? auth()->user()?->name) }}" required autocomplete="name"></label>
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_1f8261d174') }} *</span><input name="customer_phone" value="{{ old('customer_phone', $defaultAddress?->phone) }}" required autocomplete="tel" inputmode="tel"></label>
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_641cca06bc') }}</span><input type="email" value="{{ auth()->user()->email }}" autocomplete="email" readonly></label>
                        </div>
                    </section>

                    <section class="z4sf-form-card" aria-labelledby="delivery-address-title">
                        <div class="z4sf-form-card__heading"><span>02</span><div><p>{{ __('store::ui.store_auto_065ab3a28c') }}</p><h2 id="delivery-address-title">{{ __('store::ui.store_auto_af0f5bdc5b') }}</h2></div></div>
                        <div class="z4sf-form-grid">
                            <div class="z4sf-field">
                                <span>{{ __('store::ui.store_auto_59716c9749') }} *</span>
                                <div class="z4sf-choice-picker" data-shipping-picker data-country-picker>
                                    <input type="hidden" name="country_code" value="{{ $selectedCountry }}" data-shipping-country>
                                    <button type="button" class="z4sf-choice-picker__toggle" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="checkout-country-options" data-picker-toggle>{{ $shippingCountries[$selectedCountry] ?? __('store::ui.store_auto_8b144ffc87') }}</button>
                                    <div class="z4sf-choice-picker__options" id="checkout-country-options" role="listbox" data-picker-options hidden>
                                        @foreach($shippingCountries as $countryCode => $countryName)
                                            <button type="button" role="option" data-picker-option data-value="{{ $countryCode }}" aria-selected="{{ $selectedCountry === $countryCode ? 'true' : 'false' }}">{{ $countryName }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="z4sf-field">
                                <span>{{ __('store::ui.store_auto_2f14f720d8') }} *</span>
                                <div class="z4sf-choice-picker" data-shipping-picker data-governorate-picker>
                                    <input type="hidden" name="governorate" value="{{ $selectedGovernorate }}" data-shipping-governorate>
                                    <button type="button" class="z4sf-choice-picker__toggle" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="checkout-governorate-options" data-picker-toggle>{{ $selectedGovernorate ?: __('store::ui.store_auto_2f0771f671') }}</button>
                                    <div class="z4sf-choice-picker__options" id="checkout-governorate-options" role="listbox" data-picker-options hidden>
                                        @foreach($shippingGovernorates[$selectedCountry] ?? [] as $governorate)
                                            <button type="button" role="option" data-picker-option data-value="{{ $governorate }}" aria-selected="{{ $selectedGovernorate === $governorate ? 'true' : 'false' }}">{{ $governorate }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_57d056ed09') }} *</span><input name="city" value="{{ old('city', $defaultAddress?->city) }}" required autocomplete="address-level2"></label>
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_deec4ff199') }}</span><input name="area" value="{{ old('area', $defaultAddress?->area) }}" autocomplete="address-level3"></label>
                            <label class="z4sf-field is-wide"><span>{{ __('store::ui.store_auto_dd7bf230fd') }} *</span><input name="address_line_1" value="{{ old('address_line_1', $defaultAddress?->street) }}" required autocomplete="street-address"></label>
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_572ed696f2') }}</span><input name="postal_code" value="{{ old('postal_code', $defaultAddress?->postal_code) }}" autocomplete="postal-code"></label>
                            <label class="z4sf-field is-wide"><span>{{ __('store::ui.store_auto_7bfff64eec') }}</span><textarea name="customer_notes" rows="4" placeholder="{{ __('store::ui.store_auto_848143a5f6') }}">{{ old('customer_notes') }}</textarea></label>
                        </div>
                        <script type="application/json" data-shipping-governorates>@json($shippingGovernorates)</script>
                    </section>

                    <section class="z4sf-form-card" aria-labelledby="payment-method-title">
                        <div class="z4sf-form-card__heading"><span>03</span><div><p>{{ __('store::ui.store_auto_c453a4b8e8') }}</p><h2 id="payment-method-title">{{ __('store::ui.store_auto_f0aaaae189') }}</h2></div></div>
                        <label class="z4sf-payment-option">
                            <input type="radio" checked disabled>
                            <span><strong>{{ __($paymentMethod->label()) }}</strong><small>{{ __($storeSettings['cod_description']) }}</small></span>
                        </label>
                    </section>
                </div>

                <aside class="z4sf-order-summary z4sf-checkout-summary">
                    <h2>{{ __('store::ui.store_auto_a05313c186') }}</h2>
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
                        <div><dt>{{ __('store::ui.store_auto_2194aaec8c') }} @if($cart['prices_include_tax'])<small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>@endif</dt><dd>{{ $cart['subtotal_formatted'] }}</dd></div>
                        @if($cart['discount_minor'] > 0)
                            <div class="is-discount"><dt>{{ __('store::ui.store_auto_adb6e651c4') }} ({{ $cart['coupon']['code'] }})</dt><dd>− {{ $cart['discount_formatted'] }}</dd></div>
                        @endif
                        @if($cart['tax_minor'] > 0 && ! $cart['prices_include_tax'])
                            <div><dt>{{ $cart['tax_name'] }}</dt><dd>{{ $cart['tax_formatted'] }}</dd></div>
                        @endif
                        <div><dt>{{ __('store::ui.store_auto_065ab3a28c') }}</dt><dd data-shipping-cost>{{ $cart['shipping_formatted'] }}</dd></div>
                        <div class="is-total"><dt>{{ __('store::ui.store_auto_96b0141273') }}</dt><dd data-order-total>{{ $cart['total_formatted'] }}</dd></div>
                    </dl>
                    <div @class(['z4sf-shipping-quote', 'is-error' => $cart['shipping_resolved'] && !$cart['shipping_supported']]) data-shipping-status aria-live="polite">
                        @if($cart['shipping_supported'])
                            <strong>{{ $cart['shipping_zone_name'] }}</strong>
                            @if($cart['shipping_estimate'])<span>{{ __('store::ui.store_auto_7e30feabb6') }}: {{ $cart['shipping_estimate'] }}</span>@endif
                            @if($cart['shipping_free_reason'])<span>{{ __('store::ui.store_auto_eeb3cc1571') }}</span>@elseif($cart['shipping_free_remaining_formatted'])<span>{{ __('store::ui.store_auto_77f2dd0b03', ['amount' => $cart['shipping_free_remaining_formatted']]) }}</span>@endif
                            @if($cart['shipping_message'])<small>{{ __($cart['shipping_message']) }}</small>@endif
                        @elseif($cart['shipping_resolved'])
                            <strong>{{ __('store::ui.store_auto_e74ff913a6') }}</strong><span>{{ __($cart['shipping_message']) }}</span>
                        @else
                            <span>{{ __('store::ui.store_auto_fafb2a6cf4') }}</span>
                        @endif
                    </div>
                    <a class="z4sf-summary-edit" href="{{ route('store.cart.index') }}">{{ __('store::ui.store_auto_0ee374d5fc') }}</a>
                    <label @class(['z4sf-check', 'z4sf-terms', 'is-invalid' => $errors->has('terms')]) data-terms-field>
                        <input type="checkbox" name="terms" value="1" required @checked(old('terms')) aria-describedby="checkout-terms-error" aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}">
                        <span>{{ __('store::ui.store_auto_f676a17f0f') }}</span>
                    </label>
                    <p class="z4sf-terms-error" id="checkout-terms-error" data-terms-error role="alert" aria-live="assertive" @if(!$errors->has('terms')) hidden @endif>{{ $errors->first('terms') ?: __('store::ui.store_auto_7962ac5657') }}</p>
                    @if($storeSettings['order_instructions'])<p>{{ $storeSettings['order_instructions'] }}</p>@endif
                    @if(collect($storeSettings['policies'])->filter()->isNotEmpty())
                        <details class="z4sf-policies"><summary>{{ __('store::ui.store_auto_ad11d568ee') }}</summary>
                            @foreach($storeSettings['policies'] as $policyName => $policyText)
                                @if($policyText)<section><strong>{{ __(ucfirst($policyName).' policy') }}</strong><p>{{ $policyText }}</p></section>@endif
                            @endforeach
                        </details>
                    @endif
                    <button class="z4sf-btn z4sf-btn-dark" type="submit" data-confirm-order data-loading-label="{{ __('store::ui.store_auto_add19061ac') }}" disabled>{{ __('store::ui.store_auto_7395559a94') }}</button>
                    <p>{{ __('store::ui.store_auto_7eb7095062') }}</p>
                </aside>
            </form>
        </div>
    </div>
    <script>
    (() => {
        const form = document.querySelector('[data-shipping-checkout]');
        if (!form) return;
        const cost = form.querySelector('[data-shipping-cost]');
        const total = form.querySelector('[data-order-total]');
        const status = form.querySelector('[data-shipping-status]');
        const confirm = form.querySelector('[data-confirm-order]');
        const terms = form.querySelector('[name="terms"]');
        const termsField = form.querySelector('[data-terms-field]');
        const termsError = form.querySelector('[data-terms-error]');
        const country = form.querySelector('[data-shipping-country]');
        const governorate = form.querySelector('[data-shipping-governorate]');
        const countryPicker = form.querySelector('[data-country-picker]');
        const governoratePicker = form.querySelector('[data-governorate-picker]');
        const governoratesSource = form.querySelector('[data-shipping-governorates]');
        const governorates = governoratesSource ? JSON.parse(governoratesSource.textContent) : {};
        let shippingSupported = @json($cart['shipping_supported'] === true);
        let submitting = false;
        let timer;
        let quoteController = null;
        let activeQuoteKey = '';
        let lastQuotedKey = '';
        const quoteFieldNames = ['saved_address_public_id', 'country_code', 'governorate', 'city', 'area', 'postal_code'];
        const quoteKey = payload => quoteFieldNames
            .map(name => name+':'+String(payload.get(name) ?? '').trim())
            .join('|');
        const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        const syncConfirm = () => {
            const governorateReady = !governorate || governorate.disabled || governorate.value !== '';
            confirm.disabled = submitting || !shippingSupported || !governorateReady;
        };
        const showTermsError = () => {
            if (!terms || !termsError) return;
            termsError.hidden = false;
            terms.setAttribute('aria-invalid', 'true');
            termsField?.classList.add('is-invalid');
            terms.focus({preventScroll: true});
            termsField?.scrollIntoView({behavior: 'smooth', block: 'center'});
        };
        const clearTermsError = () => {
            if (!terms || !termsError) return;
            termsError.hidden = true;
            terms.setAttribute('aria-invalid', 'false');
            termsField?.classList.remove('is-invalid');
        };
        const quote = async () => {
            const payload = new FormData(form);
            const requestKey = quoteKey(payload);
            if (requestKey === lastQuotedKey || requestKey === activeQuoteKey) return;

            quoteController?.abort();
            const controller = new AbortController();
            quoteController = controller;
            activeQuoteKey = requestKey;
            shippingSupported = false;
            confirm.disabled = true;
            status.classList.remove('is-error');
            status.textContent = '{{ __('store::ui.store_auto_ccf23d2cfd') }}';
            try {
                const response = await fetch(form.dataset.quoteUrl, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': payload.get('_token')},
                    body: payload,
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('quote');
                const data = await response.json();
                if (controller.signal.aborted) return;
                lastQuotedKey = requestKey;
                cost.textContent = data.shipping_formatted;
                total.textContent = data.total_formatted;
                shippingSupported = data.supported === true;
                syncConfirm();
                status.classList.toggle('is-error', data.supported !== true);
                if (data.supported) {
                    const free = data.free_reason
                        ? '<span>{{ __('store::ui.store_auto_eeb3cc1571') }}</span>'
                        : (data.free_remaining_formatted ? '<span>{{ __('store::ui.store_auto_ec211f7c20') }} '+esc(data.free_remaining_formatted)+' {{ __('store::ui.store_auto_246f3b054a') }}</span>' : '');
                    status.innerHTML = '<strong>'+esc(data.zone_name)+'</strong>'
                        +(data.estimated_delivery ? '<span>{{ __('store::ui.store_auto_7e30feabb6') }}: '+esc(data.estimated_delivery)+'</span>' : '')
                        +free+(data.message ? '<small>'+esc(data.message)+'</small>' : '');
                } else {
                    status.innerHTML = '<strong>{{ __('store::ui.store_auto_e74ff913a6') }}</strong><span>'+esc(data.message)+'</span>';
                }
            } catch (error) {
                if (error?.name === 'AbortError') return;
                shippingSupported = false;
                syncConfirm();
                status.classList.add('is-error');
                status.textContent = '{{ __('store::ui.store_auto_aed115a2c6') }}';
            } finally {
                if (quoteController === controller) {
                    quoteController = null;
                    activeQuoteKey = '';
                }
            }
        };
        const schedule = () => {
            clearTimeout(timer);
            const requestKey = quoteKey(new FormData(form));
            if (requestKey === lastQuotedKey || requestKey === activeQuoteKey) return;
            quoteController?.abort();
            quoteController = null;
            activeQuoteKey = '';
            shippingSupported = false;
            syncConfirm();
            timer = setTimeout(quote, 600);
        };
        const closePicker = picker => {
            if (!picker) return;
            const toggle = picker.querySelector('[data-picker-toggle]');
            const options = picker.querySelector('[data-picker-options]');
            options.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };
        const bindPicker = picker => {
            if (!picker) return;
            const input = picker.querySelector('input[type="hidden"]');
            const toggle = picker.querySelector('[data-picker-toggle]');
            const options = picker.querySelector('[data-picker-options]');
            toggle.addEventListener('click', () => {
                const willOpen = options.hidden;
                form.querySelectorAll('[data-shipping-picker]').forEach(other => {
                    if (other !== picker) closePicker(other);
                });
                options.hidden = !willOpen;
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            options.addEventListener('click', event => {
                const option = event.target.closest('[data-picker-option]');
                if (!option) return;
                input.value = option.dataset.value || '';
                toggle.textContent = option.textContent.trim();
                options.querySelectorAll('[data-picker-option]').forEach(item => {
                    item.setAttribute('aria-selected', item === option ? 'true' : 'false');
                });
                closePicker(picker);
                input.dispatchEvent(new Event('change', {bubbles: true}));
                toggle.focus();
            });
            toggle.addEventListener('keydown', event => {
                if (event.key === 'Escape') closePicker(picker);
            });
        };
        const renderGovernorates = preserveValue => {
            if (!country || !governorate || !governoratePicker) return;
            const current = preserveValue ? governorate.value : '';
            const options = governorates[country.value] || [];
            const list = governoratePicker.querySelector('[data-picker-options]');
            const toggle = governoratePicker.querySelector('[data-picker-toggle]');
            const selected = options.includes(current) ? current : '';
            list.replaceChildren();
            options.forEach(name => {
                const option = document.createElement('button');
                option.type = 'button';
                option.setAttribute('role', 'option');
                option.setAttribute('data-picker-option', '');
                option.dataset.value = name;
                option.setAttribute('aria-selected', selected === name ? 'true' : 'false');
                option.textContent = name;
                list.append(option);
            });
            governorate.value = selected;
            governorate.disabled = options.length === 0;
            toggle.disabled = options.length === 0;
            toggle.textContent = selected || '{{ __('store::ui.store_auto_2f0771f671') }}';
            closePicker(governoratePicker);
            syncConfirm();
        };
        bindPicker(countryPicker);
        bindPicker(governoratePicker);
        document.addEventListener('click', event => {
            form.querySelectorAll('[data-shipping-picker]').forEach(picker => {
                if (!picker.contains(event.target)) closePicker(picker);
            });
        });
        country?.addEventListener('change', () => renderGovernorates(false));
        form.querySelectorAll('[name="saved_address_public_id"],[name="country_code"],[name="governorate"],[name="city"],[name="area"],[name="postal_code"]')
            .forEach(field => field.addEventListener('change', schedule));
        terms?.addEventListener('invalid', (event) => {
            event.preventDefault();
            showTermsError();
        });
        terms?.addEventListener('change', () => {
            if (terms.checked) clearTermsError();
            syncConfirm();
        });
        form.addEventListener('submit', (event) => {
            if (!terms?.checked) {
                event.preventDefault();
                showTermsError();
                return;
            }
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            confirm.disabled = true;
            confirm.setAttribute('aria-busy', 'true');
            confirm.textContent = confirm.dataset.loadingLabel || confirm.textContent;
        });
        renderGovernorates(true);
        syncConfirm();
    })();
    </script>
</x-frontend-layout>
