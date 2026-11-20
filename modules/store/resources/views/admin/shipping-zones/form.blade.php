<x-app-layout>
@php
    $editing = $zone !== null;
    $locationValue = static fn(string $type): string => (string) $locations->firstWhere('match_type', $type)?->match_value;
    $countryLocation = $locationValue('country');
    $selectedCountry = old('country', $countryLocation !== '*' ? ($countryLocation ?: array_key_first($countries)) : array_key_first($countries));
    $selectedCountry = array_key_exists((string) $selectedCountry, $countries) ? (string) $selectedCountry : '';
    $selectedGovernorate = old('governorate', $locationValue('governorate'));
    $coverageType = old('coverage_type', $countryLocation === '*' ? 'rest_of_world' : 'country');
@endphp
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')
        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_662a805daf') }}</span>
                <h1>{{ $editing ? __('store::ui.shipping_zone_edit_title') : __('store::ui.shipping_zone_add_title') }}</h1>
                <p>{{ __('store::ui.auto_aecfc926bc') }}</p>
            </div>
            <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.shipping-zones.index') }}">{{ __('store::ui.auto_24e3d40cd3') }}</a>
        </header>

        <form method="post" action="{{ $editing ? route('admin.plugins.store.shipping-zones.update', $zone->public_id) : route('admin.plugins.store.shipping-zones.store') }}" data-dirty-form>
            @csrf
            @if($editing) @method('PUT') <input type="hidden" name="version" value="{{ $zone->version }}"> @endif

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>01</span><div><h2>{{ __('store::ui.auto_058f3a1fa7') }}</h2><p>{{ __('store::ui.auto_d761eaf41f') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_02e12c5c98') }} <b>*</b></span><input class="z4-store-input" name="name" required maxlength="255" value="{{ old('name', $zone->name ?? '') }}"></label>
                    <label><span>{{ __('store::ui.auto_502996d979') }} <b>*</b></span><input type="number" min="-10000" max="10000" class="z4-store-input" name="priority" required value="{{ old('priority', $zone->priority ?? 0) }}"><small>{{ __('store::ui.auto_193ad1d4b8') }}</small></label>
                    <label class="z4-store-check is-wide"><input type="checkbox" name="active" value="1" @checked(old('active', $zone?->status === 'active'))><span><strong>{{ __('store::ui.auto_4d3d769b81') }}</strong><small>{{ __('store::ui.auto_9d00b6262b') }}</small></span></label>
                </div>
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>02</span><div><h2>{{ __('store::ui.auto_18d63cc31e') }}</h2><p>{{ __('store::ui.auto_0db86f65ba') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_ef6718d7c9') }} <b>*</b></span>
                        <select class="z4-store-select" name="coverage_type" data-shipping-coverage required>
                            <option value="country" @selected($coverageType === 'country')>{{ __('store::ui.auto_bc391b9301') }}</option>
                            <option value="rest_of_world" @selected($coverageType === 'rest_of_world')>{{ __('store::ui.auto_ae96f59662') }}</option>
                        </select>
                        <small>{{ __('store::ui.auto_fcd4a9ba80') }}</small>
                    </label>
                    <label data-country-coverage>
                        <span>{{ __('store::ui.auto_59716c9749') }} <b>*</b></span>
                        <select class="z4-store-select" name="country" data-shipping-country required>
                            <option value="">{{ __('store::ui.auto_8b144ffc87') }}</option>
                            @foreach($countries as $countryCode => $countryName)
                                <option value="{{ $countryCode }}" @selected($selectedCountry === $countryCode)>{{ $countryName }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label data-country-coverage>
                        <span>{{ __('store::ui.auto_2f14f720d8') }}</span>
                        <select class="z4-store-select" name="governorate" data-shipping-governorate>
                            <option value="">{{ __('store::ui.auto_0c84acf09f') }}</option>
                            @foreach($governoratesByCountry[$selectedCountry] ?? [] as $governorate)
                                <option value="{{ $governorate }}" @selected($selectedGovernorate === $governorate)>{{ $governorate }}</option>
                            @endforeach
                        </select>
                        <small>{{ __('store::ui.auto_96267fb24b') }}</small>
                    </label>
                </div>
                @if($countries === [])
                    <p class="z4-store-field-error">{{ __('store::ui.auto_ff2a31d574') }}</p>
                @endif
                <script type="application/json" data-shipping-governorates>@json($governoratesByCountry)</script>
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>03</span><div><h2>{{ __('store::ui.auto_0361e641b6') }}</h2><p>{{ __('store::ui.auto_16e15100be') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_a4c8f5f416') }} <b>*</b></span><input class="z4-store-input" name="delivery_cost" required value="{{ old('delivery_cost', $editing ? number_format($zone->delivery_cost_minor / 1000, 3, '.', '') : '0.000') }}"></label>
                    <label><span>{{ __('store::ui.auto_7e75c489a9') }}</span><input class="z4-store-input" name="free_shipping_threshold" value="{{ old('free_shipping_threshold', $editing && $zone->free_shipping_threshold_minor !== null ? number_format($zone->free_shipping_threshold_minor / 1000, 3, '.', '') : '') }}" placeholder="{{ __('store::ui.auto_b4b71cc58e') }}"></label>
                </div>
            </section>

            <div class="z4-store-form-actions">
                <button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ $editing ? __('store::ui.shipping_zone_save') : __('store::ui.shipping_zone_create') }}</button>
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.shipping-zones.index') }}">{{ __('store::ui.auto_ea4788705e') }}</a>
            </div>
        </form>
    </div>
</main>
<script>
(() => {
    const country = document.querySelector('[data-shipping-country]');
    const governorate = document.querySelector('[data-shipping-governorate]');
    const coverage = document.querySelector('[data-shipping-coverage]');
    const countryCoverageFields = [...document.querySelectorAll('[data-country-coverage]')];
    const source = document.querySelector('[data-shipping-governorates]');

    if (!country || !governorate || !source) {
        return;
    }

    const governorates = JSON.parse(source.textContent);
    const syncCoverage = () => {
        const restOfWorld = coverage?.value === 'rest_of_world';
        countryCoverageFields.forEach((field) => { field.hidden = restOfWorld; });
        country.disabled = restOfWorld;
        country.required = !restOfWorld;
        governorate.disabled = restOfWorld;
    };
    coverage?.addEventListener('change', syncCoverage);
    syncCoverage();

    country.addEventListener('change', () => {
        governorate.replaceChildren(new Option(@json(__('store::ui.all_governorates')), ''));
        (governorates[country.value] || []).forEach((name) => {
            governorate.add(new Option(name, name));
        });
        governorate.focus();
    });
})();
</script>
</x-app-layout>
