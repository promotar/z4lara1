@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-auth-10" data-exhibitions-stylesheet>
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-authenticity-workspace>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',['title' => __('exhibitions::ui.artist_page_authenticity_title'),'subtitle' => __('exhibitions::ui.artist_page_authenticity_subtitle')])

@if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
<div class="z4-exhibitions-alert is-error" role="alert">
    <strong>{{ __('exhibitions::ui.auto_9215a1e6c1') }}</strong>
    <p>{{ __('exhibitions::ui.auto_79e1d44d71') }}</p>
</div>
@endif

<div class="z4-authenticity-overview">
    <div class="z4-authenticity-overview-actions">
        <a class="z4-exhibitions-button is-secondary" href="{{ route('exhibitions.dashboard.artworks.edit',$artwork->public_id) }}#artwork-step-5">{{ __('exhibitions::ui.auto_4deffdbd59') }}</a>
        <span class="z4-exhibitions-pill">{{ ucwords(str_replace('_',' ',$declaration->state ?? 'not submitted')) }}</span>
    </div>
</div>

<ol class="z4-authenticity-guide" aria-label="{{ __('exhibitions::ui.auto_f6c5caf38e') }}">
    <li><b>1</b><span><strong>{{ __('exhibitions::ui.auto_52a44c80c2') }}</strong><small>{{ __('exhibitions::ui.auto_258d9f08ec') }}</small></span></li>
    <li><b>2</b><span><strong>{{ __('exhibitions::ui.auto_e87772340b') }}</strong><small>{{ __('exhibitions::ui.auto_1d0d9035d1') }}</small></span></li>
</ol>

@php
    $ownershipBasis = old('ownership_basis', $declaration ? ($declaration->owns_artwork ? 'owner' : ($declaration->may_display_and_sell ? 'authorized' : null)) : null);
    $roleLabels = [
        'creation_stage' => 'Creation stages',
        'signature' => 'Artist signature',
        'back' => 'Back of artwork',
        'purchase_proof' => 'Purchase receipt or proof of acquisition',
        'sale_authorization' => 'Authorization to resell',
        'ownership_transfer' => 'Ownership transfer or consignment agreement',
        'prior_ownership' => 'Prior ownership or provenance record',
        'authenticity_certificate' => 'Certificate of authenticity',
        'other_requested' => 'Other requested evidence',
    ];
    $roleBases = [];
    foreach ($rolesByBasis as $basis => $basisRoles) {
        foreach ($basisRoles as $role) {
            $roleBases[$role][] = $basis;
        }
    }
    $availableRoles = collect(array_keys($roleLabels))->filter(fn ($role) => isset($roleBases[$role]))->values();
    $hasProvenanceInput = collect(['provenance_event_date','provenance_event_type','provenance_owner_name','provenance_location','provenance_description','provenance_document_reference'])->contains(fn ($field) => old($field) !== null);
@endphp

