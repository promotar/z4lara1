<x-app-layout>
@php
    $sectionRoute = route('admin.plugins.store.settings.update');
    $notificationLabels = [
        'new' => __('store::ui.settings_notification_new'),
        'confirmed' => __('store::ui.settings_notification_confirmed'),
        'processing' => __('store::ui.settings_notification_processing'),
        'ready' => __('store::ui.settings_notification_ready'),
        'shipped' => __('store::ui.settings_notification_shipped'),
        'delivered' => __('store::ui.settings_notification_delivered'),
        'cancelled' => __('store::ui.settings_notification_cancelled'),
        'returned' => __('store::ui.settings_notification_returned'),
    ];
    $policyLabels = [
        'order' => __('store::ui.settings_policy_order'),
        'cancellation' => __('store::ui.settings_policy_cancellation'),
        'delivery' => __('store::ui.settings_policy_delivery'),
        'return' => __('store::ui.settings_policy_return'),
        'cod' => __('store::ui.settings_policy_cod'),
        'privacy' => __('store::ui.settings_policy_privacy'),
    ];
    $pageLabels = [
        'shop' => __('store::ui.settings_page_shop'),
        'cart' => __('store::ui.settings_page_cart'),
        'checkout' => __('store::ui.settings_page_checkout'),
        'my_orders' => __('store::ui.settings_page_my_orders'),
        'confirmation' => __('store::ui.settings_page_confirmation'),
        'policies' => __('store::ui.settings_page_policies'),
    ];
    $continentLabels = [
        'Africa' => __('store::ui.settings_continent_africa'),
        'Asia' => __('store::ui.settings_continent_asia'),
        'Europe' => __('store::ui.settings_continent_europe'),
        'North America' => __('store::ui.settings_continent_north_america'),
        'South America' => __('store::ui.settings_continent_south_america'),
        'Oceania' => __('store::ui.settings_continent_oceania'),
        'Antarctica' => __('store::ui.settings_continent_antarctica'),
    ];
    $selectedShippingCountries = old('shipping_countries', $settings['shipping_countries']);
    $activeShippingContinent = collect($shippingCountriesByContinent)
        ->keys()
        ->first(fn (string $continent): bool => array_intersect(
            array_keys($shippingCountriesByContinent[$continent]),
            $selectedShippingCountries,
        ) !== []) ?? array_key_first($shippingCountriesByContinent);
    $settingsPageContent = [
        'general' => [__('store::ui.settings_title_general'), __('store::ui.settings_desc_general')],
        'currency-tax' => [__('store::ui.settings_title_currency_tax'), __('store::ui.settings_desc_currency_tax')],
        'pages' => [__('store::ui.settings_title_pages'), __('store::ui.settings_desc_pages')],
        'inventory' => [__('store::ui.settings_title_inventory'), __('store::ui.settings_desc_inventory')],
        'orders' => [__('store::ui.settings_title_orders'), __('store::ui.settings_desc_orders')],
        'shipping-countries' => [__('store::ui.settings_title_shipping_countries'), __('store::ui.settings_desc_shipping_countries')],
        'cash-on-delivery' => [__('store::ui.settings_title_cash_on_delivery'), __('store::ui.settings_desc_cash_on_delivery')],
        'notifications' => [__('store::ui.settings_title_notifications'), __('store::ui.settings_desc_notifications')],
        'policies' => [__('store::ui.settings_title_policies'), __('store::ui.settings_desc_policies')],
    ];
    [$settingsPageTitle, $settingsPageDescription] = $settingsPageContent[$activeSettingsPage];
