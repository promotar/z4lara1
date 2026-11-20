<x-app-layout>
@php
    $formLocale = $defaultLocale ?? 'en';
    $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
@endphp
<main class="z4-store-admin z4-store-form-page z4-store-product-editor-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading z4-store-product-heading">
            <div>
                <a class="z4-store-back" href="{{ route('admin.plugins.store.products.index') }}">{{ $t('store::ui.auto_13a16e208e') }}</a>
                <h1>{{ $t('store::ui.auto_512b2b14cb') }}</h1>
                <p>{{ $t('store::ui.auto_552242e15b') }}</p>
            </div>
        </header>

        @if (isset($productLocales) && count($productLocales) > 1)
            <div class="z4-store-lang-tabs" data-create-tabs style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px">
                @foreach ($productLocales as $code => $name)
                    <a href="#" data-create-locale="{{ $code }}"
                       style="display:inline-flex;align-items:center;gap:6px;border:1px solid #dfe3ea;border-radius:7px;background:{{ ($defaultLocale ?? 'en') === $code ? '#7c1d1d' : '#fff' }} !important;color:{{ ($defaultLocale ?? 'en') === $code ? '#fff' : '#344054' }} !important;border-color:{{ ($defaultLocale ?? 'en') === $code ? '#7c1d1d' : '#dfe3ea' }} !important;padding:7px 13px;font-size:12px;font-weight:800;text-decoration:none">
                        {{ $name }}
                        <span style="font-size:10px;opacity:.8">{{ $code === ($defaultLocale ?? 'en') ? '' : '+' }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.products.store') }}" data-dirty-form data-z4-store-slug-form>
            @csrf
            <input type="hidden" name="locale" value="{{ old('locale', $defaultLocale) }}">
            <input type="hidden" name="switch_locale" value="" data-switch-locale>

            <div class="z4-store-editor-layout">
                <div class="z4-store-editor-main">
                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>1</span><div><h2>{{ $t('store::ui.auto_c52feaf85b') }}</h2><p>{{ $t('store::ui.auto_c7045a28ce') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <fieldset class="z4-store-product-type is-wide">
                                <legend>{{ $t('store::ui.auto_3d19aea4e2') }}</legend>
                                <label><input type="radio" name="type_code" value="simple" @checked(old('type_code', 'simple') === 'simple')><span><strong>{{ $t('store::ui.auto_90d98afb8f') }}</strong><small>{{ $t('store::ui.auto_e60e6d566d') }}</small></span></label>
                                <label><input type="radio" name="type_code" value="variant" @checked(old('type_code') === 'variant')><span><strong>{{ $t('store::ui.auto_a7202b779d') }}</strong><small>{{ $t('store::ui.auto_31821daf2d') }}</small></span></label>
                            </fieldset>
                            <label class="is-wide"><span>{{ $t('store::ui.auto_b9aefa40a5') }} <b>*</b></span><input class="z4-store-input" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="off" data-slug-source><small>{{ $t('store::ui.auto_31ba0dfa0d') }}</small></label>
                            <label><span>{{ $t('store::ui.auto_0c90858852') }} <b>*</b></span><input class="z4-store-input" name="slug" value="{{ old('slug') }}" required maxlength="255" pattern="[a-z0-9][a-z0-9-]*" data-slug-target><small>{{ $t('store::ui.auto_1f609e6f26') }}</small></label>
                            <label data-simple-only><span>SKU <b>*</b></span><input class="z4-store-input" name="sku" value="{{ old('sku') }}" required maxlength="64"><small>{{ $t('store::ui.auto_59a8dfc2ef') }}</small></label>
                            <label><span>{{ $t('store::ui.auto_1be6f9eb56') }}</span><select class="z4-store-select" name="brand"><option value="">{{ $t('store::ui.auto_8e41eae149') }}</option>@foreach($brands as $brand)<option value="{{ $brand->public_id }}" @selected(old('brand') === $brand->public_id)>{{ $brand->name }}</option>@endforeach</select></label>
                            <label class="is-wide"><span>{{ $t('store::ui.auto_36e3402e4d') }}</span><textarea class="z4-store-textarea" name="short_description" rows="3" maxlength="2000">{{ old('short_description') }}</textarea></label>
                            <label class="is-wide"><span>{{ $t('store::ui.auto_8acbbeabbc') }}</span><textarea class="z4-store-textarea" name="description" rows="8" maxlength="50000">{{ old('description') }}</textarea></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>2</span><div><h2>{{ $t('store::ui.auto_6c5cc79573') }}</h2><p>{{ $t('store::ui.auto_9c76674648') }}</p></div></header>
                        <div class="z4-store-editor-media-tools">
                            <div class="z4-store-media-source">
                                <input type="file" accept="image/jpeg,image/png,image/webp" name="main_image" data-main-upload hidden>
                                <button type="button" class="z4-store-upload-box" data-open-media-picker="main">
                                    <img class="z4-store-upload-preview" data-main-preview src="" alt="" hidden>
                                    <span data-main-upload-icon aria-hidden="true">⇧</span>
                                    <strong>{{ $t('store::ui.auto_87bd939e7e') }}</strong>
                                    <small>{{ $t('store::ui.auto_14000703f2') }}</small>
                                </button>
                            </div>
                            <div class="z4-store-media-source">
                                <input type="file" accept="image/jpeg,image/png,image/webp" name="gallery[]" multiple data-gallery-upload hidden>
                                <button type="button" class="z4-store-upload-box" data-open-media-picker="gallery">
                                    <span aria-hidden="true">⇧</span>
                                    <strong>{{ $t('store::ui.auto_6d92c90c10') }}</strong>
                                    <small>{{ $t('store::ui.auto_5564cac57e') }}</small>
                                </button>
                            </div>
                            <input type="hidden" name="main_asset" value="{{ old('main_asset') }}" data-main-asset>
                            <span data-gallery-assets hidden>@foreach(old('gallery_assets', []) as $asset)<input type="hidden" name="gallery_assets[]" value="{{ $asset }}">@endforeach</span>
                        </div>
                        <div class="z4-store-selected-media" data-gallery-previews aria-label="{{ $t('store::ui.auto_65cbcb6522') }}" hidden></div>
                        <div class="z4-store-media-selection-summary"><small data-main-asset-label>{{ $t('store::ui.auto_a1595ef362') }}</small><small data-gallery-assets-label>{{ $t('store::ui.auto_fab9c600d0') }}</small></div>
                        <dialog class="z4-store-media-picker" data-media-picker aria-labelledby="z4-store-media-picker-title">
                            <header><div><span class="z4-store-eyebrow">{{ $t('store::ui.auto_bf09639f7b') }}</span><h3 id="z4-store-media-picker-title" data-media-picker-title>{{ $t('store::ui.auto_5a31ca938e') }}</h3><p>{{ $t('store::ui.auto_b762896a6a') }}</p></div><div class="z4-store-media-picker-actions"><button type="button" class="z4-store-btn z4-store-btn-primary z4-store-btn-small" data-upload-from-picker>{{ $t('store::ui.auto_0ff5840c17') }}</button><button type="button" class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" data-close-media-picker>{{ $t('store::ui.auto_d3d2e61733') }}</button></div></header>
                            <div class="z4-store-media-picker-grid">
                                @forelse($mediaAssets as $asset)
                                    <button type="button" class="z4-store-media-picker-item" data-media-asset="{{ $asset->public_id }}" data-media-name="{{ $asset->original_filename }}"><img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($asset->path) }}" alt=""><span>{{ $asset->original_filename }}</span></button>
                                @empty
                                    <div class="z4-store-inline-empty">{{ $t('store::ui.admin_product_gallery_empty') }}</div>
                                @endforelse
                            </div>
                        </dialog>
                    </section>

                    <section class="z4-store-form-card" data-variant-only hidden>
                        <header class="z4-store-section-heading"><span>3</span><div><h2>{{ $t('store::ui.auto_19954120f7') }}</h2><p>{{ $t('store::ui.auto_83ff6e22f4') }}</p></div></header>
                        <div class="z4-store-custom-options" data-option-axis-editor>
                            <div class="z4-store-custom-options-heading">
                                <div>
                                    <strong>{{ $t('store::ui.auto_9982e9bbb8') }} <b>*</b></strong>
                                    <small>{{ $t('store::ui.auto_15bdc39940') }}</small>
                                </div>
                                <button type="button" class="z4-store-btn z4-store-btn-ghost" data-add-option-axis><span aria-hidden="true">+</span> {{ $t('store::ui.auto_88a306e559') }}</button>
                            </div>
                            <div class="z4-store-option-axis-list" data-option-axis-list></div>
                            <div class="z4-store-inline-empty" data-option-axis-empty>{{ $t('store::ui.admin_product_no_attributes') }}</div>
                        </div>
                        <template data-option-axis-template>
                            <fieldset class="z4-store-option-axis" data-option-axis>
                                <div class="z4-store-option-axis-heading">
                                    <label>
                                        <span>{{ $t('store::ui.auto_1caa6ff629') }} <b>*</b></span>
                                        <input class="z4-store-input" data-option-axis-name maxlength="80" placeholder="{{ $t('store::ui.auto_7449282ec1') }}" autocomplete="off">
                                    </label>
                                    <button type="button" class="z4-store-icon-button is-danger" data-remove-option-axis aria-label="{{ $t('store::ui.auto_fc86f26e96') }}">×</button>
                                </div>
                                <div class="z4-store-option-value-entry">
                                    <label>
                                        <span>{{ $t('store::ui.auto_1cf8701415') }}</span>
                                        <input class="z4-store-input" data-option-value-input maxlength="100" placeholder="{{ $t('store::ui.auto_efa49a1dea') }}" autocomplete="off">
                                    </label>
                                    <button type="button" class="z4-store-btn z4-store-btn-ghost" data-add-option-value><span aria-hidden="true">+</span> {{ $t('store::ui.auto_6c5e4d2eda') }}</button>
                                </div>
                                <div class="z4-store-option-value-list" data-option-value-list></div>
                                <small class="z4-store-option-axis-hint" data-option-axis-hint>{{ $t('store::ui.auto_ad97398cc0') }}</small>
                            </fieldset>
                        </template>
                        <p class="z4-store-custom-options-note">{{ $t('store::ui.auto_43078e8532') }}</p>
                            <div class="z4-store-variant-builder" data-variant-builder hidden>
                                <div class="z4-store-inline-gallery-heading">
                                    <span><strong>{{ $t('store::ui.auto_13de9de5b3') }}</strong><small data-variant-count>{{ $t('store::ui.auto_df47771543') }}</small></span>
                                </div>
                                <div class="z4-store-create-variants" data-variant-list></div>
                            </div>
                            <template data-variant-template>
                                <article class="z4-store-create-variant" data-created-variant>
                                    <header>
                                        <div><span class="z4-store-eyebrow">{{ $t('store::ui.auto_492f18b608') }}</span><h3 data-variant-title></h3></div>
                                        <span class="z4-store-status is-draft">{{ $t('store::ui.auto_03c2e7e41f') }}</span>
                                    </header>
                                    <div class="z4-store-variant-image-field">
                                        <input type="file" accept="image/jpeg,image/png,image/webp" data-variant-image hidden>
                                        <input type="hidden" data-variant-asset>
                                        <button type="button" class="z4-store-upload-box" data-open-variant-media>
                                            <img class="z4-store-upload-preview" data-variant-preview src="" alt="" hidden>
                                            <span data-variant-image-icon aria-hidden="true">⇧</span>
                                            <strong>{{ $t('store::ui.auto_38b6f8ba18') }}</strong>
                                            <small>{{ $t('store::ui.auto_14000703f2') }}</small>
                                        </button>
                                    </div>
                                    <div class="z4-store-form-grid">
                                        <label><span>SKU <b>*</b></span><input class="z4-store-input" data-variant-field="sku" maxlength="64" required><small>{{ $t('store::ui.auto_59a8dfc2ef') }}</small></label>
                                        <label><span>{{ $t('store::ui.auto_d090bb34c5') }}</span><input class="z4-store-input" data-variant-field="regular_price" inputmode="decimal" placeholder="0.000"><small>{{ $t('store::ui.auto_96d7a330c5') }}</small></label>
                                        <label><span>{{ $t('store::ui.auto_8ebc03ab48') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" data-variant-field="sale_price" inputmode="decimal" placeholder="0.000"></label>
                                        <label><span>{{ $t('store::ui.auto_faeaec9eda') }}</span><select class="z4-store-select" data-variant-field="stock_status"><option value="in_stock">{{ $t('store::ui.auto_69d08bd5f8') }}</option><option value="out_of_stock">{{ $t('store::ui.auto_e990b6285e') }}</option></select></label>
                                        <label><span>{{ $t('store::ui.auto_2169dec129') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" data-variant-field="sale_starts_at"></label>
                                        <label><span>{{ $t('store::ui.auto_707e6d2433') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" data-variant-field="sale_ends_at"></label>
                                        <label><span>{{ $t('store::ui.auto_c8c5918f11') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="number" min="0" data-variant-field="stock_quantity"><small>{{ $t('store::ui.auto_2b28c66e1a') }}</small></label>
                                        <label class="z4-store-check"><input type="hidden" value="0" data-variant-backorder-hidden><input type="checkbox" value="1" data-variant-field="allow_backorder"><span><strong>{{ $t('store::ui.auto_4389e69917') }}</strong><small>{{ $t('store::ui.auto_acd39e5db5') }}</small></span></label>
                                    </div>
                                    <span data-variant-options hidden></span>
                                </article>
                            </template>
                    </section>

                    <section class="z4-store-form-card" data-simple-only>
                        <header class="z4-store-section-heading"><span>3</span><div><h2>{{ $t('store::ui.auto_e22ac25b06') }}</h2><p>{{ $t('store::ui.auto_b0b06e4575') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_d090bb34c5') }}</span><input class="z4-store-input" name="regular_price" inputmode="decimal" value="{{ old('regular_price') }}" placeholder="0.000"><small>{{ $t('store::ui.auto_96d7a330c5') }}</small></label>
                            <label><span>{{ $t('store::ui.auto_8ebc03ab48') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" name="sale_price" inputmode="decimal" value="{{ old('sale_price') }}" placeholder="0.000"></label>
                            <label><span>{{ $t('store::ui.auto_2169dec129') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_starts_at" value="{{ old('sale_starts_at') }}"></label>
                            <label><span>{{ $t('store::ui.auto_707e6d2433') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_ends_at" value="{{ old('sale_ends_at') }}"></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card" data-simple-only>
                        <header class="z4-store-section-heading"><span>4</span><div><h2>{{ $t('store::ui.auto_9e2ff9d528') }}</h2><p>{{ $t('store::ui.auto_a5e7e4dd74') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_faeaec9eda') }}</span><select class="z4-store-select" name="stock_status"><option value="in_stock" @selected(old('stock_status', 'in_stock') === 'in_stock')>{{ $t('store::ui.auto_69d08bd5f8') }}</option><option value="out_of_stock" @selected(old('stock_status') === 'out_of_stock')>{{ $t('store::ui.auto_e990b6285e') }}</option></select></label>
                            <label><span>{{ $t('store::ui.auto_c8c5918f11') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="number" name="stock_quantity" min="0" value="{{ old('stock_quantity') }}"><small>{{ $t('store::ui.auto_cb8f185dfc') }}</small></label>
                            <label class="z4-store-check is-wide"><input type="hidden" name="allow_backorder" value="0"><input type="checkbox" name="allow_backorder" value="1" @checked(old('allow_backorder'))><span><strong>{{ $t('store::ui.auto_4389e69917') }}</strong><small>{{ $t('store::ui.auto_3e7478fb54') }}</small></span></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card" data-product-policies>
                        <header class="z4-store-section-heading"><span data-policy-step>5</span><div><h2>{{ $t('store::ui.admin_product_shipping_returns') }}</h2><p>{{ $t('store::ui.auto_b30b038a35') }}</p></div></header>
                        <div class="z4-store-product-policy-grid">
                            <div class="z4-store-subcard" data-policy-editor>
                                <div class="z4-store-section-heading"><div><h3>{{ $t('store::ui.auto_5cfdabe5c8') }}</h3><p>{{ $t('store::ui.auto_6036c3e823') }}</p></div></div>
                                <label><span>{{ $t('store::ui.auto_8597698823') }}</span><select class="z4-store-select" name="shipping_policy_mode" data-policy-mode><option value="inherit" @selected(old('shipping_policy_mode', 'inherit') === 'inherit')>{{ $t('store::ui.auto_cc2b199740') }}</option><option value="custom" @selected(old('shipping_policy_mode') === 'custom')>{{ $t('store::ui.auto_6f17f27301') }}</option></select></label>
                                <div class="z4-store-policy-default"><strong>{{ $t('store::ui.auto_696a2fd70c') }}</strong><p dir="auto">{{ trim((string) ($storePolicies['delivery'] ?? '')) !== '' ? $storePolicies['delivery'] : $t('store::ui.admin_product_shipping_note') }}</p>@can('store.settings.view')<a href="{{ route('admin.plugins.store.settings.index') }}#policies">{{ $t('store::ui.auto_68d8566cf4') }}</a>@endcan</div>
                                <label data-policy-custom><span>{{ $t('store::ui.auto_c015d10c72') }} <b>*</b></span><textarea class="z4-store-textarea" name="shipping_policy" rows="7" maxlength="20000" placeholder="Describe delivery method, timing, charges, handling, or product-specific restrictions.">{{ old('shipping_policy') }}</textarea><small>{{ $t('store::ui.auto_cba18712e6') }}</small></label>
                            </div>
                            <div class="z4-store-subcard" data-policy-editor>
                                <div class="z4-store-section-heading"><div><h3>{{ $t('store::ui.auto_4762fdc5fd') }}</h3><p>{{ $t('store::ui.auto_1621776463') }}</p></div></div>
                                <label><span>{{ $t('store::ui.auto_8597698823') }}</span><select class="z4-store-select" name="return_policy_mode" data-policy-mode><option value="inherit" @selected(old('return_policy_mode', 'inherit') === 'inherit')>{{ $t('store::ui.auto_cc2b199740') }}</option><option value="custom" @selected(old('return_policy_mode') === 'custom')>{{ $t('store::ui.auto_6f17f27301') }}</option></select></label>
                                <div class="z4-store-policy-default"><strong>{{ $t('store::ui.auto_696a2fd70c') }}</strong><p dir="auto">{{ trim((string) ($storePolicies['return'] ?? '')) !== '' ? $storePolicies['return'] : $t('store::ui.admin_product_returns_note') }}</p>@can('store.settings.view')<a href="{{ route('admin.plugins.store.settings.index') }}#policies">{{ $t('store::ui.auto_68d8566cf4') }}</a>@endcan</div>
                                <label data-policy-custom><span>{{ $t('store::ui.auto_af33569104') }} <b>*</b></span><textarea class="z4-store-textarea" name="return_policy" rows="7" maxlength="20000" placeholder="Describe eligibility, time limits, product condition, exclusions, and the return process.">{{ old('return_policy') }}</textarea><small>{{ $t('store::ui.auto_5ab872d824') }}</small></label>
                            </div>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span data-visibility-step>6</span><div><h2>{{ $t('store::ui.auto_7d02189585') }}</h2><p>{{ $t('store::ui.auto_b086f6e46e') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_7d069ca957') }}</span><select class="z4-store-select" name="visibility"><option value="hidden" @selected(old('visibility', 'hidden') === 'hidden')>{{ $t('store::ui.auto_475d04ae43') }}</option><option value="public" @selected(old('visibility') === 'public')>{{ $t('store::ui.auto_0160c4dd6d') }}</option></select></label>
                            <label class="z4-store-check"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked(old('featured'))><span><strong>{{ $t('store::ui.auto_a5e5066322') }}</strong><small>{{ $t('store::ui.auto_91d8229b74') }}</small></span></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span data-organization-step>7</span><div><h2>{{ $t('store::ui.auto_b3612c3d39') }}</h2><p>{{ $t('store::ui.auto_0f37f28f2a') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <div class="is-wide z4-store-tag-editor" data-tag-editor data-tag-options="{{ json_encode($tagSuggestions, JSON_UNESCAPED_UNICODE) }}"><span>{{ $t('store::ui.auto_189f63f277') }}</span><div class="z4-store-tag-entry"><div class="z4-store-tag-input-wrap"><input class="z4-store-input" data-tag-input maxlength="120" placeholder="{{ $t('store::ui.auto_30ee061747') }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="z4-store-create-tag-suggestions"><div class="z4-store-tag-suggestions" id="z4-store-create-tag-suggestions" data-tag-suggestions role="listbox" hidden></div></div><button type="button" class="z4-store-btn z4-store-btn-ghost" data-add-tag>{{ $t('store::ui.auto_2efd89b3cc') }}</button></div><div class="z4-store-tag-list" data-tag-list></div><input type="hidden" name="tags" value="{{ old('tags') }}" data-tags-value><small>{{ $t('store::ui.auto_b027b0999f') }}</small></div>
                        </div>
                        <div class="z4-store-organization-label"><strong>{{ $t('store::ui.auto_af1b98adf7') }} <b>*</b></strong><small>{{ $t('store::ui.auto_c4b241102f') }}</small></div>
                        @can('store.categories.manage')
                            <div class="z4-store-quick-category">
                                <button type="button" class="z4-store-btn z4-store-btn-ghost" data-toggle-quick-category>{{ $t('store::ui.auto_d0d4e3688f') }}</button>
                                <div class="z4-store-quick-category-form" data-quick-category-form hidden>
                                    <div><strong>{{ $t('store::ui.auto_52015afb6e') }}</strong><small>{{ $t('store::ui.auto_8611c45f51') }}</small></div>
                                    <label><span>{{ $t('store::ui.auto_49ee308734') }} <b>*</b></span><input class="z4-store-input" data-new-category-name maxlength="255" placeholder="{{ $t('store::ui.auto_15015f6f54') }}"></label>
                                    <label><span>{{ $t('store::ui.auto_0c90858852') }} <b>*</b></span><input class="z4-store-input" data-new-category-slug maxlength="255" pattern="[a-z0-9][a-z0-9-]*" placeholder="{{ $t('store::ui.auto_c4a03397ed') }}"></label>
                                    <label><span>{{ $t('store::ui.auto_52b68aaa60') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><select class="z4-store-select" data-new-category-parent><option value="" data-depth="-1">{{ $t('store::ui.auto_3849abb4cb') }}</option>@foreach($categories as $category)<option value="{{ $category->public_id }}" data-depth="{{ $category->depth }}">{{ $category->name ?? $category->public_id }}</option>@endforeach</select></label>
                                    <div class="z4-store-quick-category-actions"><button type="button" class="z4-store-btn z4-store-btn-primary" data-create-quick-category>{{ $t('store::ui.admin_product_create_select') }}</button><button type="button" class="z4-store-btn z4-store-btn-ghost" data-cancel-quick-category>{{ $t('store::ui.auto_ea4788705e') }}</button></div>
                                    <p data-quick-category-feedback role="status"></p>
                                </div>
                            </div>
                        @endcan
                        <div class="z4-store-category-checks">
                            @forelse($categories as $category)
                                <label style="--category-depth: {{ $category->depth }}"><input type="checkbox" name="categories[]" value="{{ $category->public_id }}" @checked(in_array($category->public_id, old('categories', []), true))><span><strong>{{ $category->name ?? $category->public_id }}</strong><small>{{ $category->depth ? 'Nested category' : 'Top-level category' }}</small></span><input type="radio" name="primary_category" value="{{ $category->public_id }}" aria-label="{{ $t('store::ui.auto_f4a92e879b') }}" @checked(old('primary_category') === $category->public_id)></label>
                            @empty
                                <div class="z4-store-inline-empty">{{ $t('store::ui.auto_7b9b6a0fb3') }} <a href="{{ route('admin.plugins.store.categories.index') }}">{{ $t('store::ui.auto_cff15d4379') }}</a>.</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="z4-store-editor-side">
                    <section class="z4-store-side-card">
                        <h2>{{ $t('store::ui.auto_cee86c9977') }}</h2>
                        <ul class="z4-store-readiness" data-simple-copy>
                            <li data-ready-information>{{ $t('store::ui.auto_87e9eb1c1a') }}</li>
                            <li data-ready-main-image>{{ $t('store::ui.auto_bac1484935') }}</li>
                            <li data-ready-price>{{ $t('store::ui.auto_4d8adffdc0') }}</li>
                            <li data-ready-inventory>{{ $t('store::ui.auto_2f71113335') }}</li>
                            <li data-ready-sku>{{ $t('store::ui.auto_072f2424c7') }}</li>
                            <li data-ready-category>{{ $t('store::ui.auto_78efa72df3') }}</li>
                        </ul>
                        <ul class="z4-store-readiness" data-variant-copy hidden>
                            <li data-ready-information>{{ $t('store::ui.auto_87e9eb1c1a') }}</li>
                            <li data-ready-main-image>{{ $t('store::ui.auto_bac1484935') }}</li>
                            <li data-ready-axis>{{ $t('store::ui.auto_5ca17a0a33') }}</li>
                            <li data-ready-variants>{{ $t('store::ui.auto_13de9de5b3') }}</li>
                            <li data-ready-category>{{ $t('store::ui.auto_78efa72df3') }}</li>
                        </ul>
                    </section>

                    <section class="z4-store-side-card z4-store-product-summary-card">
                        <h2>{{ $t('store::ui.auto_e7bd3fef06') }}</h2>
                        <div class="z4-store-product-summary-identity">
                            <img data-summary-image src="" alt="" hidden>
                            <span class="z4-store-product-summary-placeholder" data-summary-image-placeholder aria-hidden="true">{{ $t('store::ui.auto_9dd7104e68') }}</span>
                            <div><strong dir="auto" data-summary-name>{{ $t('store::ui.auto_c0edb2a89b') }}</strong></div>
                        </div>
                        <dl>
                            <div><dt>{{ $t('store::ui.auto_fb025c5999') }}</dt><dd data-summary-price>{{ $t('store::ui.auto_9ba66a9f92') }}</dd></div>
                            <div><dt>SKU</dt><dd dir="auto" data-summary-sku>{{ $t('store::ui.auto_9ba66a9f92') }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_1be6f9eb56') }}</dt><dd dir="auto" data-summary-brand>{{ $t('store::ui.auto_8e41eae149') }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_af1b98adf7') }}</dt><dd data-summary-categories>{{ $t('store::ui.auto_0a6a49ffa8') }}</dd></div>
                        </dl>
                    </section>

                    <section class="z4-store-side-card z4-store-product-inventory-card">
                        <h2>{{ $t('store::ui.auto_e0b01e7810') }}</h2>
                        <div class="z4-store-side-inventory-value">
                            <strong data-inventory-value>{{ $t('store::ui.auto_db3f921088') }}</strong>
                            <small data-inventory-copy>{{ $t('store::ui.auto_868ff5c01a') }}</small>
                        </div>
                        <dl data-simple-copy>
                            <div><dt>{{ $t('store::ui.auto_2205dc082b') }}</dt><dd data-inventory-tracking>{{ $t('store::ui.auto_db3f921088') }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_65695213c3') }}</dt><dd data-inventory-backorders>{{ $t('store::ui.auto_c0902bb307') }}</dd></div>
                        </dl>
                        <dl data-variant-copy hidden>
                            <div><dt>{{ $t('store::ui.auto_b4e6d5d118') }}</dt><dd data-inventory-axes>{{ $t('store::ui.auto_0a6a49ffa8') }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_e9ad310d6d') }}</dt><dd data-inventory-variants>{{ $t('store::ui.auto_835598851a') }}</dd></div>
                        </dl>
                    </section>
                </aside>
            </div>

            <footer class="z4-store-sticky-actions">
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.index') }}">{{ $t('store::ui.auto_ea4788705e') }}</a>
                <button class="z4-store-btn z4-store-btn-ghost" name="intent" value="draft" data-submit-button><span>{{ $t('store::ui.auto_5778e182c5') }}</span></button>
                @can('store.products.publish')
                    <button class="z4-store-btn z4-store-btn-primary" formaction="{{ route('admin.plugins.store.products.store-publish') }}" name="intent" value="publish" data-submit-button data-publish-product><span>{{ $t('store::ui.auto_d1907388a3') }}</span></button>
                @endcan
            </footer>
        </form>
    </div>
</main>
<script>
(() => {
    const form = document.querySelector('[data-dirty-form]');
    if (!form) return;
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', (event) => {
        dirty = false;
        const button = event.submitter;
        if (button?.name) {
            const submitValue = document.createElement('input');
            submitValue.type = 'hidden';
            submitValue.name = button.name;
            submitValue.value = button.value;
            submitValue.dataset.submitIntent = '';
            form.appendChild(submitValue);
        }
        if (button) {
            button.disabled = true;
            const label = button.querySelector('span');
            if (label) label.textContent = 'Saving…';
        }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    form.querySelectorAll('input[name="primary_category"]').forEach(radio => radio.addEventListener('change', () => { const checkbox = radio.closest('label')?.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = true; }));
    const quickCategory = form.querySelector('[data-quick-category-form]');
    if (quickCategory) {
        const quickToggle = form.querySelector('[data-toggle-quick-category]');
        const quickName = quickCategory.querySelector('[data-new-category-name]');
        const quickSlug = quickCategory.querySelector('[data-new-category-slug]');
        const quickParent = quickCategory.querySelector('[data-new-category-parent]');
        const quickCreate = quickCategory.querySelector('[data-create-quick-category]');
        const quickFeedback = quickCategory.querySelector('[data-quick-category-feedback]');
        let quickSlugEdited = false;
        quickToggle.addEventListener('click', () => { quickCategory.hidden = false; quickName.focus(); });
        quickCategory.querySelector('[data-cancel-quick-category]').addEventListener('click', () => { quickCategory.hidden = true; quickFeedback.textContent = ''; });
        quickName.addEventListener('input', () => { if (!quickSlugEdited) quickSlug.value = window.Z4StoreSlugify(quickName.value); });
        quickSlug.addEventListener('input', () => { quickSlugEdited = quickSlug.value.trim() !== ''; });
        quickCreate.addEventListener('click', async () => {
            const name = quickName.value.trim();
            const slug = quickSlug.value.trim();
            if (!name || !slug) { quickFeedback.textContent = 'Enter a category name and slug.'; return; }
            quickCreate.disabled = true;
            quickFeedback.textContent = 'Creating category...';
            try {
                const response = await fetch('{{ route('admin.plugins.store.categories.store') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                    body: JSON.stringify({ locale: form.querySelector('input[name="locale"]').value, name, slug, parent: quickParent.value, position: 0 }),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'The category could not be created.');
                const category = payload.category;
                const selectedParent = quickParent.options[quickParent.selectedIndex];
                const depth = Number(selectedParent.dataset.depth || -1) + 1;
                const row = document.createElement('label');
                row.style.setProperty('--category-depth', String(depth));
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.name = 'categories[]'; checkbox.value = category.public_id; checkbox.checked = true;
                const copy = document.createElement('span');
                const title = document.createElement('strong'); title.textContent = category.name;
                const meta = document.createElement('small'); meta.textContent = depth ? 'Nested category' : 'Top-level category';
                copy.append(title, meta);
                const primary = document.createElement('input');
                primary.type = 'radio'; primary.name = 'primary_category'; primary.value = category.public_id; primary.setAttribute('aria-label', 'Set ' + category.name + ' as primary');
                if (!form.querySelector('input[name="primary_category"]:checked')) primary.checked = true;
                primary.addEventListener('change', () => { checkbox.checked = true; });
                row.append(checkbox, copy, primary);
                form.querySelector('.z4-store-category-checks').append(row);
                refreshCreateSidebar();
                const parentOption = document.createElement('option');
                parentOption.value = category.public_id; parentOption.dataset.depth = String(depth); parentOption.textContent = category.name;
                quickParent.append(parentOption);
                quickName.value = ''; quickSlug.value = ''; quickSlugEdited = false;
                quickFeedback.textContent = 'Category created and selected.';
                quickCategory.hidden = true;
            } catch (error) {
                quickFeedback.textContent = error.message;
            } finally {
                quickCreate.disabled = false;
            }
        });
    }
    const typeInputs = form.querySelectorAll('input[name="type_code"]');
    const skuInput = form.querySelector('input[name="sku"]');
    const publish = form.querySelector('[data-publish-product]');
    const field = name => form.querySelector(`[name="${name}"]`);
    const setReady = (selector, ready) => form.querySelectorAll(selector).forEach(item => item.classList.toggle('is-done', ready));
    const oldOptionAxes = @json(old('option_axes', []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const oldVariants = @json(old('variants', []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const variantCardCache = new Map();
    const formatPrice = value => {
        const amount = Number(value);
        return value !== '' && Number.isFinite(amount) ? `${amount.toFixed(3)} JOD` : 'Not set';
    };
    const refreshCreateSidebar = () => {
        const isVariant = form.querySelector('input[name="type_code"]:checked')?.value === 'variant';
        const name = field('name')?.value.trim() || '';
        const slug = field('slug')?.value.trim() || '';
        const sku = field('sku')?.value.trim() || '';
        const regularPrice = field('regular_price')?.value.trim() || '';
        const salePrice = field('sale_price')?.value.trim() || '';
        const mainAssetValue = form.querySelector('[data-main-asset]')?.value || '';
        const mainFile = form.querySelector('[data-main-upload]')?.files?.[0];
        const categoryCount = form.querySelectorAll('input[name="categories[]"]:checked').length;
        const axisCount = Array.from(form.querySelectorAll('[data-option-axis]')).filter(axis =>
            Boolean(axis.querySelector('[data-option-axis-name]')?.value.trim())
            && Array.isArray(axis._optionValues)
            && axis._optionValues.length > 0
        ).length;
        const brand = field('brand');
        const stockStatus = field('stock_status')?.value || 'in_stock';
        const quantity = field('stock_quantity')?.value.trim() || '';
        const allowBackorders = form.querySelector('input[name="allow_backorder"][type="checkbox"]')?.checked || false;
        const hasMainImage = Boolean(mainAssetValue || mainFile);
        const variantCards = Array.from(form.querySelectorAll('[data-created-variant]'));
        const variantPrices = variantCards
            .map(card => card.querySelector('[data-variant-field="sale_price"]')?.value.trim() || card.querySelector('[data-variant-field="regular_price"]')?.value.trim() || '')
            .filter(value => value !== '')
            .map(Number)
            .filter(Number.isFinite);
        const variantsReady = variantCards.length > 0 && variantCards.every(card =>
            Boolean(card.querySelector('[data-variant-field="sku"]')?.value.trim())
            && Boolean(card.querySelector('[data-variant-field="regular_price"]')?.value.trim())
            && Boolean(card.querySelector('[data-variant-field="stock_status"]')?.value)
        );

        setReady('[data-ready-information]', Boolean(name && slug));
        setReady('[data-ready-main-image]', hasMainImage);
        setReady('[data-ready-price]', Boolean(regularPrice));
        setReady('[data-ready-inventory]', !isVariant);
        setReady('[data-ready-sku]', Boolean(sku));
        setReady('[data-ready-category]', categoryCount > 0);
        setReady('[data-ready-axis]', axisCount > 0);
        setReady('[data-ready-variants]', variantsReady);

        form.querySelector('[data-summary-name]').textContent = name || 'Untitled product';
        if (isVariant && variantPrices.length) {
            const minimum = Math.min(...variantPrices);
            const maximum = Math.max(...variantPrices);
            form.querySelector('[data-summary-price]').textContent = minimum === maximum
                ? `${minimum.toFixed(3)} JOD`
                : `${minimum.toFixed(3)} - ${maximum.toFixed(3)} JOD`;
        } else {
            form.querySelector('[data-summary-price]').textContent = isVariant ? 'Set per variant' : formatPrice(salePrice || regularPrice);
        }
        form.querySelector('[data-summary-sku]').textContent = isVariant
            ? (variantCards.length ? `${variantCards.length} variants` : 'Set per variant')
            : (sku || 'Not set');
        form.querySelector('[data-summary-brand]').textContent = brand?.selectedOptions?.[0]?.textContent || 'No brand';
        form.querySelector('[data-summary-categories]').textContent = `${categoryCount} selected`;
        form.querySelector('[data-inventory-axes]').textContent = `${axisCount} selected`;
        form.querySelector('[data-inventory-variants]').textContent = `${variantCards.length} configured`;

        if (isVariant) {
            const quantities = variantCards
                .map(card => card.querySelector('[data-variant-field="stock_quantity"]')?.value.trim() || '')
                .filter(value => value !== '')
                .map(Number)
                .filter(Number.isFinite);
            form.querySelector('[data-inventory-value]').textContent = quantities.length
                ? quantities.reduce((total, value) => total + value, 0)
                : (variantCards.length ? `${variantCards.length} variants` : 'Not configured');
            form.querySelector('[data-inventory-copy]').textContent = quantities.length ? 'Available units across variants' : 'Inventory is set per variant.';
        } else if (quantity !== '') {
            form.querySelector('[data-inventory-value]').textContent = quantity;
            form.querySelector('[data-inventory-copy]').textContent = 'Available units';
        } else if (stockStatus === 'out_of_stock') {
            form.querySelector('[data-inventory-value]').textContent = '0';
            form.querySelector('[data-inventory-copy]').textContent = 'Out of stock';
        } else {
            form.querySelector('[data-inventory-value]').textContent = 'Always in stock';
            form.querySelector('[data-inventory-copy]').textContent = 'Quantity is not tracked.';
        }
        form.querySelector('[data-inventory-tracking]').textContent = quantity !== '' || stockStatus === 'out_of_stock' ? 'Quantity tracked' : 'Not tracked';
        form.querySelector('[data-inventory-backorders]').textContent = allowBackorders ? 'Allowed' : 'Not allowed';

        const uploadPreview = form.querySelector('[data-main-preview]');
        const summaryImage = form.querySelector('[data-summary-image]');
        const summaryPlaceholder = form.querySelector('[data-summary-image-placeholder]');
        const previewSource = uploadPreview && !uploadPreview.hidden ? uploadPreview.src : '';
        summaryImage.src = previewSource;
        summaryImage.alt = previewSource ? 'Selected product image' : '';
        summaryImage.hidden = !previewSource;
        summaryPlaceholder.hidden = Boolean(previewSource);
    };
    const setProductType = () => {
        const isVariant = form.querySelector('input[name="type_code"]:checked')?.value === 'variant';
        form.querySelectorAll('[data-simple-only]').forEach(item => { item.hidden = isVariant; });
        form.querySelectorAll('[data-variant-only]').forEach(item => { item.hidden = !isVariant; });
        form.querySelectorAll('[data-simple-copy]').forEach(item => { item.hidden = isVariant; });
        form.querySelectorAll('[data-variant-copy]').forEach(item => { item.hidden = !isVariant; });
        if (skuInput) skuInput.required = !isVariant;
        form.querySelector('[data-policy-step]').textContent = isVariant ? '4' : '5';
        form.querySelector('[data-visibility-step]').textContent = isVariant ? '5' : '6';
        form.querySelector('[data-organization-step]').textContent = isVariant ? '6' : '7';
        if (publish) { publish.disabled = false; publish.title = ''; }
        refreshCreateSidebar();
    };
    const syncPolicyEditor = editor => {
        const custom = editor.querySelector('[data-policy-custom]');
        const textarea = custom.querySelector('textarea');
        const usesCustomPolicy = editor.querySelector('[data-policy-mode]').value === 'custom';
        custom.hidden = !usesCustomPolicy;
        textarea.disabled = !usesCustomPolicy;
        textarea.required = usesCustomPolicy;
    };
    form.querySelectorAll('[data-policy-editor]').forEach(editor => {
        editor.querySelector('[data-policy-mode]').addEventListener('change', () => syncPolicyEditor(editor));
        syncPolicyEditor(editor);
    });
    typeInputs.forEach(input => input.addEventListener('change', setProductType));
    form.addEventListener('input', refreshCreateSidebar);
    form.addEventListener('change', refreshCreateSidebar);
    setProductType();

    const picker = form.querySelector('[data-media-picker]');
    const mainAsset = form.querySelector('[data-main-asset]');
    const galleryAssets = form.querySelector('[data-gallery-assets]');
    const mainLabel = form.querySelector('[data-main-asset-label]');
    const galleryLabel = form.querySelector('[data-gallery-assets-label]');
    const mainUpload = form.querySelector('[data-main-upload]');
    const galleryUpload = form.querySelector('[data-gallery-upload]');
    const pickerTitle = picker.querySelector('[data-media-picker-title]');
    const uploadFromPicker = picker.querySelector('[data-upload-from-picker]');
    const mainImageTrigger = form.querySelector('[data-open-media-picker="main"]');
    const galleryImageTrigger = form.querySelector('[data-open-media-picker="gallery"]');
    const mainPreview = form.querySelector('[data-main-preview]');
    const mainUploadIcon = form.querySelector('[data-main-upload-icon]');
    const galleryPreviews = form.querySelector('[data-gallery-previews]');
    let pickerTarget = 'main';
    let pickerTrigger = null;
    let mainUploadPreviewUrl = '';
    let galleryUploadPreviewUrls = [];
    let variantMediaCard = null;
    const openPicker = () => {
        if (typeof picker.showModal === 'function') picker.showModal();
        else picker.setAttribute('open', 'open');
        document.body.classList.add('z4-store-media-modal-open');
        window.requestAnimationFrame(() => picker.querySelector('[data-close-media-picker]')?.focus());
    };
    const closePicker = () => {
        if (typeof picker.close === 'function' && picker.open) picker.close();
        else picker.removeAttribute('open');
        document.body.classList.remove('z4-store-media-modal-open');
    };
    const galleryIds = () => Array.from(galleryAssets.querySelectorAll('input')).map(input => input.value);
    const assetButton = id => picker.querySelector('[data-media-asset="' + id + '"]');
    const assetName = id => assetButton(id)?.dataset.mediaName || 'Selected image';
    const assetImage = id => assetButton(id)?.querySelector('img')?.src || '';
    const revokePreviewUrl = url => { if (url) URL.revokeObjectURL(url); };
    const refreshMainUploadPreview = () => {
        revokePreviewUrl(mainUploadPreviewUrl);
        mainUploadPreviewUrl = mainUpload.files[0] ? URL.createObjectURL(mainUpload.files[0]) : '';
    };
    const refreshGalleryUploadPreviews = () => {
        galleryUploadPreviewUrls.forEach(revokePreviewUrl);
        galleryUploadPreviewUrls = Array.from(galleryUpload.files).map(file => URL.createObjectURL(file));
    };
    const appendGalleryPreview = (src, name) => {
        if (!src) return;
        const figure = document.createElement('figure');
        const image = document.createElement('img');
        const caption = document.createElement('figcaption');
        image.src = src;
        image.alt = name;
        caption.textContent = name;
        caption.title = name;
        figure.append(image, caption);
        galleryPreviews.append(figure);
    };
    const renderMediaSelection = () => {
        const gallery = galleryIds();
        const mainFile = mainUpload.files[0];
        const mainPreviewSrc = mainAsset.value ? assetImage(mainAsset.value) : mainUploadPreviewUrl;
        const mainPreviewName = mainAsset.value ? assetName(mainAsset.value) : (mainFile?.name || '');
        mainPreview.src = mainPreviewSrc;
        mainPreview.alt = mainPreviewName ? `Selected main product image: ${mainPreviewName}` : '';
        mainPreview.hidden = !mainPreviewSrc;
        mainUploadIcon.hidden = Boolean(mainPreviewSrc);
        mainImageTrigger.classList.toggle('has-preview', Boolean(mainPreviewSrc));
        mainLabel.textContent = mainAsset.value ? `Main image: ${assetName(mainAsset.value)}` : (mainFile ? `Uploaded image: ${mainFile.name}` : 'No main image selected.');

        galleryPreviews.replaceChildren();
        gallery.forEach(id => appendGalleryPreview(assetImage(id), assetName(id)));
        galleryUploadPreviewUrls.forEach((src, index) => appendGalleryPreview(src, galleryUpload.files[index]?.name || `Uploaded image ${index + 1}`));
        galleryPreviews.hidden = galleryPreviews.childElementCount === 0;
        const galleryTotal = gallery.length + galleryUpload.files.length;
        galleryLabel.textContent = galleryTotal ? `${galleryTotal} gallery image${galleryTotal === 1 ? '' : 's'} selected.` : 'No gallery images selected.';
        mainImageTrigger.querySelector('strong').textContent = mainAsset.value ? `Selected: ${assetName(mainAsset.value)}` : (mainUpload.files.length ? 'New image ready to upload' : 'Choose Main Product Image');
        galleryImageTrigger.querySelector('strong').textContent = galleryTotal ? `${galleryTotal} gallery image${galleryTotal === 1 ? '' : 's'} selected` : 'Add Gallery Images';
        const variantAsset = variantMediaCard?.querySelector('[data-variant-asset]')?.value || '';
        picker.querySelectorAll('[data-media-asset]').forEach(button => {
            const selected = pickerTarget === 'main'
                ? button.dataset.mediaAsset === mainAsset.value
                : (pickerTarget === 'variant' ? button.dataset.mediaAsset === variantAsset : gallery.includes(button.dataset.mediaAsset));
            button.classList.toggle('is-selected', selected);
        });
        refreshCreateSidebar();
    };
    form.querySelectorAll('[data-open-media-picker]').forEach(button => button.addEventListener('click', () => {
        pickerTrigger = button;
        pickerTarget = button.dataset.openMediaPicker;
        variantMediaCard = null;
        pickerTitle.textContent = pickerTarget === 'main' ? 'Choose main product image' : 'Choose gallery images';
        uploadFromPicker.textContent = pickerTarget === 'main' ? 'Upload image' : 'Upload files';
        openPicker();
        renderMediaSelection();
    }));
    picker.querySelector('[data-close-media-picker]')?.addEventListener('click', closePicker);
    picker.addEventListener('close', () => {
        document.body.classList.remove('z4-store-media-modal-open');
        pickerTrigger?.focus();
    });
    picker.addEventListener('click', event => {
        if (event.target !== picker) return;
        const bounds = picker.getBoundingClientRect();
        const inside = event.clientX >= bounds.left && event.clientX <= bounds.right && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
        if (!inside) closePicker();
    });
    uploadFromPicker?.addEventListener('click', () => {
        if (pickerTarget === 'variant') variantMediaCard?.querySelector('[data-variant-image]')?.click();
        else (pickerTarget === 'main' ? mainUpload : galleryUpload).click();
    });
    picker.querySelectorAll('[data-media-asset]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.mediaAsset;
        if (pickerTarget === 'main') {
            mainAsset.value = id;
            mainUpload.value = '';
            refreshMainUploadPreview();
            closePicker();
        } else if (pickerTarget === 'variant' && variantMediaCard) {
            const asset = variantMediaCard.querySelector('[data-variant-asset]');
            const image = variantMediaCard.querySelector('[data-variant-image]');
            asset.value = id;
            image.value = '';
            renderVariantMedia(variantMediaCard);
            closePicker();
        } else {
            const current = galleryAssets.querySelector('input[value="' + id + '"]');
            if (current) {
                current.remove();
                closePicker();
            } else if (galleryIds().length < 8) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'gallery_assets[]';
                input.value = id;
                galleryAssets.append(input);
                closePicker();
            }
        }
        renderMediaSelection();
    }));
    mainUpload?.addEventListener('change', () => {
        refreshMainUploadPreview();
        if (mainUpload.files.length) { mainAsset.value = ''; closePicker(); renderMediaSelection(); }
    });
    galleryUpload?.addEventListener('change', () => {
        refreshGalleryUploadPreviews();
        if (galleryUpload.files.length) { closePicker(); renderMediaSelection(); }
    });
    const optionAxisEditor = form.querySelector('[data-option-axis-editor]');
    const optionAxisList = form.querySelector('[data-option-axis-list]');
    const optionAxisTemplate = form.querySelector('[data-option-axis-template]');
    const optionAxisEmpty = form.querySelector('[data-option-axis-empty]');
    const addOptionAxisButton = form.querySelector('[data-add-option-axis]');
    const normalizeOptionText = value => value.trim().replace(/\s+/g, ' ');
    const reindexOptionAxes = () => {
        Array.from(optionAxisList?.querySelectorAll('[data-option-axis]') || []).forEach((axis, axisIndex) => {
            axis.querySelector('[data-option-axis-name]').name = `option_axes[${axisIndex}][name]`;
            axis.querySelectorAll('[data-option-value-hidden]').forEach((input, valueIndex) => {
                input.name = `option_axes[${axisIndex}][values][${valueIndex}]`;
            });
        });
        if (optionAxisEmpty) optionAxisEmpty.hidden = Boolean(optionAxisList?.childElementCount);
        if (addOptionAxisButton) addOptionAxisButton.disabled = (optionAxisList?.childElementCount || 0) >= 4;
        refreshCreateSidebar();
    };
    const renderOptionValues = axis => {
        const list = axis.querySelector('[data-option-value-list]');
        const hint = axis.querySelector('[data-option-axis-hint]');
        list.replaceChildren();
        axis._optionValues.forEach((value, index) => {
            const chip = document.createElement('span');
            chip.className = 'z4-store-option-value-chip';
            chip.append(document.createTextNode(value));
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.value = value;
            hidden.dataset.optionValueHidden = '';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${value}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                axis._optionValues.splice(index, 1);
                renderOptionValues(axis);
                renderVariantBuilder();
            });
            chip.append(hidden, remove);
            list.append(chip);
        });
        hint.textContent = axis._optionValues.length
            ? `${axis._optionValues.length} value${axis._optionValues.length === 1 ? '' : 's'} added.`
            : 'Add at least one unique value.';
        hint.classList.toggle('is-error', axis._optionValues.length === 0);
        reindexOptionAxes();
    };
    const addOptionValue = axis => {
        const input = axis.querySelector('[data-option-value-input]');
        const value = normalizeOptionText(input.value);
        const hint = axis.querySelector('[data-option-axis-hint]');
        if (!value) {
            hint.textContent = 'Enter a value before adding it.';
            hint.classList.add('is-error');
            input.focus();
            return;
        }
        if (axis._optionValues.some(current => current.toLocaleLowerCase() === value.toLocaleLowerCase())) {
            hint.textContent = 'This value has already been added.';
            hint.classList.add('is-error');
            input.select();
            return;
        }
        if (axis._optionValues.length >= 20) {
            hint.textContent = 'A single attribute may contain up to 20 values.';
            hint.classList.add('is-error');
            return;
        }
        axis._optionValues.push(value);
        input.value = '';
        renderOptionValues(axis);
        renderVariantBuilder();
        input.focus();
    };
    const createOptionAxis = (initial = {}) => {
        if (!optionAxisList || !optionAxisTemplate || optionAxisList.childElementCount >= 4) return null;
        const axis = optionAxisTemplate.content.firstElementChild.cloneNode(true);
        const nameInput = axis.querySelector('[data-option-axis-name]');
        const valueInput = axis.querySelector('[data-option-value-input]');
        axis._optionValues = Array.isArray(initial.values)
            ? initial.values.map(normalizeOptionText).filter(Boolean).slice(0, 20)
            : [];
        nameInput.value = normalizeOptionText(initial.name || '');
        nameInput.addEventListener('input', () => {
            renderVariantBuilder();
            refreshCreateSidebar();
        });
        axis.querySelector('[data-add-option-value]').addEventListener('click', () => addOptionValue(axis));
        valueInput.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            addOptionValue(axis);
        });
        axis.querySelector('[data-remove-option-axis]').addEventListener('click', () => {
            axis.remove();
            reindexOptionAxes();
            renderVariantBuilder();
        });
        optionAxisList.append(axis);
        renderOptionValues(axis);
        reindexOptionAxes();
        nameInput.focus();
        return axis;
    };
    addOptionAxisButton?.addEventListener('click', () => createOptionAxis());
    const variantBuilder = form.querySelector('[data-variant-builder]');
    const variantList = form.querySelector('[data-variant-list]');
    const variantTemplate = form.querySelector('[data-variant-template]');
    const variantCount = form.querySelector('[data-variant-count]');
    const variantKey = optionIds => [...optionIds].sort().join('|');
    const oldVariantsByKey = new Map(oldVariants.map(variant => [
        variantKey(Array.isArray(variant.options) ? variant.options : []),
        variant,
    ]));
    const suggestedVariantSku = combination => {
        const prefix = field('slug')?.value.trim() || window.Z4StoreSlugify(field('name')?.value || '') || 'VARIANT';
        const suffix = combination.map(option => option.code || window.Z4StoreSlugify(option.name)).filter(Boolean).join('-');
        return `${prefix}-${suffix}`.replace(/^-+|-+$/g, '').toUpperCase().slice(0, 64);
    };
    function renderVariantMedia(card) {
        const asset = card.querySelector('[data-variant-asset]');
        const image = card.querySelector('[data-variant-image]');
        const preview = card.querySelector('[data-variant-preview]');
        const icon = card.querySelector('[data-variant-image-icon]');
        const trigger = card.querySelector('[data-open-variant-media]');
        const selectedFile = image.files[0];
        if (selectedFile && !card._variantPreviewUrl) card._variantPreviewUrl = URL.createObjectURL(selectedFile);
        const source = asset.value ? assetImage(asset.value) : (card._variantPreviewUrl || '');
        const name = asset.value ? assetName(asset.value) : (selectedFile?.name || '');
        preview.src = source;
        preview.alt = name ? `Selected variant image: ${name}` : '';
        preview.hidden = !source;
        icon.hidden = Boolean(source);
        trigger.classList.toggle('has-preview', Boolean(source));
        trigger.querySelector('strong').textContent = name ? `Selected: ${name}` : 'Choose Variant Image';
    }
    const hydrateVariantCard = (card, combination) => {
        const optionIds = combination.map(option => option.id);
        const key = variantKey(optionIds);
        card.dataset.combinationKey = key;
        card.querySelector('[data-variant-title]').textContent = combination.map(option => option.name).join(' / ');
        const optionHolder = card.querySelector('[data-variant-options]');
        optionHolder.replaceChildren();
        combination.forEach(option => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.value = option.id;
            input.dataset.variantOption = '';
            input.dataset.optionName = option.name;
            input.dataset.optionCode = option.code;
            optionHolder.append(input);
        });

        const previous = oldVariantsByKey.get(key);
        card.querySelectorAll('[data-variant-field]').forEach(input => {
            const fieldName = input.dataset.variantField;
            if (fieldName === 'allow_backorder') input.checked = Boolean(Number(previous?.[fieldName] || 0));
            else if (previous?.[fieldName] !== undefined && previous?.[fieldName] !== null) input.value = previous[fieldName];
        });
        const sku = card.querySelector('[data-variant-field="sku"]');
        if (!sku.value) sku.value = suggestedVariantSku(combination);
        if (previous?.sku) sku.dataset.userEdited = 'true';
        const asset = card.querySelector('[data-variant-asset]');
        if (previous?.asset) asset.value = previous.asset;

        const trigger = card.querySelector('[data-open-variant-media]');
        trigger.addEventListener('click', () => {
            pickerTrigger = trigger;
            pickerTarget = 'variant';
            variantMediaCard = card;
            pickerTitle.textContent = `Choose image for ${card.querySelector('[data-variant-title]').textContent}`;
            uploadFromPicker.textContent = 'Upload image';
            openPicker();
            renderMediaSelection();
        });
        card.querySelector('[data-variant-image]').addEventListener('change', event => {
            if (card._variantPreviewUrl) URL.revokeObjectURL(card._variantPreviewUrl);
            card._variantPreviewUrl = event.currentTarget.files[0] ? URL.createObjectURL(event.currentTarget.files[0]) : '';
            if (event.currentTarget.files.length) card.querySelector('[data-variant-asset]').value = '';
            closePicker();
            renderVariantMedia(card);
            renderMediaSelection();
        });
        renderVariantMedia(card);
        return card;
    };
    const reindexVariantCards = () => {
        Array.from(variantList?.querySelectorAll('[data-created-variant]') || []).forEach((card, index) => {
            card.querySelectorAll('[data-variant-field]').forEach(input => {
                input.name = `variants[${index}][${input.dataset.variantField}]`;
            });
            card.querySelector('[data-variant-backorder-hidden]').name = `variants[${index}][allow_backorder]`;
            card.querySelector('[data-variant-image]').name = `variants[${index}][image]`;
            card.querySelector('[data-variant-asset]').name = `variants[${index}][asset]`;
            card.querySelectorAll('[data-variant-option]').forEach(input => {
                input.name = `variants[${index}][options][]`;
            });
        });
    };
    const renderVariantBuilder = () => {
        if (!variantBuilder || !variantList || !variantTemplate) return;
        const isVariant = form.querySelector('input[name="type_code"]:checked')?.value === 'variant';
        const axisElements = Array.from(form.querySelectorAll('[data-option-axis]'));
        const axes = axisElements.map((axis, axisIndex) => ({
                id: String(axisIndex),
                name: normalizeOptionText(axis.querySelector('[data-option-axis-name]')?.value || ''),
                options: (axis._optionValues || []).map((name, valueIndex) => ({
                    id: `${axisIndex}:${valueIndex}`,
                    code: window.Z4StoreSlugify(name) || `value-${valueIndex + 1}`,
                    name,
                })),
            }));
        const axesComplete = axes.length > 0 && axes.every(axis => axis.name && axis.options.length);
        if (!isVariant || !axesComplete) {
            variantBuilder.hidden = true;
            variantList.replaceChildren();
            if (variantCount) variantCount.textContent = '0 combinations';
            refreshCreateSidebar();
            return;
        }
        let combinations = [[]];
        axes.forEach(axis => {
            combinations = combinations.flatMap(combination => axis.options.map(option => [
                ...combination,
                { ...option, axisId: axis.id, axisName: axis.name },
            ]));
        });
        const total = combinations.length;
        combinations = combinations.slice(0, 100);
        const fragment = document.createDocumentFragment();
        combinations.forEach(combination => {
            const key = combination
                .map(option => `${option.id}=${option.name.toLocaleLowerCase()}`)
                .sort()
                .join('|');
            let card = variantCardCache.get(key);
            if (!card) {
                card = hydrateVariantCard(variantTemplate.content.firstElementChild.cloneNode(true), combination);
                variantCardCache.set(key, card);
            }
            fragment.append(card);
        });
        variantList.replaceChildren(fragment);
        reindexVariantCards();
        variantBuilder.hidden = false;
        if (variantCount) {
            variantCount.textContent = total > 100
                ? `100 of ${total} combinations shown`
                : `${total} combination${total === 1 ? '' : 's'}`;
        }
        refreshCreateSidebar();
    };
    typeInputs.forEach(input => input.addEventListener('change', renderVariantBuilder));
    field('name')?.addEventListener('input', () => {
        variantCardCache.forEach(card => {
            const sku = card.querySelector('[data-variant-field="sku"]');
            if (!sku.dataset.userEdited) {
                const combination = Array.from(card.querySelectorAll('[data-variant-option]')).map(input => ({
                    id: input.value,
                    code: input.dataset.optionCode,
                    name: input.dataset.optionName,
                }));
                sku.value = suggestedVariantSku(combination);
            }
        });
    });
    field('slug')?.addEventListener('input', () => field('name')?.dispatchEvent(new Event('input')));
    variantBuilder?.addEventListener('input', event => {
        if (event.target.matches('[data-variant-field="sku"]')) event.target.dataset.userEdited = 'true';
    });
    (Array.isArray(oldOptionAxes) ? oldOptionAxes : []).slice(0, 4).forEach(axis => createOptionAxis(axis));
    reindexOptionAxes();
    renderVariantBuilder();
    renderMediaSelection();
    const tagEditor = form.querySelector('[data-tag-editor]');
    if (tagEditor) {
        const tagInput = tagEditor.querySelector('[data-tag-input]');
        const tagList = tagEditor.querySelector('[data-tag-list]');
        const tagValue = tagEditor.querySelector('[data-tags-value]');
        const suggestionList = tagEditor.querySelector('[data-tag-suggestions]');
        const availableTags = JSON.parse(tagEditor.dataset.tagOptions || '[]');
        const tags = tagValue.value.split(/[,;\r\n]+/).map(value => value.trim()).filter(Boolean);
        let activeSuggestion = -1;
        let visibleSuggestions = [];
        const closeSuggestions = () => {
            activeSuggestion = -1;
            visibleSuggestions = [];
            suggestionList.hidden = true;
            suggestionList.replaceChildren();
            tagInput.setAttribute('aria-expanded', 'false');
            tagInput.removeAttribute('aria-activedescendant');
        };
        const selectSuggestion = tag => {
            tagInput.value = tag;
            addTag();
        };
        const renderSuggestions = () => {
            const query = tagInput.value.split(/[,;\r\n]+/).pop().trim().toLowerCase();
            if (!query) {
                closeSuggestions();
                return;
            }
            visibleSuggestions = availableTags
                .filter(tag => !tags.some(selected => selected.toLowerCase() === tag.toLowerCase()))
                .filter(tag => tag.toLowerCase().includes(query))
                .sort((left, right) => {
                    const leftStarts = left.toLowerCase().startsWith(query);
                    const rightStarts = right.toLowerCase().startsWith(query);
                    return leftStarts === rightStarts ? left.localeCompare(right) : (leftStarts ? -1 : 1);
                })
                .slice(0, 6);
            if (!visibleSuggestions.length) {
                closeSuggestions();
                return;
            }
            activeSuggestion = Math.min(activeSuggestion, visibleSuggestions.length - 1);
            suggestionList.replaceChildren();
            visibleSuggestions.forEach((tag, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = `z4-store-create-tag-option-${index}`;
                option.className = 'z4-store-tag-suggestion';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', index === activeSuggestion ? 'true' : 'false');
                option.classList.toggle('is-active', index === activeSuggestion);
                option.textContent = tag;
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => selectSuggestion(tag));
                suggestionList.append(option);
            });
            suggestionList.hidden = false;
            tagInput.setAttribute('aria-expanded', 'true');
            if (activeSuggestion >= 0) tagInput.setAttribute('aria-activedescendant', `z4-store-create-tag-option-${activeSuggestion}`);
            else tagInput.removeAttribute('aria-activedescendant');
        };
        const renderTags = () => {
            tagList.replaceChildren();
            tags.forEach((tag, index) => {
                const item = document.createElement('span');
                item.className = 'z4-store-tag-chip';
                item.append(document.createTextNode(tag));
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Remove ' + tag);
                remove.textContent = '×';
                remove.addEventListener('click', () => { tags.splice(index, 1); renderTags(); });
                item.append(remove);
                tagList.append(item);
            });
            tagValue.value = tags.join(', ');
        };
        const addTag = (focusInput = true) => {
            const pendingTags = tagInput.value
                .split(/[,;\r\n]+/)
                .map(value => value.trim())
                .filter(Boolean);
            pendingTags.forEach(tag => {
                if (!tags.some(value => value.toLowerCase() === tag.toLowerCase())) tags.push(tag);
            });
            if (pendingTags.length) renderTags();
            tagInput.value = '';
            closeSuggestions();
            if (focusInput) tagInput.focus();
        };
        tagEditor.querySelector('[data-add-tag]').addEventListener('click', () => addTag());
        tagInput.addEventListener('input', renderSuggestions);
        tagInput.addEventListener('focus', renderSuggestions);
        tagInput.addEventListener('blur', closeSuggestions);
        tagInput.addEventListener('keydown', event => {
            if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && visibleSuggestions.length) {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                activeSuggestion = (activeSuggestion + direction + visibleSuggestions.length) % visibleSuggestions.length;
                renderSuggestions();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (activeSuggestion >= 0) selectSuggestion(visibleSuggestions[activeSuggestion]);
                else addTag();
            } else if (event.key === 'Escape') {
                closeSuggestions();
            }
        });
        form.addEventListener('submit', () => addTag(false));
        renderTags();
    }
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-z4-store-slug-form]');
    document.querySelectorAll('[data-create-tabs] [data-create-locale]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var locale = link.dataset.createLocale;
            if (locale === @json($defaultLocale)) { return; }
            var name = form.querySelector('[name="name"]');
            if (!name || !name.value.trim()) {
                alert(@json($t('store::ui.admin_product_name_required')));
                return;
            }
            form.querySelector('[data-switch-locale]').value = locale;
            form.requestSubmit();
        });
    });
});
</script>
</x-app-layout>
