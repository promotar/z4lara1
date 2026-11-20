@php
    $activeFilters = collect([
        'q' => $filters['q'] ?? null,
        'category' => $filters['category'] ?? null,
        'tag' => $filters['tag'] ?? null,
        'brand' => $filters['brand'] ?? null,
        'min_price' => $filters['min_price'] ?? null,
        'max_price' => $filters['max_price'] ?? null,
        'in_stock' => ! empty($filters['in_stock']) ? '1' : null,
        'on_sale' => ! empty($filters['on_sale']) ? '1' : null,
        'featured' => ! empty($filters['featured']) ? '1' : null,
    ])->filter(fn ($value) => $value !== null && $value !== '');
    $filterLabels = [
        'q' => __('store::ui.store_auto_13348442cc'), 'category' => __('store::ui.store_auto_3adbdb3ac0'), 'tag' => __('store::ui.store_auto_c101058e7e'),
        'brand' => __('store::ui.store_auto_1be6f9eb56'), 'min_price' => __('store::ui.store_auto_b9bbefd932'),
        'max_price' => __('store::ui.store_auto_e6d1a870c9'), 'in_stock' => __('store::ui.store_auto_fcebe56087'),
        'on_sale' => __('store::ui.store_auto_588907ab2d'), 'featured' => __('store::ui.store_auto_15422d54ec'),
    ];
@endphp

<div
    class="z4sf z4sf-catalog-page z4sf-builder-catalog"
    data-z4-store
    data-store-cart-state-url="{{ route('store.cart.state') }}"
    dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}"
