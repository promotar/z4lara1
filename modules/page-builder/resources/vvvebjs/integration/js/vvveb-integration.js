(() => {
    const config = window.ArtInpaVvvebConfig;

    if (!config || !window.Vvveb) {
        return;
    }

    const page = config.page;
    let documentVersion = page.documentVersion || '';
    let staleDocument = false;
    const topPanel = document.querySelector('#top-panel');
    const logo = document.querySelector('#logo');

    registerFrontendMenuComponent();
    registerGlobalZIndexProperty();

    if (logo) {
        const brand = document.createElement('a');
        brand.className = 'art-inpa-vvveb-brand float-start';
        brand.href = config.indexUrl;
        brand.innerHTML = 'Art INPA <small>VvvebJs</small>';
        logo.replaceWith(brand);
    }

    const modalMarkup = `
        <div class="modal fade art-inpa-vvveb-settings" id="art-inpa-vvveb-settings" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Page settings</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><div class="art-inpa-vvveb-settings-grid">
                    <label>Title<input class="form-control" name="title" value="${escapeHtml(page.title || '')}"></label>
                    <label>Slug<input class="form-control" name="slug" value="${escapeHtml(page.slug || '')}"></label>
                    <label>Type<select class="form-select" name="content_type">${options(['page', 'header', 'footer', 'block'], page.contentType)}</select></label>
                    <label>Status<select class="form-select" name="status">${options(['draft', 'published'], page.status)}</select></label>
                    <label>SEO title<input class="form-control" name="seo_title" value="${escapeHtml(page.seoTitle || '')}"></label>
                    <label>Block key<input class="form-control" name="block_key" value="${escapeHtml(page.blockKey || '')}"></label>
                    <label>Sort order<input class="form-control" type="number" name="sort_order" value="${Number(page.sortOrder || 0)}"></label>
                    <label class="wide">Meta description<textarea class="form-control" rows="3" name="meta_description">${escapeHtml(page.metaDescription || '')}</textarea></label>
                    <div class="wide"><strong>Revisions</strong><div class="list-group mt-2" data-vvveb-revisions><span class="text-muted">Loading…</span></div></div>
                </div></div>
                <div class="modal-footer"><a class="btn btn-outline-secondary me-auto" href="${config.indexUrl}">All pages</a><a class="btn btn-outline-primary" target="_blank" href="${config.previewUrl}">Preview</a><button class="btn btn-primary" data-bs-dismiss="modal">Done</button></div>
            </div></div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', modalMarkup);

    if (topPanel) {
        const actions = topPanel.querySelector('.float-end.me-3');
        const settings = document.createElement('button');
        settings.type = 'button';
        settings.className = 'btn btn-light btn-sm me-2';
        settings.innerHTML = '<i class="icon-settings-outline"></i> Settings';
        settings.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(document.querySelector('#art-inpa-vvveb-settings')).show());
        actions?.prepend(settings);

        const preview = topPanel.querySelector('.btn-preview-url');
        if (preview) preview.href = config.previewUrl;
    }

    const originalSaveAjax = Vvveb.Builder.saveAjax.bind(Vvveb.Builder);
    Vvveb.Builder.saveAjax = (data, url, callback, error) => {
        Object.assign(data, settingsPayload(), {_token: config.csrfToken, document_version: documentVersion});
        return originalSaveAjax(data, url || config.saveUrl, (response) => {
            let parsed = response;
            try { parsed = JSON.parse(response); } catch (_) {}
            if (parsed?.document_version) documentVersion = parsed.document_version;
            callback?.(parsed);
        }, error);
    };

    for (const reusable of config.reusables || []) {
        const key = `site/${reusable.type}-${reusable.id}`;
        const registry = reusable.type === 'section' ? Vvveb.Sections : Vvveb.Blocks;
        const groups = reusable.type === 'section' ? Vvveb.SectionsGroup : Vvveb.BlocksGroup;
        registry.add(key, {name: reusable.name, image: '/page-builder-assets/v5/libs/builder/icons/panel.svg', html: reusable.html});
        groups['Site reusable'] ||= [];
        groups['Site reusable'].push(key);
    }
    Vvveb.Builder.loadSectionGroups();
    Vvveb.Builder.loadBlockGroups();

    const settingsModal = document.querySelector('#art-inpa-vvveb-settings');
    settingsModal.addEventListener('show.bs.modal', loadRevisions);

    if (window.MediaModal) {
        const originalGallery = MediaModal.prototype.initGallery;
        MediaModal.prototype.initGallery = function () {
            window.mediaScanUrl = config.mediaUrl;
            return originalGallery.call(this);
        };

        MediaModal.prototype.onUpload = function (event) {
            const file = event.target.files?.[0];
            if (!file || !Vvveb.MediaModal) return;
            Vvveb.MediaModal.showUploadLoading();
            const body = new FormData();
            body.append('file', file);
            fetch(config.mediaUploadUrl, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken},
                body,
                credentials: 'same-origin'
            }).then(response => {
                if (!response.ok) throw new Error('Upload failed');
                return response.json();
            }).then(data => {
                const media = data.media;
                const item = Vvveb.MediaModal.addFile({
                    name: media.name,
                    type: 'file',
                    path: String(media.url).replace(/^\//, ''),
                    size: media.size_bytes || 1
                }, true);
                item.scrollIntoView({behavior: 'smooth', block: 'center'});
            }).catch(() => displayToast('bg-danger', 'Media', 'Upload failed'))
              .finally(() => Vvveb.MediaModal.hideUploadLoading());
        };
    }

    let lastSnapshot = '';
    window.setInterval(() => {
        if (staleDocument || !window.FrameDocument || !Vvveb.Builder?.getHtml) return;
        const html = Vvveb.Builder.getHtml();
        if (html === lastSnapshot) return;
        lastSnapshot = html;
        fetch(config.saveUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            credentials: 'same-origin',
            body: new URLSearchParams({...settingsPayload(), _token: config.csrfToken, document_version: documentVersion, html, autosave: '1'})
        }).then(async response => {
            const data = await response.json().catch(() => ({}));
            if (response.status === 409) {
                staleDocument = true;
                displayToast('bg-danger', 'Reload required', data.message || 'This editor is out of date.');
                return;
            }
            if (response.ok && data.document_version) documentVersion = data.document_version;
        }).catch(() => {});
    }, 60000);

    function settingsPayload() {
        const modal = document.querySelector('#art-inpa-vvveb-settings');
        return Object.fromEntries([...modal.querySelectorAll('[name]')].map(input => [input.name, input.value]));
    }

    function registerGlobalZIndexProperty() {
        const components = Vvveb.Components?._components;
        if (!components) return;

        Object.values(components).forEach(component => {
            if (!component || !Array.isArray(component.properties)) return;
            if (component.properties.some(property => property?.key === 'z-index')) return;

            component.properties.push(
                {
                    name: false,
                    key: 'layering_header',
                    section: 'advanced',
                    sort: 89,
                    inputtype: SectionInput,
                    data: {header: 'Layering', expanded: true}
                },
                {
                    name: 'Z-index',
                    key: 'z-index',
                    htmlAttr: 'style',
                    section: 'advanced',
                    sort: 90,
                    col: 12,
                    inline: false,
                    inputtype: NumberInput,
                    data: {step: 1}
                }
            );

            component.properties.sort((first, second) => (first.sort || 0) - (second.sort || 0));
        });
    }

    function registerFrontendMenuComponent() {
        const menus = Array.isArray(config.frontendMenus) ? config.frontendMenus : [];
        const firstKey = menus[0]?.value || 'platform.frontend';
        const menuOptions = menus.length
            ? menus.map(menu => ({value: menu.value, text: menu.name || menu.value}))
            : [{value: firstKey, text: 'Frontend Menu'}];
        const select = (name, key, attribute, options, extra = {}) => ({
            name,
            key,
            htmlAttr: attribute,
            inputtype: SelectInput,
            inline: false,
            data: {options},
            ...extra
        });
        const unit = (name, key, attribute, defaultValue, extra = {}) => ({
            name,
            key,
            htmlAttr: attribute,
            inputtype: CssUnitInput,
            inline: false,
            defaultValue,
            ...extra
        });
        const color = (name, key, attribute, extra = {}) => ({
            name,
            key,
            htmlAttr: attribute,
            inputtype: ColorInput,
            inline: true,
            ...extra
        });

        Vvveb.Components.extend('html/navbar', 'site/frontend-menu', {
            name: 'Frontend Menu',
            attributes: ['data-platform-menu-key'],
            image: 'icons/navbar.svg',
            stylesheets: [{
                src: `${config.assetBase}/integration/css/frontend-menu.css`,
                mustHaveElement: '[data-platform-menu-key]'
            }],
            html: frontendMenuMarkup(firstKey),
            properties: [
                {name: false, key: 'frontend_menu_layout_header', inputtype: SectionInput, data: {header: 'Menu & Layout'}},
                select('Frontend menu', 'frontend-menu-key', 'data-platform-menu-key', menuOptions, {col: 12}),
                select('Layout', 'frontend-menu-layout', 'data-platform-menu-layout', [
                    {value: 'horizontal', text: 'Horizontal'},
                    {value: 'vertical', text: 'Vertical'},
                    {value: 'offcanvas', text: 'Off-canvas'}
                ], {col: 6}),
                select('Menu icon', 'frontend-menu-icon', 'data-platform-menu-icon', [
                    {value: 'bars', text: 'Hamburger'},
                    {value: 'compact', text: 'Compact bars'},
                    {value: 'dots', text: 'Three dots'},
                    {value: 'grid', text: 'Grid'}
                ], {col: 6}),
                select('Side', 'frontend-menu-side', 'data-platform-menu-side', [
                    {value: 'end', text: 'End'},
                    {value: 'start', text: 'Start'}
                ], {col: 6}),
                unit('Panel width', 'frontend-menu-offcanvas-width', 'data-platform-menu-offcanvas-width', '320px', {col: 6}),

                {name: false, key: 'frontend_menu_typography_header', inputtype: SectionInput, data: {header: 'Typography', expanded: false}},
                select('Font', 'frontend-menu-font-family', 'data-platform-menu-font-family', [
                    {value: 'inherit', text: 'Theme default'},
                    {value: 'system-ui', text: 'System UI'},
                    {value: 'Arial', text: 'Arial'},
                    {value: 'Helvetica', text: 'Helvetica'},
                    {value: 'Tahoma', text: 'Tahoma'},
                    {value: 'Verdana', text: 'Verdana'},
                    {value: 'Georgia', text: 'Georgia'},
                    {value: 'Times New Roman', text: 'Times New Roman'}
                ], {col: 6}),
                select('Weight', 'frontend-menu-font-weight', 'data-platform-menu-font-weight', [
                    {value: '400', text: 'Regular'},
                    {value: '500', text: 'Medium'},
                    {value: '600', text: 'Semibold'},
                    {value: '700', text: 'Bold'}
                ], {col: 6}),
                unit('Font size', 'frontend-menu-font-size', 'data-platform-menu-font-size', '15px', {col: 6}),

                {name: false, key: 'frontend_menu_colors_header', inputtype: SectionInput, data: {header: 'Colors', expanded: false}},
                color('Text', 'frontend-menu-text-color', 'data-platform-menu-text-color', {col: 6}),
                color('Background', 'frontend-menu-background', 'data-platform-menu-background', {col: 6}),
                color('Hover text', 'frontend-menu-hover-color', 'data-platform-menu-hover-color', {col: 6}),
                color('Hover background', 'frontend-menu-hover-background', 'data-platform-menu-hover-background', {col: 6}),
                color('Submenu', 'frontend-menu-submenu-background', 'data-platform-menu-submenu-background', {col: 6}),

                {name: false, key: 'frontend_menu_spacing_header', inputtype: SectionInput, data: {header: 'Item Spacing', expanded: false}},
                unit('Item margin', 'frontend-menu-item-margin', 'data-platform-menu-item-margin', '0px', {col: 6}),
                unit('Item padding', 'frontend-menu-item-padding', 'data-platform-menu-item-padding', '12px', {col: 6})
            ],
            init(node) {
                applyFrontendMenuDesign(node);
                const toggle = node.querySelector('.platform-menu-toggle');
                if (toggle && !toggle.dataset.previewBound) {
                    toggle.dataset.previewBound = '1';
                    toggle.addEventListener('click', event => {
                        event.preventDefault();
                        node.classList.toggle('is-preview-open');
                    });
                }
                const close = node.querySelector('.platform-menu-close');
                if (close && !close.dataset.previewBound) {
                    close.dataset.previewBound = '1';
                    close.addEventListener('click', event => {
                        event.preventDefault();
                        node.classList.remove('is-preview-open');
                    });
                }
            },
            onChange(node) {
                applyFrontendMenuDesign(node);
                return node;
            }
        });

        Vvveb.ComponentsGroup['Site'] ||= [];
        if (!Vvveb.ComponentsGroup['Site'].includes('site/frontend-menu')) {
            Vvveb.ComponentsGroup['Site'].unshift('site/frontend-menu');
        }
        Vvveb.Builder.loadControlGroups();
    }

    function frontendMenuMarkup(key) {
        return `<nav class="navbar platform-menu-component" data-platform-menu-key="${escapeHtml(key)}" data-platform-menu-layout="horizontal" data-platform-menu-icon="bars" data-platform-menu-side="end" data-platform-menu-font-family="inherit" data-platform-menu-font-size="15px" data-platform-menu-font-weight="500" data-platform-menu-text-color="#1f2937" data-platform-menu-background="#ffffff" data-platform-menu-hover-color="#991b1b" data-platform-menu-hover-background="#fef2f2" data-platform-menu-submenu-background="#ffffff" data-platform-menu-item-margin="0px" data-platform-menu-item-padding="12px" data-platform-menu-offcanvas-width="320px">
            <button type="button" class="platform-menu-toggle" aria-label="Preview menu"><span class="platform-menu-icon platform-menu-icon-bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span></button>
            <div class="platform-menu-surface"><button type="button" class="platform-menu-close" aria-label="Close">&times;</button><div class="platform-menu-items">${frontendMenuItemsMarkup(key)}</div></div>
        </nav>`;
    }

    function frontendMenuItemsMarkup(key) {
        const items = config.frontendMenuItems?.[key] || [];
        if (!items.length) return '<span class="platform-menu-empty">This frontend menu is empty.</span>';

        return items.map(item => {
            const children = Array.isArray(item.children) ? item.children : [];
            const label = escapeHtml(item.label || 'Menu item');
            const link = item.href
                ? `<a href="${escapeHtml(item.href)}" target="${item.target === '_blank' ? '_blank' : '_self'}">${label}</a>`
                : `<span class="platform-menu-link">${label}</span>`;
            if (!children.length) return link;
            return `<span class="platform-menu-item platform-menu-item-has-children">${link}<span class="platform-submenu">${children.map(child => frontendMenuPreviewItem(child)).join('')}</span></span>`;
        }).join('');
    }

    function frontendMenuPreviewItem(item) {
        const children = Array.isArray(item.children) ? item.children : [];
        const label = escapeHtml(item.label || 'Menu item');
        const link = item.href ? `<a href="${escapeHtml(item.href)}">${label}</a>` : `<span class="platform-menu-link">${label}</span>`;
        return children.length
            ? `<span class="platform-menu-item platform-menu-item-has-children">${link}<span class="platform-submenu">${children.map(child => frontendMenuPreviewItem(child)).join('')}</span></span>`
            : link;
    }

    function applyFrontendMenuDesign(node) {
        if (!node?.matches?.('[data-platform-menu-key]')) return;
        const key = node.getAttribute('data-platform-menu-key') || 'platform.frontend';
        const items = node.querySelector('.platform-menu-items');
        if (items) items.innerHTML = frontendMenuItemsMarkup(key);

        const icon = node.getAttribute('data-platform-menu-icon') || 'bars';
        const iconNode = node.querySelector('.platform-menu-icon');
        if (iconNode) iconNode.className = `platform-menu-icon platform-menu-icon-${icon}`;

        const variables = {
            '--platform-menu-font-family': 'data-platform-menu-font-family',
            '--platform-menu-font-size': 'data-platform-menu-font-size',
            '--platform-menu-font-weight': 'data-platform-menu-font-weight',
            '--platform-menu-color': 'data-platform-menu-text-color',
            '--platform-menu-background': 'data-platform-menu-background',
            '--platform-menu-hover-color': 'data-platform-menu-hover-color',
            '--platform-menu-hover-background': 'data-platform-menu-hover-background',
            '--platform-menu-submenu-background': 'data-platform-menu-submenu-background',
            '--platform-menu-item-margin': 'data-platform-menu-item-margin',
            '--platform-menu-item-padding': 'data-platform-menu-item-padding',
            '--platform-menu-offcanvas-width': 'data-platform-menu-offcanvas-width'
        };
        Object.entries(variables).forEach(([variable, attribute]) => {
            const value = node.getAttribute(attribute);
            if (value) {
                node.style.setProperty(variable, value);
            } else {
                node.style.removeProperty(variable);
            }
        });
    }

    function loadRevisions() {
        const host = document.querySelector('[data-vvveb-revisions]');
        fetch(config.revisionsUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(response => response.json())
            .then(data => {
                host.innerHTML = data.revisions.length ? data.revisions.map(revision => `
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-restore="${revision.restore_url}">
                        <span>${escapeHtml(revision.title)}</span><small>${escapeHtml(revision.created_at || '')}</small>
                    </button>`).join('') : '<span class="text-muted">No revisions yet.</span>';
                host.querySelectorAll('[data-restore]').forEach(button => button.addEventListener('click', () => {
                    if (!window.confirm('Restore this revision?')) return;
                    fetch(button.dataset.restore, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken}, credentials: 'same-origin'})
                        .then(response => { if (!response.ok) throw new Error('Restore failed'); return response.json(); })
                        .then(() => window.location.reload())
                        .catch(() => displayToast('bg-danger', 'Revisions', 'Restore failed'));
                }));
            }).catch(() => { host.innerHTML = '<span class="text-danger">Could not load revisions.</span>'; });
    }

    function options(values, selected) {
        return values.map(value => `<option value="${value}" ${value === selected ? 'selected' : ''}>${value[0].toUpperCase() + value.slice(1)}</option>`).join('');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }
})();
