(() => {
    const platformConfig = window.ArtInpaVvvebConfig;
    const extension = platformConfig?.extensions?.['blog.templates'];

    if (
        !extension
        || !window.Vvveb
        || typeof SelectInput === 'undefined'
        || typeof SectionInput === 'undefined'
        || typeof TextInput === 'undefined'
        || typeof NumberInput === 'undefined'
    ) {
        return;
    }

    const templates = Array.isArray(extension.templates) ? extension.templates : [];
    const templateBySlug = Object.fromEntries(templates.map(template => [template.slug, template]));
    const firstTemplate = templates[0]?.slug || '';
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

    Vvveb.Components.extend('_base', 'blog/template', {
        name: 'Blog Template',
        attributes: ['data-blog-template-slug'],
        image: 'icons/code.svg',
        html: slotMarkup(firstTemplate),
        properties: [
            {
                name: false,
                key: 'blog_template_content_header',
                inputtype: SectionInput,
                data: {header: 'Blog Template'}
            },
            select(
                'Template',
                'blog-template-slug',
                'data-blog-template-slug',
                templates.length
                    ? templates.map(template => option(template.slug, `${template.name} · ${template.category}`))
                    : [option('', 'No active templates')],
                {col: 12}
            ),
            select('Content source', 'blog-template-source', 'data-blog-template-source', [
                option('latest', 'Latest posts'),
                option('single', 'Single post'),
                option('category', 'Category'),
                option('tag', 'Tag'),
                option('search', 'Search query')
            ], {col: 12}),
            select('Pagination', 'blog-template-pagination', 'data-blog-template-pagination', [
                option('1', 'Enabled'),
                option('0', 'Disabled')
            ], {col: 6}),
            select('Pagination style', 'blog-template-pagination-style', 'data-blog-template-pagination-style', [
                option('numbers', 'Page numbers'),
                option('simple', 'Previous / Next'),
                option('load-more', 'Load more')
            ], {col: 6}),
            select(
                'Category',
                'blog-template-category',
                'data-blog-template-category',
                [option('', 'Choose category'), ...(extension.categories || []).map(item => option(item.slug, item.name))],
                {col: 6}
            ),
            select(
                'Tag',
                'blog-template-tag',
                'data-blog-template-tag',
                [option('', 'Choose tag'), ...(extension.tags || []).map(item => option(item.slug, item.name))],
                {col: 6}
            ),
            {
                name: 'Post slug / search',
                key: 'blog-template-value',
                htmlAttr: 'data-blog-template-value',
                inputtype: TextInput,
                inline: false,
                col: 8
            },
            {
                name: 'Limit',
                key: 'blog-template-limit',
                htmlAttr: 'data-blog-template-limit',
                inputtype: NumberInput,
                inline: false,
                col: 4,
                data: {min: 1, max: 24, step: 1}
            },
            {
                name: false,
                key: 'blog_template_layout_header',
                inputtype: SectionInput,
                data: {header: 'Responsive Layout'}
            },
            select('Display', 'blog-template-layout', 'data-blog-template-layout', [
                option('template', 'Template default'),
                option('grid', 'Grid'),
                option('cards', 'Cards'),
                option('slider', 'Slider')
            ], {col: 12}),
            select('Desktop columns', 'blog-template-columns-desktop', 'data-blog-template-columns-desktop', columnOptions(6), {col: 4}),
            select('Tablet columns', 'blog-template-columns-tablet', 'data-blog-template-columns-tablet', columnOptions(4), {col: 4}),
            select('Mobile columns', 'blog-template-columns-mobile', 'data-blog-template-columns-mobile', columnOptions(2), {col: 4}),
            {
                name: 'Column gap (px)',
                key: 'blog-template-gap',
                htmlAttr: 'data-blog-template-gap',
                inputtype: NumberInput,
                inline: false,
                col: 6,
                data: {min: 0, max: 120, step: 1}
            }
        ],
        init(node) {
            ensureInstanceId(node);
            synchronizeSourceValue(node);
            renderPreview(node);
        },
        onChange(node) {
            ensureInstanceId(node);
            synchronizeSourceValue(node);
            renderPreview(node);

            return node;
        }
    });

    Vvveb.ComponentsGroup.Blog ||= [];
    if (!Vvveb.ComponentsGroup.Blog.includes('blog/template')) {
        Vvveb.ComponentsGroup.Blog.unshift('blog/template');
    }
    Vvveb.Builder.loadControlGroups();

    function slotMarkup(slug) {
        return `<div class="blog-template-slot" data-blog-template-slug="${escapeHtml(slug)}" data-blog-template-source="latest" data-blog-template-category="" data-blog-template-tag="" data-blog-template-value="" data-blog-template-limit="6" data-blog-template-pagination="1" data-blog-template-pagination-style="numbers" data-blog-template-layout="grid" data-blog-template-columns-desktop="3" data-blog-template-columns-tablet="2" data-blog-template-columns-mobile="1" data-blog-template-gap="24"><span hidden>Blog Template</span></div>`;
    }

    function ensureInstanceId(node) {
        if (node.hasAttribute('data-blog-template-instance')) {
            return;
        }

        const random = window.crypto?.randomUUID?.()
            || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
        node.setAttribute('data-blog-template-instance', `bt-${random}`);
    }

    function synchronizeSourceValue(node) {
        const source = node.getAttribute('data-blog-template-source') || 'latest';
        if (source === 'category') {
            node.setAttribute('data-blog-template-value', node.getAttribute('data-blog-template-category') || '');
        } else if (source === 'tag') {
            node.setAttribute('data-blog-template-value', node.getAttribute('data-blog-template-tag') || '');
        }
    }

    function renderPreview(node) {
        if (!node?.attachShadow) {
            return;
        }

        const template = templateBySlug[node.getAttribute('data-blog-template-slug') || ''];
        const root = node.shadowRoot || node.attachShadow({mode: 'open'});
        root.innerHTML = template
            ? `<style>${previewFrameCss()}${layoutPreviewCss(node)}</style><div class="blog-template-preview" data-blog-template-layout="${escapeHtml(node.getAttribute('data-blog-template-layout') || 'template')}">${template.previewHtml || ''}</div>`
            : `<style>${previewFrameCss()}</style><div class="blog-template-empty">Create and activate a Blog template first.</div>`;
    }

    function previewFrameCss() {
        return `
            :host { display: block; min-height: 72px; }
            .blog-template-preview { display: block; min-height: 72px; }
            .blog-template-empty {
                display: grid; min-height: 96px; place-items: center; padding: 20px;
                border: 1px dashed #d9b5b5; border-radius: 8px; background: #fff8f8;
                color: #7f1d1d; font: 600 13px/1.5 system-ui, sans-serif;
            }
        `;
    }

    function layoutPreviewCss(node) {
        const desktop = integerAttribute(node, 'data-blog-template-columns-desktop', 3, 1, 6);
        const tablet = integerAttribute(node, 'data-blog-template-columns-tablet', 2, 1, 4);
        const mobile = integerAttribute(node, 'data-blog-template-columns-mobile', 1, 1, 2);
        const gap = integerAttribute(node, 'data-blog-template-gap', 24, 0, 120);
        const layout = node.getAttribute('data-blog-template-layout') || 'template';
        if (layout === 'template') return '';
        const cardCss = layout === 'cards'
            ? '[data-blog-template-items] > * { background:#fff; border:1px solid #eadada; border-radius:12px; overflow:hidden; box-shadow:0 8px 22px rgba(44,20,20,.08); }'
            : '';
        const trackCss = layout === 'slider'
            ? `display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - ${(desktop - 1) * gap}px)/${desktop});grid-template-columns:none;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;`
            : `display:grid;grid-template-columns:repeat(${desktop},minmax(0,1fr));`;

        return `[data-blog-template-items]{${trackCss}position:relative;width:100%;grid-column:1/-1;flex:0 0 100%;gap:${gap}px}[data-blog-template-items]>*{min-width:0;scroll-snap-align:start}${cardCss}
            @media(max-width:991px){[data-blog-template-items]{${layout === 'slider' ? `grid-auto-columns:calc((100% - ${(tablet - 1) * gap}px)/${tablet})` : `grid-template-columns:repeat(${tablet},minmax(0,1fr))`}}}
            @media(max-width:575px){[data-blog-template-items]{${layout === 'slider' ? `grid-auto-columns:calc((100% - ${(mobile - 1) * gap}px)/${mobile})` : `grid-template-columns:repeat(${mobile},minmax(0,1fr))`}}}`;
    }

    function integerAttribute(node, name, fallback, min, max) {
        const value = Number.parseInt(node.getAttribute(name) || '', 10);
        return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback;
    }

    function columnOptions(max) {
        return Array.from({length: max}, (_, index) => option(String(index + 1), String(index + 1)));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[character]);
    }
})();
