@php
    $primary = $media->first();
    $artistName = $artist->artistic_name ?: $artist->legal_name;
    $price = $commerce->priceMinor === null ? null : $commerce->priceMinor / 1000;
    $priceLabel = $price === null
        ? 'Price unavailable'
        : rtrim(rtrim(number_format($price, 3, '.', ''), '0'), '.').' '.$commerce->currency;
    $colors = is_string($artwork->primary_colors) ? json_decode($artwork->primary_colors, true) : $artwork->primary_colors;
    $colorsLabel = is_array($colors) ? implode(', ', $colors) : (string) $colors;
    $dimensionParts = collect([$artwork->width, $artwork->height, $artwork->depth])->filter(fn ($value) => $value !== null && $value !== '');
    $dimensions = $dimensionParts->isEmpty() ? null : $dimensionParts->implode(' × ').' '.($artwork->dimension_unit ?: 'cm');
    $authAccepted = $authenticity && in_array($authenticity->state, ['approved', 'documentation_accepted'], true);
    $hasSummary = filled($artwork->short_description) || filled($artwork->detailed_description);
    $present = fn ($value) => (is_string($value) && in_array(trim($value), ['', 'Not specified'], true)) ? null : $value;
    $tabs = ['overview' => 'Overview'];
    if (filled($artwork->artwork_story)) { $tabs['story'] = 'Artwork story'; }
    if (filled($artwork->care_instructions)) { $tabs['care'] = 'Care instructions'; }
    if (filled($artwork->shipping_notes)) { $tabs['shipping'] = 'Shipping & delivery'; }

@endphp

