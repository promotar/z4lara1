<x-frontend-layout>
    @php
        $formatSalePeriod = static function (?string $startsAt, ?string $endsAt): string {
            if ($startsAt === null) {
                return '';
            }

            $starts = \Illuminate\Support\Carbon::parse($startsAt)->format('d/m/Y H:i');
            if ($endsAt === null) {
                return __('store::ui.store_auto_6c174b2678', ['date' => $starts]);
            }

            $ends = \Illuminate\Support\Carbon::parse($endsAt)->format('d/m/Y H:i');

            return __('store::ui.store_auto_25e1ebd0a3', ['start' => $starts, 'end' => $ends]);
        };
        $defaultSalePeriod = $product['price']['on_sale']
            ? $formatSalePeriod($product['price']['sale_starts_at'], $product['price']['sale_ends_at'])
            : '';
        $defaultTracksQuantity = $product['default_variant']['tracking_mode'] === 'tracked'
            && $product['default_variant']['available_quantity'] !== null;
        $defaultVariantPublicId = $product['default_variant']['public_id'];
        $defaultCapacity = $cartCapacities[$defaultVariantPublicId] ?? [
            'cart_quantity' => 0,
            'maximum_quantity' => $product['default_variant']['available_quantity'] ?? 99,
            'remaining_quantity' => $product['default_variant']['available_quantity'] ?? 99,
            'at_limit' => false,
            'can_add' => $product['available'],
        ];
        $defaultCartQuantity = (int) $defaultCapacity['cart_quantity'];
        $defaultRemainingQuantity = max(0, (int) $defaultCapacity['remaining_quantity']);
        $defaultAtLimit = (bool) $defaultCapacity['at_limit'];
        $defaultCanAdd = (bool) $defaultCapacity['can_add'];
    @endphp
    <div class="z4sf" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        @include('store::storefront.partials.nav')

        <main class="z4sf-container z4sf-pdp" role="main">
            <nav class="z4sf-pdp__breadcrumbs" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_fdb0c388de') }}</a>
                @if ($product['categories']->isNotEmpty())
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('store.categories.show', $product['categories']->first()['slug']) }}">{{ $product['categories']->first()['name'] }}</a>
                @endif
                <span aria-hidden="true">/</span>
                <span aria-current="page" dir="auto">{{ $product['name'] }}</span>
            </nav>

            <section class="z4sf-pdp__hero" aria-labelledby="product-title">
                <div class="z4sf-pdp-gallery" data-store-gallery>
                    <div class="z4sf-pdp-gallery__stage">
                        <img
                            src="{{ $product['media']->first()['url'] }}"
                            alt="{{ $product['media']->first()['alt'] ?: $product['name'] }}"
                            @if($product['media']->first()['width'] ?? null) width="{{ $product['media']->first()['width'] }}" @endif
                            @if($product['media']->first()['height'] ?? null) height="{{ $product['media']->first()['height'] }}" @endif
                            fetchpriority="high"
                            decoding="async"
                            data-store-main-image
                        >
                        @if ($product['price']['on_sale'])
                            <span class="z4sf-badge is-sale">{{ __('store::ui.store_auto_f2cd171bd4') }}</span>
                        @elseif ($product['is_featured'])
                            <span class="z4sf-badge">{{ __('store::ui.store_auto_15422d54ec') }}</span>
                        @endif
                        @if ($product['media']->count() > 1)
                            <span class="z4sf-pdp-gallery__counter" aria-live="polite">
                                <b data-store-gallery-current>1</b> / {{ $product['media']->count() }}
                            </span>
                        @endif
                    </div>

                    @if ($product['media']->count() > 1)
                        <div class="z4sf-pdp-gallery__rail">
                            <button type="button" class="z4sf-pdp-gallery__arrow" data-store-gallery-previous aria-label="{{ __('store::ui.store_auto_ee34f6ba47') }}">‹</button>
                            <div class="z4sf-pdp-gallery__thumbs" aria-label="{{ __('store::ui.store_auto_2c595b4f6d') }}">
                                @foreach ($product['media'] as $index => $media)
                                    <button
                                        type="button"
                                        @class(['is-active' => $index === 0])
                                        data-store-gallery-thumb
                                        data-index="{{ $index }}"
                                        data-src="{{ $media['url'] }}"
                                        data-alt="{{ $media['alt'] ?: $product['name'] }}"
                                        aria-label="{{ __('store::ui.store_auto_31354fc58b', ['number' => $index + 1]) }}"
                                        aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                                    >
                                        <img src="{{ $media['url'] }}" alt="" aria-hidden="true">
                                    </button>
                                @endforeach
                            </div>
                            <button type="button" class="z4sf-pdp-gallery__arrow" data-store-gallery-next aria-label="{{ __('store::ui.store_auto_558ed0bec0') }}">›</button>
                        </div>
                    @endif
                </div>

                <aside class="z4sf-pdp-purchase" data-store-product>
                    <div class="z4sf-pdp-purchase__eyebrow">
                        @if ($product['is_featured'])<span>{{ __('store::ui.store_auto_3b591c0e26') }}</span>@endif
                        @if ($product['brand_name'])
                            <a href="{{ route('store.brands.show', $product['brand_slug']) }}">{{ $product['brand_name'] }}</a>
                        @elseif ($product['categories']->isNotEmpty())
                            <a href="{{ route('store.categories.show', $product['categories']->first()['slug']) }}">{{ $product['categories']->first()['name'] }}</a>
                        @endif
                    </div>

                    <h1 id="product-title" dir="auto">{{ $product['name'] }}</h1>

                    @if ($product['artist_name'])
                        <p class="z4sf-pdp-purchase__artist">
                            <span>{{ __('store::ui.store_auto_e2d6fa45d4') }}</span>
                            <strong dir="auto">{{ $product['artist_name'] }}</strong>
                        </p>
                    @endif

                    @if ($product['short_description'])
                        <p class="z4sf-pdp-purchase__summary" dir="auto">{{ $product['short_description'] }}</p>
                    @endif

                    <div class="z4sf-pdp-purchase__commercial">
                        <div class="z4sf-product__price" data-store-product-price>
                            <strong>{{ $product['price']['formatted'] }}</strong>
                            @if ($product['price']['on_sale'] && $product['price']['regular_formatted'])
                                <del>{{ $product['price']['regular_formatted'] }}</del>
                            @endif
                            @if($product['prices_include_tax'] ?? false)
                                <small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>
                            @endif
                        </div>
                        <p class="z4sf-pdp-sale-period" data-store-sale-period @if ($defaultSalePeriod === '') hidden @endif>
                            <span>{{ __('store::ui.store_auto_1067f7e113') }}</span>
                            <strong data-store-sale-period-value>{{ $defaultSalePeriod }}</strong>
                        </p>

                        @if ($product['show_stock_status'])
                            <div class="z4sf-pdp-stock-summary">
                                <div
                                    @class(['z4sf-stock', 'is-available' => $product['available'], 'is-unavailable' => ! $product['available']])
                                    data-store-product-stock
                                    data-available-label="{{ __('store::ui.store_auto_6c83ac70f7') }}"
                                    data-untracked-label="{{ __('store::ui.store_auto_fcebe56087') }}"
                                    data-unavailable-label="{{ __('store::ui.store_auto_a445c2660c') }}"
                                    @if ($defaultTracksQuantity && $product['available']) hidden @endif
                                >
                                    {{ $product['available'] ? ($defaultTracksQuantity ? __('store::ui.store_auto_6c83ac70f7') : __('store::ui.store_auto_fcebe56087')) : __('store::ui.store_auto_a445c2660c') }}
                                </div>
                                <p class="z4sf-stock-quantity" data-store-stock-quantity @if (! $defaultTracksQuantity) hidden @endif>
                                    <span>{{ __('store::ui.store_auto_f8a09f634b') }}</span>
                                    <strong data-store-stock-quantity-value>{{ $product['default_variant']['available_quantity'] }}</strong>
                                </p>
                            </div>
                        @endif
                    </div>

                    @php
                        $variantAxes = collect();
                        foreach ($product['variants'] as $variant) {
                            foreach ($variant['option_values'] ?? [] as $optionValue) {
                                $axis = $variantAxes->get($optionValue['attribute_code'], [
                                    'code' => $optionValue['attribute_code'],
                                    'name' => $optionValue['attribute_name'],
                                    'values' => collect(),
                                ]);
                                $axis['values']->put($optionValue['option_code'], [
                                    'code' => $optionValue['option_code'],
                                    'name' => $optionValue['option_name'],
                                ]);
                                $variantAxes->put($optionValue['attribute_code'], $axis);
                            }
                        }
                        $defaultOptionMap = collect($product['default_variant']['option_values'] ?? [])
                            ->mapWithKeys(fn (array $option): array => [$option['attribute_code'] => $option['option_code']]);
                        $showsVariantOptions = $variantAxes->isNotEmpty();
                    @endphp
                    <form class="z4sf-pdp-form" method="post" action="{{ route('store.cart.items.store') }}" data-store-add-form>
                        @csrf

                        @if ($showsVariantOptions)
                            <section class="z4sf-pdp-options" aria-label="{{ __('store::ui.store_auto_e26eee82f3') }}" data-store-option-picker>
                                <div class="z4sf-pdp-options__heading">
                                    <span>{{ __('store::ui.store_auto_e26eee82f3') }}</span>
                                    <small>{{ __('store::ui.store_auto_fe10818551') }}</small>
                                </div>
                                <div class="z4sf-pdp-options__axes">
                                    @foreach ($variantAxes as $axis)
                                        <fieldset
                                            class="z4sf-pdp-option-axis"
                                            data-store-option-axis
                                            data-attribute-code="{{ $axis['code'] }}"
                                        >
                                            <legend dir="auto">{{ $axis['name'] }}</legend>
                                            <div class="z4sf-pdp-option-axis__values">
                                                @foreach ($axis['values'] as $optionValue)
                                                    @php
                                                        $optionAvailable = $product['variants']->contains(function (array $variant) use ($axis, $optionValue): bool {
                                                            return $variant['available'] && collect($variant['option_values'] ?? [])->contains(
                                                                fn (array $candidate): bool => $candidate['attribute_code'] === $axis['code']
                                                                    && $candidate['option_code'] === $optionValue['code']
                                                            );
                                                        });
                                                    @endphp
                                                    <label @class(['is-unavailable' => ! $optionAvailable])>
                                                        <input
                                                            type="radio"
                                                            name="product_options[{{ $axis['code'] }}]"
                                                            value="{{ $optionValue['code'] }}"
                                                            data-store-option
                                                            data-attribute-code="{{ $axis['code'] }}"
                                                            @checked($defaultOptionMap->get($axis['code']) === $optionValue['code'])
                                                            @disabled(! $optionAvailable)
                                                        >
                                                        <span dir="auto">{{ $optionValue['name'] }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endforeach
                                </div>

                                <div hidden data-store-variant-definitions>
                                    @foreach ($product['variants'] as $variant)
                                        @php
                                            $variantSalePeriod = $variant['on_sale'] ? $formatSalePeriod($variant['sale_starts_at'], $variant['sale_ends_at']) : '';
                                            $variantOptions = collect($variant['option_values'] ?? [])
                                                ->mapWithKeys(fn (array $option): array => [$option['attribute_code'] => $option['option_code']])
                                                ->all();
                                            $variantCapacity = $cartCapacities[$variant['public_id']] ?? [
                                                'cart_quantity' => 0,
                                                'remaining_quantity' => $variant['available_quantity'] ?? 99,
                                            ];
                                        @endphp
                                        <span
                                            data-store-variant
                                            data-variant-id="{{ $variant['public_id'] }}"
                                            data-options="{{ json_encode($variantOptions, JSON_THROW_ON_ERROR) }}"
                                            data-price="{{ $variant['price_formatted'] }}"
                                            data-regular-price="{{ $variant['on_sale'] ? $variant['regular_price_formatted'] : '' }}"
                                            data-available="{{ $variant['available'] ? '1' : '0' }}"
                                            data-maximum="{{ $variant['available_quantity'] ?? 99 }}"
                                            data-cart-quantity="{{ $variantCapacity['cart_quantity'] }}"
                                            data-remaining="{{ $variantCapacity['remaining_quantity'] }}"
                                            data-tracking-mode="{{ $variant['tracking_mode'] }}"
                                            data-sale-period="{{ $variantSalePeriod }}"
                                            data-sku="{{ $variant['sku'] }}"
                                            data-image="{{ $variant['image_url'] ?? '' }}"
                                            data-image-alt="{{ $variant['image_alt'] ?: $product['name'] }}"
                                        ></span>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <input type="hidden" name="variant_public_id" value="{{ $product['default_variant']['public_id'] }}" data-store-variant-value>

                        <div class="z4sf-pdp-form__label">{{ __('store::ui.store_auto_694e8d1f2e') }}</div>
                        <div class="z4sf-pdp-form__actions">
                            <div class="z4sf-quantity" data-store-quantity>
                                <button type="button" data-store-quantity-minus aria-label="{{ __('store::ui.store_auto_ae5ff2e566') }}" @disabled(! $defaultCanAdd)>−</button>
                                <input type="number" name="quantity" min="1" max="{{ max(1, $defaultRemainingQuantity) }}" value="1" aria-label="{{ __('store::ui.store_auto_694e8d1f2e') }}" @disabled(! $defaultCanAdd)>
                                <button type="button" data-store-quantity-plus aria-label="{{ __('store::ui.store_auto_20979edc44') }}" @disabled(! $defaultCanAdd)>+</button>
                            </div>
                            <button
                                class="z4sf-btn z4sf-btn-dark z4sf-add-button"
                                type="submit"
                                data-store-add-button
                                data-add-label="{{ __('store::ui.store_auto_2d0f6b8300') }}"
                                data-limit-label="{{ __('store::ui.store_auto_2c04f1ad76') }}"
                                data-adding-label="{{ __('store::ui.store_auto_c303d2fa01') }}"
                                data-unavailable-label="{{ __('store::ui.store_auto_b55197a49e') }}"
                                data-select-label="{{ __('store::ui.store_auto_e26eee82f3') }}"
                                @disabled(! $defaultCanAdd)
                            >
                                {{ $defaultAtLimit ? __('store::ui.store_auto_2c04f1ad76') : ($product['available'] ? __('store::ui.store_auto_2d0f6b8300') : __('store::ui.store_auto_b55197a49e')) }}
                            </button>
                        </div>
                        <p
                            class="z4sf-pdp-form__label"
                            data-store-cart-capacity
                            data-remaining-template="{{ __('store::ui.store_auto_37bc0cfcbd', ['quantity' => '{quantity}', 'remaining' => '{remaining}']) }}"
                            data-limit-template="{{ __('store::ui.store_auto_65540e6fd2', ['quantity' => '{quantity}']) }}"
                            @if($defaultCartQuantity === 0) hidden @endif
                        >
                            <span data-store-cart-capacity-message>
                                {{ $defaultAtLimit
                                    ? __('store::ui.store_auto_65540e6fd2', ['quantity' => $defaultCartQuantity])
                                    : __('store::ui.store_auto_37bc0cfcbd', ['quantity' => $defaultCartQuantity, 'remaining' => $defaultRemainingQuantity]) }}
                            </span>
                            <a href="{{ route('store.cart.index') }}" data-store-view-cart @if(! $defaultAtLimit) hidden @endif>{{ __('store::ui.store_auto_57c1fee9db') }}</a>
                        </p>
                    </form>

                </aside>
            </section>

            <section class="z4sf-pdp-information" data-store-tabs>
                <div class="z4sf-pdp-tabs" role="tablist" aria-label="{{ __('store::ui.store_auto_87e9eb1c1a') }}">
                    <button type="button" role="tab" id="product-details-tab" aria-controls="product-details-panel" aria-selected="true" data-store-tab>
                        {{ __('store::ui.store_auto_81e226e829') }}
                    </button>
                    <button type="button" role="tab" id="product-shipping-tab" aria-controls="product-shipping-panel" aria-selected="false" tabindex="-1" data-store-tab>
                        {{ __('store::ui.store_auto_5cfdabe5c8') }}
                    </button>
                    <button type="button" role="tab" id="product-returns-tab" aria-controls="product-returns-panel" aria-selected="false" tabindex="-1" data-store-tab>
                        {{ __('store::ui.store_auto_b15120bacd') }}
                    </button>
                </div>

                <div class="z4sf-pdp-panel" id="product-details-panel" role="tabpanel" aria-labelledby="product-details-tab" data-store-tab-panel>
                    <div class="z4sf-pdp-panel__intro">
                        <span>{{ __('store::ui.store_auto_268a90936f') }}</span>
                        <h2 dir="auto">{{ $product['name'] }}</h2>
                        @if ($product['description'])
                            <div class="z4sf-rich-text" dir="auto">{!! nl2br(e($product['description'])) !!}</div>
                        @else
                            <p>{{ __('store::ui.store_auto_3a625527c0') }}</p>
                        @endif
                        @if ($product['tags'] !== [])
                            <div class="z4sf-tags">
                                @foreach ($product['tags'] as $tag)<span dir="auto">{{ $tag }}</span>@endforeach
                            </div>
                        @endif
                    </div>

                    <div class="z4sf-pdp-specification-block">
                        <h3>{{ __('store::ui.store_auto_b36d218456') }}</h3>
                        <dl class="z4sf-specs">
                            @if ($product['attributes']->isNotEmpty())
                                @foreach ($product['attributes'] as $attribute)
                                    <div><dt dir="auto">{{ $attribute['name'] }}</dt><dd dir="auto">{{ $attribute['value'] }}</dd></div>
                                @endforeach
                            @endif
                            @if ($product['artist_name'])
                                <div><dt>{{ __('store::ui.store_auto_4047c5607d') }}</dt><dd dir="auto">{{ $product['artist_name'] }}</dd></div>
                            @endif
                            @if ($product['brand_name'])
                                <div><dt>{{ __('store::ui.store_auto_1be6f9eb56') }}</dt><dd dir="auto">{{ $product['brand_name'] }}</dd></div>
                            @endif
                            @if ($product['default_variant']['sku'])
                                <div><dt>{{ __('store::ui.store_auto_6665e37610') }}</dt><dd data-store-product-sku dir="auto">{{ $product['default_variant']['sku'] }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="z4sf-pdp-panel" id="product-shipping-panel" role="tabpanel" aria-labelledby="product-shipping-tab" data-store-tab-panel hidden>
                    @if ($product['shipping_policy'] !== '')
                        <div class="z4sf-pdp-policy is-custom-policy">
                            <span aria-hidden="true">01</span>
                            <div><h2>{{ __('store::ui.store_auto_d6fbe9eb5c') }}</h2><div class="z4sf-rich-text" dir="auto">{!! nl2br(e($product['shipping_policy'])) !!}</div></div>
                        </div>
                    @else
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">01</span>
                            <div><h2>{{ __('store::ui.store_auto_2a3b6c7687') }}</h2><p>{{ __('store::ui.store_auto_766d8d678e') }}</p></div>
                        </div>
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">02</span>
                            <div><h2>{{ __('store::ui.store_auto_fb077ecba5') }}</h2><p>{{ __('store::ui.store_auto_c978044441') }}</p></div>
                        </div>
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">03</span>
                            <div><h2>{{ __('store::ui.store_auto_6fa95e707c') }}</h2><p>{{ __('store::ui.store_auto_33648d7b21') }}</p></div>
                        </div>
                    @endif
                </div>

                <div class="z4sf-pdp-panel" id="product-returns-panel" role="tabpanel" aria-labelledby="product-returns-tab" data-store-tab-panel hidden>
                    @if ($product['return_policy'] !== '')
                        <div class="z4sf-pdp-policy is-custom-policy">
                            <span aria-hidden="true">01</span>
                            <div><h2>{{ __('store::ui.store_auto_b15120bacd') }}</h2><div class="z4sf-rich-text" dir="auto">{!! nl2br(e($product['return_policy'])) !!}</div></div>
                        </div>
                    @else
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">01</span>
                            <div><h2>{{ __('store::ui.store_auto_cbd32732ea') }}</h2><p>{{ __('store::ui.store_auto_79e2123856') }}</p></div>
                        </div>
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">02</span>
                            <div><h2>{{ __('store::ui.store_auto_78e03043df') }}</h2><p>{{ __('store::ui.store_auto_45cbe2462f') }}</p></div>
                        </div>
                        <div class="z4sf-pdp-policy">
                            <span aria-hidden="true">03</span>
                            <div><h2>{{ __('store::ui.store_auto_ae4a611c11') }}</h2><p>{{ __('store::ui.store_auto_c79c552482') }}</p></div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="z4sf-product-reviews" id="product-reviews" aria-labelledby="product-reviews-title">
                <header class="z4sf-product-reviews__heading">
                    <div>
                        <p>{{ __('store::ui.store_auto_0bec8134b8') }}</p>
                        <h2 id="product-reviews-title">{{ __('store::ui.store_auto_580606daf1') }}</h2>
                    </div>
                    @if($reviews['count'] > 0)
                        <div class="z4sf-product-reviews__summary">
                            <strong>{{ number_format($reviews['average'], 1) }}</strong>
                            <div>
                                <div class="z4-review-stars" aria-label="{{ __('store::ui.store_auto_070398c48c', ['rating' => number_format($reviews['average'], 1)]) }}">
                                    @for($star = 1; $star <= 5; $star++)<span @class(['is-active' => $star <= round($reviews['average'])]) aria-hidden="true">★</span>@endfor
                                </div>
                                <span>{{ __('store::ui.store_auto_098383d868', ['count' => $reviews['count']]) }}</span>
                            </div>
                        </div>
                    @endif
                </header>

                @if($reviews['items']->isEmpty())
                    <div class="z4sf-product-reviews__empty">
                        <span class="material-symbols-outlined" aria-hidden="true">reviews</span>
                        <div><strong>{{ __('store::ui.store_auto_bc6ec305cd') }}</strong><p>{{ __('store::ui.store_auto_190acf86bd') }}</p></div>
                    </div>
                @else
                    <div class="z4sf-product-review-list">
                        @foreach($reviews['items'] as $review)
                            <article class="z4sf-product-review">
                                <header>
                                    <div class="z4-review-stars" aria-label="{{ __('store::ui.store_auto_070398c48c', ['rating' => $review->rating]) }}">
                                        @for($star = 1; $star <= 5; $star++)<span @class(['is-active' => $star <= $review->rating]) aria-hidden="true">★</span>@endfor
                                    </div>
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($review->created_at)->toDateString() }}">{{ app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class)->format($review->created_at, 'M d, Y') }}</time>
                                </header>
                                @if($review->title)<h3 dir="auto">{{ $review->title }}</h3>@endif
                                <p dir="auto">{{ $review->body }}</p>
                                <footer>
                                    <strong dir="auto">{{ $review->reviewer_name }}</strong>
                                    @if($review->verified_purchase)<span><span class="material-symbols-outlined" aria-hidden="true">verified</span>{{ __('store::ui.store_auto_a7080a1e44') }}</span>@endif
                                </footer>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($product['related']->isNotEmpty())
                <section class="z4sf-section z4sf-pdp-related" aria-labelledby="related-products-title">
                    <div class="z4sf-section-heading">
                        <div>
                            <p>{{ __('store::ui.store_auto_a8bc56cf4a') }}</p>
                            <h2 id="related-products-title">{{ __('store::ui.store_auto_124117dd22') }}</h2>
                        </div>
                        <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_bca54823c9') }}</a>
                    </div>
                    <div class="z4sf-product-grid is-featured">
                        @foreach ($product['related'] as $related)
                            @include('store::storefront.partials.product-card', ['product' => $related])
                        @endforeach
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-frontend-layout>
