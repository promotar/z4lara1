@php
    $variantId = (string) $product['variant_public_id'];
    $capacity = $cartCapacities[$variantId] ?? [
        'cart_quantity' => 0,
        'maximum_quantity' => $product['available_quantity'] ?? 99,
        'remaining_quantity' => $product['available_quantity'] ?? 99,
        'at_limit' => false,
        'can_add' => $product['available'],
    ];
    $detailUrl = $product['detail_url'] ?? route('store.products.show', $product['slug']);
    $isExhibitionArtwork = ($product['source_owner'] ?? null) === 'exhibitions';
@endphp

<article class="z4sf-product-card" data-store-catalog-product="{{ $product['public_id'] }}">
    <a
        class="z4sf-product-card__media"
        href="{{ $detailUrl }}"
        @unless($isExhibitionArtwork)data-store-quick-view="{{ route('store.products.quick-view', $product['slug']) }}"@endunless
        aria-label="{{ $isExhibitionArtwork ? __('store::ui.store_auto_17ec860418', ['product' => $product['name']]) : __('store::ui.store_auto_e87559b203', ['product' => $product['name']]) }}"
    >
        @if ($product['image'])
            <img src="{{ $product['image']['url'] }}" alt="{{ $product['image']['alt'] ?: $product['name'] }}" loading="lazy">
        @else
            <span class="z4sf-product-card__placeholder" aria-hidden="true">{{ mb_substr($product['name'], 0, 1) }}</span>
        @endif
        @if ($product['on_sale'])
            <span class="z4sf-badge is-sale">{{ __('store::ui.store_auto_f2cd171bd4') }}</span>
        @elseif ($product['is_featured'])
            <span class="z4sf-badge">{{ __('store::ui.store_auto_15422d54ec') }}</span>
        @endif
        <span class="z4sf-quick-view-label">{{ $isExhibitionArtwork ? __('store::ui.store_auto_6d2c955257') : __('store::ui.store_auto_c91e4ee170') }}</span>
    </a>

    <div class="z4sf-product-card__body">
        <div class="z4sf-product-card__meta">
            @if ($product['brand_name'])
                <a href="{{ route('store.brands.show', $product['brand_slug']) }}">{{ $product['brand_name'] }}</a>
            @elseif ($product['categories']->isNotEmpty())
                <a href="{{ route('store.categories.show', $product['categories']->first()['slug']) }}">{{ $product['categories']->first()['name'] }}</a>
            @else
                <span>{{ __('store::ui.store_auto_fdb0c388de') }}</span>
            @endif
            @if($product['show_stock_status'] ?? true)
                <span @class(['is-available' => $product['available'], 'is-unavailable' => ! $product['available']])>
                    {{ $product['available'] ? __('store::ui.store_auto_fcebe56087') : __('store::ui.store_auto_b55197a49e') }}
                </span>
            @endif
        </div>

        <h3><a href="{{ $detailUrl }}">{{ $product['name'] }}</a></h3>

        <div class="z4sf-product-card__footer">
            <div class="z4sf-price">
                <strong>{{ $product['price_formatted'] }}</strong>
                @if ($product['on_sale'] && $product['regular_price_formatted'])
                    <del>{{ $product['regular_price_formatted'] }}</del>
                @endif
                @if($product['prices_include_tax'] ?? false)
                    <small class="z4sf-tax-included">{{ __('store::ui.store_auto_f4a0d7cb0c') }}</small>
                @endif
            </div>
        </div>

        @include('store::storefront.partials.cart-control', [
            'variantId' => $variantId,
            'capacity' => $capacity,
            'available' => $product['available'],
        ])
    </div>
</article>
