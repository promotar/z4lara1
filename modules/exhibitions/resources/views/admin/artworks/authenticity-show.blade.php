@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin" data-admin-workspace data-authenticity-review>
<div class="z4-exhibitions-container">
<header class="z4-exhibitions-heading"><div><a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.artworks.authenticity.index') }}">{{ __('exhibitions::ui.auto_76d2341bdc') }}</a><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_6dd63e0c8c') }}</span><h1>{{ $artwork->title }}</h1><p>{{ __('exhibitions::ui.auto_f220b2c989') }}</p></div><span class="z4-exhibitions-status">{{ ucwords(str_replace('_',' ',$declaration->state ?? 'not submitted')) }}</span></header>
@if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="z4-exhibitions-notice z4-authenticity-human-notice"><strong>{{ __('exhibitions::ui.auto_8b4c224bf2') }}</strong><p>{{ __('exhibitions::ui.auto_021d6e94f7') }}</p></div>

<div class="z4-exhibitions-columns z4-authenticity-grid">
<section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_4f5bb07693') }}</h2><p>{{ __('exhibitions::ui.auto_87399a8ddf') }}</p></div></div>
@if($declaration)<dl class="z4-exhibitions-details"><div><dt>{{ __('exhibitions::ui.auto_14baf27de7') }}</dt><dd>{{ $declaration->owns_artwork ? __('exhibitions::ui.accepted') : __('exhibitions::ui.missing') }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_a17074e98a') }}</dt><dd>{{ $declaration->may_display_and_sell ? __('exhibitions::ui.accepted') : __('exhibitions::ui.missing') }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_cc7239cbaa') }}</dt><dd>{{ $declaration->inspiration_disclosure }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_ab213491ac') }}</dt><dd>{{ $declaration->digital_tools_disclosure }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_4af0750091') }}</dt><dd>{{ $declaration->current_physical_location }}</dd></div></dl>@else<div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_961c896e98') }}</strong><p>{{ __('exhibitions::ui.auto_0dd21a5ac2') }}</p></div>@endif
</section>

<section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_a5fca61a70') }}</h2><p>{{ __('exhibitions::ui.auto_3588106f3f') }}</p></div></div>
<ul class="z4-exhibitions-file-list">@forelse($evidence as $item)<li><a href="{{ route('admin.plugins.exhibitions.artworks.authenticity.evidence.download',[$artwork->public_id,$item->public_id]) }}">{{ $item->original_name }}</a><span>{{ __('exhibitions::ui.state_'.$item->role) }} · {{ number_format($item->size_bytes / 1024,1) }} KB</span>@if($item->exact_match_public_id)<b class="z4-exhibitions-neutral-flag">{{ __('exhibitions::ui.auto_551aafc5da') }}</b>@endif</li>@empty<li>{{ __('exhibitions::ui.auto_78d20c48d6') }}</li>@endforelse</ul>
</section>
</div>

<div class="z4-exhibitions-columns z4-authenticity-grid">
<section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_71b6a723d0') }}</h2><p>{{ __('exhibitions::ui.auto_a885b59f4a') }}</p></div></div><ol class="z4-exhibitions-timeline">@forelse($provenance as $event)<li><span></span><div><strong>{{ $event->event_date }} · {{ $event->event_type }}</strong><p>{{ $event->description }}</p>@if($event->owner_name || $event->location)<small>{{ $event->owner_name }} · {{ $event->location }}</small>@endif</div></li>@empty<li><span></span><div>{{ __('exhibitions::ui.auto_fc1ecd6eab') }}</div></li>@endforelse</ol></section>

<section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_7290c9a2d1') }}</h2><p>{{ __('exhibitions::ui.auto_d8867fdb3a') }}</p></div></div>
@php($decisionLabels=['documentation_accepted'=>'Accept documentation','additional_evidence_required'=>'Request additional evidence','under_investigation'=>'Open investigation','ownership_dispute'=>'Open ownership dispute','rejected'=>'Reject documentation'])
<form class="z4-exhibitions-form" method="post" action="{{ route('admin.plugins.exhibitions.artworks.authenticity.decide',$artwork->public_id) }}" data-authenticity-decision>@csrf
<label><span>{{ __('exhibitions::ui.auto_5d30f54fc5') }}</span><select name="decision" data-authenticity-decision-select>@foreach($decisions as $decision)<option value="{{ $decision }}">{{ $decisionLabels[$decision] ?? __('exhibitions::ui.state_'.$decision) }}</option>@endforeach</select></label>
<label><span>{{ __('exhibitions::ui.auto_3d2b9e064c') }}</span><textarea name="reason" rows="4" required></textarea></label>
<label><span>{{ __('exhibitions::ui.auto_8b92c37721') }}</span><textarea name="review_notes" rows="4"></textarea></label>
<label data-evidence-instructions><span>{{ __('exhibitions::ui.auto_36f8df7c33') }}</span><textarea name="request_instructions" rows="4" placeholder="State exactly what the artist must upload or clarify."></textarea></label>
<fieldset><legend>{{ __('exhibitions::ui.auto_9cb8215a44') }}</legend>@forelse($evidence as $item)<label><input type="checkbox" name="evidence_references[]" value="{{ $item->public_id }}"> <span>{{ $item->original_name }}</span></label>@empty<p>{{ __('exhibitions::ui.auto_d285b296ac') }}</p>@endforelse</fieldset>
<button class="is-primary">{{ __('exhibitions::ui.auto_3f2ddea4d4') }}</button></form>
</section>
</div>

<section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>05</span><div><h2>{{ __('exhibitions::ui.auto_674b1fd595') }}</h2><p>{{ __('exhibitions::ui.auto_4f507e6d14') }}</p></div></div><ol class="z4-exhibitions-timeline">@forelse($reviews as $review)<li><span></span><div><strong>v{{ $review->review_version }} · {{ $decisionLabels[$review->decision] ?? __('exhibitions::ui.state_'.$review->decision) }}</strong><time>{{ $review->occurred_at }}</time><p>{{ $review->reason }}</p>@if($review->request_instructions)<small><b>{{ __('exhibitions::ui.auto_abb398c082') }}</b> {{ $review->request_instructions }}</small>@endif</div></li>@empty<li><span></span><div>{{ __('exhibitions::ui.auto_22ba22d474') }}</div></li>@endforelse</ol></section>
</div>
</main>
</x-app-layout>
<script>document.addEventListener('DOMContentLoaded',()=>{const form=document.querySelector('[data-authenticity-decision]'),select=form?.querySelector('[data-authenticity-decision-select]'),field=form?.querySelector('[data-evidence-instructions]'),textarea=field?.querySelector('textarea');if(!select||!field||!textarea)return;const sync=()=>{const required=select.value==='additional_evidence_required';field.hidden=!required;textarea.required=required;if(!required)textarea.value=''};select.addEventListener('change',sync);sync()});</script>
