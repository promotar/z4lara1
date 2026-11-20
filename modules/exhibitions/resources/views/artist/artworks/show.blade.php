@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-detail-3" data-exhibitions-stylesheet>
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artwork-detail>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',['title'=>$artwork->title ?: 'Untitled artwork','subtitle' => __('exhibitions::ui.artist_page_show_subtitle')])

@if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

<nav class="z4-artwork-detail-toolbar" aria-label="{{ __('exhibitions::ui.auto_569816f84b') }}">
    <a class="z4-artwork-detail-back" href="{{ route('exhibitions.dashboard.artworks') }}"><span aria-hidden="true">&larr;</span> {{ __('exhibitions::ui.auto_352c531b9b') }}</a>
    <div class="z4-artwork-detail-actions">
        @if(in_array($artwork->state,['draft','submitted','changes_required','rejected'],true))
            <a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.artworks.edit',$artwork->public_id) }}">{{ __('exhibitions::ui.auto_431dc5fbdf') }}</a>
        @endif
        <a class="z4-exhibitions-button is-secondary" href="{{ route('exhibitions.dashboard.artworks.preview',$artwork->public_id) }}">{{ __('exhibitions::ui.auto_7dc5669330') }}</a>
        <form method="post" action="{{ route('exhibitions.dashboard.artworks.destroy',$artwork->public_id) }}" onsubmit="return confirm('Delete this artwork permanently? This cannot be undone.');">
            @csrf
            <input type="hidden" name="confirmation" value="delete-artwork">
            <button class="z4-exhibitions-button is-danger" type="submit">{{ __('exhibitions::ui.auto_23a7081942') }}</button>
        </form>
    </div>
</nav>

<div class="z4-exhibitions-columns z4-artwork-detail-grid">
<div class="z4-artwork-detail-main">
    <section class="z4-exhibitions-panel z4-artwork-detail-information">
        <div class="z4-exhibitions-panel-heading"><div><h2>{{ __('exhibitions::ui.auto_4d83c2dfc0') }}</h2><p>{{ __('exhibitions::ui.auto_4d43ef09d1') }}</p></div><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.state_'.$artwork->state) }}</span></div>

        <div class="z4-artwork-detail-section">
            <h3>{{ __('exhibitions::ui.auto_807402b244') }}</h3>
            <dl class="z4-artwork-detail-data-grid">
                @foreach(['title'=>'Name','completion_year'=>'Completion year','work_type'=>'Work type','collection_name'=>'Collection','edition_type'=>'Edition','category'=>'Category'] as $field=>$label)
                    <div><dt>{{ $label }}</dt><dd>{{ $artwork->{$field} ? str_replace('_',' ',$artwork->{$field}) : '—' }}</dd></div>
                @endforeach
            </dl>
        </div>

        <div class="z4-artwork-detail-section">
            <h3>{{ __('exhibitions::ui.auto_5d25ef0fe8') }}</h3>
            <dl class="z4-artwork-detail-data-grid">
                @foreach(['artistic_style'=>'Style','subject'=>'Subject','technique'=>'Technique','material'=>'Material','surface'=>'Surface','orientation'=>'Orientation','condition'=>'Condition'] as $field=>$label)
                    <div><dt>{{ $label }}</dt><dd>{{ $artwork->{$field} ? str_replace('_',' ',$artwork->{$field}) : '—' }}</dd></div>
                @endforeach
                <div><dt>{{ __('exhibitions::ui.auto_f4843c1c79') }}</dt><dd>{{ $artwork->width ?: '—' }} × {{ $artwork->height ?: '—' }}@if($artwork->depth) × {{ $artwork->depth }}@endif {{ $artwork->dimension_unit }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_8c489d0946') }}</dt><dd>{{ $artwork->weight ? $artwork->weight.' '.$artwork->weight_unit : '—' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_6b73ad3725') }}</dt><dd>{{ $artwork->is_framed ? __('exhibitions::ui.yes') : __('exhibitions::ui.no') }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_0a31457556') }}</dt><dd>{{ $artwork->ready_to_hang ? __('exhibitions::ui.yes') : __('exhibitions::ui.no') }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="z4-exhibitions-panel z4-artwork-content-panel">
        <div class="z4-exhibitions-panel-heading"><div><h2>{{ __('exhibitions::ui.auto_d05194506f') }}</h2><p>{{ __('exhibitions::ui.auto_f103fd52f2') }}</p></div></div>
        <div class="z4-artwork-prose-list">
            @foreach(['short_description'=>'Short description','detailed_description'=>'Detailed description','artwork_story'=>'Artwork story','inspiration_source'=>'Source of inspiration','care_instructions'=>'Care instructions'] as $field=>$label)
                <article><h3>{{ $label }}</h3><p>{{ $artwork->{$field} ?: 'No information provided.' }}</p></article>
            @endforeach
        </div>
    </section>

    <section class="z4-exhibitions-panel z4-artwork-commerce-panel">
        <div class="z4-exhibitions-panel-heading"><div><h2>{{ __('exhibitions::ui.auto_b19ec707a7') }}</h2><p>{{ __('exhibitions::ui.auto_688adeeaaf') }}</p></div></div>
        <dl class="z4-artwork-detail-data-grid">
            <div><dt>{{ __('exhibitions::ui.auto_2a5ab1af48') }}</dt><dd>{{ $artwork->requested_price ? $artwork->requested_price.' '.$artwork->currency : '—' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_faeaec9eda') }}</dt><dd data-effective-availability>{{ $artwork->effective_availability }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_ec83eb0ab2') }}</dt><dd>{{ $artwork->frame_cost ? $artwork->frame_cost.' '.$artwork->currency : '—' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_45b4d795bf') }}</dt><dd>{{ $artwork->packaging_cost ? $artwork->packaging_cost.' '.$artwork->currency : '—' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_64979b52d7') }}</dt><dd>{{ $artwork->preparation_days !== null ? $artwork->preparation_days.' day(s)' : '—' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_0de4afdad5') }}</dt><dd>{{ __('exhibitions::ui.auto_5d52b91fdc') }}</dd></div>
        </dl>
        <div class="z4-artwork-shipping-note"><h3>{{ __('exhibitions::ui.auto_a78c2337cf') }}</h3><p>{{ $artwork->shipping_notes ?: 'No shipping notes provided.' }}</p></div>
    </section>
</div>

<aside class="z4-exhibitions-panel z4-artwork-revision-panel">
    <div class="z4-exhibitions-panel-heading"><div><h2>{{ __('exhibitions::ui.auto_54564cd0d8') }}</h2><p>{{ __('exhibitions::ui.auto_daddc4ac93') }}</p></div><span class="z4-artwork-revision-count">{{ $revisions->count() }}</span></div>
    <ol class="z4-exhibitions-timeline z4-artwork-revision-list">
        @forelse($revisions as $revision)
            <li><span></span><div><strong>Revision {{ $revision->revision_number }}</strong><time>{{ $revision->created_at }}</time><p>{{ __('exhibitions::ui.state_'.$revision->reason) }}</p></div></li>
        @empty
            <li><span></span><div><strong>{{ __('exhibitions::ui.auto_7a456aa718') }}</strong><p>{{ __('exhibitions::ui.auto_6f6e929a98') }}</p></div></li>
        @endforelse
    </ol>
</aside>
</div>
</div>
</main>
</x-frontend-layout>