@endphp
<main class="z4-store-admin z4-store-form-page" data-z4-store-foundation>
    <div class="z4-store-container z4-store-container-narrow">
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_0f41c0a69c') }}</span>
                <h1>{{ $settingsPageTitle }}</h1>
                <p>{{ $settingsPageDescription }}</p>
            </div>
        </header>

        @if($activeSettingsPage === 'general')
        <section class="z4-store-side-card">
            <strong>{{ $settings['store_accepting_orders'] ? __('store::ui.settings_orders_open') : __('store::ui.settings_orders_paused') }}</strong>
            <p>{{ __('store::ui.auto_ee8f4b5746') }}</p>
        </section>

        <form id="general" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="general">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_52f4393e1b') }}</h2><p>{{ __('store::ui.auto_13173907f9') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_e3fb0b5e9c') }}</span><input class="z4-store-input" name="store_name" value="{{ $settings['store_name'] }}" required></label>
                <label><span>{{ __('store::ui.auto_daf70fa0c7') }}</span><input class="z4-store-input" type="email" name="store_email" value="{{ $settings['store_email'] }}"></label>
                <label><span>{{ __('store::ui.auto_7769c0e0d1') }}</span><input class="z4-store-input" name="store_phone" value="{{ $settings['store_phone'] }}"></label>
                <label><span>{{ __('store::ui.auto_b5794f8317') }}</span><select class="z4-store-select" name="store_country" data-store-country required>@foreach($shippingCountries as $countryCode => $countryName)<option value="{{ $countryCode }}" @selected($settings['store_country'] === $countryCode)>{{ $countryName }}</option>@endforeach</select></label>
                <label><span>{{ __('store::ui.auto_951cfb94bc') }}</span><select class="z4-store-select" name="default_language"><option value="en" @selected($settings['default_language'] === 'en')>{{ __('store::ui.auto_78463a384a') }}</option><option value="ar" @selected($settings['default_language'] === 'ar')>{{ __('store::ui.auto_272ccb8592') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_236df51bb0') }}</span><input class="z4-store-input" name="timezone" value="{{ $storeCountryTimezoneLabels[$settings['store_country']] ?? $settings['timezone'] }}" title="{{ $settings['timezone'] }}" data-store-timezone readonly><input type="hidden" name="timezone_id" value="{{ $settings['timezone'] }}" data-store-timezone-id><small>{{ __('store::ui.auto_eb2f941bf9') }}</small></label>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_653d48136a') }}</span><textarea class="z4-store-textarea" name="store_address">{{ $settings['store_address'] }}</textarea></label>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_ee307d3cb7') }}</span><textarea class="z4-store-textarea" name="store_paused_message" required>{{ __($settings['store_paused_message']) }}</textarea></label>
            </div>
            <label class="z4-store-check"><input type="checkbox" name="store_accepting_orders" value="1" @checked($settings['store_accepting_orders'])><span><strong>{{ __('store::ui.auto_cf80e77987') }}</strong><small>{{ __('store::ui.auto_eb1588cefd') }}</small></span></label>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_061c6c6c91') }}</button>
        </form>
        <script>
            (() => {
                const country = document.querySelector('[data-store-country]');
                const timezone = document.querySelector('[data-store-timezone]');
                const timezoneId = document.querySelector('[data-store-timezone-id]');
                const timezones = @json($storeCountryTimezones);
                const timezoneLabels = @json($storeCountryTimezoneLabels);
                if (!country || !timezone) return;
                const syncTimezone = () => {
                    const id = timezones[country.value] || 'UTC';
                    timezone.value = timezoneLabels[country.value] || id;
                    timezone.title = id;
                    if (timezoneId) timezoneId.value = id;
                };
                country.addEventListener('change', syncTimezone);
                syncTimezone();
            })();
        </script>
        @endif

        @if($activeSettingsPage === 'shipping-countries')
        <form id="shipping-countries" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="shipping">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_961c3d1773') }}</h2><p>{{ __('store::ui.auto_8707750407') }}</p></div></header>
            <div class="z4-shipping-country-tabs" data-shipping-country-tabs>
                <div class="z4-shipping-country-tab-list" role="tablist" aria-label="{{ __('store::ui.auto_f427fdbc94') }}">
                    @foreach($shippingCountriesByContinent as $continent => $countries)
                        @php($continentId = 'shipping-continent-'.\Illuminate\Support\Str::slug($continent))
                        <button
                            type="button"
                            role="tab"
                            id="{{ $continentId }}-tab"
                            aria-controls="{{ $continentId }}"
                            aria-selected="{{ $activeShippingContinent === $continent ? 'true' : 'false' }}"
                            data-shipping-continent-tab="{{ $continent }}"
                        >
                            <span>{{ $continentLabels[$continent] ?? $continent }}</span>
                            <small>{{ count($countries) }}</small>
                        </button>
                    @endforeach
                </div>
                @foreach($shippingCountriesByContinent as $continent => $countries)
                    @php($continentId = 'shipping-continent-'.\Illuminate\Support\Str::slug($continent))
                    <section
                        id="{{ $continentId }}"
                        class="z4-shipping-country-panel"
                        role="tabpanel"
                        aria-labelledby="{{ $continentId }}-tab"
                        data-shipping-continent-panel="{{ $continent }}"
                        @if($activeShippingContinent !== $continent) hidden @endif
                    >
                        <div class="z4-shipping-country-panel-heading">
                            <div>
                                <strong>{{ $continentLabels[$continent] ?? $continent }}</strong>
                                <span><b data-shipping-selected-count>0</b> {{ __('store::ui.auto_4269ddcf8b') }}</span>
                            </div>
                            <div>
                                <button type="button" class="z4-store-btn z4-store-btn-ghost" data-shipping-select-all>{{ __('store::ui.auto_4c41e0bd95') }}</button>
                                <button type="button" class="z4-store-btn z4-store-btn-ghost" data-shipping-clear>{{ __('store::ui.auto_dc30bc0c79') }}</button>
                            </div>
                        </div>
                        <div class="z4-shipping-country-grid">
                            @foreach($countries as $countryCode => $countryName)
                                <label class="z4-shipping-country-option">
                                    <input type="checkbox" name="shipping_countries[]" value="{{ $countryCode }}" @checked(in_array($countryCode, $selectedShippingCountries, true))>
                                    <span><strong>{{ $countryName }}</strong><small>{{ $countryCode }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <p class="z4-shipping-country-attribution">
                {{ __('store::ui.auto_e44b95b2c5') }}
                <a href="https://github.com/dr5hn/countries-states-cities-database" target="_blank" rel="noopener noreferrer">{{ __('store::ui.auto_192b14c930') }}</a>
                {{ __('store::ui.auto_60504a1c32') }}
            </p>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_386548553d') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'currency-tax')
        <form id="currency-and-tax" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="currency">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_17a9223ce6') }}</h2><p>{{ __('store::ui.auto_448790a254') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_386c339d37') }}</span><select class="z4-store-select" name="currency" data-store-currency>
                    <option value="JOD" data-symbol="د.أ" data-position="after" data-decimals="3" @selected($settings['currency'] === 'JOD')>{{ __('store::ui.auto_c7da4fbef2') }}</option>
                    <option value="USD" data-symbol="$" data-position="before" data-decimals="2" @selected($settings['currency'] === 'USD')>{{ __('store::ui.auto_e7ad8d2d19') }}</option>
                </select></label>
                <label><span>{{ __('store::ui.auto_b9987a246a') }}</span><select class="z4-store-select" name="price_display"><option value="code" @selected($settings['price_display'] === 'code')>{{ __('store::ui.auto_fb31aad9da') }}</option><option value="symbol" @selected($settings['price_display'] === 'symbol')>{{ __('store::ui.auto_62f9da2dd4') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_94cebe752e') }}</span><input class="z4-store-input" name="currency_symbol" value="{{ $settings['currency_symbol'] }}" data-store-currency-symbol></label>
                <label><span>{{ __('store::ui.auto_30dcd74be6') }}</span><select class="z4-store-select" name="currency_symbol_position" data-store-currency-position><option value="after" @selected($settings['currency_symbol_position'] === 'after')>{{ __('store::ui.auto_1dbc824f71') }}</option><option value="before" @selected($settings['currency_symbol_position'] === 'before')>{{ __('store::ui.auto_b1931d2d4d') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_879feebaa3') }}</span><input class="z4-store-input" type="number" min="0" max="4" name="currency_decimal_places" value="{{ $settings['currency_decimal_places'] }}" data-store-currency-decimals></label>
                <label><span>{{ __('store::ui.auto_a338473667') }}</span><input class="z4-store-input" maxlength="1" name="currency_thousands_separator" value="{{ $settings['currency_thousands_separator'] }}"></label>
                <label><span>{{ __('store::ui.auto_25092150e9') }}</span><input class="z4-store-input" maxlength="1" name="currency_decimal_separator" value="{{ $settings['currency_decimal_separator'] }}" required></label>
                <label><span>{{ __('store::ui.auto_3822ca331d') }}</span><div class="z4-store-input-suffix"><input class="z4-store-input" type="number" min="0.0001" max="1000" step="0.0001" name="usd_per_jod" value="{{ number_format($settings['usd_per_jod_basis_points'] / 10000, 4, '.', '') }}" required><span>{{ __('store::ui.auto_a7865f1b8b') }}</span></div><small>{{ __('store::ui.auto_1fe41ff2e8') }}</small></label>
            </div>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_5c25e920b5') }}</button>
        </form>

        <form method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="tax">
            <header class="z4-store-section-heading"><span>2</span><div><h2>{{ __('store::ui.auto_c77d72eca3') }}</h2><p>{{ __('store::ui.auto_32fd0a808b') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_bf678da5e7') }}</span><input class="z4-store-input" name="tax_name" value="{{ $settings['tax_name'] }}" required></label>
                <label><span>{{ __('store::ui.auto_8fe77c2601') }}</span><div class="z4-store-input-suffix"><input class="z4-store-input" type="number" min="0" max="1000" step="0.01" name="tax_percent" value="{{ number_format($settings['tax_basis_points'] / 100, 2, '.', '') }}"><span>%</span></div></label>
            </div>
            <label class="z4-store-check"><input type="checkbox" name="prices_include_tax" value="1" @checked($settings['prices_include_tax'])><span><strong>{{ __('store::ui.auto_3f9b387de0') }}</strong><small>{{ __('store::ui.auto_45caaf9cee') }}</small></span></label>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_9d79faca43') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'inventory')
        <form id="inventory" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="inventory">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_e7c0e1bf6d') }}</h2><p>{{ __('store::ui.auto_240a4e107e') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_201f414261') }}</span><input class="z4-store-input" type="number" min="0" max="1000000" name="default_safety_stock" value="{{ $settings['default_safety_stock'] }}"></label>
                <label><span>{{ __('store::ui.auto_d866b08860') }}</span><select class="z4-store-select" name="out_of_stock_visibility"><option value="visible" @selected($settings['out_of_stock_visibility'] === 'visible')>{{ __('store::ui.auto_5ab36167aa') }}</option><option value="hidden" @selected($settings['out_of_stock_visibility'] === 'hidden')>{{ __('store::ui.auto_c2f5527d3b') }}</option></select></label>
            </div>
            @foreach([
                'inventory_enabled' => __('store::ui.settings_inventory_enabled'),
                'cancelled_orders_restore_stock' => __('store::ui.settings_inventory_cancelled_restore'),
                'returned_products_restore_stock' => __('store::ui.settings_inventory_returned_restore'),
                'admin_low_stock_notification' => __('store::ui.settings_inventory_low_stock_notify'),
                'show_stock_status' => __('store::ui.settings_inventory_show_status'),
            ] as $key => $label)
                <label class="z4-store-check"><input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key])><span><strong>{{ $label }}</strong></span></label>
            @endforeach
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_a099c82a6e') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'orders')
        <form id="orders" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="orders">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_d72eaa592e') }}</h2><p>{{ __('store::ui.auto_57a57cff58') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_a6c584f210') }}</span><input class="z4-store-input" name="order_number_prefix" value="{{ $settings['order_number_prefix'] }}"></label>
                <label><span>{{ __('store::ui.auto_4c3fa5f4f8') }}</span><select class="z4-store-select" name="order_default_status"><option value="new" @selected($settings['order_default_status'] === 'new')>{{ __('store::ui.auto_2d13df6f8b') }}</option><option value="confirmed" @selected($settings['order_default_status'] === 'confirmed')>{{ __('store::ui.auto_205bc73c4a') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_953423da71') }}</span><div class="z4-store-input-suffix"><input class="z4-store-input" type="number" min="0" step="0.001" name="minimum_order" value="{{ number_format($settings['minimum_order_minor'] / 1000, 3, '.', '') }}"><span>JOD</span></div></label>
                <div class="z4-store-form-grid-wide z4-store-field-note">
                    <strong>{{ __('store::ui.auto_4f62d4d528') }}</strong>
                    <p>{{ __('store::ui.auto_a51eb79179') }}</p>
                </div>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_e3b5634fe3') }}</span><textarea class="z4-store-textarea" name="order_instructions">{{ $settings['order_instructions'] }}</textarea></label>
            </div>
            <label class="z4-store-check"><input type="checkbox" name="coupon_release_on_cancel" value="1" @checked($settings['coupon_release_on_cancel'])><span><strong>{{ __('store::ui.auto_10ec651f37') }}</strong></span></label>
            <label class="z4-store-check"><input type="checkbox" name="store_accepting_orders" value="1" @checked($settings['store_accepting_orders'])><span><strong>{{ __('store::ui.auto_cf80e77987') }}</strong></span></label>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_2c3eadc565') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'cash-on-delivery')
        <form id="cash-on-delivery" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="cod">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_469ded86e4') }}</h2><p>{{ __('store::ui.auto_77198d448f') }}</p></div></header>
            <div class="z4-store-form-grid">
                <label><span>{{ __('store::ui.auto_ab92c90bca') }}</span><input class="z4-store-input" name="cod_label" value="{{ $settings['cod_label'] }}" required></label>
                <label><span>{{ __('store::ui.auto_5c9cabaa8f') }}</span><select class="z4-store-select" name="cod_default_payment_status"><option value="cod_pending" @selected($settings['cod_default_payment_status'] === 'cod_pending')>{{ __('store::ui.auto_2e61c07cf5') }}</option><option value="pending" @selected($settings['cod_default_payment_status'] === 'pending')>{{ __('store::ui.auto_2d13df6f8b') }}</option></select></label>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_b5a7adde1a') }}</span><textarea class="z4-store-textarea" name="cod_description">{{ $settings['cod_description'] }}</textarea></label>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_a3d8704551') }}</span><textarea class="z4-store-textarea" name="cod_instructions">{{ $settings['cod_instructions'] }}</textarea></label>
                <label class="z4-store-form-grid-wide"><span>{{ __('store::ui.auto_8375a4ac1f') }}</span><textarea class="z4-store-textarea" name="cod_confirmation_message">{{ $settings['cod_confirmation_message'] }}</textarea></label>
            </div>
            <label class="z4-store-check"><input type="checkbox" name="cod_enabled" value="1" @checked($settings['cod_enabled'])><span><strong>{{ __('store::ui.auto_8fe6173f67') }}</strong></span></label>
            <label class="z4-store-check"><input type="checkbox" name="cod_allow_collection" value="1" @checked($settings['cod_allow_collection'])><span><strong>{{ __('store::ui.auto_6a1ef47599') }}</strong></span></label>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_16096c7dc7') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'notifications')
        <form id="notifications" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="customer_notifications">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_135a8b2322') }}</h2><p>{{ __('store::ui.auto_850b1aa777') }}</p></div></header>
            @foreach($notificationLabels as $status => $label)
                <fieldset class="z4-store-side-card">
                    <legend><strong>{{ $label }}</strong></legend>
                    <label class="z4-store-check"><input type="checkbox" name="{{ $status }}[enabled]" value="1" @checked($settings['customer_notifications'][$status]['enabled'])><span>{{ __('store::ui.auto_00d23a76e4') }}</span></label>
                    <label><span>{{ __('store::ui.auto_c7892ebbb1') }}</span><input class="z4-store-input" name="{{ $status }}[subject]" value="{{ $settings['customer_notifications'][$status]['subject'] }}"></label>
                    <label><span>{{ __('store::ui.auto_4c2a8fe7ea') }}</span><textarea class="z4-store-textarea" name="{{ $status }}[body]">{{ $settings['customer_notifications'][$status]['body'] }}</textarea></label>
                </fieldset>
            @endforeach
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_1938483c83') }}</button>
        </form>

        <form method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="admin_notifications">
            <header class="z4-store-section-heading"><span>2</span><div><h2>{{ __('store::ui.auto_8519323e0b') }}</h2><p>{{ __('store::ui.auto_7c49f497d7') }}</p></div></header>
            <label><span>{{ __('store::ui.auto_5959cf158e') }}</span><textarea class="z4-store-textarea" name="recipients">{{ implode(', ', $settings['admin_notifications']['recipients']) }}</textarea></label>
            @foreach([
                'new_order' => __('store::ui.settings_admin_new_order'),
                'cancellation_request' => __('store::ui.settings_admin_cancellation_request'),
                'low_stock' => __('store::ui.settings_admin_low_stock'),
                'customer_email_failed' => __('store::ui.settings_admin_email_failed'),
            ] as $key => $label)
                <label class="z4-store-check"><input type="checkbox" name="{{ $key }}" value="1" @checked($settings['admin_notifications'][$key])><span><strong>{{ $label }}</strong></span></label>
            @endforeach
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_e11223af46') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'policies')
        <form id="policies" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="policies">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_378d5b0544') }}</h2><p>{{ __('store::ui.auto_d5e14cc890') }}</p></div></header>
            @foreach($policyLabels as $key => $label)
                <label><span>{{ $label }}</span><textarea class="z4-store-textarea" name="{{ $key }}" rows="4">{{ $settings['policies'][$key] }}</textarea></label>
            @endforeach
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_09642b2286') }}</button>
        </form>
        @endif

        @if($activeSettingsPage === 'pages')
        <form id="store-pages" method="post" action="{{ $sectionRoute }}" class="z4-store-form-card">
            @csrf @method('PUT') <input type="hidden" name="section" value="pages">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_2cd3a2f14a') }}</h2><p>{{ __('store::ui.auto_200f3bf660') }}</p></div></header>
            <div class="z4-store-form-grid">
                @foreach($pageLabels as $key => $label)
                    <label><span>{{ $label }}</span><input class="z4-store-input" name="{{ $key }}" value="{{ $settings['store_pages'][$key] }}" placeholder="{{ __('store::ui.auto_7308eb79d9') }}"></label>
                @endforeach
            </div>
            <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_5b32d406d2') }}</button>
        </form>
        @endif
    </div>
