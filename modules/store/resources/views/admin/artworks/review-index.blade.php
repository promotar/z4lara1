<x-app-layout><main class="z4-store-admin z4-store-artwork-page" data-z4-store-foundation><div class="z4-store-container">
@include('store::admin._navigation')
<header class="z4-store-heading"><div><span class="z4-store-eyebrow">{{ __('store::ui.auto_220b79c589') }}</span><h1>{{ __('store::ui.auto_6c8b9ec219') }}</h1><p>{{ __('store::ui.auto_28b3d1f21f') }}</p></div></header>
@if(session('success'))<div class="z4-store-alert is-success">{{ session('success') }}</div>@endif
<div class="z4-store-artwork-list">@forelse($artworks as $artwork)<article><div><span class="z4-store-status">{{ ucwords(str_replace('_',' ',$artwork->workflow_state)) }}</span><h2>{{ $artwork->title }}</h2><p>{{ __('store::ui.auto_6834fe4e3c') }}</p></div><a class="z4-store-button is-secondary" href="{{ route('admin.plugins.store.artworks.review.show',$artwork->product_public_id) }}">{{ __('store::ui.auto_457dd55184') }}</a></article>@empty<section class="z4-store-empty"><h2>{{ __('store::ui.auto_c906b59e99') }}</h2></section>@endforelse</div><x-platform-pagination :paginator="$artworks" />
</div></main></x-app-layout>
