@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-review-4" data-exhibitions-stylesheet>
@endpush

@php
    $sectionMap = $sections->keyBy('section');
    $reviewIsActive = $currentRound->state === 'under_review';
    $reviewAreas = [
        'content' => ['label' => 'Artwork information', 'description' => 'Every field submitted by the artist, including fields left empty.'],
        'images' => ['label' => 'Images', 'description' => $media->count().' protected image(s) ready for inspection.'],
        'authenticity' => ['label' => 'Ownership & authenticity', 'description' => $authEvidence->count().' private evidence file(s) submitted.'],
        'pricing' => ['label' => 'Pricing & delivery', 'description' => 'Artist pricing, preparation, shipping, and supporting evidence.'],
    ];
    $informationGroups = [
        'Basic information' => [
            'title' => 'Artwork name', 'completion_year' => 'Completion year', 'work_type' => 'Work type',
            'collection_name' => 'Artistic collection', 'edition_type' => 'Edition', 'edition_number' => 'Edition number',
            'edition_total' => 'Total edition count',
        ],
        'Artistic classification' => [
            'category' => 'Category', 'artistic_style' => 'Artistic style', 'subject' => 'Subject',
            'technique' => 'Technique', 'material' => 'Material', 'surface' => 'Surface',
            'primary_colors' => 'Primary colors', 'orientation' => 'Orientation',
        ],
        'Physical details' => [
            'width' => 'Width', 'height' => 'Height', 'depth' => 'Depth', 'dimension_unit' => 'Dimension unit',
            'weight' => 'Weight', 'weight_unit' => 'Weight unit', 'is_framed' => 'Artwork is framed',
            'frame_material' => 'Frame material', 'condition' => 'Condition', 'ready_to_hang' => 'Ready to hang',
        ],
        'Description & story' => [
            'short_description' => 'Short description', 'detailed_description' => 'Detailed description',
            'artwork_story' => 'Artwork story', 'inspiration_source' => 'Source of inspiration',
            'care_instructions' => 'Care instructions',
        ],
    ];
    $pricingFields = [
        'requested_price' => 'Artist requested price', 'currency' => 'Currency', 'frame_cost' => 'Frame cost',
        'packaging_cost' => 'Packaging cost', 'availability' => 'Availability',
        'preparation_days' => 'Preparation time', 'shipping_notes' => 'Shipping notes',
    ];
    $isEmptyReviewValue = static function ($value): bool {
        if ($value === null || (is_string($value) && trim($value) === '')) return true;
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) && $decoded === [];
        }
        return false;
    };
    $formatReviewValue = static function (string $field, $value) use ($artwork, $isEmptyReviewValue): string {
        if ($isEmptyReviewValue($value)) return '';
        if (in_array($field, ['is_framed', 'ready_to_hang'], true)) return $value ? __('exhibitions::ui.yes') : __('exhibitions::ui.no');
        if ($field === 'preparation_days') return $value.' day(s)';
        if (in_array($field, ['requested_price', 'frame_cost', 'packaging_cost'], true)) return number_format((float) $value, 2).' '.($artwork->currency ?? '');
        if ($field === 'primary_colors' && is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return implode(', ', $decoded);
        }
        if (in_array($field, ['edition_type', 'orientation', 'condition', 'availability'], true)) return ucwords(str_replace('_', ' ', (string) $value));
        return (string) $value;
    };
@endphp

