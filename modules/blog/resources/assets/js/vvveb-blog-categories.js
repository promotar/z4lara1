(() => {
    const extension = window.ArtInpaVvvebConfig?.extensions?.['blog.categories'];
    if (!extension || !window.Vvveb || typeof SelectInput === 'undefined' || typeof SectionInput === 'undefined' || typeof NumberInput === 'undefined') return;

    const option = (value, text) => ({value, text});
    const select = (name, key, htmlAttr, options, extra = {}) => ({name, key, htmlAttr, inputtype: SelectInput, inline: false, data: {options}, ...extra});
    const yesNo = [option('1', 'Show'), option('0', 'Hide')];

    Vvveb.Components.extend('_base', 'blog/categories', {
        name: 'Blog Categories',
        attributes: ['data-blog-categories'],
        image: 'icons/posts.svg',
        html: slot(),
        properties: [
            {name: false, key: 'blog_categories_header', inputtype: SectionInput, data: {header: 'Blog Categories'}},
            {name: 'Categories', key: 'blog-categories-limit', htmlAttr: 'data-blog-categories-limit', inputtype: NumberInput, inline: false, col: 6, data: {min: 1, max: 24, step: 1}},
            select('Order', 'blog-categories-order', 'data-blog-categories-order', [option('popular', 'Most posts'), option('name', 'Name: A to Z'), option('name-desc', 'Name: Z to A'), option('newest', 'Newest')], {col: 6}),
            select('Layout', 'blog-categories-layout', 'data-blog-categories-layout', [option('grid', 'Grid'), option('list', 'List')], {col: 6}),
            {name: false, key: 'blog_categories_layout', inputtype: SectionInput, data: {header: 'Responsive Layout'}},
            select('Desktop columns', 'blog-categories-columns-desktop', 'data-blog-categories-columns-desktop', columns(6), {col: 4}),
            select('Tablet columns', 'blog-categories-columns-tablet', 'data-blog-categories-columns-tablet', columns(4), {col: 4}),
            select('Mobile columns', 'blog-categories-columns-mobile', 'data-blog-categories-columns-mobile', columns(2), {col: 4}),
            {name: 'Gap (px)', key: 'blog-categories-gap', htmlAttr: 'data-blog-categories-gap', inputtype: NumberInput, inline: false, col: 6, data: {min: 0, max: 120, step: 1}},
            select('Description', 'blog-categories-show-description', 'data-blog-categories-show-description', yesNo, {col: 6}),
            select('Post count', 'blog-categories-show-count', 'data-blog-categories-show-count', yesNo, {col: 6})
        ],
        init(node) { ensureId(node); preview(node); },
        onChange(node) { ensureId(node); preview(node); return node; }
    });

    Vvveb.ComponentsGroup.Blog ||= [];
    if (!Vvveb.ComponentsGroup.Blog.includes('blog/categories')) Vvveb.ComponentsGroup.Blog.push('blog/categories');
    Vvveb.Builder.loadControlGroups();
    installPreviewLifecycle();

    function installPreviewLifecycle() {
        const component = Vvveb.Components.get('blog/categories');
        if (component) component.afterDrop = node => { ensureId(node); preview(node); return node; };
        const hydrate = doc => {
            if (!doc?.querySelectorAll) return;
            doc.querySelectorAll('[data-blog-categories]').forEach(node => { ensureId(node); preview(node); });
        };
        window.addEventListener('vvveb.iframe.loaded', event => hydrate(event.detail));
        hydrate(Vvveb.Builder.frameDoc);
    }

    function slot() {
        return '<div class="blog-categories-slot" data-blog-categories="1" data-blog-categories-limit="6" data-blog-categories-order="popular" data-blog-categories-layout="grid" data-blog-categories-columns-desktop="3" data-blog-categories-columns-tablet="2" data-blog-categories-columns-mobile="1" data-blog-categories-gap="20" data-blog-categories-show-description="1" data-blog-categories-show-count="1"><span hidden>Blog Categories</span></div>';
    }
    function ensureId(node) {
        if (node.hasAttribute('data-blog-categories-instance')) return;
        const id = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
        node.setAttribute('data-blog-categories-instance', `blog-categories-${id}`);
    }
    function preview(node) {
        if (!node?.attachShadow) return;
        const root = node.shadowRoot || node.attachShadow({mode: 'open'});
        const limit = integer(node, 'data-blog-categories-limit', 6, 1, 24);
        root.innerHTML = extension.hasCategories
            ? `<style>${css(node)}</style><div class="items">${extension.previewHtml || ''}</div>`
            : `<style>${emptyCss()}</style><div class="empty">No blog categories are available.</div>`;
        root.querySelectorAll('.blog-category-card').forEach((card, index) => { card.hidden = index >= limit; });
    }
    function css(node) {
        const layout = node.getAttribute('data-blog-categories-layout') || 'grid';
        const desktop = layout === 'list' ? 1 : integer(node, 'data-blog-categories-columns-desktop', 3, 1, 6);
        const tablet = integer(node, 'data-blog-categories-columns-tablet', 2, 1, 4);
        const mobile = integer(node, 'data-blog-categories-columns-mobile', 1, 1, 2);
        const gap = integer(node, 'data-blog-categories-gap', 20, 0, 120);
        const hide = [node.getAttribute('data-blog-categories-show-description') === '0' ? '.blog-category-description' : '', node.getAttribute('data-blog-categories-show-count') === '0' ? '.blog-category-count' : ''].filter(Boolean).join(',');
        return `:host{display:block}.items{display:grid;grid-template-columns:repeat(${desktop},minmax(0,1fr));gap:${gap}px}.blog-category-card{display:grid;gap:10px;padding:20px;border:1px solid rgba(15,23,42,.12);border-radius:12px;background:#fff;color:#0f172a}.blog-category-card h3,.blog-category-card p{margin:0}.blog-category-card a{color:inherit;text-decoration:none}.blog-category-description{color:#64748b}.blog-category-count{color:#991b1b;font-weight:700}${hide ? `${hide}{display:none}` : ''}${layout === 'list' ? '' : `@media(max-width:991px){.items{grid-template-columns:repeat(${tablet},minmax(0,1fr))}}@media(max-width:575px){.items{grid-template-columns:repeat(${mobile},minmax(0,1fr))}}`}`;
    }
    function emptyCss() { return ':host{display:block}.empty{padding:20px;border:1px dashed #cbd5e1;border-radius:8px;color:#475569}'; }
    function integer(node, attr, fallback, min, max) { const value = Number.parseInt(node.getAttribute(attr) || '', 10); return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback; }
    function columns(max) { return Array.from({length: max}, (_, index) => option(String(index + 1), String(index + 1))); }
})();
