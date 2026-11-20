@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-wizard-14" data-exhibitions-stylesheet>@endpush
@php
    $editing = $artwork !== null;
    $v = fn (string $key, mixed $default = null) => old($key, $artwork?->{$key} ?? $default);
    $colors = old('primary_colors', $artwork ? implode(', ', json_decode($artwork->primary_colors ?: '[]', true)) : '');
    $declaration = $authenticity ?? null;
    $authenticityEvidence = $authenticityEvidence ?? collect();
    $authenticityProvenance = $authenticityProvenance ?? collect();
    $ownershipBasis = old('ownership_basis', $declaration ? ($declaration->owns_artwork ? 'owner' : ($declaration->may_display_and_sell ? 'authorized' : null)) : null);
    $evidenceRoleLabels = [
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
    $evidenceRoleBases = [];
    foreach (($rolesByBasis ?? []) as $basis => $basisRoles) {
        foreach ($basisRoles as $role) $evidenceRoleBases[$role][] = $basis;
    }
    $artworkStepFields = [
        1 => ['title', 'completion_year', 'work_type', 'collection_name', 'edition_type', 'edition_number', 'edition_total'],
        2 => ['category', 'artistic_style', 'subject', 'technique', 'material', 'surface', 'primary_colors', 'orientation', 'width', 'height', 'depth', 'dimension_unit', 'weight', 'weight_unit', 'is_framed', 'frame_material', 'condition', 'ready_to_hang'],
        3 => ['short_description', 'detailed_description', 'artwork_story', 'inspiration_source', 'care_instructions'],
        4 => ['requested_price', 'currency', 'frame_cost', 'packaging_cost', 'availability', 'preparation_days', 'shipping_notes'],
        5 => ['ownership_basis', 'inspiration_disclosure', 'digital_tools_disclosure', 'current_physical_location', 'evidence_role', 'evidence_file', 'provenance_event_date', 'provenance_event_type', 'provenance_description'],
    ];
    $initialArtworkStep = 1;
    foreach ($artworkStepFields as $step => $fieldNames) {
        if (collect($fieldNames)->contains(fn (string $field) => $errors->has($field))) {
            $initialArtworkStep = $step;
            break;
        }
    }
@endphp
<x-frontend-layout><main class="z4-exhibitions-admin z4-artist-dashboard" data-artwork-form><div class="z4-exhibitions-container z4-exhibitions-form-container">
@include('exhibitions::artist.dashboard._nav',['title'=>$editing?'Edit artwork':'Add artwork','subtitle' => __('exhibitions::ui.artist_page_form_subtitle')])
@if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert"><strong>{{ __('exhibitions::ui.auto_481871569a') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(isset($sellingAllowed) && !$sellingAllowed)<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_b7b97241da') }}</strong><p>{{ __('exhibitions::ui.auto_5a3206f18f') }}</p><a href="{{ route('exhibitions.application.status') }}">{{ __('exhibitions::ui.auto_953c1b0cde') }}</a></div>@endif
<form class="z4-exhibitions-form" method="post" enctype="multipart/form-data" action="{{ $editing ? route('exhibitions.dashboard.artworks.update',$artwork->public_id) : route('exhibitions.dashboard.artworks.store') }}" data-autosave-form data-autosave-url="{{ $editing ? route('exhibitions.dashboard.artworks.autosave.update',$artwork->public_id) : route('exhibitions.dashboard.artworks.autosave.store') }}">
@csrf
<input type="hidden" name="draft_key" value="{{ old('draft_key',$draftKey) }}">
<input type="hidden" name="lock_version" value="{{ old('lock_version',$artwork?->lock_version) }}" data-lock-version>
<div class="z4-autosave-status is-saved" data-autosave-status role="status" aria-live="polite"><span data-autosave-message>{{ $editing ? 'Draft loaded. Changes save automatically.' : 'Start entering details. Changes save automatically.' }}</span><button type="button" data-autosave-retry hidden>{{ __('exhibitions::ui.auto_1550978139') }}</button></div>
<nav class="z4-artwork-stepper" aria-label="{{ __('exhibitions::ui.auto_824751f61a') }}" data-artwork-stepper data-initial-step="{{ $initialArtworkStep }}">
@foreach([
    1 => ['Basics', 'Name, year and edition'],
    2 => ['Technical details', 'Medium, size and condition'],
    3 => ['Content', 'Description, story and care'],
    4 => ['Pricing, delivery & images', 'Price, shipping and artwork views'],
    5 => ['Ownership declaration', 'Rights, disclosures and evidence'],
] as $step => [$label, $description])
<button type="button" data-artwork-step-target="{{ $step }}" aria-controls="artwork-step-{{ $step }}"><span>{{ $step }}</span><strong>{{ $label }}</strong><small>{{ $description }}</small></button>
@endforeach
</nav>
<section id="artwork-step-1" class="z4-exhibitions-panel z4-artwork-step-panel" data-artwork-step="1"><div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_807402b244') }}</h2><p>{{ __('exhibitions::ui.auto_3d55ca248e') }}</p></div></div><div class="z4-exhibitions-fields">
<label>{{ __('exhibitions::ui.auto_b4f4fa1814') }} <b>*</b><input name="title" maxlength="180" value="{{ $v('title') }}" required>@error('title')<small>{{ $message }}</small>@enderror</label>
<label>{{ __('exhibitions::ui.auto_f748dac898') }} <b>*</b><input type="number" name="completion_year" min="1000" max="{{ date('Y') }}" value="{{ $v('completion_year') }}" required>@error('completion_year')<small>{{ $message }}</small>@enderror</label>
<label>{{ __('exhibitions::ui.auto_eb3cb8225e') }} <b>*</b><input name="work_type" maxlength="80" value="{{ $v('work_type') }}" placeholder="{{ __('exhibitions::ui.auto_320078e55e') }}"></label>
<label>{{ __('exhibitions::ui.auto_9216426ff1') }}<input name="collection_name" maxlength="160" value="{{ $v('collection_name') }}"></label>
<label>{{ __('exhibitions::ui.auto_226c03c443') }} <b>*</b><select name="edition_type" data-edition-type required><option value="original" @selected($v('edition_type','original')==='original')>{{ __('exhibitions::ui.auto_0a52da7a03') }}</option><option value="limited" @selected(in_array($v('edition_type'),['limited','limited_edition'],true))>{{ __('exhibitions::ui.auto_4411048ef8') }}</option></select></label>
<div class="z4-exhibitions-conditional" data-limited-fields @if(!in_array($v('edition_type', 'original'), ['limited', 'limited_edition'], true)) hidden @endif><label>{{ __('exhibitions::ui.auto_ac16ed173e') }} <b>*</b><input type="number" name="edition_number" min="1" value="{{ $v('edition_number') }}">@error('edition_number')<small>{{ $message }}</small>@enderror</label><label>{{ __('exhibitions::ui.auto_13b8e2ca2a') }} <b>*</b><input type="number" name="edition_total" min="1" value="{{ $v('edition_total') }}">@error('edition_total')<small>{{ $message }}</small>@enderror</label></div>
</div></section>
<section id="artwork-step-2" class="z4-exhibitions-panel z4-artwork-step-panel" data-artwork-step="2" hidden><div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_52eb434851') }}</h2><p>{{ __('exhibitions::ui.auto_c7bfb354a5') }}</p></div></div><div class="z4-exhibitions-fields">
<label>{{ __('exhibitions::ui.auto_3adbdb3ac0') }} <b>*</b><input name="category" maxlength="100" value="{{ $v('category') }}"></label><label>{{ __('exhibitions::ui.auto_9b7d966268') }}<input name="artistic_style" maxlength="100" value="{{ $v('artistic_style') }}"></label>
<label>{{ __('exhibitions::ui.auto_c7892ebbb1') }}<input name="subject" maxlength="160" value="{{ $v('subject') }}"></label><label>{{ __('exhibitions::ui.auto_22a70073ad') }} <b>*</b><input name="technique" maxlength="160" value="{{ $v('technique') }}"></label>
<label>{{ __('exhibitions::ui.auto_d92a8333dd') }} <b>*</b><input name="material" maxlength="160" value="{{ $v('material') }}"></label><label>{{ __('exhibitions::ui.auto_aa0d528ba1') }}<input name="surface" maxlength="120" value="{{ $v('surface') }}"></label>
<label>{{ __('exhibitions::ui.auto_07bc591ec1') }}<em>{{ __('exhibitions::ui.auto_1635f217b7') }}</em><input name="primary_colors" maxlength="500" value="{{ $colors }}"></label><label>{{ __('exhibitions::ui.auto_abbd64f40c') }} <b>*</b><select name="orientation"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['portrait','landscape','square','panoramic','other'] as $option)<option value="{{ $option }}" @selected($v('orientation')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
<label>{{ __('exhibitions::ui.auto_32954654ac') }} <b>*</b><input type="number" step="0.01" min="0.01" name="width" value="{{ $v('width') }}"></label><label>{{ __('exhibitions::ui.auto_eec6c4bdbd') }} <b>*</b><input type="number" step="0.01" min="0.01" name="height" value="{{ $v('height') }}"></label>
<label>{{ __('exhibitions::ui.auto_675056ad14') }}<input type="number" step="0.01" min="0" name="depth" value="{{ $v('depth') }}"></label><label>{{ __('exhibitions::ui.auto_cc13d15630') }} <b>*</b><select name="dimension_unit"><option value="cm" @selected($v('dimension_unit','cm')==='cm')>cm</option><option value="in" @selected($v('dimension_unit')==='in')>in</option></select></label>
<label>{{ __('exhibitions::ui.auto_8c489d0946') }}<input type="number" step="0.001" min="0" name="weight" value="{{ $v('weight') }}"></label><label>{{ __('exhibitions::ui.auto_f489118ea9') }}<select name="weight_unit"><option value="kg" @selected($v('weight_unit','kg')==='kg')>kg</option><option value="lb" @selected($v('weight_unit')==='lb')>lb</option></select></label>
<label class="z4-exhibitions-consent"><input type="hidden" name="is_framed" value="0"><input type="checkbox" name="is_framed" value="1" data-framed @checked((bool)$v('is_framed'))> {{ __('exhibitions::ui.auto_c9d1a9c251') }}</label>
<label data-frame-field>{{ __('exhibitions::ui.auto_f2c6ef3aee') }}<input name="frame_material" maxlength="120" value="{{ $v('frame_material') }}">@error('frame_material')<small>{{ $message }}</small>@enderror</label>
<label>{{ __('exhibitions::ui.auto_9e2941b3c8') }} <b>*</b><select name="condition"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['new','excellent','good','fair','restored'] as $option)<option value="{{ $option }}" @selected($v('condition')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
<label class="z4-exhibitions-consent"><input type="hidden" name="ready_to_hang" value="0"><input type="checkbox" name="ready_to_hang" value="1" @checked((bool)$v('ready_to_hang'))> {{ __('exhibitions::ui.auto_0a31457556') }}</label>
</div></section>
<section id="artwork-step-3" class="z4-exhibitions-panel z4-artwork-step-panel" data-artwork-step="3" hidden><div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_f15c1cae78') }}</h2><p>{{ __('exhibitions::ui.auto_3bff17b91b') }}</p></div></div><div class="z4-exhibitions-fields is-single">
<label>{{ __('exhibitions::ui.auto_c1069a4808') }} <b>*</b><textarea name="short_description" rows="3" maxlength="500">{{ $v('short_description') }}</textarea></label><label>{{ __('exhibitions::ui.auto_829253ef1d') }} <b>*</b><textarea name="detailed_description" rows="6" maxlength="10000">{{ $v('detailed_description') }}</textarea></label>
<label>Artwork story<textarea name="artwork_story" rows="5" maxlength="10000">{{ $v('artwork_story') }}</textarea></label><label>Source of inspiration<textarea name="inspiration_source" rows="4" maxlength="5000">{{ $v('inspiration_source') }}</textarea></label>
<label>Care instructions<textarea name="care_instructions" rows="3" maxlength="3000">{{ $v('care_instructions') }}</textarea></label>
</div></section>
<section id="artwork-step-4" class="z4-exhibitions-panel z4-artwork-step-panel" data-artwork-step="4" hidden><div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_e45366851d') }}</h2><p>{{ __('exhibitions::ui.auto_12e2ad92e5') }}</p></div></div><div class="z4-exhibitions-fields">
<label>{{ __('exhibitions::ui.auto_2a5ab1af48') }} <b>*</b><input type="number" step="0.01" min="0.01" name="requested_price" value="{{ $v('requested_price') }}"></label><label>{{ __('exhibitions::ui.auto_386c339d37') }} <b>*</b><select name="currency"><option value="JOD" @selected($v('currency','JOD')==='JOD')>JOD</option></select></label>
<label data-frame-field>{{ __('exhibitions::ui.auto_ec83eb0ab2') }}<input type="number" step="0.01" min="0" name="frame_cost" value="{{ $v('frame_cost') }}"></label><label>{{ __('exhibitions::ui.auto_45b4d795bf') }}<input type="number" step="0.01" min="0" name="packaging_cost" value="{{ $v('packaging_cost') }}"></label>
<label>{{ __('exhibitions::ui.auto_faeaec9eda') }} <b>*</b><select name="availability"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['available'=>'Available','made_to_order'=>'Made to order','temporarily_unavailable'=>'Temporarily unavailable'] as $key=>$label)<option value="{{ $key }}" @selected($v('availability')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>{{ __('exhibitions::ui.auto_d504ae0e01') }} <b>*</b><input type="number" min="0" max="365" name="preparation_days" value="{{ $v('preparation_days') }}"></label>
<label class="z4-exhibitions-field-span">Shipping notes<textarea name="shipping_notes" rows="4" maxlength="3000">{{ $v('shipping_notes') }}</textarea></label>
</div>
<div class="z4-artwork-workflow-card">
<div><strong>{{ __('exhibitions::ui.auto_3888c23456') }}</strong><p>{{ __('exhibitions::ui.auto_dc9ef4f563') }}</p>@if($editing)<span class="z4-artwork-media-summary">{{ $mediaCount ?? 0 }} image(s) · {{ ($hasPrimaryImage ?? false) ? 'Main image selected' : 'Main image required' }}</span>@endif</div>
@if($editing)<a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.artworks.media.index',$artwork->public_id) }}">{{ __('exhibitions::ui.auto_9678dc355d') }}</a>@else<button class="z4-exhibitions-button" type="submit" name="intent" value="save_and_media" formnovalidate>{{ __('exhibitions::ui.auto_abd18a9e71') }}</button>@endif
</div>
</section>
<section id="artwork-step-5" class="z4-exhibitions-panel z4-artwork-step-panel z4-artwork-ownership-step" data-artwork-step="5" hidden>
    <div class="z4-exhibitions-section-title"><span>05</span><div><h2>{{ __('exhibitions::ui.auto_16c76e9ad5') }}</h2><p>{{ __('exhibitions::ui.auto_4999505329') }}</p></div></div>
    @if($editing)<div class="z4-artwork-ownership-status"><span class="z4-exhibitions-pill">{{ ucwords(str_replace('_',' ',$authenticityState ?: 'not submitted')) }}</span><span>{{ $authenticityEvidenceCount ?? 0 }} private evidence file(s)</span></div>@endif

    <div class="z4-artwork-ownership-grid">
        <div>
            <h3>{{ __('exhibitions::ui.auto_b9c14d52c4') }}</h3>
            <div class="z4-authenticity-choice-grid" role="radiogroup" aria-label="{{ __('exhibitions::ui.auto_e9177f50c7') }}">
                <label class="z4-authenticity-choice" data-ownership-card>
                    <input type="radio" name="ownership_basis" value="owner" required @checked($ownershipBasis === 'owner')>
                    <span><strong>{{ __('exhibitions::ui.auto_c84ba27acb') }}</strong><small>{{ __('exhibitions::ui.auto_04fb6a4749') }}</small></span>
                </label>
                <label class="z4-authenticity-choice" data-ownership-card>
                    <input type="radio" name="ownership_basis" value="authorized" required @checked($ownershipBasis === 'authorized')>
                    <span><strong>{{ __('exhibitions::ui.auto_e684bc7ead') }}</strong><small>{{ __('exhibitions::ui.auto_a42793111a') }}</small></span>
                </label>
            </div>
            @error('ownership_basis')<small class="z4-field-error">{{ $message }}</small>@enderror

            <div class="z4-authenticity-disclosures">
                <label><span>{{ __('exhibitions::ui.auto_f9c9cb01ab') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_83d2986dab') }}</small><textarea name="inspiration_disclosure" rows="4" maxlength="6000" required>{{ old('inspiration_disclosure',$declaration?->inspiration_disclosure) }}</textarea>@error('inspiration_disclosure')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
                <label><span>{{ __('exhibitions::ui.auto_f42f52492f') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_5c394b33d0') }}</small><textarea name="digital_tools_disclosure" rows="4" maxlength="6000" required>{{ old('digital_tools_disclosure',$declaration?->digital_tools_disclosure) }}</textarea>@error('digital_tools_disclosure')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
                <label><span>{{ __('exhibitions::ui.auto_a7bf9bfc7f') }} <b>*</b></span><small>{{ __('exhibitions::ui.auto_22ff8882cf') }}</small><input name="current_physical_location" maxlength="500" value="{{ old('current_physical_location',$declaration?->current_physical_location) }}" required>@error('current_physical_location')<em class="z4-field-error">{{ $message }}</em>@enderror</label>
            </div>
        </div>

        <aside class="z4-artwork-evidence-panel">
            <h3>{{ __('exhibitions::ui.auto_80d9691c3a') }} <small>{{ __('exhibitions::ui.auto_ebb061953c') }}</small></h3>
            <p>{{ __('exhibitions::ui.auto_b22a850af9') }}</p>
            <label><span>{{ __('exhibitions::ui.auto_5c1b5953e5') }}</span><select name="evidence_role" data-evidence-role>
                <option value="" data-evidence-bases="">{{ __('exhibitions::ui.auto_48fceb094b') }}</option>
                @foreach($evidenceRoleLabels as $role => $label)
                    @if(isset($evidenceRoleBases[$role]))<option value="{{ $role }}" data-evidence-bases="{{ implode(' ', $evidenceRoleBases[$role]) }}" @selected(old('evidence_role') === $role)>{{ $label }}</option>@endif
                @endforeach
            </select></label>
            @error('evidence_role')<small class="z4-field-error">{{ $message }}</small>@enderror
            <label class="z4-exhibitions-upload z4-authenticity-upload"><strong>{{ __('exhibitions::ui.auto_4b8410c935') }}</strong><small>{{ __('exhibitions::ui.auto_a23875a83c') }}</small><input type="file" name="evidence_file" accept="application/pdf,image/jpeg,image/png,image/webp" data-evidence-file><span data-evidence-file-name>{{ __('exhibitions::ui.auto_f787618e51') }}</span></label>
            @error('evidence_file')<small class="z4-field-error">{{ $message }}</small>@enderror
            @if($authenticityEvidence->isNotEmpty())
                <ul class="z4-exhibitions-file-list z4-artwork-inline-evidence-list">
                    @foreach($authenticityEvidence as $item)<li><a href="{{ URL::temporarySignedRoute('exhibitions.dashboard.artworks.authenticity.evidence.download',now()->addMinutes(5),['artwork'=>$artwork->public_id,'evidence'=>$item->public_id]) }}">{{ $item->original_name }}</a><small>{{ $evidenceRoleLabels[$item->role] ?? __('exhibitions::ui.state_'.$item->role) }}</small></li>@endforeach
                </ul>
            @endif
        </aside>
    </div>

    <details class="z4-artwork-provenance-entry" @if(collect(['provenance_event_date','provenance_event_type','provenance_description'])->contains(fn ($field) => old($field) !== null)) open @endif>
        <summary><span><strong>{{ __('exhibitions::ui.auto_87d662502b') }}</strong><small>{{ __('exhibitions::ui.auto_da3a121207') }}</small></span><span>{{ $authenticityProvenance->count() }} existing record(s)</span></summary>
        <div class="z4-artwork-provenance-fields">
            <label>{{ __('exhibitions::ui.auto_44749712db') }}<input type="date" name="provenance_event_date" value="{{ old('provenance_event_date') }}"></label>
            <label>{{ __('exhibitions::ui.auto_5e85ed076f') }}<input name="provenance_event_type" maxlength="64" value="{{ old('provenance_event_type') }}" placeholder="{{ __('exhibitions::ui.auto_c900104dc6') }}"></label>
            <label>{{ __('exhibitions::ui.auto_29040e98cd') }}<input name="provenance_owner_name" maxlength="255" value="{{ old('provenance_owner_name') }}"></label>
            <label>{{ __('exhibitions::ui.auto_ce5bf55137') }}<input name="provenance_location" maxlength="500" value="{{ old('provenance_location') }}"></label>
            <label class="is-wide">Description<textarea name="provenance_description" rows="3" maxlength="6000">{{ old('provenance_description') }}</textarea></label>
            <label class="is-wide">{{ __('exhibitions::ui.auto_d4ab17e24e') }}<input name="provenance_document_reference" maxlength="500" value="{{ old('provenance_document_reference') }}"></label>
        </div>
        @foreach(['provenance_event_date','provenance_event_type','provenance_description'] as $field)@error($field)<small class="z4-field-error">{{ $message }}</small>@enderror @endforeach
    </details>

    <div class="z4-artwork-ownership-notice"><strong>{{ __('exhibitions::ui.auto_2dbe5c85de') }}</strong><p>{{ __('exhibitions::ui.auto_a48160cd28') }}</p></div>
</section>
<div class="z4-exhibitions-actions z4-artwork-wizard-actions"><button class="is-secondary" type="submit" name="intent" value="save" formnovalidate>{{ __('exhibitions::ui.auto_b71462d5c7') }}</button><div><button class="is-secondary" type="button" data-artwork-step-back hidden>{{ __('exhibitions::ui.auto_0557fa923d') }}</button><button class="is-primary" type="button" data-artwork-step-next>{{ __('exhibitions::ui.auto_10ac3d0425') }}</button><button class="is-primary" type="submit" name="intent" value="submit" data-artwork-submit hidden>{{ __('exhibitions::ui.auto_447541e661') }}</button></div></div>
</form></div></main></x-frontend-layout>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-autosave-form]');
    const stepper = form?.querySelector('[data-artwork-stepper]');
    const panels = form?.querySelectorAll('[data-artwork-step]');
    const targets = stepper?.querySelectorAll('[data-artwork-step-target]');
    const back = form?.querySelector('[data-artwork-step-back]');
    const next = form?.querySelector('[data-artwork-step-next]');
    const submit = form?.querySelector('[data-artwork-submit]');
    if (!form || !stepper || !panels?.length) return;

    const required = {
        1: ['title', 'completion_year', 'work_type', 'edition_type'],
        2: ['category', 'technique', 'material', 'orientation', 'width', 'height', 'dimension_unit', 'condition'],
        3: ['short_description', 'detailed_description'],
        4: ['requested_price', 'currency', 'availability', 'preparation_days'],
    };
    const total = panels.length;
    const hashStep = Number(location.hash.match(/^#artwork-step-(\d+)$/)?.[1]);
    const initial = Number(stepper.dataset.initialStep) || 1;
    let current = hashStep >= 1 && hashStep <= total ? hashStep : initial;

    const validate = step => {
        const names = [...(required[step] || [])];
        if (step === 1 && form.elements.edition_type?.value === 'limited') names.push('edition_number', 'edition_total');
        if (step === 2 && form.elements.is_framed?.checked) names.push('frame_material');
        for (const name of names) {
            const field = form.elements[name];
            if (!field) continue;
            field.setCustomValidity('');
            if (String(field.value ?? '').trim() === '') field.setCustomValidity('Please complete this required field.');
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }
        return true;
    };
    const show = (step, push = true, scroll = true) => {
        current = Math.max(1, Math.min(total, Number(step) || 1));
        form.dataset.currentStep = String(current);
        panels.forEach(panel => { panel.hidden = Number(panel.dataset.artworkStep) !== current; });
        targets.forEach(target => {
            const active = Number(target.dataset.artworkStepTarget) === current;
            target.classList.toggle('is-current', active);
            target.setAttribute('aria-current', active ? 'step' : 'false');
        });
        back.hidden = current === 1;
        next.hidden = current === total;
        submit.hidden = current !== total;
        if (push) history.replaceState({}, '', location.pathname + location.search + '#artwork-step-' + current);
        if (scroll) stepper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const go = step => {
        const target = Math.max(1, Math.min(total, Number(step) || 1));
        if (target > current) {
            for (let candidate = current; candidate < target; candidate++) {
                if (!validate(candidate)) return show(candidate);
            }
        }
        show(target);
    };

    targets.forEach(target => target.addEventListener('click', () => go(target.dataset.artworkStepTarget)));
    back.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => go(current + 1));
    form.addEventListener('input', event => event.target?.setCustomValidity?.(''));
    show(current, false, false);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-autosave-form]');
    if (!form) return;

    const edition = form.querySelector('[data-edition-type]');
    const limited = form.querySelector('[data-limited-fields]');
    const framed = form.querySelector('[data-framed]');
    const frameFields = form.querySelectorAll('[data-frame-field]');
    const relationshipRadios = [...form.querySelectorAll('input[name="ownership_basis"]')];
    const evidenceRole = form.querySelector('[data-evidence-role]');
    const evidenceFile = form.querySelector('[data-evidence-file]');
    const evidenceFileName = form.querySelector('[data-evidence-file-name]');
    const status = form.querySelector('[data-autosave-status]');
    const message = form.querySelector('[data-autosave-message]');
    const retry = form.querySelector('[data-autosave-retry]');
    const lock = form.querySelector('[data-lock-version]');
    const ownershipFields = [
        'ownership_basis', 'inspiration_disclosure', 'digital_tools_disclosure', 'current_physical_location',
        'evidence_role', 'evidence_file', 'provenance_event_date', 'provenance_event_type',
        'provenance_owner_name', 'provenance_location', 'provenance_description', 'provenance_document_reference',
    ];
    let timer;
    let busy = false;
    let dirty = false;
    let submitting = false;

    const syncConditionalFields = () => {
        if (limited) limited.hidden = edition?.value !== 'limited';
        frameFields.forEach(field => { field.hidden = !framed?.checked; });
    };
    const syncEvidenceRoles = () => {
        const basis = relationshipRadios.find(radio => radio.checked)?.value || '';
        form.querySelectorAll('[data-ownership-card]').forEach(card => card.classList.toggle('is-selected', card.querySelector('input')?.checked));
        [...(evidenceRole?.options || [])].forEach(option => {
            const bases = option.dataset.evidenceBases?.split(' ').filter(Boolean) || [];
            const enabled = option.value === '' || (basis !== '' && bases.includes(basis));
            option.disabled = !enabled;
            option.hidden = !enabled;
        });
        if (evidenceRole && (!basis || evidenceRole.selectedOptions[0]?.disabled)) evidenceRole.value = '';
    };
    const state = (kind, text, canRetry = false) => {
        status.className = 'z4-autosave-status is-' + kind;
        message.textContent = text;
        retry.hidden = !canRetry;
    };
    const save = async () => {
        if (submitting || busy || !dirty) return;
        if (!navigator.onLine) return state('offline', 'Offline. Your unsaved changes remain in this form.', true);
        busy = true;
        dirty = false;
        state('saving', 'Saving private draft…');
        const data = new FormData(form);
        data.set('intent', 'save');
        ownershipFields.forEach(field => data.delete(field));
        try {
            const response = await fetch(form.dataset.autosaveUrl, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await response.json().catch(() => ({}));
            if (response.status === 409) {
                dirty = true;
                return state('conflict', body.message || 'This draft changed elsewhere. Refresh before retrying.');
            }
            if (!response.ok) {
                dirty = true;
                return state('error', body.message || 'Draft could not be saved. Review fields or retry.', true);
            }
            form.dataset.autosaveUrl = body.autosave_url;
            lock.value = body.lock_version;
            history.replaceState({}, '', body.edit_url + '#artwork-step-' + (form.dataset.currentStep || '1'));
            state('saved', 'Private draft saved just now.');
        } catch (error) {
            dirty = true;
            state('offline', 'Connection interrupted. Changes remain here; retry when online.', true);
        } finally {
            busy = false;
            if (dirty && navigator.onLine) timer = setTimeout(save, 2500);
        }
    };
    const changed = event => {
        clearTimeout(timer);
        if (event.target.closest('[data-artwork-step="5"]')) {
            state('pending', 'Ownership changes will be saved with the draft or submitted for review.');
            return;
        }
        dirty = true;
        state('pending', 'Unsaved changes…');
        timer = setTimeout(save, event.type === 'change' ? 500 : 1200);
    };

    edition?.addEventListener('change', syncConditionalFields);
    framed?.addEventListener('change', syncConditionalFields);
    relationshipRadios.forEach(radio => radio.addEventListener('change', syncEvidenceRoles));
    evidenceFile?.addEventListener('change', () => { evidenceFileName.textContent = evidenceFile.files?.[0]?.name || 'No file selected'; });
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', () => { submitting = true; clearTimeout(timer); });
    retry.addEventListener('click', () => { dirty = true; save(); });
    window.addEventListener('offline', () => state('offline', 'Offline. Your unsaved changes remain in this form.', true));
    window.addEventListener('online', () => { state('pending', 'Back online. Retrying draft save…'); dirty = true; save(); });
    syncConditionalFields();
    syncEvidenceRoles();
});
</script>
