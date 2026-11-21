(() => {
    const platformConfig = window.ArtInpaVvvebConfig;
    const extension = platformConfig?.extensions?.['exhibitions.random-artworks'];

    if (
        !extension
        || !window.Vvveb
        || typeof SelectInput === 'undefined'
        || typeof SectionInput === 'undefined'
        || typeof NumberInput === 'undefined'
    ) {
        return;
    }

    const option = (value, text) => ({value, text});
    const select = (name, key, attribute, options, extra = {}) => ({
        name,
        key,
        htmlAttr: attribute,
        inputtype: SelectInput,
        inline: false,
        data: {options},
        ...extra
    });
    const yesNo = [option('1', 'Show'), option('0', 'Hide')];

    Vvveb.Components.extend('_base', 'exhibitions/random-artworks', {
        name: 'Artworks',
        attributes: ['data-exhibitions-random-artworks'],
        image: 'icons/products.svg',
        html: slotMarkup(),
        properties: [
            {name: false, key: 'exhibitions_random_content_header', inputtype: SectionInput, data: {header: 'Artworks'}},
            {name: 'Number of artworks', key: 'exhibitions-artworks-limit', htmlAttr: 'data-exhibitions-artworks-limit', inputtype: NumberInput, inline: false, col: 6, data: {min: 1, max: 12, step: 1}},
            select('Order', 'exhibitions-artworks-order', 'data-exhibitions-artworks-order', [
                option('random', 'Random'),
                option('newest', 'Newest first'),
                option('oldest', 'Oldest first'),
                option('price-low', 'Price: low to high'),
                option('price-high', 'Price: high to low')
            ], {col: 6}),
            select('Image display', 'exhibitions-artworks-image-fit', 'data-exhibitions-artworks-image-fit', [
                option('contain', 'Contain (no cropping)'),
                option('cover', 'Cover (fill and crop)')
            ], {col: 6}),
            {name: false, key: 'exhibitions_random_layout_header', inputtype: SectionInput, data: {header: 'Responsive Layout'}},
            select('Desktop columns', 'exhibitions-artworks-columns-desktop', 'data-exhibitions-artworks-columns-desktop', columnOptions(6), {col: 4}),
            select('Tablet columns', 'exhibitions-artworks-columns-tablet', 'data-exhibitions-artworks-columns-tablet', columnOptions(4), {col: 4}),
            select('Mobile columns', 'exhibitions-artworks-columns-mobile', 'data-exhibitions-artworks-columns-mobile', columnOptions(2), {col: 4}),
            {name: 'Gap (px)', key: 'exhibitions-artworks-gap', htmlAttr: 'data-exhibitions-artworks-gap', inputtype: NumberInput, inline: false, col: 6, data: {min: 0, max: 120, step: 1}},
            {name: false, key: 'exhibitions_random_details_header', inputtype: SectionInput, data: {header: 'Card Details'}},
            select('Artist', 'exhibitions-artworks-show-artist', 'data-exhibitions-artworks-show-artist', yesNo, {col: 6}),
            select('Category and year', 'exhibitions-artworks-show-category', 'data-exhibitions-artworks-show-category', yesNo, {col: 6}),
            select('Price', 'exhibitions-artworks-show-price', 'data-exhibitions-artworks-show-price', yesNo, {col: 6}),
            select('Availability', 'exhibitions-artworks-show-state', 'data-exhibitions-artworks-show-state', yesNo, {col: 6})
        ],
        init(node) {
            ensureInstanceId(node);
            renderPreview(node);
        },
        onChange(node) {
            ensureInstanceId(node);
            renderPreview(node);
            return node;
        }
    });

    Vvveb.ComponentsGroup.Exhibitions ||= [];
    if (!Vvveb.ComponentsGroup.Exhibitions.includes('exhibitions/random-artworks')) {
        Vvveb.ComponentsGroup.Exhibitions.unshift('exhibitions/random-artworks');
    }
    Vvveb.Builder.loadControlGroups();
    installPreviewLifecycle();

    function installPreviewLifecycle() {
        const component = Vvveb.Components.get('exhibitions/random-artworks');
        if (component) {
            component.afterDrop = node => {
                ensureInstanceId(node);
                renderPreview(node);
                return node;
            };
        }

        const hydrate = frameDocument => {
            if (!frameDocument?.querySelectorAll) return;
            frameDocument.querySelectorAll('[data-exhibitions-random-artworks]').forEach(node => {
                ensureInstanceId(node);
                renderPreview(node);
            });
        };

        window.addEventListener('vvveb.iframe.loaded', event => hydrate(event.detail));
        hydrate(Vvveb.Builder.frameDoc);
    }

    function slotMarkup() {
        return '<div class="exhibitions-random-artworks-slot" data-exhibitions-random-artworks="1" data-exhibitions-artworks-limit="3" data-exhibitions-artworks-order="random" data-exhibitions-artworks-columns-desktop="3" data-exhibitions-artworks-columns-tablet="2" data-exhibitions-artworks-columns-mobile="1" data-exhibitions-artworks-gap="24" data-exhibitions-artworks-image-fit="contain" data-exhibitions-artworks-show-artist="1" data-exhibitions-artworks-show-category="1" data-exhibitions-artworks-show-price="1" data-exhibitions-artworks-show-state="1"><span hidden>Artworks</span></div>';
    }

    function ensureInstanceId(node) {
        if (node.hasAttribute('data-exhibitions-artworks-instance')) return;
        const random = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
        node.setAttribute('data-exhibitions-artworks-instance', `exhibitions-${random}`);
    }

    function renderPreview(node) {
        if (!node?.attachShadow) return;
        const root = node.shadowRoot || node.attachShadow({mode: 'open'});
        const order = node.getAttribute('data-exhibitions-artworks-order') || 'random';
        const previewHtml = extension.previewHtmlByOrder?.[order]
            || extension.previewHtmlByOrder?.random
            || '';
        root.innerHTML = extension.hasArtworks
            ? `<style>${previewCss(node)}</style><div class="exhibitions-random-artworks-preview">${previewHtml}</div>`
            : `<style>${emptyCss()}</style><div class="exhibitions-random-artworks-empty">No published artworks are available.</div>`;
        const limit = integerAttribute(node, 'data-exhibitions-artworks-limit', 3, 1, 12);
        root.querySelectorAll('.exhibitions-random-artwork-card').forEach((card, index) => {
            card.hidden = index >= limit;
        });
    }

    function previewCss(node) {
        const desktop = integerAttribute(node, 'data-exhibitions-artworks-columns-desktop', 3, 1, 6);
        const tablet = integerAttribute(node, 'data-exhibitions-artworks-columns-tablet', 2, 1, 4);
        const mobile = integerAttribute(node, 'data-exhibitions-artworks-columns-mobile', 1, 1, 2);
        const gap = integerAttribute(node, 'data-exhibitions-artworks-gap', 24, 0, 120);
        const fit = node.getAttribute('data-exhibitions-artworks-image-fit') === 'cover' ? 'cover' : 'contain';
        const hidden = [
            ['data-exhibitions-artworks-show-artist', '.exhibitions-random-artwork-artist'],
            ['data-exhibitions-artworks-show-category', '.exhibitions-random-artwork-meta'],
            ['data-exhibitions-artworks-show-price', '.exhibitions-random-artwork-price'],
            ['data-exhibitions-artworks-show-state', '.exhibitions-random-artwork-state']
        ].filter(([attribute]) => node.getAttribute(attribute) === '0').map(([, selector]) => selector).join(',');
        return `:host{display:block;min-height:72px}.exhibitions-random-artworks-preview{display:block}.exhibitions-random-artworks-preview{display:grid;grid-template-columns:repeat(${desktop},minmax(0,1fr));gap:${gap}px}.exhibitions-random-artwork-card{min-width:0;overflow:hidden;border:1px solid rgba(15,23,42,.12);border-radius:14px;background:#fff;color:#0f172a}.exhibitions-random-artwork-image{position:relative;display:flex;width:100%;aspect-ratio:4/3;align-items:center;justify-content:center;overflow:hidden;background:#f3f4f6;text-decoration:none}.exhibitions-random-artwork-image img{display:block;width:100%;height:100%;object-fit:${fit}}.exhibitions-random-artworks-placeholder{font:800 2rem/1 system-ui,sans-serif;color:#94a3b8}.exhibitions-random-artwork-state{position:absolute;inset-block-start:12px;inset-inline-end:12px;border-radius:999px;background:#166534;padding:6px 10px;color:#fff;font:700 12px/1 system-ui,sans-serif;font-style:normal}.exhibitions-random-artwork-card[data-artwork-state="sold"] .exhibitions-random-artwork-state{background:#0f172a}.exhibitions-random-artwork-body{display:grid;gap:9px;padding:16px}.exhibitions-random-artwork-meta{display:flex;justify-content:space-between;gap:12px;color:#64748b;font:600 12px/1.4 system-ui,sans-serif}.exhibitions-random-artwork-card h3{margin:0;font:700 1.05rem/1.45 system-ui,sans-serif}.exhibitions-random-artwork-card h3 a{color:inherit;text-decoration:none}.exhibitions-random-artwork-artist{margin:0;color:#64748b;font:400 14px/1.5 system-ui,sans-serif}.exhibitions-random-artwork-price{font:700 14px/1.4 system-ui,sans-serif}${hidden ? `${hidden}{display:none}` : ''}@media(max-width:991px){.exhibitions-random-artworks-preview{grid-template-columns:repeat(${tablet},minmax(0,1fr))}}@media(max-width:575px){.exhibitions-random-artworks-preview{grid-template-columns:repeat(${mobile},minmax(0,1fr))}}`;
    }

    function emptyCss() {
        return ':host{display:block;min-height:72px}.exhibitions-random-artworks-empty{display:grid;min-height:96px;place-items:center;padding:20px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#475569;font:600 13px/1.5 system-ui,sans-serif}';
    }

    function integerAttribute(node, name, fallback, min, max) {
        const value = Number.parseInt(node.getAttribute(name) || '', 10);
        return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback;
    }

    function columnOptions(max) {
        return Array.from({length: max}, (_, index) => option(String(index + 1), String(index + 1)));
    }
})();
