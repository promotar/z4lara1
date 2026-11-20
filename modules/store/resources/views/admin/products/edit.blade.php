<x-app-layout>
@php
    $formLocale = $productLocale ?? ($product->default_locale ?? 'en');
    $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
    $displayStatus = $product->status === 'archived' ? 'archived' : ($product->status === 'draft' ? 'draft' : ($product->visibility === 'hidden' ? 'hidden' : 'published'));
    $isVariableProduct = $product->type_code === 'variant';
    $defaultVariant = $variants->firstWhere('is_default', true) ?? $variants->first();
    $regularPrice = $defaultVariant?->prices?->firstWhere('type', 'regular');
    $activeSale = $defaultVariant?->prices?->first(function ($price) {
        $now = now();
        return $price->type === 'sale' && $price->status === 'active'
            && ($price->starts_at === null || $now->gte($price->starts_at))
            && ($price->ends_at === null || $now->lt($price->ends_at));
    });
    $activeSalePrice = $activeSale ? number_format($activeSale->amount_minor / 1000, 3, '.', '') : '';
    $activeSaleStarts = $activeSale?->starts_at ? \Illuminate\Support\Carbon::parse($activeSale->starts_at)->format('Y-m-d\TH:i') : '';
    $activeSaleEnds = $activeSale?->ends_at ? \Illuminate\Support\Carbon::parse($activeSale->ends_at)->format('Y-m-d\TH:i') : '';
    $publishedAt = $product->published_at ? \Illuminate\Support\Carbon::parse($product->published_at)->format('Y-m-d\TH:i') : '';
    $selectedBrand = old('brand', $brands->firstWhere('id', (int) $product->brand_id)?->public_id);
    $selectedCategories = old('categories', $categories->filter(fn ($category) => $assignedCategories->has($category->id))->pluck('public_id')->all());
    $inventory = $defaultVariant?->inventory;
    $stockStatus = $inventory?->tracking_mode === 'tracked' && ((int) $inventory->on_hand - (int) $inventory->reserved) <= 0 ? 'out_of_stock' : 'in_stock';
    $stockQuantity = $inventory?->tracking_mode === 'tracked' ? (int) $inventory->on_hand : null;
    $currentMainMedia = $media->first(fn ($item) => (bool) $item->is_primary);
    $currentGalleryMedia = $media->reject(fn ($item) => (bool) $item->is_primary)->values();
    $currentGalleryAssetIds = $currentGalleryMedia->pluck('asset_public_id')->filter()->values();
    $summaryPrice = $activeSale ?? $regularPrice;
    $summaryPriceFormatted = $summaryPrice
        ? app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $summaryPrice->amount_minor)
        : 'Not set';
    $summaryBrand = $brands->firstWhere('public_id', $selectedBrand)?->name ?? 'No brand';
    $inventoryAvailable = $inventory?->tracking_mode === 'tracked'
        ? max(0, (int) $inventory->on_hand - (int) $inventory->reserved)
        : null;
    $pricedVariants = $variants->filter(fn ($variant) => $variant->prices->contains('type', 'regular'))->count();
    $inventoriedVariants = $variants->filter(fn ($variant) => $variant->inventory !== null)->count();
    $shippingPolicyMode = old('shipping_policy_mode', filled($product->shipping_policy) ? 'custom' : 'inherit');
    $returnPolicyMode = old('return_policy_mode', filled($product->return_policy) ? 'custom' : 'inherit');
