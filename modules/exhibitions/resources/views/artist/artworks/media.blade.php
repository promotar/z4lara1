@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-media-2" data-exhibitions-stylesheet>
@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artwork-media>
<div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',['title' => __('exhibitions::ui.artist_page_media_title'),'subtitle' => __('exhibitions::ui.artist_page_media_subtitle')])
@if(session('status'))
<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
@endif
@if($errors->any())
<div class="z4-exhibitions-alert is-error" role="alert"><strong>{{ __('exhibitions::ui.auto_812415254f') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="z4-exhibitions-actions z4-exhibitions-actions-top"><a class="z4-exhibitions-button is-secondary" href="{{ route('exhibitions.dashboard.artworks.edit',$artwork->public_id) }}#artwork-step-4">{{ __('exhibitions::ui.auto_6cf57c343a') }}</a></div>

<section class="z4-exhibitions-panel">
<div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_08b91e6e03') }}</h2><p>JPEG, PNG, or WebP; {{ config('exhibitions.media.minimum_dimension', 400) }}–{{ config('exhibitions.media.maximum_dimension', 20000) }}px on both dimensions; maximum 20MB each. Image quality decisions remain manual.</p></div></div>
<form class="z4-exhibitions-form" method="post" enctype="multipart/form-data" action="{{ route('exhibitions.dashboard.artworks.media.upload',$artwork->public_id) }}" data-media-upload>
@csrf
@php($roleLabels=['front'=>'Front / full artwork','detail'=>'Artwork detail','signature'=>'Artist signature','back'=>'Back of artwork','edge'=>'Edges / sides','scale'=>'Scale / room reference','authenticity_certificate'=>'Certificate of authenticity'])
<div class="z4-media-upload-heading">
<div><strong>{{ __('exhibitions::ui.auto_75ad2ea6e0') }}</strong><small>{{ __('exhibitions::ui.auto_fe9983d316') }}</small></div>
<span data-media-slot-count>{{ __('exhibitions::ui.auto_1539b256f8') }}</span>
</div>
<div class="z4-media-upload-items" data-media-upload-items>
<div class="z4-media-upload-item" data-media-upload-item>
<label>{{ __('exhibitions::ui.auto_2dd1148046') }}<select name="items[0][role]">@foreach($roles as $role)<option value="{{ $role }}">{{ $roleLabels[$role] ?? __('exhibitions::ui.state_'.$role) }}</option>@endforeach</select></label>
<label class="z4-exhibitions-upload"><strong>{{ __('exhibitions::ui.auto_5a31ca938e') }}</strong><small>{{ __('exhibitions::ui.auto_2231bff470') }}</small><input type="file" name="items[0][file]" accept="image/jpeg,image/png,image/webp" capture="environment" required data-media-file><span data-media-selection>{{ __('exhibitions::ui.auto_58d5915670') }}</span></label>
<button class="z4-media-remove-item" type="button" data-remove-media-item aria-label="{{ __('exhibitions::ui.auto_73c8a0a110') }}" hidden>{{ __('exhibitions::ui.auto_1063e38cb5') }}</button>
</div>
</div>
<template data-media-upload-template>
<div class="z4-media-upload-item" data-media-upload-item>
<label>{{ __('exhibitions::ui.auto_2dd1148046') }}<select name="items[__INDEX__][role]">@foreach($roles as $role)<option value="{{ $role }}">{{ $roleLabels[$role] ?? __('exhibitions::ui.state_'.$role) }}</option>@endforeach</select></label>
<label class="z4-exhibitions-upload"><strong>{{ __('exhibitions::ui.auto_5a31ca938e') }}</strong><small>{{ __('exhibitions::ui.auto_2231bff470') }}</small><input type="file" name="items[__INDEX__][file]" accept="image/jpeg,image/png,image/webp" capture="environment" required data-media-file><span data-media-selection>{{ __('exhibitions::ui.auto_58d5915670') }}</span></label>
<button class="z4-media-remove-item" type="button" data-remove-media-item aria-label="{{ __('exhibitions::ui.auto_73c8a0a110') }}">{{ __('exhibitions::ui.auto_1063e38cb5') }}</button>
</div>
</template>
<div class="z4-media-upload-status" data-media-upload-status role="status" aria-live="polite" hidden><span data-media-upload-message></span><button type="button" data-media-upload-retry hidden>{{ __('exhibitions::ui.auto_19fcac334c') }}</button></div>
<div class="z4-media-upload-toolbar">
<div><button class="z4-exhibitions-button is-secondary" type="button" data-add-media-item>{{ __('exhibitions::ui.auto_53f372a7ab') }}</button><small>{{ __('exhibitions::ui.auto_683d4a612c') }}</small></div>
<button class="z4-exhibitions-button" type="submit" data-media-upload-submit>{{ __('exhibitions::ui.auto_c02159c09f') }}</button>
</div>
</form>
</section>

<section class="z4-exhibitions-panel">
<div class="z4-exhibitions-panel-heading"><h2>{{ __('exhibitions::ui.auto_af55743f90') }}</h2><span>{{ $media->count() }} file(s)</span></div>
@if($media->isEmpty())
<div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_65d7e60a64') }}</strong><p>{{ __('exhibitions::ui.auto_ca9a6cd1b7') }}</p></div>
@else
<form method="post" action="{{ route('exhibitions.dashboard.artworks.media.reorder',$artwork->public_id) }}" data-media-reorder>
@csrf
<div class="z4-artwork-media-grid">
@foreach($media as $item)
<article data-media-card="{{ $item->public_id }}">
<img src="{{ URL::temporarySignedRoute('exhibitions.dashboard.artworks.media.display',now()->addMinutes(5),['artwork'=>$artwork->public_id,'media'=>$item->public_id]) }}" alt="{{ $item->original_name }}" width="{{ $item->width }}" height="{{ $item->height }}" loading="lazy">
<div><strong>{{ __('exhibitions::ui.state_'.$item->role) }}</strong><span>{{ $item->width }}×{{ $item->height }} · {{ number_format($item->size_bytes/1024) }} KB</span>@if($item->is_primary)<b>{{ __('exhibitions::ui.auto_074de7e7c2') }}</b>@endif</div>
<div class="z4-artwork-media-order"><span>{{ __('exhibitions::ui.auto_662d335c75') }}</span><button type="button" data-media-move="up" aria-label="Move {{ $item->original_name }} earlier">{{ __('exhibitions::ui.auto_adc40ce824') }}</button><input type="number" min="1" value="{{ $item->sort_order }}" aria-label="Order for {{ $item->original_name }}" data-media-order="{{ $item->public_id }}" readonly><button type="button" data-media-move="down" aria-label="Move {{ $item->original_name }} later">{{ __('exhibitions::ui.auto_6fab98d8cf') }}</button></div>
<input type="hidden" name="order[]" value="{{ $item->public_id }}" data-order-value>
<div class="z4-artwork-media-actions">@if(!$item->is_primary)<button form="primary-{{ $item->public_id }}">{{ __('exhibitions::ui.auto_2fcf6dc144') }}</button>@endif<a href="{{ URL::temporarySignedRoute('exhibitions.dashboard.artworks.media.original',now()->addMinutes(5),['artwork'=>$artwork->public_id,'media'=>$item->public_id]) }}">{{ __('exhibitions::ui.auto_2f3af5f35a') }}</a><label>{{ __('exhibitions::ui.auto_89021ba2ae') }}<input form="replace-{{ $item->public_id }}" type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label><button form="replace-{{ $item->public_id }}">{{ __('exhibitions::ui.auto_13cbea3ad6') }}</button><button class="is-danger" form="delete-{{ $item->public_id }}" data-confirm-delete>{{ __('exhibitions::ui.auto_f2a6c498fb') }}</button></div>
</article>
@endforeach
</div>
<button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_58ba392c76') }}</button>
</form>
@foreach($media as $item)
<form id="primary-{{ $item->public_id }}" method="post" action="{{ route('exhibitions.dashboard.artworks.media.primary',[$artwork->public_id,$item->public_id]) }}">@csrf</form>
<form id="replace-{{ $item->public_id }}" method="post" enctype="multipart/form-data" action="{{ route('exhibitions.dashboard.artworks.media.replace',[$artwork->public_id,$item->public_id]) }}">@csrf</form>
<form id="delete-{{ $item->public_id }}" method="post" action="{{ route('exhibitions.dashboard.artworks.media.delete',[$artwork->public_id,$item->public_id]) }}">@csrf</form>
@endforeach
@endif
</section>

<section class="z4-exhibitions-panel"><div class="z4-exhibitions-panel-heading"><h2>{{ __('exhibitions::ui.auto_ecbd33459d') }}</h2></div><ol class="z4-exhibitions-timeline">@forelse($history as $entry)<li><span></span><div><strong>{{ __('exhibitions::ui.state_'.$entry->action) }}</strong><time>{{ $entry->occurred_at }}</time><p>{{ $entry->reason ?: 'Immutable media event recorded.' }}</p></div></li>@empty<li><span></span><div>{{ __('exhibitions::ui.auto_ade0c649e3') }}</div></li>@endforelse</ol></section>
</div>
</main>
</x-frontend-layout>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reorder = document.querySelector('[data-media-reorder]');
    const renumber = () => {
        if (!reorder) return;
        [...reorder.querySelectorAll('[data-media-card]')].forEach((card, index) => card.querySelector('[data-media-order]').value = index + 1);
    };
    reorder?.addEventListener('click', event => {
        const button = event.target.closest('[data-media-move]');
        if (!button) return;
        const card = button.closest('[data-media-card]');
        const sibling = button.dataset.mediaMove === 'up' ? card.previousElementSibling : card.nextElementSibling;
        if (!sibling) return;
        if (button.dataset.mediaMove === 'up') card.parentElement.insertBefore(card, sibling);
        else card.parentElement.insertBefore(sibling, card);
        renumber();
    });
    reorder?.addEventListener('submit', () => {
        const ordered = [...reorder.querySelectorAll('[data-media-card]')].map(card => card.dataset.mediaCard);
        reorder.querySelectorAll('[data-order-value]').forEach((input, index) => input.value = ordered[index]);
    });
    document.querySelectorAll('[data-confirm-delete]').forEach(button => button.addEventListener('click', event => {
        if (!confirm('Delete this image?')) event.preventDefault();
    }));

    const upload = document.querySelector('[data-media-upload]');
    if (!upload) return;
    const items = upload.querySelector('[data-media-upload-items]');
    const template = upload.querySelector('[data-media-upload-template]');
    const add = upload.querySelector('[data-add-media-item]');
    const slotCount = upload.querySelector('[data-media-slot-count]');
    const status = upload.querySelector('[data-media-upload-status]');
    const message = upload.querySelector('[data-media-upload-message]');
    const retry = upload.querySelector('[data-media-upload-retry]');
    const submit = upload.querySelector('[data-media-upload-submit]');
    const maximum = 8;
    let nextIndex = 1;

    const refreshRows = () => {
        const rows = [...items.querySelectorAll('[data-media-upload-item]')];
        rows.forEach(row => row.querySelector('[data-remove-media-item]').hidden = rows.length === 1);
        slotCount.textContent = rows.length + ' of ' + maximum + ' image slots';
        add.disabled = rows.length >= maximum;
        add.textContent = rows.length >= maximum ? 'Maximum 8 images reached' : '+ Add another image';
    };
    const bindRow = row => {
        const file = row.querySelector('[data-media-file]');
        const selection = row.querySelector('[data-media-selection]');
        file.addEventListener('change', () => selection.textContent = file.files.length ? file.files[0].name : 'No image selected');
        row.querySelector('[data-remove-media-item]').addEventListener('click', () => {
            if (items.querySelectorAll('[data-media-upload-item]').length === 1) return;
            row.remove();
            refreshRows();
        });
    };
    items.querySelectorAll('[data-media-upload-item]').forEach(bindRow);
    add.addEventListener('click', () => {
        if (items.querySelectorAll('[data-media-upload-item]').length >= maximum) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)).trim();
        const row = wrapper.firstElementChild;
        items.appendChild(row);
        bindRow(row);
        refreshRows();
        row.querySelector('select').focus();
    });
    refreshRows();

    const send = async () => {
        if (!upload.reportValidity()) return;
        status.hidden = false;
        retry.hidden = true;
        submit.disabled = true;
        add.disabled = true;
        message.textContent = 'Uploading and creating optimized copies...';
        try {
            const response = await fetch(upload.action, {method: 'POST', body: new FormData(upload), headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = body.errors ? Object.values(body.errors).flat().join(' ') : body.message;
                throw new Error(errors || 'Upload failed.');
            }
            message.textContent = body.message || 'Images uploaded safely.';
            location.reload();
        } catch (error) {
            message.textContent = error.message || 'Connection interrupted. Your selected files are still here.';
            retry.hidden = false;
            submit.disabled = false;
            refreshRows();
        }
    };
    upload.addEventListener('submit', event => { event.preventDefault(); send(); });
    retry.addEventListener('click', send);
});
</script>