</main>
<script>
(() => {
    const currency = document.querySelector('[data-store-currency]');
    if (!currency) return;

    currency.addEventListener('change', () => {
        const selected = currency.selectedOptions[0];
        const symbol = document.querySelector('[data-store-currency-symbol]');
        const position = document.querySelector('[data-store-currency-position]');
        const decimals = document.querySelector('[data-store-currency-decimals]');

        if (symbol) symbol.value = selected.dataset.symbol ?? '';
        if (position) position.value = selected.dataset.position ?? 'after';
        if (decimals) decimals.value = selected.dataset.decimals ?? '2';
    });
})();

(() => {
    const root = document.querySelector('[data-shipping-country-tabs]');
    if (!root) return;

    const tabs = [...root.querySelectorAll('[data-shipping-continent-tab]')];
    const panels = [...root.querySelectorAll('[data-shipping-continent-panel]')];

    const updateCount = (panel) => {
        const count = panel.querySelectorAll('input[name="shipping_countries[]"]:checked').length;
        const target = panel.querySelector('[data-shipping-selected-count]');
        if (target) target.textContent = String(count);
    };

    const activate = (continent) => {
        tabs.forEach((tab) => tab.setAttribute(
            'aria-selected',
            tab.dataset.shippingContinentTab === continent ? 'true' : 'false',
        ));
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.shippingContinentPanel !== continent;
        });
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.shippingContinentTab)));
    panels.forEach((panel) => {
        updateCount(panel);
        panel.addEventListener('change', () => updateCount(panel));
        panel.querySelector('[data-shipping-select-all]')?.addEventListener('click', () => {
            panel.querySelectorAll('input[name="shipping_countries[]"]').forEach((input) => { input.checked = true; });
            updateCount(panel);
        });
        panel.querySelector('[data-shipping-clear]')?.addEventListener('click', () => {
            panel.querySelectorAll('input[name="shipping_countries[]"]').forEach((input) => { input.checked = false; });
            updateCount(panel);
        });
    });
})();
</script>
</x-app-layout>
