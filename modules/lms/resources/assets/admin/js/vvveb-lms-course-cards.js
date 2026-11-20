(() => {
    const extension = window.ArtInpaVvvebConfig?.extensions?.['lms.courses'];
    if (!extension || !window.Vvveb || typeof SelectInput === 'undefined' || typeof SectionInput === 'undefined' || typeof TextInput === 'undefined' || typeof NumberInput === 'undefined') return;

    const option = (value, text) => ({value, text});
    const select = (name, key, htmlAttr, options, extra = {}) => ({name, key, htmlAttr, inputtype: SelectInput, inline: false, data: {options}, ...extra});
    const yesNo = [option('1', 'Show'), option('0', 'Hide')];

    Vvveb.Components.extend('_base', 'lms/courses', {
        name: 'LMS Courses',
        attributes: ['data-lms-course-template'],
        image: 'icons/products.svg',
        html: slot(),
        properties: [
            {name: false, key: 'lms_courses_content_header', inputtype: SectionInput, data: {header: 'Course Cards'}},
            select('Source', 'lms-course-source', 'data-lms-course-source', [option('latest', 'All published courses'), option('category', 'Category'), option('level', 'Level'), option('search', 'Search text')], {col: 12}),
            select('Category', 'lms-course-category', 'data-lms-course-category', [option('', 'Choose category'), ...(extension.categories || []).map(value => option(value, value))], {col: 6}),
            select('Level', 'lms-course-level', 'data-lms-course-level', [option('', 'Choose level'), ...(extension.levels || []).map(value => option(value, value))], {col: 6}),
            {name: 'Search text', key: 'lms-course-value', htmlAttr: 'data-lms-course-value', inputtype: TextInput, inline: false, col: 8},
            {name: 'Courses', key: 'lms-course-limit', htmlAttr: 'data-lms-course-limit', inputtype: NumberInput, inline: false, col: 4, data: {min: 1, max: 24, step: 1}},
            select('Order', 'lms-course-order', 'data-lms-course-order', [option('newest', 'Newest first'), option('oldest', 'Oldest first'), option('title', 'Course title'), option('price-low', 'Price: low to high'), option('price-high', 'Price: high to low'), option('random', 'Random')], {col: 6}),
            select('Pagination', 'lms-course-pagination', 'data-lms-course-pagination', [option('0', 'Disabled'), option('1', 'Enabled')], {col: 6}),
            select('Image display', 'lms-course-image-fit', 'data-lms-course-image-fit', [option('contain', 'Contain (no cropping)'), option('cover', 'Cover')], {col: 6}),
            {name: false, key: 'lms_courses_layout_header', inputtype: SectionInput, data: {header: 'Responsive Layout'}},
            select('Desktop columns', 'lms-course-columns-desktop', 'data-lms-course-columns-desktop', columns(6), {col: 4}),
            select('Tablet columns', 'lms-course-columns-tablet', 'data-lms-course-columns-tablet', columns(4), {col: 4}),
            select('Mobile columns', 'lms-course-columns-mobile', 'data-lms-course-columns-mobile', columns(2), {col: 4}),
            {name: 'Gap (px)', key: 'lms-course-gap', htmlAttr: 'data-lms-course-gap', inputtype: NumberInput, inline: false, col: 6, data: {min: 0, max: 120, step: 1}},
            {name: false, key: 'lms_courses_details_header', inputtype: SectionInput, data: {header: 'Card Details'}},
            select('Category', 'lms-course-show-category', 'data-lms-course-show-category', yesNo, {col: 4}),
            select('Instructor', 'lms-course-show-instructor', 'data-lms-course-show-instructor', yesNo, {col: 4}),
            select('Lessons', 'lms-course-show-lessons', 'data-lms-course-show-lessons', yesNo, {col: 4}),
            select('Level', 'lms-course-show-level', 'data-lms-course-show-level', yesNo, {col: 4}),
            select('Price', 'lms-course-show-price', 'data-lms-course-show-price', yesNo, {col: 4})
        ],
        init(node) { ensure(node); syncSource(node); preview(node); },
        onChange(node) { ensure(node); syncSource(node); preview(node); return node; }
    });

    Vvveb.ComponentsGroup.LMS ||= [];
    if (!Vvveb.ComponentsGroup.LMS.includes('lms/courses')) Vvveb.ComponentsGroup.LMS.unshift('lms/courses');
    Vvveb.Builder.loadControlGroups();
    installLifecycle();

    function slot() {
        return '<div class="lms-course-cards-slot" data-lms-course-template="cards" data-lms-course-source="latest" data-lms-course-category="" data-lms-course-level="" data-lms-course-value="" data-lms-course-limit="6" data-lms-course-order="newest" data-lms-course-pagination="0" data-lms-course-image-fit="contain" data-lms-course-columns-desktop="3" data-lms-course-columns-tablet="2" data-lms-course-columns-mobile="1" data-lms-course-gap="24" data-lms-course-show-category="1" data-lms-course-show-instructor="1" data-lms-course-show-lessons="1" data-lms-course-show-level="1" data-lms-course-show-price="1"><span hidden>LMS Courses</span></div>';
    }

    function installLifecycle() {
        const component = Vvveb.Components.get('lms/courses');
        if (component) component.afterDrop = node => { component.init(node); return node; };
        const hydrate = doc => {
            if (!doc?.querySelectorAll) return;
            doc.querySelectorAll('[data-lms-course-template]').forEach(node => component?.init?.(node));
        };
        window.addEventListener('vvveb.iframe.loaded', event => hydrate(event.detail));
        hydrate(Vvveb.Builder.frameDoc);
    }

    function ensure(node) {
        if (node.hasAttribute('data-lms-course-instance')) return;
        const id = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
        node.setAttribute('data-lms-course-instance', `lms-courses-${id}`);
    }

    function syncSource(node) {
        const source = node.getAttribute('data-lms-course-source') || 'latest';
        if (source === 'category') node.setAttribute('data-lms-course-value', node.getAttribute('data-lms-course-category') || '');
        if (source === 'level') node.setAttribute('data-lms-course-value', node.getAttribute('data-lms-course-level') || '');
    }

    function preview(node) {
        if (!node?.attachShadow) return;
        const root = node.shadowRoot || node.attachShadow({mode: 'open'});
        root.innerHTML = extension.hasCourses
            ? `<style>${css(node)}</style><div class="items">${extension.previewHtml || ''}</div>`
            : '<style>:host{display:block}.empty{padding:20px;border:1px dashed #cbd5e1;border-radius:8px;color:#475569}</style><div class="empty">No published courses are available.</div>';
        if (!extension.hasCourses) return;

        const source = node.getAttribute('data-lms-course-source') || 'latest';
        const value = (node.getAttribute('data-lms-course-value') || '').trim().toLowerCase();
        const cards = [...root.querySelectorAll('.lms-course-card')];
        const filtered = cards.filter(card => {
            if (!value || source === 'latest') return true;
            if (source === 'category') return (card.dataset.courseCategory || '').toLowerCase() === value;
            if (source === 'level') return (card.dataset.courseLevel || '').toLowerCase() === value;
            return card.textContent.toLowerCase().includes(value);
        });
        const order = node.getAttribute('data-lms-course-order') || 'newest';
        filtered.sort((left, right) => {
            if (order === 'oldest') return numeric(left, 'coursePublished') - numeric(right, 'coursePublished');
            if (order === 'title') return (left.dataset.courseTitle || '').localeCompare(right.dataset.courseTitle || '');
            if (order === 'price-low') return numeric(left, 'coursePrice') - numeric(right, 'coursePrice');
            if (order === 'price-high') return numeric(right, 'coursePrice') - numeric(left, 'coursePrice');
            if (order === 'random') return Math.random() - 0.5;
            return numeric(right, 'coursePublished') - numeric(left, 'coursePublished');
        });
        filtered.forEach(card => root.querySelector('.items').append(card));
        const limit = integer(node, 'data-lms-course-limit', 6, 1, 24);
        cards.forEach(card => { card.hidden = !filtered.includes(card) || filtered.indexOf(card) >= limit; });
    }

    function css(node) {
        const desktop=integer(node,'data-lms-course-columns-desktop',3,1,6),tablet=integer(node,'data-lms-course-columns-tablet',2,1,4),mobile=integer(node,'data-lms-course-columns-mobile',1,1,2),gap=integer(node,'data-lms-course-gap',24,0,120),fit=node.getAttribute('data-lms-course-image-fit')==='cover'?'cover':'contain';
        const hide=[node.getAttribute('data-lms-course-show-category')==='0'?'.lms-course-card-category':'',node.getAttribute('data-lms-course-show-instructor')==='0'?'.lms-course-card-instructor':'',node.getAttribute('data-lms-course-show-lessons')==='0'?'.lms-course-card-lessons':'',node.getAttribute('data-lms-course-show-level')==='0'?'.lms-course-card-level':'',node.getAttribute('data-lms-course-show-price')==='0'?'.lms-course-card-price':''].filter(Boolean).join(',');
        return `:host{display:block}.items{display:grid;grid-template-columns:repeat(${desktop},minmax(0,1fr));gap:${gap}px}.lms-course-card{min-width:0;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a}.lms-course-card-image{display:flex;aspect-ratio:16/10;align-items:center;justify-content:center;overflow:hidden;background:#f5eeee}.lms-course-card-image img{width:100%;height:100%;object-fit:${fit}}.lms-course-card-body{display:grid;gap:10px;padding:18px}.lms-course-card h3,.lms-course-card p{margin:0}.lms-course-card a{color:inherit;text-decoration:none}.lms-course-card-category,.lms-course-card-footer a{color:#991b1b}.lms-course-card-instructor,.lms-course-card-meta{color:#64748b}.lms-course-card-meta,.lms-course-card-footer{display:flex;justify-content:space-between;gap:12px}${hide?`${hide}{display:none}`:''}@media(max-width:991px){.items{grid-template-columns:repeat(${tablet},minmax(0,1fr))}}@media(max-width:575px){.items{grid-template-columns:repeat(${mobile},minmax(0,1fr))}}`;
    }

    function numeric(card, key) { return Number.parseFloat(card.dataset[key] || '0') || 0; }
    function integer(node, attr, fallback, min, max) { const value=Number.parseInt(node.getAttribute(attr)||'',10);return Number.isFinite(value)?Math.max(min,Math.min(max,value)):fallback; }
    function columns(max) { return Array.from({length:max},(_,index)=>option(String(index+1),String(index+1))); }
})();