<form method="post" enctype="multipart/form-data" action="{{ route('exhibitions.dashboard.artworks.authenticity.save',$artwork->public_id) }}" class="z4-exhibitions-form z4-authenticity-save-all" data-authenticity-save-all>
@csrf
<div class="z4-exhibitions-columns z4-authenticity-grid z4-authenticity-primary-grid">
<section class="z4-exhibitions-panel z4-authenticity-declaration-panel">
    <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_b9c14d52c4') }}</h2><p>{{ __('exhibitions::ui.auto_b50212ec99') }}</p></div><em>{{ __('exhibitions::ui.auto_b651efdb98') }}</em></div>
    <div class="z4-authenticity-choice-grid" role="radiogroup" aria-label="{{ __('exhibitions::ui.auto_e9177f50c7') }}">
        <label class="z4-authenticity-choice" data-ownership-card>
            <input type="radio" name="ownership_basis" value="owner" required @checked($ownershipBasis==='owner')>
            <span><strong>{{ __('exhibitions::ui.auto_c84ba27acb') }}</strong><small>{{ __('exhibitions::ui.auto_87c5737e7d') }}</small></span>
        </label>
        <label class="z4-authenticity-choice" data-ownership-card>
            <input type="radio" name="ownership_basis" value="authorized" required @checked($ownershipBasis==='authorized')>
            <span><strong>{{ __('exhibitions::ui.auto_e684bc7ead') }}</strong><small>{{ __('exhibitions::ui.auto_728bea6f16') }}</small></span>
        </label>
    </div>
    @error('ownership_basis')<small class="z4-field-error">{{ $message }}</small>@enderror

    <div class="z4-authenticity-disclosures">
        <label><span>{{ __('exhibitions::ui.auto_f9c9cb01ab') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_7153dcecba') }}</small><textarea name="inspiration_disclosure" rows="4" required placeholder="Example: Inspired by Amman’s architecture; no elements were copied or adapted. Or: Adapted with permission from [work] by [artist].">{{ old('inspiration_disclosure',$declaration?->inspiration_disclosure) }}</textarea>@error('inspiration_disclosure')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
        <label><span>{{ __('exhibitions::ui.auto_f42f52492f') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_7d7c681049') }}</small><textarea name="digital_tools_disclosure" rows="4" required placeholder="List the tools and explain how they were used.">{{ old('digital_tools_disclosure',$declaration?->digital_tools_disclosure) }}</textarea>@error('digital_tools_disclosure')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
        <label><span>{{ __('exhibitions::ui.auto_a7bf9bfc7f') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_22ff8882cf') }}</small><input name="current_physical_location" value="{{ old('current_physical_location',$declaration?->current_physical_location) }}" required placeholder="{{ __('exhibitions::ui.auto_c553165032') }}">@error('current_physical_location')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
    </div>
</section>

<section class="z4-exhibitions-panel z4-authenticity-evidence-panel">
    <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_80d9691c3a') }}</h2></div><em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></div>
    <div class="z4-authenticity-context" data-evidence-context aria-live="polite">
        <strong>{{ __('exhibitions::ui.auto_48fceb094b') }}</strong>
        <span>{{ __('exhibitions::ui.auto_4c2d128151') }}</span>
    </div>
    <label><span>{{ __('exhibitions::ui.auto_5c1b5953e5') }}</span>
        <select name="evidence_role" data-evidence-role>
            <option value="" data-evidence-bases="" disabled @selected(old('evidence_role')===null)>{{ __('exhibitions::ui.auto_48fceb094b') }}</option>
            @foreach($availableRoles as $role)
                <option value="{{ $role }}" data-evidence-bases="{{ implode(' ', $roleBases[$role] ?? []) }}" @selected(old('evidence_role')===$role)>{{ $roleLabels[$role] ?? __('exhibitions::ui.state_'.$role) }}</option>
            @endforeach
        </select>
    </label>
    @error('evidence_role')<small class="z4-field-error">{{ $message }}</small>@enderror
    <label class="z4-exhibitions-upload z4-authenticity-upload">
        <strong>{{ __('exhibitions::ui.auto_4b8410c935') }}</strong>
        <small>{{ __('exhibitions::ui.auto_1b5051a32b') }}</small>
        <input type="file" name="evidence_file" accept="application/pdf,image/jpeg,image/png,image/webp" data-evidence-file>
        <span data-evidence-file-name>{{ __('exhibitions::ui.auto_f787618e51') }}</span>
    </label>
    @error('evidence_file')<small class="z4-field-error">{{ $message }}</small>@enderror

    <div class="z4-authenticity-files-heading"><strong>{{ __('exhibitions::ui.auto_d1fde6a22e') }}</strong><span>{{ $evidence->count() }} file(s)</span></div>
    <ul class="z4-exhibitions-file-list z4-authenticity-evidence-list">
        @forelse($evidence as $item)
        <li>
            <span class="z4-authenticity-file-icon">{{ strtolower(pathinfo($item->original_name, PATHINFO_EXTENSION)) ?: 'file' }}</span>
            <span class="z4-authenticity-file-copy"><a href="{{ URL::temporarySignedRoute('exhibitions.dashboard.artworks.authenticity.evidence.download',now()->addMinutes(5),['artwork'=>$artwork->public_id,'evidence'=>$item->public_id]) }}">{{ $item->original_name }}</a><small>{{ $roleLabels[$item->role] ?? __('exhibitions::ui.state_'.$item->role) }} &middot; {{ $item->uploaded_at }}</small></span>
        </li>
        @empty
        <li class="z4-authenticity-empty"><strong>{{ __('exhibitions::ui.auto_90ed517f60') }}</strong><small>{{ __('exhibitions::ui.auto_c35ce35d01') }}</small></li>
        @endforelse
    </ul>
</section>
</div>

<details class="z4-exhibitions-panel z4-authenticity-provenance" @if($hasProvenanceInput) open @endif>
    <summary>
        <span class="z4-exhibitions-section-title"><span>03</span><span><strong>{{ __('exhibitions::ui.auto_4fa514366a') }}</strong><small>{{ __('exhibitions::ui.auto_6642f5c1b7') }}</small></span></span>
        <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em>
    </summary>
    <div class="z4-authenticity-provenance-body">
        <div class="z4-exhibitions-fields"><label><span>{{ __('exhibitions::ui.auto_44749712db') }}</span><input type="date" name="provenance_event_date" value="{{ old('provenance_event_date') }}"></label><label><span>{{ __('exhibitions::ui.auto_5e85ed076f') }}</span><input name="provenance_event_type" value="{{ old('provenance_event_type') }}" placeholder="{{ __('exhibitions::ui.auto_c900104dc6') }}"></label></div>
        <div class="z4-exhibitions-fields"><label><span>{{ __('exhibitions::ui.auto_29040e98cd') }}</span><input name="provenance_owner_name" value="{{ old('provenance_owner_name') }}"></label><label><span>{{ __('exhibitions::ui.auto_ce5bf55137') }}</span><input name="provenance_location" value="{{ old('provenance_location') }}"></label></div>
        <label><span>{{ __('exhibitions::ui.auto_b5a7adde1a') }}</span><textarea name="provenance_description" rows="4" placeholder="Describe what happened and why this event is relevant.">{{ old('provenance_description') }}</textarea></label>
        <label><span>{{ __('exhibitions::ui.auto_d4ab17e24e') }}</span><input name="provenance_document_reference" value="{{ old('provenance_document_reference') }}" placeholder="{{ __('exhibitions::ui.auto_5a797e0fe4') }}"></label>
        @foreach(['provenance_event_date','provenance_event_type','provenance_description'] as $field)@error($field)<small class="z4-field-error">{{ $message }}</small>@enderror @endforeach
    </div>
</details>

<section class="z4-exhibitions-panel z4-authenticity-history">
    <div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_b859e22b2a') }}</h2><p>{{ __('exhibitions::ui.auto_3a201f6c86') }}</p></div></div>
    <ol class="z4-exhibitions-timeline">
        @foreach($provenance as $event)<li><span></span><div><strong>{{ $event->event_date }} &middot; {{ ucwords($event->event_type) }}</strong><p>{{ $event->description }}</p>@if($event->owner_name || $event->location)<small>{{ $event->owner_name }} {{ $event->location }}</small>@endif</div></li>@endforeach
        @foreach($reviews as $review)<li class="{{ $review->request_instructions ? 'is-action-required' : '' }}"><span></span><div><strong>{{ __('exhibitions::ui.state_'.$review->decision) }}</strong><time>{{ $review->occurred_at }}</time><p>{{ $review->reason }}</p>@if($review->request_instructions)<small><b>{{ __('exhibitions::ui.auto_6b721da642') }}</b> {{ $review->request_instructions }}</small>@endif</div></li>@endforeach
        @if($provenance->isEmpty() && $reviews->isEmpty())<li><span></span><div><strong>{{ __('exhibitions::ui.auto_6f70eff9e6') }}</strong><p>{{ __('exhibitions::ui.auto_c8ec314758') }}</p></div></li>@endif
    </ol>
</section>

<div class="z4-authenticity-save-bar">
    <span><strong>{{ __('exhibitions::ui.auto_e507f52178') }}</strong><small>{{ __('exhibitions::ui.auto_470104b094') }}</small></span>
    <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_c45ec8c74c') }}</button>
</div>
</form>
</div>
</main>
</x-frontend-layout>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const form=document.querySelector('[data-authenticity-save-all]');
    if(!form)return;
    const radios=[...form.querySelectorAll('input[name="ownership_basis"]')];
    const select=form.querySelector('[data-evidence-role]');
    const context=form.querySelector('[data-evidence-context]');
    const file=form.querySelector('[data-evidence-file]');
    const fileName=form.querySelector('[data-evidence-file-name]');
    const copy={
        owner:['Evidence for an owner','Creation stages, signature, back view, or an authenticity certificate are most useful.'],
        authorized:['Evidence for an authorized reseller','Provide authorization to resell, acquisition or transfer records, provenance, or an authenticity certificate.']
    };
    const sync=()=>{
        const basis=radios.find(radio=>radio.checked)?.value||'';
        form.querySelectorAll('[data-ownership-card]').forEach(card=>card.classList.toggle('is-selected',card.querySelector('input')?.checked));
        [...select.options].forEach(option=>{
            const enabled=basis!==''&&option.dataset.evidenceBases.split(' ').includes(basis);
            option.disabled=!enabled;
            option.hidden=!enabled;
        });
        select.disabled=basis==='';
        if(basis&&select.selectedOptions[0]?.disabled){
            const first=[...select.options].find(option=>!option.disabled);
            if(first)select.value=first.value;
        }
        const message=copy[basis]||['Choose your relationship first','We will then show only the evidence that applies to you.'];
        context.querySelector('strong').textContent=message[0];
        context.querySelector('span').textContent=message[1];
        context.classList.toggle('is-ready',basis!=='');
    };
    radios.forEach(radio=>radio.addEventListener('change',sync));
    file?.addEventListener('change',()=>{fileName.textContent=file.files?.[0]?.name||'No file selected'});
    sync();
});
</script>
