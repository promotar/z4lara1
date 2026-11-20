<x-frontend-layout>
    <div class="z4sf z4sf-artworks-page" data-z4-store data-store-artwork-catalog dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        @include('store::storefront.partials.nav')

        <header class="z4sf-artworks-hero">
            <div class="z4sf-container">
                <nav class="z4sf-pdp__breadcrumbs" aria-label="{{ __('store::ui.store_auto_1cdb526d06') }}">
                    <a href="{{ route('store.index') }}">{{ __('store::ui.store_auto_fdb0c388de') }}</a><span>/</span><span>{{ __('store::ui.store_auto_44eac6ddda') }}</span>
                </nav>
                <p>{{ __('store::ui.store_auto_450334f31c') }}</p>
                <h1>{{ __('store::ui.store_auto_1fa0bb32a9') }}</h1>
                <div>{{ __('store::ui.store_auto_01dbb91969') }}</div>
            </div>
        </header>

        <main class="z4sf-container z4sf-artworks-catalog">
            <form class="z4sf-artwork-filters" method="get" action="{{ route('store.artworks.index') }}">
                <label><span>{{ __('store::ui.store_auto_13348442cc') }}</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('store::ui.store_auto_4ef2d6f64c') }}"></label>
                <label><span>{{ __('store::ui.store_auto_3adbdb3ac0') }}</span><select name="category"><option value="">{{ __('store::ui.store_auto_62f5d708d6') }}</option>@foreach($categories as $category)<option value="{{ $category['slug'] }}" @selected(($filters['category'] ?? '') === $category['slug'])>{{ $category['name'] }} ({{ $category['products_count'] }})</option>@endforeach</select></label>
                <label><span>{{ __('store::ui.store_auto_6b46ae4842') }}</span><select name="sort"><option value="newest">{{ __('store::ui.store_auto_ac71d8452c') }}</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('store::ui.store_auto_9852361b41') }}</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('store::ui.store_auto_fda1bbb2d7') }}</option><option value="name_asc" @selected(in_array(($filters['sort'] ?? ''), ['name','name_asc'], true))>{{ __('store::ui.store_auto_41bdf50376') }}</option></select></label>
                <button class="z4sf-btn z4sf-btn-dark" type="submit">{{ __('store::ui.store_auto_9639e32cab') }}</button>
            </form>

            <div class="z4sf-artworks-heading"><div><p>{{ __('store::ui.store_auto_f0752538ba') }}</p><h2>{{ __('store::ui.store_auto_06b1f2ed68') }}</h2></div><span>{{ __('store::ui.store_auto_a44cf346e4', ['count' => $products->total()]) }}</span></div>
            @if($products->isEmpty())
                @include('store::storefront.partials.empty-state', ['title' => __('store::ui.store_auto_e00013399c'), 'message' => __('store::ui.store_auto_784b24b7b0')])
            @else
                <div class="z4sf-artwork-grid">@foreach($products as $product)@include('store::storefront.partials.artwork-card', ['product' => $product])@endforeach</div>
                <x-platform-pagination :paginator="$products" />
            @endif
        </main>
    </div>
</x-frontend-layout>
