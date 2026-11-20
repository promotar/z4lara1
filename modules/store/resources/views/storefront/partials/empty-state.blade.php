<section class="z4sf-empty" aria-labelledby="store-empty-title">
    <p>{{ __('store::ui.store_auto_7585404487') }}</p>
    <h2 id="store-empty-title">{{ $title ?? __('store::ui.store_auto_b9af863559') }}</h2>
    <div>{{ $message ?? __('store::ui.store_auto_441e6e2fc7') }}</div>
    <div class="z4sf-empty__actions">
        <a class="z4sf-btn z4sf-btn-dark" href="{{ route('store.index') }}">{{ __('store::ui.store_auto_bca54823c9') }}</a>
        @if (isset($filterCategories) && $filterCategories->isNotEmpty())
            <a href="{{ route('store.categories.show', $filterCategories->first()['slug']) }}">{{ __('store::ui.store_auto_442a14f3e7') }}</a>
        @endif
    </div>
</section>
