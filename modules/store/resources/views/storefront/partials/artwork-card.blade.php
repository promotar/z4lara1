<article class="z4sf-artwork-card" data-store-artwork-card>
    <a class="z4sf-artwork-card__media" href="{{ route('store.products.show', $product['slug']) }}">
        @if ($product['image'])
            <img src="{{ $product['image']['url'] }}" alt="{{ $product['image']['alt'] ?: $product['name'] }}" loading="lazy">
        @endif
        <span @class(['z4sf-artwork-status', 'is-sold' => ! $product['available']])>{{ $product['available'] ? __('store::ui.store_auto_78945de8de') : __('store::ui.store_auto_d85a8a381c') }}</span>
    </a>
    <div class="z4sf-artwork-card__body">
        <div class="z4sf-artwork-card__eyebrow">
            <span dir="auto">{{ $product['artwork']['category'] ?: ($product['artwork']['work_type'] ?: __('store::ui.store_auto_f1eabc4e8f')) }}</span>
            @if ($product['artwork']['completion_year'])<span>{{ $product['artwork']['completion_year'] }}</span>@endif
        </div>
        <h2><a href="{{ route('store.products.show', $product['slug']) }}" dir="auto">{{ $product['name'] }}</a></h2>
        @if ($product['artist_name'])<p>{{ __('store::ui.store_auto_b6e77d6195', ['artist' => $product['artist_name']]) }}</p>@endif
        @if ($product['short_description'])<div dir="auto">{{ Illuminate\Support\Str::limit($product['short_description'], 120) }}</div>@endif
        <footer>
            <strong>{{ $product['price_formatted'] }}</strong>
            <a href="{{ route('store.products.show', $product['slug']) }}">{{ __('store::ui.store_auto_6d2c955257') }}</a>
        </footer>
    </div>
</article>
