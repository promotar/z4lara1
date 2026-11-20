@php
    $defaultVariant = $product['default_variant'];
    $defaultCapacity = $cartCapacities[$defaultVariant['public_id']] ?? [
        'cart_quantity' => 0,
        'maximum_quantity' => $defaultVariant['available_quantity'] ?? 99,
        'remaining_quantity' => $defaultVariant['available_quantity'] ?? 99,
        'at_limit' => false,
        'can_add' => $defaultVariant['available'],
    ];
    $primaryImage = $defaultVariant['image_url'] ?: ($product['media']->first()['url'] ?? null);
@endphp

<article class="z4sf-quick-product" data-store-quick-product>
    <div class="z4sf-quick-product__media">
        @if ($primaryImage)
            <img src="{{ $primaryImage }}" alt="{{ $defaultVariant['image_alt'] ?: $product['name'] }}" data-store-quick-image>
        @else
            <span class="z4sf-quick-product__placeholder">{{ mb_substr($product['name'], 0, 1) }}</span>
        @endif
    </div>

    <div class="z4sf-quick-product__content">
        <div class="z4sf-quick-product__meta">
            <span>{{ $product['brand_name'] ?: ($product['categories']->first()['name'] ?? __('store::ui.store_auto_fdb0c388de')) }}</span>
            @if ($product['price']['on_sale'])<strong>{{ __('store::ui.store_auto_f2cd171bd4') }}</strong>@endif
        </div>
        <h2 id="store-quick-view-title" dir="auto">{{ $product['name'] }}</h2>
        @if ($product['artist_name'])<p class="z4sf-quick-product__artist" dir="auto">{{ __('store::ui.store_auto_b6e77d6195', ['artist' => $product['artist_name']]) }}</p>@endif
        @if ($product['short_description'])<p class="z4sf-quick-product__summary" dir="auto">{{ $product['short_description'] }}</p>@endif

        <div class="z4sf-quick-product__price" data-store-quick-price>
            <strong>{{ $product['price']['formatted'] }}</strong>
            @if ($product['price']['on_sale'] && $product['price']['regular_formatted'])
                <del>{{ $product['price']['regular_formatted'] }}</del>
            @endif
        </div>

        @if ($product['variants']->count() > 1)
            <label class="z4sf-field z4sf-quick-product__variants">
                <span>{{ __('store::ui.store_auto_e26eee82f3') }}</span>
                <select data-store-quick-variant>
                    @foreach ($product['variants'] as $variant)
                        @php($variantCapacity = $cartCapacities[$variant['public_id']] ?? ['cart_quantity' => 0, 'maximum_quantity' => $variant['available_quantity'] ?? 99, 'can_add' => $variant['available']])
                        <option
                            value="{{ $variant['public_id'] }}"
                            data-price="{{ $variant['price_formatted'] }}"
                            data-regular-price="{{ $variant['on_sale'] ? $variant['regular_price_formatted'] : '' }}"
                            data-image="{{ $variant['image_url'] ?: ($product['media']->first()['url'] ?? '') }}"
                            data-image-alt="{{ $variant['image_alt'] ?: $product['name'] }}"
                            data-quantity="{{ (int) ($variantCapacity['cart_quantity'] ?? 0) }}"
                            data-maximum="{{ (int) ($variantCapacity['maximum_quantity'] ?? 0) }}"
                            data-can-add="{{ ! empty($variantCapacity['can_add']) ? '1' : '0' }}"
                            data-update-url="{{ route('store.cart.items.update', $variant['public_id']) }}"
                            data-remove-url="{{ route('store.cart.items.destroy', $variant['public_id']) }}"
                            @selected($variant['public_id'] === $defaultVariant['public_id'])
                            @disabled(! $variant['available'])
                        >{{ $variant['option_label'] ?: $variant['sku'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @include('store::storefront.partials.cart-control', [
            'variantId' => $defaultVariant['public_id'],
            'capacity' => $defaultCapacity,
            'available' => $defaultVariant['available'],
        ])

        @if ($product['tags'] !== [])
            <div class="z4sf-quick-product__tags">
                @foreach ($product['tags'] as $tag)<span dir="auto">{{ $tag }}</span>@endforeach
            </div>
        @endif

        <a class="z4sf-quick-product__details" href="{{ route('store.products.show', $product['slug']) }}">
            {{ __('store::ui.store_auto_0435a3431b') }}
            <span aria-hidden="true">→</span>
        </a>
    </div>
</article>
