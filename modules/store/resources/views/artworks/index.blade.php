@php($hasSellerNavigation = is_array($sellerNavigation ?? null))
@if($hasSellerNavigation && Route::has('exhibitions.assets.stylesheet'))
@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-store-seller-workspace-1" data-exhibitions-stylesheet>
@endpush
@endif
<x-app-layout>
<main class="z4-store-admin z4-store-artwork-page {{ $hasSellerNavigation ? 'z4-artist-dashboard' : '' }}" data-z4-store-foundation><div class="z4-store-container">
    @if($hasSellerNavigation)
        @include($sellerNavigation['view'], $sellerNavigation['data'] + [
            'title' => 'My Artwork Products',
            'subtitle' => 'Artwork products are owned, reviewed, published, and sold by Store.',
        ])
        <div class="z4-store-artwork-index-actions">
            <a class="z4-store-button z4-store-artwork-add-button" href="{{ route('store.seller.artworks.create') }}"><span class="z4-store-button-icon" aria-hidden="true">+</span><span>Add Artwork</span></a>
        </div>
    @else
        <header class="z4-store-heading"><div><span class="z4-store-eyebrow">Seller workspace</span><h1>My Artwork Products</h1><p>Artwork products are owned, reviewed, published, and sold by Store.</p></div><a class="z4-store-button z4-store-artwork-add-button" href="{{ route('store.seller.artworks.create') }}"><span class="z4-store-button-icon" aria-hidden="true">+</span><span>Add Artwork</span></a></header>
    @endif
    @if(session('status'))<div class="z4-store-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-store-alert is-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="z4-store-artwork-list">
        @forelse($artworks as $artwork)
            <article>
                <div>
                    <span class="z4-store-status">{{ ucwords(str_replace('_', ' ', $artwork->workflow_state)) }}</span>
                    <h2>{{ $artwork->title }}</h2>
                    <p>{{ $artwork->artist_name }} &middot; {{ $artwork->work_type ?: 'Artwork' }}</p>
                </div>
                <div class="z4-store-artwork-row-actions">
                    <details class="z4-store-actions-menu" data-store-action-menu>
                        <summary aria-label="Actions for {{ $artwork->title }}">&bull;&bull;&bull;</summary>
                        <div role="menu">
                            @if($artwork->product_status === 'published' && $artwork->product_visibility === 'public' && $artwork->slug)
                                <a href="{{ route('store.products.show', $artwork->slug) }}" target="_blank" rel="noopener" role="menuitem">View artwork</a>
                            @else
                                <span class="z4-store-actions-note" role="note">Public preview is available after publication.</span>
                            @endif
                            @if(in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true))
                                <a href="{{ route('store.seller.artworks.edit', $artwork->product_public_id) }}" role="menuitem">Edit artwork</a>
                            @endif
                            <form method="post" action="{{ route('store.seller.artworks.duplicate', $artwork->product_public_id) }}">
                                @csrf
                                <button type="submit" role="menuitem">Duplicate artwork</button>
                            </form>
                            @if($artwork->workflow_state === 'approved' && $artwork->product_status === 'published')
                                <form method="post" action="{{ route('store.seller.artworks.unpublish-for-edit', $artwork->product_public_id) }}" onsubmit="return confirm('Edit this published artwork? Store will hide it until your changes are reviewed and approved.');">
                                    @csrf
                                    <input type="hidden" name="confirmation" value="unpublish-for-edit">
                                    <button type="submit" role="menuitem">Edit artwork</button>
                                </form>
                            @endif
                            @if($artwork->workflow_state !== 'archived')
                                <form method="post" action="{{ route('store.seller.artworks.archive', $artwork->product_public_id) }}" onsubmit="return confirm('Archive this artwork?');">
                                    @csrf
                                    <input type="hidden" name="confirmation" value="archive-artwork">
                                    <button class="is-danger" type="submit" role="menuitem">Archive artwork</button>
                                </form>
                            @endif
                        </div>
                    </details>
                </div>
            </article>
        @empty
            <section class="z4-store-empty"><h2>No artwork products yet</h2><p>Add your first work directly to Store.</p><a class="z4-store-button z4-store-artwork-add-button" href="{{ route('store.seller.artworks.create') }}"><span class="z4-store-button-icon" aria-hidden="true">+</span><span>Add Artwork</span></a></section>
        @endforelse
    </div>
    <x-platform-pagination :paginator="$artworks" />
</div></main>
</x-app-layout>