@endphp
<main class="z4-store-admin z4-store-form-page z4-store-product-editor-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading z4-store-product-heading">
            <div>
                <a class="z4-store-back" href="{{ route('admin.plugins.store.products.index') }}">&larr; All Products</a>
                <div class="z4-store-title-line"><h1>{{ $t('store::ui.auto_7177e0adfa') }}</h1><span class="z4-store-status is-{{ $displayStatus }}">{{ $displayStatus === 'archived' ? 'Deleted' : ucfirst($displayStatus) }}</span></div>
                <p>{{ $t('store::ui.auto_734baf3692') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                @if($product->status === 'published' && $product->visibility === 'public' && $product->slug && \Illuminate\Support\Facades\Route::has('store.products.show'))
                    <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('store.products.show', $product->slug) }}" target="_blank" rel="noopener">{{ $t('store::ui.auto_727b632b86') }}</a>
                @endif
                @if($product->status === 'archived')
                    @can('store.products.archive')
                        <form method="post" action="{{ route('admin.plugins.store.products.restore', $product->public_id) }}">@csrf<input type="hidden" name="version" value="{{ $product->version }}"><button class="z4-store-btn z4-store-btn-primary">{{ $t('store::ui.auto_5cfed32bf9') }}</button></form>
                    @endcan
                @endif
            </div>
        </header>

        @if (isset($productLocales) && count($productLocales) > 1)
            <div class="z4-store-lang-tabs" style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px">
                @foreach ($productLocales as $code => $name)
                    <a href="{{ route('admin.plugins.store.products.edit', ['product' => $product->public_id, 'locale' => $code]) }}"
                       style="display:inline-flex;align-items:center;gap:6px;border:1px solid #dfe3ea;border-radius:7px;background:{{ ($productLocale ?? $defaultLocale) === $code ? '#7c1d1d' : '#fff' }} !important;color:{{ ($productLocale ?? $defaultLocale) === $code ? '#fff' : '#344054' }} !important;border-color:{{ ($productLocale ?? $defaultLocale) === $code ? '#7c1d1d' : '#dfe3ea' }} !important;padding:7px 13px;font-size:12px;font-weight:800;text-decoration:none">
                        {{ $name }}
                        <span style="font-size:10px;opacity:.8">{{ $code === $defaultLocale ? '' : (in_array($code, $translatedLocales ?? [], true) ? '✓' : '+') }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if (($productLocale ?? $defaultLocale) !== $defaultLocale)
            <p style="border:1px solid #f0dada;border-radius:6px;background:#fff8f7;padding:8px 12px;color:#8a4a44;font-size:12px;margin:0 0 14px">{{ $t('core.content.translation_note') }}</p>
        @endif

        <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.products.update', $product->public_id) }}" data-dirty-form data-z4-store-slug-form>
            @csrf @method('PATCH')
            <input type="hidden" name="version" value="{{ $product->version }}">
            <input type="hidden" name="locale" value="{{ old('locale', $productLocale ?? $product->default_locale) }}">
            <input type="hidden" name="internal_code" value="{{ $product->internal_code }}">
            <input type="hidden" name="categories_present" value="1">
            @if(!$isVariableProduct)
                <input type="hidden" name="inventory_present" value="1">
                <input type="hidden" name="regular_price_initial" value="{{ $regularPrice ? number_format($regularPrice->amount_minor / 1000, 3, '.', '') : '' }}">
                <input type="hidden" name="active_sale" value="{{ $activeSale?->public_id }}">
                <input type="hidden" name="sale_price_initial" value="{{ $activeSalePrice }}">
                <input type="hidden" name="sale_starts_at_initial" value="{{ $activeSaleStarts }}">
                <input type="hidden" name="sale_ends_at_initial" value="{{ $activeSaleEnds }}">
            @endif

            <div class="z4-store-editor-layout">
                <div class="z4-store-editor-main">
                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>1</span><div><h2>{{ $t('store::ui.auto_c52feaf85b') }}</h2><p>{{ $t('store::ui.auto_c7045a28ce') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label class="is-wide"><span>{{ $t('store::ui.auto_b9aefa40a5') }} <b>*</b></span><input class="z4-store-input" name="name" value="{{ old('name', $product->name) }}" required maxlength="255" data-slug-source><small>{{ $t('store::ui.auto_31ba0dfa0d') }}</small></label>
                            <label><span>{{ $t('store::ui.auto_0c90858852') }} <b>*</b></span><input class="z4-store-input" name="slug" value="{{ old('slug', $product->slug) }}" required maxlength="255" pattern="[a-z0-9][a-z0-9-]*" data-slug-target><small>{{ $t('store::ui.auto_1f609e6f26') }}</small></label>
                            @if(!$isVariableProduct)
                                <label><span>SKU <b>*</b></span><input class="z4-store-input" value="{{ $defaultVariant?->sku }}" disabled><small>{{ $t('store::ui.auto_81d708679e') }}</small></label>
                            @endif
                            <label><span>{{ $t('store::ui.auto_1be6f9eb56') }}</span><select class="z4-store-select" name="brand"><option value="">{{ $t('store::ui.auto_8e41eae149') }}</option>@foreach($brands as $brand)<option value="{{ $brand->public_id }}" @selected($selectedBrand === $brand->public_id)>{{ $brand->name }}</option>@endforeach</select></label>
                            <label class="is-wide"><span>{{ $t('store::ui.auto_36e3402e4d') }}</span><textarea class="z4-store-textarea" name="short_description" rows="3" maxlength="2000">{{ old('short_description', $product->short_description) }}</textarea></label>
                            <label class="is-wide"><span>{{ $t('store::ui.auto_8acbbeabbc') }}</span><textarea class="z4-store-textarea" name="description" rows="8" maxlength="50000">{{ old('description', $product->description) }}</textarea></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>2</span><div><h2>{{ $t('store::ui.auto_6c5cc79573') }}</h2><p>{{ $t('store::ui.auto_5838e57859') }}</p></div></header>
                        <div class="z4-store-editor-media-tools">
                            <div class="z4-store-media-source">
                                <input type="file" accept="image/jpeg,image/png,image/webp" name="main_image" data-main-upload hidden>
                                <button type="button" class="z4-store-upload-box {{ $currentMainMedia ? 'has-preview' : '' }}" data-open-media-picker="main">
                                    <img class="z4-store-upload-preview" data-main-preview data-current-asset="{{ $currentMainMedia?->asset_public_id }}" src="{{ $currentMainMedia ? app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($currentMainMedia->path) : '' }}" alt="{{ $currentMainMedia ? 'Current main product image' : '' }}" @hidden(!$currentMainMedia)>
                                    <span data-main-upload-icon aria-hidden="true" @hidden($currentMainMedia)>⇧</span>
                                    <strong>{{ $currentMainMedia ? 'Change Main Product Image' : 'Choose Main Product Image' }}</strong>
                                    <small>{{ $t('store::ui.auto_14000703f2') }}</small>
                                </button>
                                @if($currentMainMedia)
                                    <input type="hidden" name="media[]" value="{{ $currentMainMedia->public_id }}" form="media-order-form">
                                    <div class="z4-store-media-source-footer"><span><strong>{{ $t('store::ui.auto_98813b7918') }}</strong><small>{{ $currentMainMedia->original_filename }}</small></span><button class="z4-store-link-danger" type="submit" form="remove-media-{{ $currentMainMedia->public_id }}">{{ $t('store::ui.auto_8443a93098') }}</button></div>
                                @endif
                            </div>
                            <div class="z4-store-media-source">
                                <input type="file" accept="image/jpeg,image/png,image/webp" name="gallery[]" multiple data-gallery-upload hidden>
                                <button type="button" class="z4-store-upload-box" data-open-media-picker="gallery">
                                    <span aria-hidden="true">⇧</span>
                                    <strong>{{ $t('store::ui.auto_6d92c90c10') }}</strong>
                                    <small>{{ $t('store::ui.auto_5564cac57e') }}</small>
                                </button>
                                @if($currentGalleryMedia->isNotEmpty())
                                    <div class="z4-store-inline-gallery-heading"><span><strong>{{ $t('store::ui.auto_11b8cfbdf3') }}</strong><small>{{ $t('store::ui.auto_eda1293827') }}</small></span>@if($currentGalleryMedia->count() > 1)<button class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" type="submit" form="media-order-form">{{ $t('store::ui.auto_a3bc168927') }}</button>@endif</div>
                                    <div class="z4-store-inline-gallery" data-media-list aria-label="{{ $t('store::ui.auto_f3e4aee8c1') }}">
                                        @foreach($currentGalleryMedia as $item)
                                            <article class="z4-store-inline-gallery-item" data-media-item>
                                                <input type="hidden" name="media[]" value="{{ $item->public_id }}" form="media-order-form">
                                                <img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($item->path) }}" alt="">
                                                <div><strong>{{ $item->original_filename ?? $item->public_id }}</strong><small>{{ ucfirst($item->role) }}</small></div>
                                                <div class="z4-store-media-actions"><button type="button" data-move-up aria-label="{{ $t('store::ui.auto_e5933ad861') }}" title="{{ $t('store::ui.auto_e5933ad861') }}">↑</button><button type="button" data-move-down aria-label="{{ $t('store::ui.auto_dd125b5ebc') }}" title="{{ $t('store::ui.auto_dd125b5ebc') }}">↓</button><button class="z4-store-link-danger" type="submit" form="remove-media-{{ $item->public_id }}">{{ $t('store::ui.auto_1063e38cb5') }}</button></div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <input type="hidden" name="main_asset" value="{{ old('main_asset') }}" data-main-asset>
                            <span data-gallery-assets hidden>@foreach(old('gallery_assets', []) as $asset)<input type="hidden" name="gallery_assets[]" value="{{ $asset }}">@endforeach</span>
                        </div>
                        <div class="z4-store-selected-media" data-gallery-previews aria-label="{{ $t('store::ui.auto_e8495cdfe3') }}" hidden></div>
                        <div class="z4-store-media-selection-summary">
                            <small data-main-asset-label>{{ $currentMainMedia ? 'Current main image: '.$currentMainMedia->original_filename : 'No main image selected.' }}</small>
                            <small data-gallery-assets-label>{{ $t('store::ui.auto_264e5de3b6') }}</small>
                        </div>

                        <dialog class="z4-store-media-picker" data-media-picker aria-labelledby="z4-store-edit-media-picker-title">
                            <header><div><span class="z4-store-eyebrow">{{ $t('store::ui.auto_bf09639f7b') }}</span><h3 id="z4-store-edit-media-picker-title" data-media-picker-title>{{ $t('store::ui.auto_5a31ca938e') }}</h3><p>{{ $t('store::ui.auto_b762896a6a') }}</p></div><div class="z4-store-media-picker-actions"><button type="button" class="z4-store-btn z4-store-btn-primary z4-store-btn-small" data-upload-from-picker>{{ $t('store::ui.auto_0ff5840c17') }}</button><button type="button" class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" data-close-media-picker>{{ $t('store::ui.auto_d3d2e61733') }}</button></div></header>
                            <div class="z4-store-media-picker-grid">
                                @forelse($mediaAssets as $asset)
                                    <button type="button" class="z4-store-media-picker-item" data-media-asset="{{ $asset->public_id }}" data-media-name="{{ $asset->original_filename }}" @if($asset->public_id === $currentMainMedia?->asset_public_id) data-current-main @endif @if($currentGalleryAssetIds->contains($asset->public_id)) data-current-gallery @endif><img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($asset->path) }}" alt=""><span>{{ $asset->original_filename }}</span>@if($asset->public_id === $currentMainMedia?->asset_public_id || $currentGalleryAssetIds->contains($asset->public_id))<small>{{ $t('store::ui.auto_11670f7569') }}</small>@endif</button>
                                @empty
                                    <div class="z4-store-inline-empty">{{ $t('store::ui.auto_d2b51a525b') }}</div>
                                @endforelse
                            </div>
                        </dialog>

                        @if($media->isEmpty())
                            <div class="z4-store-inline-empty">{{ $t('store::ui.auto_9f14a0ef9f') }}</div>
                        @endif
                    </section>

                    @if(!$isVariableProduct)
                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>3</span><div><h2>{{ $t('store::ui.auto_e22ac25b06') }}</h2><p>{{ $t('store::ui.auto_c50a2b4c70') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_d090bb34c5') }}</span><input class="z4-store-input" name="regular_price" inputmode="decimal" value="{{ old('regular_price', $regularPrice ? number_format($regularPrice->amount_minor / 1000, 3, '.', '') : '') }}" placeholder="0.000"><small>{{ $t('store::ui.auto_4152697988') }}</small></label>
                            <label><span>{{ $t('store::ui.auto_8ebc03ab48') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" name="sale_price" inputmode="decimal" value="{{ old('sale_price', $activeSalePrice) }}" placeholder="0.000"><small>{{ $activeSale ? 'Update the active sale price or period.' : 'Leave blank when no sale is needed.' }}</small></label>
                            <label><span>{{ $t('store::ui.auto_2169dec129') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_starts_at" value="{{ old('sale_starts_at', $activeSaleStarts) }}"></label>
                            <label><span>{{ $t('store::ui.auto_707e6d2433') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_ends_at" value="{{ old('sale_ends_at', $activeSaleEnds) }}"></label>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>4</span><div><h2>{{ $t('store::ui.auto_9e2ff9d528') }}</h2><p>{{ $t('store::ui.auto_a5e7e4dd74') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_faeaec9eda') }}</span><select class="z4-store-select" name="stock_status"><option value="in_stock" @selected(old('stock_status', $stockStatus) === 'in_stock')>{{ $t('store::ui.auto_69d08bd5f8') }}</option><option value="out_of_stock" @selected(old('stock_status', $stockStatus) === 'out_of_stock')>{{ $t('store::ui.auto_e990b6285e') }}</option></select></label>
                            <label><span>{{ $t('store::ui.auto_c8c5918f11') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="number" name="stock_quantity" min="0" value="{{ old('stock_quantity', $stockQuantity) }}"><small>{{ $t('store::ui.auto_cb8f185dfc') }}</small></label>
                            <label class="z4-store-check is-wide"><input type="hidden" name="allow_backorder" value="0"><input type="checkbox" name="allow_backorder" value="1" @checked(old('allow_backorder', (bool) $inventory?->allow_backorder))><span><strong>{{ $t('store::ui.auto_4389e69917') }}</strong><small>{{ $t('store::ui.auto_3e7478fb54') }}</small></span></label>
                        </div>
                    </section>
                    @endif

                    <section class="z4-store-form-card" data-product-policies>
                        <header class="z4-store-section-heading"><span>{{ $isVariableProduct ? 3 : 5 }}</span><div><h2>Shipping &amp; Returns</h2><p>{{ $t('store::ui.auto_b30b038a35') }}</p></div></header>
                        <div class="z4-store-product-policy-grid">
                            <div class="z4-store-subcard" data-policy-editor>
                                <div class="z4-store-section-heading"><div><h3>{{ $t('store::ui.auto_5cfdabe5c8') }}</h3><p>{{ $t('store::ui.auto_6036c3e823') }}</p></div></div>
                                <label><span>{{ $t('store::ui.auto_8597698823') }}</span><select class="z4-store-select" name="shipping_policy_mode" data-policy-mode><option value="inherit" @selected($shippingPolicyMode === 'inherit')>{{ $t('store::ui.auto_cc2b199740') }}</option><option value="custom" @selected($shippingPolicyMode === 'custom')>{{ $t('store::ui.auto_6f17f27301') }}</option></select></label>
                                <div class="z4-store-policy-default"><strong>{{ $t('store::ui.auto_696a2fd70c') }}</strong><p dir="auto">{{ trim((string) ($storePolicies['delivery'] ?? '')) !== '' ? $storePolicies['delivery'] : 'The general shipping policy has not been configured yet. The standard delivery information will be shown.' }}</p>@can('store.settings.view')<a href="{{ route('admin.plugins.store.settings.index') }}#policies">{{ $t('store::ui.auto_68d8566cf4') }}</a>@endcan</div>
                                <label data-policy-custom><span>{{ $t('store::ui.auto_c015d10c72') }} <b>*</b></span><textarea class="z4-store-textarea" name="shipping_policy" rows="7" maxlength="20000" placeholder="Describe delivery method, timing, charges, handling, or product-specific restrictions.">{{ old('shipping_policy', $product->shipping_policy) }}</textarea><small>{{ $t('store::ui.auto_cba18712e6') }}</small></label>
                            </div>
                            <div class="z4-store-subcard" data-policy-editor>
                                <div class="z4-store-section-heading"><div><h3>{{ $t('store::ui.auto_4762fdc5fd') }}</h3><p>{{ $t('store::ui.auto_1621776463') }}</p></div></div>
                                <label><span>{{ $t('store::ui.auto_8597698823') }}</span><select class="z4-store-select" name="return_policy_mode" data-policy-mode><option value="inherit" @selected($returnPolicyMode === 'inherit')>{{ $t('store::ui.auto_cc2b199740') }}</option><option value="custom" @selected($returnPolicyMode === 'custom')>{{ $t('store::ui.auto_6f17f27301') }}</option></select></label>
                                <div class="z4-store-policy-default"><strong>{{ $t('store::ui.auto_696a2fd70c') }}</strong><p dir="auto">{{ trim((string) ($storePolicies['return'] ?? '')) !== '' ? $storePolicies['return'] : 'The general returns policy has not been configured yet. The standard return information will be shown.' }}</p>@can('store.settings.view')<a href="{{ route('admin.plugins.store.settings.index') }}#policies">{{ $t('store::ui.auto_68d8566cf4') }}</a>@endcan</div>
                                <label data-policy-custom><span>{{ $t('store::ui.auto_af33569104') }} <b>*</b></span><textarea class="z4-store-textarea" name="return_policy" rows="7" maxlength="20000" placeholder="Describe eligibility, time limits, product condition, exclusions, and the return process.">{{ old('return_policy', $product->return_policy) }}</textarea><small>{{ $t('store::ui.auto_5ab872d824') }}</small></label>
                            </div>
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>{{ $isVariableProduct ? 4 : 6 }}</span><div><h2>{{ $t('store::ui.auto_7d02189585') }}</h2><p>{{ $t('store::ui.auto_b086f6e46e') }}</p></div></header>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_7d069ca957') }}</span><select class="z4-store-select" name="visibility"><option value="hidden" @selected(old('visibility', $product->visibility) === 'hidden')>{{ $t('store::ui.auto_7e98d5cc7e') }}</option><option value="public" @selected(old('visibility', $product->visibility) === 'public')>{{ $t('store::ui.auto_0160c4dd6d') }}</option></select></label>
                            <label class="z4-store-check"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked(old('featured', (bool) $product->is_featured))><span><strong>{{ $t('store::ui.auto_a5e5066322') }}</strong><small>{{ $t('store::ui.auto_91d8229b74') }}</small></span></label>
                            @if($product->status === 'published')
                                <label><span>{{ $t('store::ui.auto_2b3b9b61e8') }}</span><input class="z4-store-input" type="datetime-local" name="published_at" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('published_at', $publishedAt) }}" required><small>{{ $t('store::ui.auto_8af79a5361') }}</small></label>
                            @endif
                        </div>
                    </section>

                    <section class="z4-store-form-card">
                        <header class="z4-store-section-heading"><span>{{ $isVariableProduct ? 5 : 7 }}</span><div><h2>{{ $t('store::ui.auto_b3612c3d39') }}</h2><p>{{ $t('store::ui.auto_0f37f28f2a') }}</p></div></header>
                        <div class="z4-store-form-grid"><div class="is-wide z4-store-tag-editor" data-tag-editor data-tag-options="{{ json_encode($tagSuggestions, JSON_UNESCAPED_UNICODE) }}"><span>{{ $t('store::ui.auto_189f63f277') }}</span><div class="z4-store-tag-entry"><div class="z4-store-tag-input-wrap"><input class="z4-store-input" data-tag-input maxlength="120" placeholder="{{ $t('store::ui.auto_30ee061747') }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="z4-store-tag-suggestions"><div class="z4-store-tag-suggestions" id="z4-store-tag-suggestions" data-tag-suggestions role="listbox" hidden></div></div><button type="button" class="z4-store-btn z4-store-btn-ghost" data-add-tag>{{ $t('store::ui.auto_2efd89b3cc') }}</button></div><div class="z4-store-tag-list" data-tag-list></div><input type="hidden" name="tags" value="{{ old('tags', $product->tags_list) }}" data-tags-value><small>{{ $t('store::ui.auto_b027b0999f') }}</small></div></div>
                        <div class="z4-store-organization-label"><strong>{{ $t('store::ui.auto_af1b98adf7') }} <b>*</b></strong><small>{{ $t('store::ui.auto_c4b241102f') }}</small></div>
                        <div class="z4-store-category-checks">
                            @forelse($categories as $category)
                                <label style="--category-depth: {{ $category->depth }}"><input type="checkbox" name="categories[]" value="{{ $category->public_id }}" @checked(in_array($category->public_id, $selectedCategories, true))><span><strong>{{ $category->name ?? $category->public_id }}</strong><small>{{ $category->depth ? 'Nested category' : 'Top-level category' }}</small></span><input type="radio" name="primary_category" value="{{ $category->public_id }}" aria-label="{{ $t('store::ui.auto_4d6f1df300') }}" @checked(old('primary_category', $assignedCategories->get($category->id)?->is_primary ? $category->public_id : null) === $category->public_id)></label>
                            @empty
                                <div class="z4-store-inline-empty">{{ $t('store::ui.auto_7b9b6a0fb3') }} <a href="{{ route('admin.plugins.store.categories.index') }}">{{ $t('store::ui.auto_cff15d4379') }}</a>.</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="z4-store-editor-side">
                    <section class="z4-store-side-card">
                        <h2>{{ $t('store::ui.auto_cee86c9977') }}</h2>
                        <ul class="z4-store-readiness">
                            <li class="{{ $product->name ? 'is-done' : '' }}">{{ $t('store::ui.auto_87e9eb1c1a') }}</li>
                            <li class="{{ $media->where('is_primary', true)->isNotEmpty() ? 'is-done' : '' }}">{{ $t('store::ui.auto_bac1484935') }}</li>
                            @if($isVariableProduct)
                                <li class="{{ $variants->isNotEmpty() ? 'is-done' : '' }}">{{ $t('store::ui.auto_13de9de5b3') }}</li>
                                <li class="{{ $variants->isNotEmpty() && $pricedVariants === $variants->count() ? 'is-done' : '' }}">{{ $t('store::ui.auto_67d1fb926a') }}</li>
                                <li class="{{ $variants->isNotEmpty() && $inventoriedVariants === $variants->count() ? 'is-done' : '' }}">{{ $t('store::ui.auto_902eb6ff34') }}</li>
                            @else
                                <li class="{{ $regularPrice ? 'is-done' : '' }}">{{ $t('store::ui.auto_4d8adffdc0') }}</li>
                                <li class="{{ $inventory ? 'is-done' : '' }}">{{ $t('store::ui.auto_2f71113335') }}</li>
                                <li class="{{ $defaultVariant?->sku ? 'is-done' : '' }}">{{ $t('store::ui.auto_072f2424c7') }}</li>
                            @endif
                            <li class="{{ $assignedCategories->isNotEmpty() ? 'is-done' : '' }}">{{ $t('store::ui.auto_78efa72df3') }}</li>
                        </ul>
                    </section>

                    <section class="z4-store-side-card z4-store-product-summary-card">
                        <h2>{{ $t('store::ui.auto_e7bd3fef06') }}</h2>
                        <div class="z4-store-product-summary-identity">
                            @if($currentMainMedia)
                                <img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($currentMainMedia->path) }}" alt="">
                            @else
                                <span class="z4-store-product-summary-placeholder" aria-hidden="true">{{ $t('store::ui.auto_9dd7104e68') }}</span>
                            @endif
                            <div><strong dir="auto">{{ $product->name }}</strong><small dir="auto">{{ $product->internal_code }}</small></div>
                        </div>
                        <dl>
                            <div><dt>{{ $t('store::ui.auto_fb025c5999') }}</dt><dd>{{ $isVariableProduct ? 'Set per variant' : $summaryPriceFormatted }}</dd></div>
                            <div><dt>SKU</dt><dd dir="auto">{{ $isVariableProduct ? $variants->count().' variants' : ($defaultVariant?->sku ?: 'Not set') }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_1be6f9eb56') }}</dt><dd dir="auto">{{ $summaryBrand }}</dd></div>
                            <div><dt>{{ $t('store::ui.auto_af1b98adf7') }}</dt><dd>{{ $t('store::ui.auto_dbacf5f198') }}</dd></div>
                        </dl>
                    </section>

                    <section class="z4-store-side-card z4-store-product-inventory-card">
                        <h2>{{ $t('store::ui.auto_e0b01e7810') }}</h2>
                        @if($isVariableProduct)
                            <div class="z4-store-side-inventory-value">
                                <strong>{{ $t('store::ui.auto_62c13d5099') }}</strong>
                                <small>{{ $t('store::ui.auto_74edbe09c7') }}</small>
                            </div>
                        @else
                        <div class="z4-store-side-inventory-value">
                            @if(!$inventory)
                                <strong>{{ $t('store::ui.auto_db3f921088') }}</strong><small>{{ $t('store::ui.auto_868ff5c01a') }}</small>
                            @elseif($inventory->tracking_mode === 'tracked')
                                <strong>{{ $inventoryAvailable }}</strong><small>{{ $t('store::ui.auto_aec554bdcb') }}</small>
                            @else
                                <strong>{{ $t('store::ui.auto_420f273793') }}</strong><small>{{ $t('store::ui.auto_46a4cb519b') }}</small>
                            @endif
                        </div>
                        @if($inventory)
                            <dl>
                                <div><dt>{{ $t('store::ui.auto_2205dc082b') }}</dt><dd>{{ $inventory->tracking_mode === 'tracked' ? 'Quantity tracked' : 'Not tracked' }}</dd></div>
                                @if($inventory->tracking_mode === 'tracked')
                                    <div><dt>{{ $t('store::ui.auto_9ee0473b57') }}</dt><dd>{{ (int) $inventory->on_hand }}</dd></div>
                                    <div><dt>{{ $t('store::ui.auto_942d4e37dd') }}</dt><dd>{{ (int) $inventory->reserved }}</dd></div>
                                @endif
                                <div><dt>{{ $t('store::ui.auto_65695213c3') }}</dt><dd>{{ $inventory->allow_backorder ? 'Allowed' : 'Not allowed' }}</dd></div>
                            </dl>
                            <a class="z4-store-btn z4-store-btn-ghost z4-store-side-card-action" href="{{ route('admin.plugins.store.inventory.index') }}#item-{{ $inventory->public_id }}">{{ $t('store::ui.auto_ccc9658d0f') }}</a>
                        @endif
                        @endif
                    </section>
                </aside>
            </div>

            <footer class="z4-store-sticky-actions">
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.index') }}">{{ $t('store::ui.auto_ea4788705e') }}</a>
                @if($product->status === 'published')
                    @can('store.products.publish')
                        <button class="z4-store-btn z4-store-btn-primary" name="intent" value="publish" data-submit-button><span>{{ $t('store::ui.auto_f5d6040ed7') }}</span></button>
                    @else
                        <button class="z4-store-btn z4-store-btn-primary" name="intent" value="draft" data-submit-button><span>{{ $t('store::ui.auto_f5d6040ed7') }}</span></button>
                    @endcan
                @else
                    <button class="z4-store-btn z4-store-btn-ghost" name="intent" value="draft" data-submit-button><span>{{ $t('store::ui.auto_f5d6040ed7') }}</span></button>
                    @can('store.products.publish')
                        @if($product->status !== 'archived')
                            <button class="z4-store-btn z4-store-btn-primary" name="intent" value="publish" data-submit-button><span>{{ $t('store::ui.auto_d1907388a3') }}</span></button>
                        @endif
                    @endcan
                @endif
            </footer>
        </form>

        @if($media->isNotEmpty())
            <form id="media-order-form" method="post" action="{{ route('admin.plugins.store.products.media.reorder', $product->public_id) }}" data-media-order>@csrf @method('PUT')</form>
            @foreach($media as $item)
                <form id="remove-media-{{ $item->public_id }}" method="post" action="{{ route('admin.plugins.store.products.media.destroy', [$product->public_id, $item->public_id]) }}" data-z4-confirm data-confirm-title="{{ $t('store::ui.auto_74b0a7d7d1') }}" data-confirm-message="{{ $item->is_primary ? 'This main image will be removed. Choose another main image before publishing the product.' : 'This image will be removed from the product gallery.' }}" data-confirm-label="Remove image">@csrf @method('DELETE')</form>
            @endforeach
        @endif

        @if($product->type_code === 'variant')
            @php
                $variantAxes = $attributes->filter(fn ($attribute) => (bool) $attribute->supports_variant_axis && (bool) ($attribute->assignment?->is_variant_axis ?? false));
            @endphp
            <section class="z4-store-form-card z4-store-variant-workspace" id="product-options">
                <header class="z4-store-section-heading"><span>6</span><div><h2>Product Options &amp; Variants</h2><p>{{ $t('store::ui.auto_36a08d8b29') }}</p></div></header>

                <div class="z4-store-variant-axes">
                    <div><h3>{{ $t('store::ui.auto_b4e6d5d118') }}</h3><p>{{ $t('store::ui.auto_50684c65b9') }}</p></div>
                    <div class="z4-store-axis-list">
                        @forelse($attributes->where('supports_variant_axis', true) as $attribute)
                            @if((bool) ($attribute->assignment?->is_variant_axis ?? false))
                                <span class="z4-store-axis-pill is-active"><strong>{{ $attribute->name ?: $attribute->code }}</strong><small>{{ $t('store::ui.auto_4ac2424cbd') }}</small></span>
                            @else
                                <form method="post" action="{{ route('admin.plugins.store.products.organization.attributes', $product->public_id) }}">@csrf<input type="hidden" name="attribute" value="{{ $attribute->public_id }}"><input type="hidden" name="variant_axis" value="1"><input type="hidden" name="visible" value="1"><input type="hidden" name="position" value="0"><button class="z4-store-axis-pill" type="submit"><strong>{{ $attribute->name ?: $attribute->code }}</strong><small>{{ $t('store::ui.auto_6fc2e14836') }}</small></button></form>
                            @endif
                        @empty
                            <div class="z4-store-inline-empty">{{ $t('store::ui.auto_4a41a50280') }} <a href="{{ route('admin.plugins.store.attributes.index') }}">{{ $t('store::ui.auto_71e6a8cdbd') }}</a> {{ $t('store::ui.auto_a21d63b43b') }}</div>
                        @endforelse
                    </div>
                </div>

                @if($variantAxes->isNotEmpty())
                    <form method="post" action="{{ route('admin.plugins.store.products.variants.store', $product->public_id) }}" class="z4-store-variant-create" data-dirty-form>
                        @csrf
                        <div><h3>{{ $t('store::ui.auto_60e14310ad') }}</h3><p>{{ $t('store::ui.auto_854f10d4a9') }}</p></div>
                        <div class="z4-store-form-grid">
                            <label><span>{{ $t('store::ui.auto_688c70338c') }} <b>*</b></span><input class="z4-store-input" name="sku" required maxlength="64" placeholder="{{ $t('store::ui.auto_f29b8ef7e7') }}"></label>
                            @foreach($variantAxes as $axis)
                                <label><span>{{ $axis->name ?: $axis->code }} <b>*</b></span><select class="z4-store-select" name="options[{{ $axis->code }}]" required><option value="">{{ $t('store::ui.auto_61f251de8a') }}</option>@foreach($axis->options as $option)<option value="{{ $option->code }}">{{ $option->name ?: $option->code }}</option>@endforeach</select></label>
                            @endforeach
                            <label class="z4-store-check"><input type="checkbox" name="is_default" value="1"><span><strong>{{ $t('store::ui.auto_82bc84b637') }}</strong><small>{{ $t('store::ui.auto_58854481c4') }}</small></span></label>
                            <div class="z4-store-form-actions"><button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ $t('store::ui.auto_8137a2a8c9') }}</button></div>
                        </div>
                    </form>
                @else
                    <div class="z4-store-inline-empty">{{ $t('store::ui.auto_612b1f6ea2') }}</div>
                @endif

                <div class="z4-store-variant-list">
                    @forelse($variants as $variant)
                        @php
                            $variantPrice = $variant->prices->firstWhere('type', 'regular');
                            $variantSale = $variant->prices->first(function ($price) {
                                $now = now();
                                return $price->type === 'sale' && $price->status === 'active'
                                    && ($price->starts_at === null || $now->gte($price->starts_at))
                                    && ($price->ends_at === null || $now->lt($price->ends_at));
                            });
                            $variantSalePrice = $variantSale ? number_format($variantSale->amount_minor / 1000, 3, '.', '') : '';
                            $variantSaleStarts = $variantSale?->starts_at ? \Illuminate\Support\Carbon::parse($variantSale->starts_at)->format('Y-m-d\TH:i') : '';
                            $variantSaleEnds = $variantSale?->ends_at ? \Illuminate\Support\Carbon::parse($variantSale->ends_at)->format('Y-m-d\TH:i') : '';
                            $variantStock = $variant->inventory?->tracking_mode === 'tracked' ? (int) $variant->inventory->on_hand - (int) $variant->inventory->reserved : null;
                            $variantStockStatus = $variant->inventory?->tracking_mode === 'tracked' && $variantStock <= 0 ? 'out_of_stock' : 'in_stock';
                            $variantStockQuantity = $variant->inventory?->tracking_mode === 'tracked' ? (int) $variant->inventory->on_hand : null;
                            $variantImage = $media->first(fn ($item) => (int) $item->variant_id === (int) $variant->id);
                        @endphp
                        <article class="z4-store-variant-card">
                            <header><div><span class="z4-store-eyebrow">{{ $variant->is_default ? 'Default variant' : 'Variant' }}</span><h3>{{ collect($variant->options)->map(fn ($value, $key) => ucfirst($key).': '.str_replace('-', ' ', $value))->implode(' · ') ?: 'Option combination' }}</h3></div><span class="z4-store-status is-{{ $variant->status === 'active' ? 'published' : ($variant->status === 'archived' ? 'archived' : 'draft') }}">{{ $variant->status === 'archived' ? 'Deleted' : ucfirst($variant->status) }}</span></header>
                            <div class="z4-store-variant-summary-grid"><div><small>SKU</small><strong>{{ $variant->sku }}</strong></div><div><small>{{ $t('store::ui.auto_4d8adffdc0') }}</small><strong>{{ $variantPrice ? app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $variantPrice->amount_minor) : 'Not set' }}</strong></div><div><small>{{ $t('store::ui.auto_acb312bca5') }}</small><strong>{{ $variantStock === null ? 'Not tracked' : $variantStock }}</strong></div><div><small>{{ $t('store::ui.auto_be53a0541a') }}</small><strong>{{ $variantImage ? 'Assigned' : 'Not assigned' }}</strong></div></div>
                            @if($variant->status !== 'archived')
                                <form
                                    method="post"
                                    enctype="multipart/form-data"
                                    action="{{ route('admin.plugins.store.products.variants.update', [$product->public_id, $variant->public_id]) }}"
                                    class="z4-store-variant-editor"
                                    data-variant-editor
                                >
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="version" value="{{ $variant->version }}">
                                    <input type="hidden" name="locale" value="{{ $productLocale ?? $product->default_locale }}">
                                    <input type="hidden" name="active_sale" value="{{ $variantSale?->public_id }}">
                                    <input type="hidden" name="regular_price_initial" value="{{ $variantPrice ? number_format($variantPrice->amount_minor / 1000, 3, '.', '') : '' }}">
                                    <input type="hidden" name="sale_price_initial" value="{{ $variantSalePrice }}">
                                    <input type="hidden" name="sale_starts_at_initial" value="{{ $variantSaleStarts }}">
                                    <input type="hidden" name="sale_ends_at_initial" value="{{ $variantSaleEnds }}">
                                    <input type="hidden" name="inventory_present" value="1">
                                    <input type="hidden" name="allow_backorder" value="0">
                                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">

                                    <div class="z4-store-variant-forms">
                                        <div class="z4-store-subcard z4-store-form-grid">
                                            <h4 class="is-wide">{{ $t('store::ui.auto_c9c5c65fb4') }}</h4>
                                            <label><span>SKU <b>*</b></span><input class="z4-store-input" name="sku" value="{{ $variant->sku }}" required maxlength="64"></label>
                                            <label><span>{{ $t('store::ui.auto_b93eaef026') }}</span><input class="z4-store-input" name="barcode" value="{{ $variant->barcode }}" maxlength="128"></label>
                                            <label><span>{{ $t('store::ui.auto_9fd320386d') }}</span><input class="z4-store-input" type="number" name="position" value="{{ $variant->position }}" min="0" required></label>
                                        </div>

                                        <div class="z4-store-subcard z4-store-form-grid">
                                            <h4 class="is-wide">Price &amp; sale</h4>
                                            <label><span>{{ $t('store::ui.auto_ef70d86b19') }} <b>*</b></span><input class="z4-store-input" name="regular_price" inputmode="decimal" value="{{ $variantPrice ? number_format($variantPrice->amount_minor / 1000, 3, '.', '') : '' }}" placeholder="0.000" required></label>
                                            <label><span>{{ $t('store::ui.auto_b1c7a44878') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" name="sale_price" inputmode="decimal" value="{{ $variantSalePrice }}" placeholder="0.000"></label>
                                            <label><span>{{ $t('store::ui.auto_4c1d129121') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_starts_at" value="{{ $variantSaleStarts }}"></label>
                                            <label><span>{{ $t('store::ui.auto_9f1c3f4fa6') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="datetime-local" name="sale_ends_at" value="{{ $variantSaleEnds }}"></label>
                                        </div>

                                        <div class="z4-store-subcard z4-store-form-grid">
                                            <h4 class="is-wide">{{ $t('store::ui.auto_4d604dd8f0') }}</h4>
                                            <label><span>{{ $t('store::ui.auto_faeaec9eda') }}</span><select class="z4-store-select" name="stock_status"><option value="in_stock" @selected($variantStockStatus === 'in_stock')>{{ $t('store::ui.auto_69d08bd5f8') }}</option><option value="out_of_stock" @selected($variantStockStatus === 'out_of_stock')>{{ $t('store::ui.auto_e990b6285e') }}</option></select></label>
                                            <label><span>{{ $t('store::ui.auto_f8a09f634b') }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="number" name="stock_quantity" min="0" value="{{ $variantStockQuantity }}"><small>{{ $t('store::ui.auto_6b44466cdd') }}</small></label>
                                            <label class="z4-store-check is-wide"><input type="checkbox" name="allow_backorder" value="1" @checked((bool) $variant->inventory?->allow_backorder)><span><strong>{{ $t('store::ui.auto_4389e69917') }}</strong><small>{{ $t('store::ui.auto_3e7478fb54') }}</small></span></label>
                                            @if($variant->inventory)<a class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" href="{{ route('admin.plugins.store.inventory.index') }}#item-{{ $variant->inventory->public_id }}">{{ $t('store::ui.auto_0b6260bd19') }}</a>@endif
                                        </div>

                                        <div class="z4-store-subcard z4-store-form-grid">
                                            <h4 class="is-wide">{{ $t('store::ui.auto_580e9b81a2') }}</h4>
                                            @if($variantImage)
                                                <div class="z4-store-variant-current-image"><img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($variantImage->path) }}" alt=""><span>{{ $t('store::ui.auto_2169525f97') }}</span></div>
                                            @endif
                                            <label class="is-wide"><span>{{ $variantImage ? 'Replace image' : 'Image' }} <small>{{ $t('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" type="file" accept="image/jpeg,image/png,image/webp" name="image"><small>{{ $t('store::ui.auto_918579677a') }}</small></label>
                                        </div>
                                    </div>

                                    <div class="z4-store-variant-save">
                                        <span>{{ $variant->status === 'inactive' ? 'This variant will activate automatically when you publish the product.' : 'Changes remain live after you save and publish the product.' }}</span>
                                        <button class="z4-store-btn z4-store-btn-primary" data-submit-button><span>{{ $t('store::ui.auto_3bc160e420') }}</span></button>
                                    </div>
                                </form>

                                <form method="post" action="{{ route('admin.plugins.store.products.variants.lifecycle', [$product->public_id, $variant->public_id]) }}" class="z4-store-variant-delete">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $variant->version }}">
                                    <button class="z4-store-link-danger" name="action" value="archive" data-z4-confirm-trigger data-confirm-title="{{ $t('store::ui.auto_fa6bc0246e') }}" data-confirm-message="This option will no longer be sellable. Its inventory and order history will remain preserved." data-confirm-label="Delete variant">{{ $t('store::ui.auto_81df7902bb') }}</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="z4-store-inline-empty">{{ $t('store::ui.auto_5fdbb52dcb') }}</div>
                    @endforelse
                </div>
            </section>
        @endif

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
            if (label) label.textContent = 'Saving...';
        }
    });
    window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    form.querySelectorAll('input[name="primary_category"]').forEach((radio) => radio.addEventListener('change', () => { const checkbox = radio.closest('label')?.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = true; }));
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
    const currentMainAsset = mainPreview.dataset.currentAsset || '';
    const currentMainSrc = mainPreview.getAttribute('src') || '';
    const currentGalleryAssets = new Set(Array.from(picker.querySelectorAll('[data-current-gallery]')).map(button => button.dataset.mediaAsset));
    let pickerTarget = 'main';
    let pickerTrigger = null;
    let mainUploadPreviewUrl = '';
    let galleryUploadPreviewUrls = [];

    const openPicker = () => {
        document.body.classList.add('z4-store-media-modal-open');
        if (typeof picker.showModal === 'function') picker.showModal();
        else picker.setAttribute('open', '');
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
        const selectedMainAsset = mainAsset.value;
        const mainPreviewSrc = selectedMainAsset ? assetImage(selectedMainAsset) : (mainUploadPreviewUrl || currentMainSrc);
        const mainPreviewName = selectedMainAsset ? assetName(selectedMainAsset) : (mainFile?.name || mainPreview.alt);
        mainPreview.src = mainPreviewSrc;
        mainPreview.alt = mainPreviewName ? `Selected main product image: ${mainPreviewName}` : '';
        mainPreview.hidden = !mainPreviewSrc;
        mainUploadIcon.hidden = Boolean(mainPreviewSrc);
        mainImageTrigger.classList.toggle('has-preview', Boolean(mainPreviewSrc));
        mainLabel.textContent = selectedMainAsset
            ? `New main image: ${assetName(selectedMainAsset)}`
            : (mainFile ? `Uploaded main image: ${mainFile.name}` : (currentMainSrc ? 'The current main image will be kept.' : 'No main image selected.'));

        galleryPreviews.replaceChildren();
        gallery.forEach(id => appendGalleryPreview(assetImage(id), assetName(id)));
        galleryUploadPreviewUrls.forEach((src, index) => appendGalleryPreview(src, galleryUpload.files[index]?.name || `Uploaded image ${index + 1}`));
        galleryPreviews.hidden = galleryPreviews.childElementCount === 0;
        const galleryTotal = gallery.length + galleryUpload.files.length;
        galleryLabel.textContent = galleryTotal ? `${galleryTotal} new gallery image${galleryTotal === 1 ? '' : 's'} selected.` : 'No new gallery images selected.';
        mainImageTrigger.querySelector('strong').textContent = selectedMainAsset || mainFile ? 'New Main Image Selected' : (currentMainSrc ? 'Change Main Product Image' : 'Choose Main Product Image');
        galleryImageTrigger.querySelector('strong').textContent = galleryTotal ? `${galleryTotal} new gallery image${galleryTotal === 1 ? '' : 's'} selected` : 'Add Gallery Images';
        picker.querySelectorAll('[data-media-asset]').forEach(button => {
            const id = button.dataset.mediaAsset;
            const selected = pickerTarget === 'main'
                ? id === (selectedMainAsset || currentMainAsset)
                : currentGalleryAssets.has(id) || gallery.includes(id);
            button.classList.toggle('is-selected', selected);
        });
    };
    form.querySelectorAll('[data-open-media-picker]').forEach(button => button.addEventListener('click', () => {
        pickerTrigger = button;
        pickerTarget = button.dataset.openMediaPicker;
        pickerTitle.textContent = pickerTarget === 'main' ? 'Choose main product image' : 'Choose gallery image';
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
    uploadFromPicker?.addEventListener('click', () => { (pickerTarget === 'main' ? mainUpload : galleryUpload).click(); });
    picker.querySelectorAll('[data-media-asset]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.mediaAsset;
        if (pickerTarget === 'main') {
            mainAsset.value = id === currentMainAsset ? '' : id;
            mainUpload.value = '';
            refreshMainUploadPreview();
            closePicker();
        } else {
            if (!currentGalleryAssets.has(id)) {
                const current = galleryAssets.querySelector('input[value="' + id + '"]');
                if (current) {
                    current.remove();
                } else {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'gallery_assets[]';
                    input.value = id;
                    galleryAssets.append(input);
                }
            }
            closePicker();
        }
        renderMediaSelection();
    }));
    mainUpload.addEventListener('change', () => {
        refreshMainUploadPreview();
        if (mainUpload.files.length) {
            mainAsset.value = '';
            closePicker();
            renderMediaSelection();
        }
    });
    galleryUpload.addEventListener('change', () => {
        refreshGalleryUploadPreviews();
        if (galleryUpload.files.length) {
            closePicker();
            renderMediaSelection();
        }
    });
    renderMediaSelection();

    const mediaList = form.querySelector('[data-media-list]');
    if (mediaList) {
        const syncMediaOrderControls = () => {
            const items = Array.from(mediaList.querySelectorAll('[data-media-item]'));
            items.forEach((item, index) => {
                item.querySelector('[data-move-up]').disabled = index === 0;
                item.querySelector('[data-move-down]').disabled = index === items.length - 1;
            });
        };
        mediaList.addEventListener('click', event => {
            const move = event.target.closest('[data-move-up], [data-move-down]');
            if (!move) return;
            const item = move.closest('[data-media-item]');
            if (move.hasAttribute('data-move-up') && item.previousElementSibling) {
                mediaList.insertBefore(item, item.previousElementSibling);
            } else if (move.hasAttribute('data-move-down') && item.nextElementSibling) {
                mediaList.insertBefore(item.nextElementSibling, item);
            }
            syncMediaOrderControls();
        });
        syncMediaOrderControls();
    }

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
                option.id = `z4-store-tag-option-${index}`;
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
            if (activeSuggestion >= 0) tagInput.setAttribute('aria-activedescendant', `z4-store-tag-option-${activeSuggestion}`);
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
</x-app-layout>