>
    <main class="z4sf-market z4sf-container" id="store-products">
        <header class="z4sf-market-toolbar">
            <div class="z4sf-market-toolbar__title">
                <p>{{ __('store::ui.store_auto_7585404487') }}</p>
                <h2>{{ __('store::ui.store_auto_40dec54641') }}</h2>
                <span>{{ __('store::ui.store_auto_bf64687ac0', ['count' => $products->total()]) }}</span>
            </div>

            <div class="z4sf-market-toolbar__actions">
                @if($showFilters)
                    <button class="z4sf-filter-toggle" type="button" data-store-filter-toggle aria-expanded="false" aria-controls="builder-store-filters">
                        <span aria-hidden="true">☷</span> {{ __('store::ui.store_auto_f3f43e30c8') }}
                    </button>
                @endif
                <form class="z4sf-display-controls" method="get" action="{{ request()->url() }}">
                    @foreach(request()->except(['columns', 'per_page', 'page']) as $name => $value)
                        @if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                    @endforeach
                    <fieldset>
                        <legend>{{ __('store::ui.store_auto_168b82d33f') }}</legend>
                        @foreach([3, 4, 5] as $option)
                            <button type="submit" name="columns" value="{{ $option }}" @class(['is-active' => $columns === $option])>{{ $option }}</button>
                        @endforeach
                    </fieldset>
                    <label>
                        <span>{{ __('store::ui.store_auto_4dc23b29ac') }}</span>
                        <select name="per_page" data-store-auto-submit>
                            @foreach([12, 24, 48] as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>@endforeach
                        </select>
                    </label>
                </form>
            </div>
        </header>

        @if($activeFilters->isNotEmpty())
            <div class="z4sf-active-filters" aria-label="{{ __('store::ui.store_auto_2143f8cb66') }}">
                @foreach($activeFilters as $key => $value)
                    <a href="{{ request()->fullUrlWithQuery([$key => null, 'page' => null]) }}">
                        <span>{{ $filterLabels[$key] ?? $key }}{{ in_array($key, ['q', 'category', 'tag', 'brand', 'min_price', 'max_price'], true) ? ': '.$value : '' }}</span>
                        <b aria-hidden="true">×</b>
                    </a>
                @endforeach
                <a class="is-clear" href="{{ request()->url() }}">{{ __('store::ui.store_auto_81aa2078d1') }}</a>
            </div>
        @endif

        <div class="z4sf-catalog {{ $showFilters ? '' : 'is-without-filters' }}">
            @if($showFilters)
                <aside class="z4sf-filters" id="builder-store-filters" data-store-filters>
                    <div class="z4sf-filters__heading">
                        <strong>{{ __('store::ui.store_auto_1932297c4c') }}</strong>
                        <button type="button" data-store-filter-toggle aria-controls="builder-store-filters" aria-label="{{ __('store::ui.store_auto_1f2968e157') }}">×</button>
                    </div>
                    <form id="builder-store-filter-form" method="get" action="{{ request()->url() }}">
                        <label class="z4sf-field"><span>{{ __('store::ui.store_auto_13348442cc') }}</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('store::ui.store_auto_b1b820c598') }}"></label>
                        <label class="z4sf-field">
                            <span>{{ __('store::ui.store_auto_3adbdb3ac0') }}</span>
                            <select name="category"><option value="">{{ __('store::ui.store_auto_62f5d708d6') }}</option>@foreach($filterCategories as $category)<option value="{{ $category['slug'] }}" @selected(($filters['category'] ?? '') === $category['slug'])>{{ $category['name'] }} ({{ $category['products_count'] }})</option>@endforeach</select>
                        </label>
                        @if($tags->isNotEmpty())
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_c101058e7e') }}</span><select name="tag"><option value="">{{ __('store::ui.store_auto_325337e662') }}</option>@foreach($tags as $tag)<option value="{{ $tag['name'] }}" @selected(($filters['tag'] ?? '') === $tag['name'])>{{ $tag['name'] }} ({{ $tag['products_count'] }})</option>@endforeach</select></label>
                        @endif
                        @if($filterBrands->isNotEmpty())
                            <label class="z4sf-field"><span>{{ __('store::ui.store_auto_1be6f9eb56') }}</span><select name="brand"><option value="">{{ __('store::ui.store_auto_e18a22d145') }}</option>@foreach($filterBrands as $brand)<option value="{{ $brand['slug'] }}" @selected(($filters['brand'] ?? '') === $brand['slug'])>{{ $brand['name'] }} ({{ $brand['products_count'] }})</option>@endforeach</select></label>
                        @endif
                        <fieldset class="z4sf-price-filter">
                            <legend>{{ __('store::ui.store_auto_10dc718bf6') }}</legend>
                            <label><span>{{ __('store::ui.store_auto_5da618e8e4') }}</span><input type="number" name="min_price" min="0" step="0.001" value="{{ $filters['min_price'] ?? '' }}"></label>
                            <label><span>{{ __('store::ui.store_auto_e12167aa0a') }}</span><input type="number" name="max_price" min="0" step="0.001" value="{{ $filters['max_price'] ?? '' }}"></label>
                        </fieldset>
                        <div class="z4sf-filter-checks">
                            <label class="z4sf-check"><input type="checkbox" name="in_stock" value="1" @checked(! empty($filters['in_stock']))><span>{{ __('store::ui.store_auto_fcebe56087') }}</span></label>
                            <label class="z4sf-check"><input type="checkbox" name="on_sale" value="1" @checked(! empty($filters['on_sale']))><span>{{ __('store::ui.store_auto_588907ab2d') }}</span></label>
                            <label class="z4sf-check"><input type="checkbox" name="featured" value="1" @checked(! empty($filters['featured']))><span>{{ __('store::ui.store_auto_15422d54ec') }}</span></label>
                        </div>
                        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'newest' }}">
                        <input type="hidden" name="columns" value="{{ $columns }}">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <div class="z4sf-filter-actions"><button class="z4sf-btn z4sf-btn-dark" type="submit">{{ __('store::ui.store_auto_4e0c5e2513') }}</button><a href="{{ request()->url() }}">{{ __('store::ui.store_auto_526d688f37') }}</a></div>
                    </form>
                </aside>
            @endif

            <section class="z4sf-results" aria-label="{{ __('store::ui.store_auto_068f80c751') }}">
                @if($showSort)
                    <form class="z4sf-sort" method="get" action="{{ request()->url() }}">
                        @foreach(request()->except(['sort', 'page']) as $name => $value)@if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}" data-store-preserved-filter>@endif @endforeach
                        <label for="builder-store-sort">{{ __('store::ui.store_auto_33d8042bd7') }}</label>
                        <select id="builder-store-sort" name="sort" data-store-auto-submit data-store-preserve-form="builder-store-filter-form">
                            @if(! empty($filters['q']))<option value="relevance" @selected(($filters['sort'] ?? '') === 'relevance')>{{ __('store::ui.store_auto_41ac377cca') }}</option>@endif
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>{{ __('store::ui.store_auto_ac71d8452c') }}</option>
                            <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('store::ui.store_auto_9852361b41') }}</option>
                            <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('store::ui.store_auto_fda1bbb2d7') }}</option>
                            <option value="name_asc" @selected(in_array(($filters['sort'] ?? ''), ['name', 'name_asc'], true))>{{ __('store::ui.store_auto_41bdf50376') }}</option>
                            <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>{{ __('store::ui.store_auto_9f4dc7aa11') }}</option>
                        </select>
                    </form>
                @endif

                @if($products->isEmpty())
                    @include('store::storefront.partials.empty-state', ['title' => __('store::ui.store_auto_b9af863559'), 'message' => __('store::ui.store_auto_a6288873fa')])
                @else
                    <div class="z4sf-product-grid" data-columns="{{ $columns }}">
                        @foreach($products as $product)@include('store::storefront.partials.product-card', ['product' => $product])@endforeach
                    </div>
                    <x-platform-pagination :paginator="$products" />
                @endif
            </section>
        </div>
    </main>

    <dialog class="z4sf-quick-dialog" data-store-quick-dialog aria-labelledby="store-quick-view-title">
        <button class="z4sf-quick-dialog__close" type="button" data-store-quick-close aria-label="{{ __('store::ui.store_auto_d3d2e61733') }}">×</button>
        <div class="z4sf-quick-dialog__body" data-store-quick-content></div>
    </dialog>
    <div class="z4sf-toast" data-store-toast role="status" aria-live="polite" hidden></div>
</div>