<x-frontend-layout>
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-public-artwork-3" data-exhibitions-stylesheet>
<main class="z4-public-artwork-detail" data-public-artwork="{{ $artwork->public_id }}">
<div class="z4-public-artwork-container">
    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <nav class="z4-public-breadcrumb" aria-label="{{ __('exhibitions::ui.breadcrumb') }}"><a href="{{ url('/') }}">{{ __('exhibitions::ui.home') }}</a><span>›</span><a href="{{ route('exhibitions.artworks.index') }}">{{ __('exhibitions::ui.artworks') }}</a><span>›</span><strong>{{ $artwork->title }}</strong></nav>

    <section class="z4-public-artwork-hero">
        <div class="z4-public-gallery" data-artwork-gallery>
            <div class="z4-public-gallery-main">
                @if($primary)
                    <img src="{{ route('exhibitions.artworks.media', [$artwork->public_id, $primary->public_id]) }}" alt="{{ $artwork->image_alt_text ?: $artwork->title }}" @if($primary->width) width="{{ $primary->width }}" @endif @if($primary->height) height="{{ $primary->height }}" @endif fetchpriority="high" decoding="async" data-gallery-main>
                    <a href="{{ route('exhibitions.artworks.media', [$artwork->public_id, $primary->public_id]) }}" target="_blank" rel="noopener" aria-label="{{ __('exhibitions::ui.open-full-image') }}" data-gallery-full>↗</a>
                @else
                    <div class="z4-public-gallery-empty">{{ __('exhibitions::ui.image-is-being-prepared-for-public-display') }}</div>
                @endif
            </div>
            @if($media->count() > 1)
                <div class="z4-public-thumbnails" role="list" aria-label="{{ __('exhibitions::ui.artwork-images') }}">
                    @foreach($media as $image)
                        <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-gallery-thumbnail data-src="{{ route('exhibitions.artworks.media', [$artwork->public_id, $image->public_id]) }}" data-alt="{{ ucwords(str_replace('_', ' ', $image->role)) }} — {{ $artwork->title }}" aria-label="Show {{ ucwords(str_replace('_', ' ', $image->role)) }} image"><img src="{{ route('exhibitions.artworks.media', [$artwork->public_id, $image->public_id]) }}" alt="" @if($image->width) width="{{ $image->width }}" @endif @if($image->height) height="{{ $image->height }}" @endif loading="lazy" decoding="async"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <article class="z4-public-purchase-card">
            <div class="z4-public-title-actions">
                <div class="z4-public-badges"><span class="is-availability">{{ $availability }}</span><span>{{ $artwork->edition_type === 'limited' ? 'Limited Edition' : 'Original Artwork' }}</span></div>
                <div><button type="button" data-artwork-share aria-label="{{ __('exhibitions::ui.share-artwork') }}">{{ __('exhibitions::ui.share') }}</button><button type="button" data-artwork-favorite aria-pressed="false" aria-label="{{ __('exhibitions::ui.save-artwork-to-favorites') }}">♡</button></div>
            </div>
            <h1>{{ $artwork->title }}</h1>
            <p class="z4-public-byline">by <strong>{{ $artistName }}</strong></p>
            <p class="z4-public-price">{{ $priceLabel }}</p>
            <p class="z4-public-price-policy"><strong>{{ __('exhibitions::ui.frame-and-packaging-are-included-in-the-approved-sale-price') }}</strong> {{ __('exhibitions::ui.shipping-and-tax-are-calculated-by-store-at-checkout') }}</p>
            @if($commerce->maximumQuantity > 0)<p><strong>{{ __('exhibitions::ui.maximum-quantity') }}</strong> {{ $commerce->maximumQuantity }}</p>@endif
            @if($hasSummary)<p class="z4-public-summary">{{ $artwork->short_description ?: $artwork->detailed_description }}</p>@endif
            <dl class="z4-public-key-facts">
                <div><dt>{{ __('exhibitions::ui.year') }}</dt><dd>{{ $artwork->completion_year ?: 'Not specified' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.edition') }}</dt><dd>{{ $artwork->edition_type ? ucwords(str_replace('_', ' ', $artwork->edition_type)) : 'Not specified' }}@if($artwork->edition_total) · {{ $artwork->edition_number ?: 1 }} of {{ $artwork->edition_total }}@endif</dd></div>
                <div><dt>{{ __('exhibitions::ui.condition') }}</dt><dd>{{ $artwork->condition ? ucwords(str_replace('_', ' ', $artwork->condition)) : 'Not specified' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.orientation') }}</dt><dd>{{ $artwork->orientation ? ucfirst($artwork->orientation) : 'Not specified' }}</dd></div>
            </dl>
            <div class="z4-public-purchase-actions">
                @if($canAddToCart)
                    <form method="post" action="{{ route('store.cart.items.store') }}">@csrf<input type="hidden" name="variant_public_id" value="{{ $publication->store_variant_public_id }}"><input type="hidden" name="quantity" value="1"><button type="submit" class="is-primary">{{ __('exhibitions::ui.add-to-cart-2') }}</button></form>
                @elseif($availability === 'Sold')
                    <button type="button" disabled>{{ __('exhibitions::ui.sold') }}</button>
                @else
                    <button type="button" disabled>{{ __('exhibitions::ui.currently-unavailable') }}</button>
                @endif
                <a href="#artwork-inquiry">{{ __('exhibitions::ui.ask-about-this-artwork') }}</a>
            </div>
            @if(!$canAddToCart && $availability !== 'Sold')<div class="z4-exhibitions-alert">{{ __('exhibitions::ui.store-purchase-is-temporarily-unavailable') }}</div>@endif
            <div class="z4-public-trust-row"><span>{{ __('exhibitions::ui.secure-checkout') }}</span>@if($authAccepted)<span>{{ __('exhibitions::ui.authenticity-reviewed') }}</span>@endif<span>{{ __('exhibitions::ui.platform-managed-shipping') }}</span></div>
            <p class="z4-public-share-status" data-share-status aria-live="polite"></p>
        </article>
    </section>

    <section class="z4-public-artwork-body">
        <aside class="z4-public-specifications">
            <h2>{{ __('exhibitions::ui.specifications') }}</h2>
            <dl>
                @foreach([
                    'Category' => $present($artwork->category),
                ] as $label => $value)
                    @if(filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                @endforeach
            </dl>
        </aside>

        <div class="z4-public-detail-tabs" data-artwork-tabs>
            @if(count($tabs) > 1)<div class="z4-public-tab-list" role="tablist">
                @foreach($tabs as $key => $label)<button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-tab-target="{{ $key }}">{{ $label }}</button>@endforeach
            </div>@endif
            <section role="tabpanel" data-tab-panel="overview"><p>{{ $artwork->detailed_description ?: $artwork->short_description }}</p></section>
            @if(isset($tabs['story']))<section role="tabpanel" data-tab-panel="story" hidden><p>{{ $artwork->artwork_story }}</p></section>@endif
            @if(isset($tabs['care']))<section role="tabpanel" data-tab-panel="care" hidden><p>{{ $artwork->care_instructions }}</p></section>@endif
            @if(isset($tabs['shipping']))<section role="tabpanel" data-tab-panel="shipping" hidden><p>{{ $artwork->shipping_notes ?: 'Shipping arrangements and final delivery availability are confirmed by the platform during checkout.' }}</p><p><strong>{{ __('exhibitions::ui.preparation') }}</strong> {{ $artwork->preparation_days !== null ? $artwork->preparation_days.' day(s)' : 'Confirmed after purchase' }}</p></section>@endif

        </div>
    </section>

    <section class="z4-public-inquiry" id="artwork-inquiry">
        <div><h2>{{ __('exhibitions::ui.ask-about-this-artwork') }}</h2><p>{{ __('exhibitions::ui.your-question-goes-to-the-platform-team-the-artist-never-rec') }}</p></div>
        @auth<form method="post" action="{{ route('exhibitions.artworks.inquire', $artwork->public_id) }}">@csrf<label for="artwork-question">{{ __('exhibitions::ui.your-question') }}</label><textarea id="artwork-question" name="message" minlength="10" maxlength="4000" required placeholder="{{ __('exhibitions::ui.ask-about-delivery-display-condition-or-any-artwork-detail') }}">{{ old('message') }}</textarea><button type="submit">{{ __('exhibitions::ui.send-inquiry') }}</button></form>@else<a class="z4-public-outline-button" href="{{ route('login') }}">{{ __('exhibitions::ui.sign-in-to-ask-a-question') }}</a>@endauth
    </section>

    <section class="z4-public-related">
        <div class="z4-public-section-heading"><h2>{{ __('exhibitions::ui.you-may-also-like') }}</h2><a href="{{ route('exhibitions.artworks.index') }}">{{ __('exhibitions::ui.view-all-artworks') }}</a></div>
        @if($related->isEmpty())<div class="z4-public-empty-state">{{ __('exhibitions::ui.no-other-available-artworks-match-this-selection-yet') }}</div>@else<div class="z4-public-related-grid">@foreach($related as $item)@php($relatedPermalink = filled($item->slug ?? null) ? $item->slug : (\Illuminate\Support\Str::slug($item->title) ?: $item->public_id))<article>@if($item->primary_media)<a href="{{ route('exhibitions.artworks.show', $relatedPermalink) }}"><img src="{{ route('exhibitions.artworks.media', [$item->public_id, $item->primary_media->public_id]) }}" alt="{{ $item->title }}" @if($item->primary_media->width ?? null) width="{{ $item->primary_media->width }}" @endif @if($item->primary_media->height ?? null) height="{{ $item->primary_media->height }}" @endif loading="lazy" decoding="async"></a>@endif<div><h3><a href="{{ route('exhibitions.artworks.show', $relatedPermalink) }}">{{ $item->title }}</a></h3><p>by {{ $item->artistic_name ?: $item->legal_name }}</p><strong>{{ number_format((float) $item->approved_amount_snapshot, 2) }} {{ $item->currency }}</strong><a class="z4-public-outline-button" href="{{ route('exhibitions.artworks.show', $relatedPermalink) }}">{{ __('exhibitions::ui.view-artwork') }}</a></div></article>@endforeach</div>@endif
    </section>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    const root=document.querySelector('[data-public-artwork]');if(!root)return;
    const main=root.querySelector('[data-gallery-main]'),full=root.querySelector('[data-gallery-full]');
    root.querySelectorAll('[data-gallery-thumbnail]').forEach(button=>button.addEventListener('click',()=>{if(main){main.src=button.dataset.src;main.alt=button.dataset.alt||''}if(full)full.href=button.dataset.src;root.querySelectorAll('[data-gallery-thumbnail]').forEach(item=>item.classList.toggle('is-active',item===button))}));
    root.querySelectorAll('[data-tab-target]').forEach(tab=>tab.addEventListener('click',()=>{const key=tab.dataset.tabTarget;root.querySelectorAll('[data-tab-target]').forEach(item=>item.setAttribute('aria-selected',item===tab?'true':'false'));root.querySelectorAll('[data-tab-panel]').forEach(panel=>panel.hidden=panel.dataset.tabPanel!==key)}));
    const favorite=root.querySelector('[data-artwork-favorite]'),favoriteKey='z4.favorite.artwork.'+root.dataset.publicArtwork;if(favorite){const paint=()=>{const saved=localStorage.getItem(favoriteKey)==='1';favorite.setAttribute('aria-pressed',saved?'true':'false');favorite.textContent=saved?'♥':'♡'};paint();favorite.addEventListener('click',()=>{localStorage.setItem(favoriteKey,localStorage.getItem(favoriteKey)==='1'?'0':'1');paint()})}
    const share=root.querySelector('[data-artwork-share]'),status=root.querySelector('[data-share-status]');share?.addEventListener('click',async()=>{try{if(navigator.share)await navigator.share({title:document.title,url:location.href});else{await navigator.clipboard.writeText(location.href);if(status)status.textContent='Artwork link copied.'}}catch(error){if(error?.name!=='AbortError'&&status)status.textContent='Could not share the link. Please copy it from the address bar.'}});
});
</script>
</x-frontend-layout>
