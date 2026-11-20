(() => {
    const platformConfig = window.ArtInpaVvvebConfig;
    const extension = platformConfig?.extensions?.['lms.courses'];

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

    Vvveb.Components.extend('_base', 'lms/courses', {
        name: 'LMS Courses',
        attributes: ['data-lms-course-template'],
        image: 'icons/products.svg',
        html: slotMarkup(firstTemplate),
        properties: [
            {name: false, key: 'lms_courses_content_header', inputtype: SectionInput, data: {header: 'LMS Courses'}},
            select(
                'Course template',
                'lms-course-template',
                'data-lms-course-template',
                templates.length
                    ? templates.map(template => option(template.slug, template.name))
                    : [option('', 'No active course templates')],
                {col: 12}
            ),
            select('Course source', 'lms-course-source', 'data-lms-course-source', [
                option('latest', 'All published courses'),
                option('category', 'Category'),
                option('level', 'Level'),
                option('search', 'Search text')
            ], {col: 12}),
            select(
                'Category',
                'lms-course-category',
                'data-lms-course-category',
                [option('', 'Choose category'), ...(extension.categories || []).map(value => option(value, value))],
                {col: 6}
            ),
            select(
                'Level',
                'lms-course-level',
                'data-lms-course-level',
                [option('', 'Choose level'), ...(extension.levels || []).map(value => option(value, value))],
                {col: 6}
            ),
            {name: 'Search text', key: 'lms-course-value', htmlAttr: 'data-lms-course-value', inputtype: TextInput, inline: false, col: 8},
            {name: 'Courses per page', key: 'lms-course-limit', htmlAttr: 'data-lms-course-limit', inputtype: NumberInput, inline: false, col: 4, data: {min: 1, max: 24, step: 1}},
            select('Order', 'lms-course-order', 'data-lms-course-order', [
                option('newest', 'Newest first'),
                option('oldest', 'Oldest first'),
                option('title', 'Course title'),
                option('price-low', 'Price: low to high'),
                option('price-high', 'Price: high to low')
            ], {col: 6}),
            select('Pagination', 'lms-course-pagination', 'data-lms-course-pagination', [option('1', 'Enabled'), option('0', 'Disabled')], {col: 6}),
            {name: false, key: 'lms_courses_layout_header', inputtype: SectionInput, data: {header: 'Responsive Layout'}},
            select('Display', 'lms-course-layout', 'data-lms-course-layout', [
                option('template', 'Template default'),
                option('grid', 'Grid'),
                option('cards', 'Cards'),
                option('slider', 'Horizontal slider')
            ], {col: 12}),
            select('Desktop columns', 'lms-course-columns-desktop', 'data-lms-course-columns-desktop', columnOptions(6), {col: 4}),
            select('Tablet columns', 'lms-course-columns-tablet', 'data-lms-course-columns-tablet', columnOptions(4), {col: 4}),
            select('Mobile columns', 'lms-course-columns-mobile', 'data-lms-course-columns-mobile', columnOptions(2), {col: 4}),
            {name: 'Column gap (px)', key: 'lms-course-gap', htmlAttr: 'data-lms-course-gap', inputtype: NumberInput, inline: false, col: 6, data: {min: 0, max: 120, step: 1}}
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

    Vvveb.ComponentsGroup.LMS ||= [];
    if (!Vvveb.ComponentsGroup.LMS.includes('lms/courses')) {
        Vvveb.ComponentsGroup.LMS.unshift('lms/courses');
    }
    Vvveb.Builder.loadControlGroups();

    function slotMarkup(slug) {
        return `<div class="lms-course-template-slot" data-lms-course-template="${escapeHtml(slug)}" data-lms-course-source="latest" data-lms-course-category="" data-lms-course-level="" data-lms-course-value="" data-lms-course-limit="6" data-lms-course-order="newest" data-lms-course-pagination="1" data-lms-course-layout="grid" data-lms-course-columns-desktop="3" data-lms-course-columns-tablet="2" data-lms-course-columns-mobile="1" data-lms-course-gap="24"><span hidden>LMS Courses</span></div>`;
    }

    function ensureInstanceId(node) {
        if (node.hasAttribute('data-lms-course-instance')) return;
        const random = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
        node.setAttribute('data-lms-course-instance', `lms-${random}`);
    }

    function synchronizeSourceValue(node) {
        const source = node.getAttribute('data-lms-course-source') || 'latest';
        if (source === 'category') node.setAttribute('data-lms-course-value', node.getAttribute('data-lms-course-category') || '');
        if (source === 'level') node.setAttribute('data-lms-course-value', node.getAttribute('data-lms-course-level') || '');
    }

    function renderPreview(node) {
        if (!node?.attachShadow) return;
        const template = templateBySlug[node.getAttribute('data-lms-course-template') || ''];
        const root = node.shadowRoot || node.attachShadow({mode: 'open'});
        root.innerHTML = template
            ? `<style>${previewFrameCss()}${layoutPreviewCss(node)}</style><div class="lms-course-template-preview">${template.previewHtml || ''}</div>`
            : `<style>${previewFrameCss()}</style><div class="lms-course-template-empty">Create and activate an LMS Courses template first.</div>`;
    }

    function previewFrameCss() {
        return `:host{display:block;min-height:72px}.lms-course-template-preview{display:block;min-height:72px}.lms-course-template-empty{display:grid;min-height:96px;place-items:center;padding:20px;border:1px dashed #d9b5b5;border-radius:8px;background:#fff8f8;color:#7f1d1d;font:600 13px/1.5 system-ui,sans-serif}`;
    }

    function layoutPreviewCss(node) {
        const desktop = integerAttribute(node, 'data-lms-course-columns-desktop', 3, 1, 6);
        const tablet = integerAttribute(node, 'data-lms-course-columns-tablet', 2, 1, 4);
        const mobile = integerAttribute(node, 'data-lms-course-columns-mobile', 1, 1, 2);
        const gap = integerAttribute(node, 'data-lms-course-gap', 24, 0, 120);
        const layout = node.getAttribute('data-lms-course-layout') || 'template';
        if (layout === 'template') return '';
        const track = layout === 'slider'
            ? `display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - ${(desktop - 1) * gap}px)/${desktop});grid-template-columns:none;overflow-x:auto;scroll-snap-type:x mandatory`
            : `display:grid;grid-template-columns:repeat(${desktop},minmax(0,1fr))`;
        const cards = layout === 'cards' ? '[data-lms-template-items]>*{overflow:hidden;border:1px solid #eadada;border-radius:12px;background:#fff}' : '';
        return `[data-lms-template-items]{${track};position:relative;width:100%;grid-column:1/-1;flex:0 0 100%;gap:${gap}px}[data-lms-template-items]>*{min-width:0;scroll-snap-align:start}${cards}@media(max-width:991px){[data-lms-template-items]{${layout === 'slider' ? `grid-auto-columns:calc((100% - ${(tablet - 1) * gap}px)/${tablet})` : `grid-template-columns:repeat(${tablet},minmax(0,1fr))`}}}@media(max-width:575px){[data-lms-template-items]{${layout === 'slider' ? `grid-auto-columns:calc((100% - ${(mobile - 1) * gap}px)/${mobile})` : `grid-template-columns:repeat(${mobile},minmax(0,1fr))`}}}`;
    }

    function integerAttribute(node, name, fallback, min, max) {
        const value = Number.parseInt(node.getAttribute(name) || '', 10);
        return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback;
    }

    function columnOptions(max) {
        return Array.from({length: max}, (_, index) => option(String(index + 1), String(index + 1)));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'})[character]);
    }
})();
