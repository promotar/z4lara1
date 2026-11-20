@php
    $mediaPickerKey = $mediaPickerKey ?? 'lms-media';
    $mediaIndexUrl = $mediaIndexUrl ?? (Route::has('admin.media.index') ? route('admin.media.index', [], false) : '');
    $mediaStoreUrl = $mediaStoreUrl ?? (Route::has('admin.media.store') ? route('admin.media.store', [], false) : '');
@endphp

<div class="lms-shared-media-modal" data-lms-shared-media="{{ $mediaPickerKey }}" data-index-url="{{ $mediaIndexUrl }}" data-store-url="{{ $mediaStoreUrl }}" hidden aria-hidden="true">
    <div class="lms-shared-media-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $mediaPickerKey }}-title">
        <header>
            <div><h2 id="{{ $mediaPickerKey }}-title">Platform Media Studio</h2><p data-media-description>Choose an existing file or upload a new one.</p></div>
            <button type="button" class="lms-shared-media-close" data-media-close aria-label="Close Media Studio">×</button>
        </header>
        <div class="lms-shared-media-toolbar"><input type="search" placeholder="Search media" data-media-search><span data-media-count></span></div>
        <form class="lms-shared-media-upload" data-media-upload enctype="multipart/form-data">
            <div class="lms-shared-media-upload-head"><strong>Upload new media</strong><span>It will be added to the platform gallery and selected here.</span></div>
            <label><span>File</span><input type="file" name="media" required data-media-file><small data-media-filename style="color:#6b7280"></small></label>
            <label><span>Title</span><input type="text" name="title" maxlength="255" data-media-title></label>
            <label><span>Alternative text</span><input type="text" name="alt_text" maxlength="255"></label>
            <label><span>Caption</span><input type="text" name="caption" maxlength="500"></label>
            <label class="is-wide"><span>Description</span><textarea name="description" maxlength="1000" rows="2"></textarea></label>
            <button type="submit" class="lms-shared-media-primary">Upload &amp; use</button>
            <p data-media-status role="status" aria-live="polite"></p>
        </form>
        <div class="lms-shared-media-items" data-media-items><p>Loading media…</p></div>
    </div>
</div>

