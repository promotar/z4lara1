@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-view-1" data-exhibitions-stylesheet>
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin" data-admin-artwork-view>
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading z4-artwork-view-heading">
        <div>
            <a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.workspace', ['area' => 'artworks']) }}">&larr; {{ __('exhibitions::ui.all_artworks') }}</a>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_15effbf1a9') }}</span>
            <h1>{{ $artwork->title ?: 'Untitled artwork' }}</h1>
            <p>{{ __('exhibitions::ui.auto_41c0f38ccb') }}</p>
        </div>
        <div class="z4-artwork-view-heading-actions">
            <span class="z4-exhibitions-pill">{{ __('exhibitions::ui.state_'.$artwork->state) }}</span>
            @if(in_array($artwork->state, ['submitted','under_review','changes_required'], true))
                <a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.artworks.review.show', $artwork->public_id) }}">{{ __('exhibitions::ui.auto_d373afbab8') }}</a>
            @endif
            @if($artwork->state === 'published' && $publication?->state === 'published')
                @php($artworkPermalink = filled($artwork->slug ?? null) ? $artwork->slug : (\Illuminate\Support\Str::slug($artwork->title) ?: $artwork->public_id))
                <a class="z4-exhibitions-button is-secondary" href="{{ route('exhibitions.artworks.show', $artworkPermalink) }}" target="_blank" rel="noopener">{{ __('exhibitions::ui.auto_419388991c') }}</a>
            @endif
        </div>
    </header>

    <section class="z4-artwork-preview-card z4-admin-artwork-preview">
        <div>
            @if($primaryMedia)
                <img class="z4-artwork-preview-image" src="{{ route('admin.plugins.exhibitions.artworks.review.media.display', [$artwork->public_id, $primaryMedia->public_id]) }}" alt="{{ $artwork->image_alt_text ?: $artwork->title }}">
            @else
                <div class="z4-artwork-preview-placeholder" role="img" aria-label="{{ __('exhibitions::ui.auto_8b16f8a70f') }}">{{ __('exhibitions::ui.auto_7a72b1657f') }}</div>
            @endif
            @if($media->count() > 1)<small class="z4-artwork-media-count">{{ __('exhibitions::ui.auto_0afcca5801') }}</small>@endif
        </div>
        <div class="z4-artwork-view-content">
            <span class="z4-exhibitions-eyebrow">{{ $artwork->category ?: 'Uncategorized artwork' }}</span>
            <h2>{{ $artwork->title ?: 'Untitled artwork' }}</h2>
            <p>{{ $artwork->short_description ?: 'No short description has been saved.' }}</p>
            <dl>
                <div><dt>{{ __('exhibitions::ui.auto_4047c5607d') }}</dt><dd>{{ $artwork->artist_name }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_22a70073ad') }}</dt><dd>{{ $artwork->technique ?: '—' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_d92a8333dd') }}</dt><dd>{{ $artwork->material ?: '—' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_f4843c1c79') }}</dt><dd>{{ $artwork->width ?: '—' }} × {{ $artwork->height ?: '—' }} {{ $artwork->dimension_unit }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_2a5ab1af48') }}</dt><dd>{{ $artwork->requested_price ? number_format((float) $artwork->requested_price, 2).' '.$artwork->currency : 'Pending' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_3d182b6064') }}</dt><dd>{{ $pricing?->approved_amount_snapshot ? number_format((float) $pricing->approved_amount_snapshot, 2).' '.$pricing->currency : 'Pending' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_faeaec9eda') }}</dt><dd>{{ \Illuminate\Support\Str::headline($artwork->availability ?: 'not set') }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_6bd6beac1d') }}</dt><dd>{{ $publication ? __('exhibitions::ui.state_'.$publication->state) : __('exhibitions::ui.not_created') }}</dd></div>
            </dl>
        </div>
    </section>

    @if($artwork->detailed_description || $artwork->artwork_story)
        <section class="z4-exhibitions-panel z4-artwork-view-details">
            @if($artwork->detailed_description)<div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_b5a7adde1a') }}</span><p>{{ $artwork->detailed_description }}</p></div>@endif
            @if($artwork->artwork_story)<div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_f9f65874f3') }}</span><p>{{ $artwork->artwork_story }}</p></div>@endif
        </section>
    @endif
</div>
</main>
</x-app-layout>