<x-app-layout>
<main class="z4-exhibitions-admin" data-artwork-review-workspace>
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading z4-review-page-heading">
        <div>
            <a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.workspace', ['area' => 'artworks', 'tab' => 'review_queue']) }}">{{ __('exhibitions::ui.auto_ec8bad11ab') }}</a>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_c2db4fd22f') }}</span>
            <h1>{{ $artwork->title }}</h1>
            <p>{{ __('exhibitions::ui.auto_855ba0f9d6') }}</p>
        </div>
        <span class="z4-exhibitions-status">{{ __('exhibitions::ui.state_'.$currentRound->state) }}</span>
    </header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="z4-review-area-strip" aria-label="{{ __('exhibitions::ui.auto_3a7140cddc') }}">
        @foreach($reviewAreas as $key => $area)
            @php($section = $sectionMap->get($key))
            <article>
                <span class="z4-review-area-number">{{ $loop->iteration }}</span>
                <div><strong>{{ $area['label'] }}</strong><small>{{ $area['description'] }}</small></div>
                <span class="z4-exhibitions-pill">{{ ucwords(str_replace('_', ' ', $section?->state ?? 'pending')) }}</span>
            </article>
        @endforeach
    </section>

    <section class="z4-review-inspection-grid">
        <article class="z4-exhibitions-panel z4-review-content-card">
            <div class="z4-exhibitions-panel-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_8c1ecac7a8') }}</span><h2>{{ __('exhibitions::ui.auto_9250d42966') }}</h2><p>{{ __('exhibitions::ui.auto_6e3a2a0dc7') }}</p></div></div>
            @foreach($informationGroups as $group => $fields)
                <section class="z4-review-field-group">
                    <h3>{{ $group }}</h3>
                    <dl class="z4-exhibitions-details">
                        @foreach($fields as $field => $label)
                            @php($value = $artwork->{$field} ?? null)
                            <div class="{{ in_array($field, ['short_description', 'detailed_description', 'artwork_story', 'inspiration_source', 'care_instructions'], true) ? 'is-wide' : '' }} {{ $isEmptyReviewValue($value) ? 'is-empty' : '' }}">
                                <dt>{{ $label }}</dt>
                                <dd>@if($isEmptyReviewValue($value))<em>{{ __('exhibitions::ui.auto_2b0a06012c') }}</em>@else{{ $formatReviewValue($field, $value) }}@endif</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </article>

        <article class="z4-exhibitions-panel z4-review-evidence-card">
            <div class="z4-exhibitions-panel-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_6b69b61de0') }}</span><h2>{{ __('exhibitions::ui.auto_3888c23456') }}</h2><p>{{ __('exhibitions::ui.auto_ad2e66f5e0') }}</p></div><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.auto_9b322a38a3') }}</span></div>
            <div class="z4-review-media-gallery">
                @forelse($media as $image)
                    <figure>
                        <a class="z4-review-media-preview" href="{{ route('admin.plugins.exhibitions.artworks.review.media.display', [$artwork->public_id, $image->public_id]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.plugins.exhibitions.artworks.review.media.display', [$artwork->public_id, $image->public_id]) }}" alt="{{ __('exhibitions::ui.state_'.$image->role) }}" loading="lazy"></a>
                        <figcaption>
                            <div><strong>{{ __('exhibitions::ui.state_'.$image->role) }}</strong>@if($image->is_primary)<span class="z4-exhibitions-pill">{{ __('exhibitions::ui.auto_98813b7918') }}</span>@endif</div>
                            <small>{{ $image->width }} × {{ $image->height }} px · {{ $image->original_name }}</small>
                            <a href="{{ route('admin.plugins.exhibitions.artworks.review.media.download', [$artwork->public_id, $image->public_id]) }}">{{ __('exhibitions::ui.auto_641ceac69c') }}</a>
                        </figcaption>
                    </figure>
                @empty<div class="z4-review-empty">{{ __('exhibitions::ui.auto_7437adcca4') }}</div>@endforelse
            </div>
        </article>

        <article class="z4-exhibitions-panel z4-review-evidence-card">
            <div class="z4-exhibitions-panel-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_6ec99889bb') }}</span><h2>{{ __('exhibitions::ui.auto_9fab335338') }}</h2><p>{{ __('exhibitions::ui.auto_f615627a83') }}</p></div><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.auto_f1718ef3c5') }}</span></div>
            <dl class="z4-exhibitions-details z4-review-declaration-details">
                <div class="{{ $authenticity ? '' : 'is-empty' }}"><dt>{{ __('exhibitions::ui.auto_daea5331e5') }}</dt><dd>@if(!$authenticity)<em>{{ __('exhibitions::ui.auto_2b0a06012c') }}</em>@else{{ $authenticity->owns_artwork ? __('exhibitions::ui.owner_of_artwork') : ($authenticity->may_display_and_sell ? __('exhibitions::ui.authorized_to_sell') : __('exhibitions::ui.not_declared')) }}@endif</dd></div>
                @foreach(['inspiration_disclosure' => 'Related works, references or inspiration', 'digital_tools_disclosure' => 'Digital or generative tools used', 'current_physical_location' => 'Current physical location'] as $field => $label)
                    @php($value = $authenticity->{$field} ?? null)
                    <div class="{{ $field !== 'current_physical_location' ? 'is-wide' : '' }} {{ $isEmptyReviewValue($value) ? 'is-empty' : '' }}"><dt>{{ $label }}</dt><dd>@if($isEmptyReviewValue($value))<em>{{ __('exhibitions::ui.auto_2b0a06012c') }}</em>@else{{ $value }}@endif</dd></div>
                @endforeach
            </dl>
            <div class="z4-review-file-list">
                @forelse($authEvidence as $evidence)
                    <a href="{{ route('admin.plugins.exhibitions.artworks.authenticity.evidence.download', [$artwork->public_id, $evidence->public_id]) }}"><span><strong>{{ __('exhibitions::ui.state_'.$evidence->role) }}</strong><small>{{ $evidence->original_name }} · {{ number_format($evidence->size_bytes / 1024, 1) }} KB</small></span><b>{{ __('exhibitions::ui.auto_0733e177a0') }}</b></a>
                @empty<div class="z4-review-empty">{{ __('exhibitions::ui.auto_9b950a9994') }}</div>@endforelse
            </div>
        </article>

        <article class="z4-exhibitions-panel z4-review-evidence-card">
            <div class="z4-exhibitions-panel-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_f4477d89d9') }}</span><h2>{{ __('exhibitions::ui.auto_83d2786189') }}</h2><p>{{ __('exhibitions::ui.auto_a47e60f8cd') }}</p></div><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.auto_feab70670c') }}</span></div>
            <dl class="z4-exhibitions-details">
                @foreach($pricingFields as $field => $label)
                    @php($value = $artwork->{$field} ?? null)
                    <div class="{{ $field === 'shipping_notes' ? 'is-wide' : '' }} {{ $isEmptyReviewValue($value) ? 'is-empty' : '' }}"><dt>{{ $label }}</dt><dd>@if($isEmptyReviewValue($value))<em>{{ __('exhibitions::ui.auto_2b0a06012c') }}</em>@else{{ $formatReviewValue($field, $value) }}@endif</dd></div>
                @endforeach
            </dl>
            <div class="z4-review-file-list">
                @forelse($pricingEvidence as $evidence)
                    <a href="{{ route('admin.plugins.exhibitions.artworks.pricing.evidence.download', [$artwork->public_id, $evidence->public_id]) }}"><span><strong>{{ __('exhibitions::ui.auto_ee1b10b1e4') }}</strong><small>{{ $evidence->original_name ?? 'Supporting file' }}</small></span><b>{{ __('exhibitions::ui.auto_0733e177a0') }}</b></a>
                @empty<div class="z4-review-empty">{{ __('exhibitions::ui.auto_927adb9472') }}</div>@endforelse
            </div>
        </article>
    </section>

    @if($reviewIsActive)
        <section class="z4-exhibitions-panel z4-review-final-decision">
            <div class="z4-review-decision-copy"><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_4b138a5296') }}</span><h2>{{ __('exhibitions::ui.auto_6c76164fc5') }}</h2><p>{{ __('exhibitions::ui.auto_aec08a08e5') }}</p></div>
            <form method="post" action="{{ route('admin.plugins.exhibitions.artworks.review.decide-overall', [$artwork->public_id, $currentRound->public_id]) }}" class="z4-exhibitions-form z4-review-decision-form">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $currentRound->lock_version }}">
                <label><span>{{ __('exhibitions::ui.auto_5340e5541d') }}</span><select name="decision" required><option value="">{{ __('exhibitions::ui.auto_1f587bdebc') }}</option>@foreach($overallDecisions as $decision)<option value="{{ $decision }}" @selected(old('decision') === $decision)>{{ ['approved' => 'Approve artwork', 'changes_requested' => 'Request changes', 'rejected' => 'Reject artwork'][$decision] }}</option>@endforeach</select></label>
                <label><span>{{ __('exhibitions::ui.auto_797f1c0de7') }}</span><textarea name="reason" placeholder="Write all notes for the artist here in one clear message.">{{ old('reason') }}</textarea><small>Required for Request changes or Reject; optional for approval.</small></label>
                <button class="is-primary">{{ __('exhibitions::ui.auto_895e9fc0f6') }}</button>
            </form>
        </section>
    @else
        <section class="z4-exhibitions-panel z4-review-final-decision is-closed"><div class="z4-review-decision-copy"><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_21acbb43d8') }}</span><h2>{{ __('exhibitions::ui.state_'.$currentRound->state) }}</h2><p>{{ __('exhibitions::ui.auto_732c48b69b') }}</p></div></section>
    @endif

    <section class="z4-review-history-grid">
        <article class="z4-exhibitions-panel">
            <div class="z4-exhibitions-panel-heading"><h2>{{ __('exhibitions::ui.auto_76c3974853') }}</h2><span class="z4-exhibitions-pill">{{ $comments->count() }}</span></div>
            <ol class="z4-review-comments">
                @forelse($comments as $comment)<li><strong>{{ __('exhibitions::ui.auto_7449c00b9a') }}</strong><span>{{ __('exhibitions::ui.state_'.$comment->state) }}</span><p>{{ $comment->body }}</p><time>{{ $comment->created_at }}</time></li>@empty<li class="is-empty">{{ __('exhibitions::ui.auto_99c38a0a05') }}</li>@endforelse
            </ol>
        </article>
        <article class="z4-exhibitions-panel">
            <div class="z4-exhibitions-panel-heading"><h2>{{ __('exhibitions::ui.auto_8827394250') }}</h2><span class="z4-exhibitions-pill">{{ __('exhibitions::ui.auto_36d5f7f25d') }}</span></div>
            <ol class="z4-exhibitions-timeline">@foreach($rounds as $round)<li><span></span><div><strong>{{ __('exhibitions::ui.auto_067b2d792d') }}</strong><time>{{ $round->opened_at }}</time></div></li>@endforeach</ol>
        </article>
    </section>
</div>
</main>
</x-app-layout>