<script>
(() => {
    const key = @json($mediaPickerKey);
    const modal = document.querySelector(`[data-lms-shared-media="${key}"]`);
    if (!modal || modal.dataset.ready === '1') return;
    modal.dataset.ready = '1';
    const indexUrl = modal.dataset.indexUrl;
    const storeUrl = modal.dataset.storeUrl;
    const itemsBox = modal.querySelector('[data-media-items]');
    const search = modal.querySelector('[data-media-search]');
    const count = modal.querySelector('[data-media-count]');
    const uploadForm = modal.querySelector('[data-media-upload]');
    const uploadFile = modal.querySelector('[data-media-file]');
    uploadFile?.addEventListener('change', () => { const nameLabel = modal.querySelector('[data-media-filename]'); if (nameLabel) nameLabel.textContent = uploadFile.files?.[0]?.name || ''; });
    const uploadTitle = modal.querySelector('[data-media-title]');
    const status = modal.querySelector('[data-media-status]');
    const description = modal.querySelector('[data-media-description]');
    let currentTarget = '';
    let currentType = 'image';
    let mediaItems = [];

    const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const itemUrl = (item) => item.media_url || item.url || item.image_url || '';
    const itemName = (item) => item.title || item.name || 'Media file';
    const isImage = (item) => String(item.mime_type || item.mime || '').startsWith('image/') || /\.(png|jpe?g|webp|gif|svg|ico)$/i.test(itemUrl(item));
    const render = () => {
        const term = search.value.trim().toLowerCase();
        const filtered = mediaItems.filter((item) => `${itemName(item)} ${item.alt_text || ''} ${item.caption || ''}`.toLowerCase().includes(term));
        count.textContent = `${filtered.length} file${filtered.length === 1 ? '' : 's'}`;
        itemsBox.innerHTML = filtered.length ? `<div class="lms-shared-media-grid">${filtered.map((item, index) => {
            const preview = item.thumbnail_url || (isImage(item) ? itemUrl(item) : '');
            return `<button type="button" data-media-index="${index}">${preview ? `<img src="${escapeHtml(preview)}" alt="">` : '<span class="lms-shared-media-file-icon">FILE</span>'}<strong>${escapeHtml(itemName(item))}</strong><small>${escapeHtml(item.size || '')}</small></button>`;
        }).join('')}</div>` : '<div class="lms-shared-media-empty">No matching media found.</div>';
        itemsBox.querySelectorAll('[data-media-index]').forEach((button) => button.addEventListener('click', () => select(filtered[Number(button.dataset.mediaIndex)])));
    };
    const select = (item) => {
        const url = itemUrl(item);
        if (!url || !currentTarget) return;
        const urlField = document.querySelector(`[data-lms-media-url="${currentTarget}"]`);
        const nameField = document.querySelector(`[data-lms-media-name="${currentTarget}"]`);
        const label = document.querySelector(`[data-lms-media-label="${currentTarget}"]`);
        const preview = document.querySelector(`[data-lms-media-preview="${currentTarget}"]`);
        if (urlField) urlField.value = url;
        if (nameField) nameField.value = itemName(item);
        if (label) label.textContent = `Selected: ${itemName(item)}`;
        if (preview && isImage(item)) { preview.src = url; preview.hidden = false; }
        close();
    };
    const load = async () => {
        itemsBox.innerHTML = '<p>Loading media…</p>';
        if (!indexUrl) { itemsBox.innerHTML = '<div class="lms-shared-media-empty">Media Studio is unavailable.</div>'; return; }
        try {
            const endpoint = `${indexUrl}${indexUrl.includes('?') ? '&' : '?'}type=${encodeURIComponent(currentType)}`;
            const response = await fetch(endpoint, {headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Media Studio could not be loaded.');
            mediaItems = Array.isArray(payload.items) ? payload.items : [];
            render();
        } catch (error) { itemsBox.innerHTML = `<div class="lms-shared-media-empty">${escapeHtml(error.message || 'Media Studio could not be loaded.')}</div>`; }
    };
    const open = (button) => {
        currentTarget = button.dataset.mediaTarget || '';
        currentType = button.dataset.mediaType || 'image';
        description.textContent = button.dataset.mediaDescription || 'Choose an existing file or upload a new one.';
        uploadFile.accept = button.dataset.mediaAccept || (currentType === 'image' ? 'image/*' : currentType === 'video' ? 'video/*' : 'image/*,video/*,application/pdf,.pdf,.doc,.docx,.zip');
        uploadForm.reset(); search.value = ''; status.textContent = '';
        modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('lms-shared-media-open');
        load();
    };
    const close = () => { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('lms-shared-media-open'); };
    document.querySelectorAll(`[data-lms-media-trigger="${key}"]`).forEach((button) => button.addEventListener('click', () => open(button)));
    modal.querySelector('[data-media-close]').addEventListener('click', close);
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    search.addEventListener('input', render);
    uploadFile.addEventListener('change', () => { if (uploadFile.files?.[0] && !uploadTitle.value) uploadTitle.value = uploadFile.files[0].name.replace(/\.[^.]+$/, ''); });
    uploadForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!storeUrl || !uploadFile.files?.[0]) { status.textContent = 'Choose a file first.'; return; }
        const data = new FormData(uploadForm);
        status.textContent = 'Uploading…';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('[name="_token"]')?.value || '';
            const response = await fetch(storeUrl, {method: 'POST', body: data, headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf}});
            const payload = await response.json();
            if (!response.ok || !payload.media) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Upload failed.');
            select(payload.media);
        } catch (error) { status.textContent = error.message || 'Upload failed.'; }
    });
})();
</script>
