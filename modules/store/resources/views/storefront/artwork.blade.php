<x-frontend-layout>
    @php
        $art = $product['artwork'] ?? [];
        $primary = $product['media']->first();
        $variantId = $product['default_variant']['public_id'];
        $capacity = $cartCapacities[$variantId] ?? ['at_limit' => false, 'can_add' => $product['available'], 'cart_quantity' => 0];
        $canAdd = (bool) ($capacity['can_add'] ?? false);
        $cartAtLimit = (bool) ($capacity['at_limit'] ?? false);
        $cartQuantity = (int) ($capacity['cart_quantity'] ?? 0);
        $dimensions = collect([$art['width'] ?? null, $art['height'] ?? null, $art['depth'] ?? null])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->implode(' × ');
        $dimensionLabel = $dimensions === '' ? null : trim($dimensions.' '.($art['dimension_unit'] ?? ''));
        $weightLabel = ($art['weight'] ?? null) === null || $art['weight'] === ''
            ? null
            : trim($art['weight'].' '.($art['weight_unit'] ?? ''));
        $colors = collect($art['primary_colors'] ?? [])->filter(fn ($color) => filled($color))->values();
        $editionLabel = match ($art['edition_type'] ?? null) {
            'original' => __('store::ui.store_auto_3ecb365ac8'),
            'limited' => ($art['edition_number'] ?? null) && ($art['edition_total'] ?? null)
                ? __('store::ui.store_auto_f3714a1dc5', ['number' => $art['edition_number'], 'total' => $art['edition_total']])
                : __('store::ui.store_auto_0492b8a464'),
            default => __('store::ui.store_auto_f1eabc4e8f'),
        };
        $availabilityLabel = match ($art['availability'] ?? null) {
            'made_to_order' => __('store::ui.store_auto_2b46dd6e31'),
            'temporarily_unavailable' => __('store::ui.store_auto_cb365946fd'),
            default => __('store::ui.store_auto_78945de8de'),
        };
        if (! $product['available']) {
            $availabilityLabel = __('store::ui.store_auto_d85a8a381c');
        }
        $authAccepted = ($art['authenticity_state'] ?? null) === 'documentation_accepted';
        $specifications = [
            __('store::ui.store_auto_eb3cb8225e') => $art['work_type'] ?? null,
            __('store::ui.store_auto_d15b505cce') => $art['collection_name'] ?? null,
            __('store::ui.store_auto_3adbdb3ac0') => $art['category'] ?? null,
            __('store::ui.store_auto_ae284f900f') => $art['artistic_style'] ?? null,
            __('store::ui.store_auto_c7892ebbb1') => $art['subject'] ?? null,
            __('store::ui.store_auto_23ce0eb7d1') => $art['material'] ?? null,
            __('store::ui.store_auto_aa0d528ba1') => $art['surface'] ?? null,
            __('store::ui.store_auto_07bc591ec1') => $colors->isEmpty() ? null : $colors->implode(', '),
            __('store::ui.store_auto_f4843c1c79') => $dimensionLabel,
            __('store::ui.store_auto_8c489d0946') => $weightLabel,
            __('store::ui.store_auto_f2c6ef3aee') => ! empty($art['is_framed']) ? ($art['frame_material'] ?? null) : null,
            __('store::ui.store_auto_0a31457556') => ! empty($art['ready_to_hang']) ? __('store::ui.store_auto_93cba07454') : __('store::ui.store_auto_bafd7322c6'),
            __('store::ui.store_auto_4e1a1383a8') => filled($art['orientation'] ?? null) ? __(ucfirst($art['orientation'])) : null,
        ];
    @endphp

    <main class="z4-public-artwork-detail z4sf-artwork-show" data-z4-store data-store-artwork-detail data-artwork-id="{{ $product['public_id'] }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <div class="z4-public-artwork-container">
            @if(session('status'))<div class="z4sf-alert z4sf-alert-success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="z4sf-alert z4sf-alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <nav class="z4-public-breadcrumb" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                <a href="{{ url('/') }}">{{ __('store::ui.store_auto_8cf04a9734') }}</a><span>›</span>
                <a href="{{ route('store.artworks.index') }}">{{ __('store::ui.store_auto_44eac6ddda') }}</a><span>›</span>
                <strong dir="auto">{{ $product['name'] }}</strong>
            </nav>

            <section class="z4-public-artwork-hero">
                <div class="z4-public-gallery" data-store-gallery>
                    <div class="z4-public-gallery-main">
                        @if($primary)
                            <img src="{{ $primary['url'] }}" alt="{{ $primary['alt'] ?: $product['name'] }}" data-store-main-image>
                            <a href="{{ $primary['url'] }}" target="_blank" rel="noopener" aria-label="{{ __('store::ui.store_auto_2771dbe192') }}" data-store-full-image>↗</a>
                        @else
                            <div class="z4-public-gallery-empty">{{ __('store::ui.store_auto_b3009ed3e6') }}</div>
                        @endif
                    </div>
                    @if($product['media']->count() > 1)
                        <div class="z4-public-thumbnails" role="list" aria-label="{{ __('store::ui.store_auto_3888c23456') }}">
                            @foreach($product['media'] as $image)
                                <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-store-gallery-thumb data-src="{{ $image['url'] }}" data-alt="{{ $image['alt'] ?: $product['name'] }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ __(ucwords(str_replace('_', ' ', $image['role'] ?? 'artwork view'))) }}">
                                    <img src="{{ $image['url'] }}" alt="" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <article class="z4-public-purchase-card">
                    <div class="z4-public-title-actions">
                        <div class="z4-public-badges"><span class="is-availability">{{ $availabilityLabel }}</span><span>{{ $editionLabel }}</span></div>
                        <div>
                            <button type="button" data-store-artwork-share aria-label="{{ __('store::ui.store_auto_269dad3f8c') }}">{{ __('store::ui.store_auto_5a95a425f7') }}</button>
                            <button type="button" data-store-artwork-favorite aria-pressed="false" aria-label="{{ __('store::ui.store_auto_c49c5c606a') }}">♡</button>
                        </div>
                    </div>
                    <h1 dir="auto">{{ $product['name'] }}</h1>
                    @if($product['artist_name'])<p class="z4-public-byline">{{ __('store::ui.store_auto_df3f079de6') }} <strong dir="auto">{{ $product['artist_name'] }}</strong></p>@endif
                    <p class="z4-public-price">{{ $product['price']['formatted'] }}</p>
                    <p class="z4-public-price-policy"><strong>{{ __('store::ui.store_auto_87b7871e3f') }}</strong> {{ __('store::ui.store_auto_5fdb9c7a65') }}</p>
                    <p class="z4-public-summary" dir="auto">{{ $product['short_description'] ?: $product['description'] }}</p>

                    <dl class="z4-public-key-facts">
                        <div><dt>{{ __('store::ui.store_auto_0efed7409b') }}</dt><dd dir="auto">{{ $art['technique'] ?? $art['material'] ?? __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_537c66b24e') }}</dt><dd>{{ $art['completion_year'] ?? __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_226c03c443') }}</dt><dd>{{ $editionLabel }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_1cc5346977') }}</dt><dd>{{ $dimensionLabel ?: __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_6b73ad3725') }}</dt><dd>{{ ! empty($art['is_framed']) ? __('store::ui.store_auto_93cba07454') : __('store::ui.store_auto_bafd7322c6') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_9e2941b3c8') }}</dt><dd>{{ filled($art['condition'] ?? null) ? __(ucfirst($art['condition'])) : __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_abbd64f40c') }}</dt><dd>{{ filled($art['orientation'] ?? null) ? __(ucfirst($art['orientation'])) : __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                        <div><dt>{{ __('store::ui.store_auto_acd95c2300') }}</dt><dd>{{ array_key_exists('preparation_days', $art) && $art['preparation_days'] !== null ? __('store::ui.store_auto_d675e30b96', ['count' => $art['preparation_days']]) : __('store::ui.store_auto_dd3195ced9') }}</dd></div>
                    </dl>

                    <div class="z4-public-purchase-actions">
                        @if($canAdd && ! $cartAtLimit)
                            <form method="post" action="{{ route('store.cart.items.store') }}">@csrf<input type="hidden" name="variant_public_id" value="{{ $variantId }}"><input type="hidden" name="quantity" value="1"><button type="submit" class="is-primary">{{ __('store::ui.store_auto_2d0f6b8300') }}</button></form>
                        @elseif($product['available'] && $cartAtLimit)
                            <button type="button" class="is-primary" disabled>{{ __('store::ui.store_auto_2c04f1ad76') }}</button>
                        @elseif(! $product['available'])
                            <button type="button" disabled>{{ __('store::ui.store_auto_d85a8a381c') }}</button>
                        @else
                            <button type="button" disabled>{{ __('store::ui.store_auto_c969d68f9b') }}</button>
                        @endif
                        <a href="{{ route('store.cart.index') }}">{{ $cartAtLimit ? __('store::ui.store_auto_57c1fee9db') : __('store::ui.store_auto_c21fe4c29e') }}</a>
                    </div>
                    @if($cartAtLimit)<div class="z4sf-alert z4sf-alert-success" role="status">{{ __('store::ui.store_auto_665cd5e299', ['count' => $cartQuantity]) }}</div>@endif
                    <div class="z4-public-trust-row"><span>{{ __('store::ui.store_auto_4d1d24dea6') }}</span>@if($authAccepted)<span>{{ __('store::ui.store_auto_86b3c98eb0') }}</span>@endif<span>{{ __('store::ui.store_auto_5f2319c672') }}</span></div>
                    <p class="z4-public-share-status" data-store-share-status aria-live="polite"></p>
                </article>
            </section>

            <section class="z4-public-artwork-body" id="artwork-information">
                <aside class="z4-public-specifications">
                    <h2>{{ __('store::ui.store_auto_b36d218456') }}</h2>
                    <dl>@foreach($specifications as $label => $value)@if(filled($value))<div><dt>{{ $label }}</dt><dd dir="auto">{{ $value }}</dd></div>@endif @endforeach</dl>
                </aside>

                <div class="z4-public-detail-tabs" data-store-tabs>
                    <div class="z4-public-tab-list" role="tablist" aria-label="{{ __('store::ui.store_auto_47fcb0f1d4') }}">
                        @foreach(['overview' => __('store::ui.store_auto_3b878279a0'), 'story' => __('store::ui.store_auto_f9f65874f3'), 'care' => __('store::ui.store_auto_abf8fe78f5'), 'shipping' => __('store::ui.store_auto_a54563f4f9'), 'authenticity' => __('store::ui.store_auto_79c25134f9')] as $key => $label)
                            <button type="button" role="tab" id="artwork-{{ $key }}-tab" aria-controls="artwork-{{ $key }}-panel" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-store-tab>{{ $label }}</button>
                        @endforeach
                    </div>
                    <section id="artwork-overview-panel" role="tabpanel" aria-labelledby="artwork-overview-tab" data-store-tab-panel>
                        <p dir="auto">{{ $product['description'] ?: $product['short_description'] }}</p>
                        @if($art['inspiration'] ?? null)<h3>{{ __('store::ui.store_auto_871f5f9e47') }}</h3><p dir="auto">{{ $art['inspiration'] }}</p>@endif
                        <div class="z4-public-visual-facts">
                            <div><strong dir="auto">{{ $art['artistic_style'] ?? $art['category'] ?? __('store::ui.store_auto_dd3195ced9') }}</strong><small>{{ __('store::ui.store_auto_ae284f900f') }}</small></div>
                            <div><strong dir="auto">{{ $art['subject'] ?? __('store::ui.store_auto_dd3195ced9') }}</strong><small>{{ __('store::ui.store_auto_c7892ebbb1') }}</small></div>
                            <div><strong dir="auto">{{ $art['technique'] ?? __('store::ui.store_auto_dd3195ced9') }}</strong><small>{{ __('store::ui.store_auto_22a70073ad') }}</small></div>
                            <div><strong>{{ ! empty($art['is_framed']) ? __('store::ui.store_auto_6b73ad3725') : __('store::ui.store_auto_83f14e7c6d') }}</strong><small>{{ __('store::ui.store_auto_c7bc3fdddd') }}</small></div>
                        </div>
                    </section>
                    <section id="artwork-story-panel" role="tabpanel" aria-labelledby="artwork-story-tab" data-store-tab-panel hidden><p dir="auto">{{ $art['story'] ?? __('store::ui.store_auto_89c21777ad') }}</p></section>
                    <section id="artwork-care-panel" role="tabpanel" aria-labelledby="artwork-care-tab" data-store-tab-panel hidden><p dir="auto">{{ $art['care_instructions'] ?? __('store::ui.store_auto_0ae95d85fc') }}</p></section>
                    <section id="artwork-shipping-panel" role="tabpanel" aria-labelledby="artwork-shipping-tab" data-store-tab-panel hidden><p dir="auto">{{ $art['shipping_notes'] ?? $product['shipping_policy'] ?? __('store::ui.store_auto_89b2418487') }}</p><p><strong>{{ __('store::ui.store_auto_25b7cd9047') }}</strong> {{ array_key_exists('preparation_days', $art) && $art['preparation_days'] !== null ? __('store::ui.store_auto_d675e30b96', ['count' => $art['preparation_days']]) : __('store::ui.store_auto_cf2218e7fc') }}</p></section>
                    <section id="artwork-authenticity-panel" role="tabpanel" aria-labelledby="artwork-authenticity-tab" data-store-tab-panel hidden><p>{{ $authAccepted ? __('store::ui.store_auto_3059e7f52b') : __('store::ui.store_auto_dcec7297f4') }}</p></section>
                </div>
            </section>

            <section class="z4-public-related">
                <div class="z4-public-section-heading"><h2>{{ __('store::ui.store_auto_124117dd22') }}</h2><a href="{{ route('store.artworks.index') }}">{{ __('store::ui.store_auto_ee0f686ba3') }}</a></div>
                @if($product['related']->isEmpty())
                    <div class="z4-public-empty-state">{{ __('store::ui.store_auto_1277e094a2') }}</div>
                @else
                    <div class="z4-public-related-grid">@foreach($product['related'] as $related)@include('store::storefront.partials.artwork-card', ['product' => $related])@endforeach</div>
                @endif
            </section>
        </div>
    </main>
</x-frontend-layout>
