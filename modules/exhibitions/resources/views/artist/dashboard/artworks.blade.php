@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-actions-3" data-exhibitions-stylesheet>
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-dashboard-artworks>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',['title' => __('exhibitions::ui.artist_page_artworks_title'),'subtitle' => __('exhibitions::ui.artist_page_artworks_subtitle')])
@if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
<div class="z4-exhibitions-actions z4-exhibitions-actions-top">
    <a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.artworks.create') }}">{{ __('exhibitions::ui.auto_354474fbc8') }}</a>
</div>
<div class="z4-artist-filter-strip">
@foreach($counts as $key=>$count)
    <a class="{{ $filter===$key?'is-active':'' }}" href="{{ route('exhibitions.dashboard.artworks',['status'=>$key]) }}">
        <span>{{ __('exhibitions::ui.artist_filter_'.$key) }}</span> <b>{{ $count }}</b>
    </a>
@endforeach
</div>
<section class="z4-exhibitions-panel">
@if($artworks->isEmpty())
    <div class="z4-exhibitions-empty">
        <strong>{{ __('exhibitions::ui.auto_2988eec6de') }}</strong>
        <p>{{ __('exhibitions::ui.auto_57132787db') }}</p>
    </div>
@else
    <div class="z4-artwork-list">
    @foreach($artworks as $artwork)
        @php($artworkPermalink = filled($artwork->slug ?? null) ? $artwork->slug : (\Illuminate\Support\Str::slug($artwork->title) ?: $artwork->public_id))
        @php($viewWorkUrl = $artwork->state === 'published' ? route('exhibitions.artworks.show', $artworkPermalink) : route('exhibitions.dashboard.artworks.preview',$artwork->public_id))
        @php($effectiveState = $artwork->effective_state ?? $artwork->state)
        <article class="z4-artwork-list-item" data-artwork-id="{{ $artwork->public_id }}" data-artwork-state="{{ $effectiveState }}">
            <a class="z4-artwork-list-main" href="{{ route('exhibitions.dashboard.artworks.show',$artwork->public_id) }}">
            <div class="z4-artwork-list-copy">
                <strong>{{ $artwork->title ?: __('exhibitions::ui.artist_untitled') }}</strong>
                <span>{{ $artwork->updated_at }} · {{ $artwork->work_type ?: __('exhibitions::ui.artist_draft_incomplete') }}</span>
            </div>
            <b>{{ __('exhibitions::ui.artist_filter_'.$effectiveState) }}</b>
            </a>
            <div class="z4-artwork-list-actions">
                <a class="z4-view-work-action" href="{{ $viewWorkUrl }}" title="{{ __('exhibitions::ui.auto_f82d24c551') }}" aria-label="{{ __('exhibitions::ui.artist_view_work_aria', ['title' => $artwork->title ?: __('exhibitions::ui.artist_untitled')]) }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                    <span>{{ __('exhibitions::ui.auto_f82d24c551') }}</span>
                </a>
                <form method="post" action="{{ route('exhibitions.dashboard.artworks.duplicate', $artwork->public_id) }}">
                    @csrf
                    <button class="z4-duplicate-work-action" type="submit" title="{{ __('exhibitions::ui.auto_e5dd5c0b88') }}" aria-label="{{ __('exhibitions::ui.artist_duplicate_aria', ['title' => $artwork->title ?: __('exhibitions::ui.artist_untitled')]) }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                        <span>{{ __('exhibitions::ui.auto_ed75712b0e') }}</span>
                    </button>
                </form>
                <form class="z4-artwork-list-delete" method="post" action="{{ route('exhibitions.dashboard.artworks.destroy', $artwork->public_id) }}" onsubmit="return confirm('{{ __('exhibitions::ui.artist_delete_confirm') }}');">
                    @csrf
                    <input type="hidden" name="confirmation" value="delete-artwork">
                    <button class="z4-delete-work-action" type="submit" title="{{ __('exhibitions::ui.auto_23a7081942') }}" aria-label="{{ __('exhibitions::ui.artist_delete_aria', ['title' => $artwork->title ?: __('exhibitions::ui.artist_untitled')]) }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M6.5 7l.9 12.1A2 2 0 0 0 9.4 21h5.2a2 2 0 0 0 2-1.9L17.5 7"/><path d="M10 11v6M14 11v6"/></svg>
                        <span>{{ __('exhibitions::ui.auto_f2a6c498fb') }}</span>
                    </button>
                </form>
            </div>
        </article>
    @endforeach
    </div>
    <x-platform-pagination :paginator="$artworks" />
@endif
</section>
</div>
</main>
</x-frontend-layout>
