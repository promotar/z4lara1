{{--
    Shared icon picker (rendered once per admin menus page).
    Any field with `data-icon-input` can be edited through it; the field also
    shows a live preview of the currently selected icon.
--}}
@php($iconPaths = \App\Platform\Core\UI\IconSet::paths())

<div id="z4-icon-picker" class="z4-icon-picker-modal" hidden>
    <div class="z4-icon-picker-backdrop" data-icon-close></div>
    <div class="z4-icon-picker-dialog" role="dialog" aria-modal="true" aria-label="Choose icon">
        <div class="z4-icon-picker-head">
            <strong>Choose icon</strong>
            <button type="button" class="z4-icon-picker-close" data-icon-close aria-label="Close">×</button>
        </div>
        <input type="search" id="z4-icon-picker-search" class="admin-input" placeholder="Search icons…" autocomplete="off">
        <div class="z4-icon-picker-grid" id="z4-icon-picker-grid">
            @foreach ($iconPaths as $iconName => $iconPath)
                <button
                    type="button"
                    class="z4-icon-picker-item"
                    data-icon-name="{{ $iconName }}"
                    title="{{ $iconName }}"
                    aria-label="{{ $iconName }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $iconPath !!}
                    </svg>
                    <span>{{ $iconName }}</span>
                </button>
            @endforeach
        </div>
        <div class="z4-icon-picker-actions">
            <button type="button" class="ainpa-button ainpa-button-compact" data-icon-clear>Clear</button>
            <button type="button" class="ainpa-button ainpa-button-compact ainpa-button-primary" data-icon-close>Done</button>
        </div>
    </div>
</div>

<style>
    z4-icon-picker-modal, .z4-icon-picker-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 3000; }
    .z4-icon-picker-modal[hidden] { display: none; }
    .z4-icon-picker-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .45); }
    .z4-icon-picker-dialog { position: relative; width: min(720px, 92vw); background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); direction: inherit; }
    .z4-icon-picker-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .z4-icon-picker-close { border: 0; background: transparent; font-size: 22px; line-height: 1; cursor: pointer; color: #475569; }
    .z4-icon-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 6px; max-height: 56vh; overflow: auto; padding: 6px 2px; }
    .z4-icon-picker-item { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 4px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; cursor: pointer; color: #334155; font-size: 10px; }
    .z4-icon-picker-item:hover, .z4-icon-picker-item.is-selected { border-color: #9a0000; background: #fff7f4; color: #9a0000; }
    .z4-icon-picker-item svg { width: 20px; height: 20px; }
    .z4-icon-picker-item span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .z4-icon-picker-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
    .z4-icon-preview { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #9a0000; flex: 0 0 auto; }
    .z4-icon-preview svg { width: 18px; height: 18px; }
    .z4-icon-preview .z4-icon-text { font-weight: 800; font-size: 13px; }
</style>

<script>
(function () {
    const modal = document.getElementById('z4-icon-picker');
    if (!modal) return;

    const search = document.getElementById('z4-icon-picker-search');
    const items = Array.from(modal.querySelectorAll('.z4-icon-picker-item'));
    let targetInput = null;

    const previewFor = (input) => {
        if (!input) return null;
        const wrap = input.closest('.admin-field') || input.parentElement;
        return wrap ? wrap.querySelector('.z4-icon-preview') : null;
    };

    const renderPreview = (input, name) => {
        const preview = previewFor(input);
        const item = items.find(i => i.dataset.iconName === name);
        if (preview) {
            const svg = item ? item.querySelector('svg').cloneNode(true) : null;
            preview.innerHTML = '';
            if (svg) { preview.appendChild(svg); } else { preview.textContent = '—'; }
        }
        // Icons are opt-in: anything that is not a known icon name counts as "no icon".
        input.value = item ? item.dataset.iconName : '';
    };

    const openFor = (input) => {
        targetInput = input;
        modal.hidden = false;
        search.value = '';
        items.forEach(i => { i.style.display = ''; i.classList.toggle('is-selected', i.dataset.iconName === (input.value || '').trim().toLowerCase()); });
        search.focus();
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-icon-open]');
        if (opener) {
            event.preventDefault();
            const input = opener.closest('.admin-field')?.querySelector('[data-icon-input]')
                || opener.closest('form')?.querySelector('[data-icon-input]');
            if (input) openFor(input);
            return;
        }

        if (event.target.closest('[data-icon-close]')) { modal.hidden = true; return; }

        const pick = event.target.closest('.z4-icon-picker-item');
        if (pick && targetInput) {
            renderPreview(targetInput, pick.dataset.iconName);
            items.forEach(i => i.classList.toggle('is-selected', i === pick));
            modal.hidden = true;
            return;
        }

        if (event.target.closest('[data-icon-clear]') && targetInput) {
            renderPreview(targetInput, '');
            modal.hidden = true;
        }
    });

    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        items.forEach(i => { i.style.display = i.dataset.iconName.includes(q) ? '' : 'none'; });
    });

    // Initial previews for every icon field on the page.
    document.querySelectorAll('[data-icon-input]').forEach(input => renderPreview(input, (input.value || '').trim().toLowerCase()));
})();
</script>
