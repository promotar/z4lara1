(() => {
    const extension = window.ArtInpaVvvebConfig?.extensions?.['lms.instructors'];
    if (!extension || !window.Vvveb || typeof SelectInput === 'undefined' || typeof SectionInput === 'undefined' || typeof NumberInput === 'undefined') return;
    const option = (value, text) => ({value, text});
    const select = (name, key, htmlAttr, options, extra = {}) => ({name, key, htmlAttr, inputtype: SelectInput, inline: false, data: {options}, ...extra});
    const yesNo = [option('1', 'Show'), option('0', 'Hide')];

    Vvveb.Components.extend('_base', 'lms/instructors', {
        name: 'Instructors', attributes: ['data-lms-instructors'], image: 'icons/user.svg', html: slot(),
        properties: [
            {name: false, key: 'lms_instructors_header', inputtype: SectionInput, data: {header: 'Instructors'}},
            {name: 'Instructors', key: 'lms-instructors-limit', htmlAttr: 'data-lms-instructors-limit', inputtype: NumberInput, inline: false, col: 6, data: {min: 1, max: 24, step: 1}},
            select('Order', 'lms-instructors-order', 'data-lms-instructors-order', [option('courses', 'Most courses'), option('students', 'Most students'), option('newest', 'Newest course'), option('name', 'Name')], {col: 6}),
            {name: false, key: 'lms_instructors_layout', inputtype: SectionInput, data: {header: 'Responsive Layout'}},
            select('Desktop columns', 'lms-instructors-columns-desktop', 'data-lms-instructors-columns-desktop', columns(6), {col: 4}),
            select('Tablet columns', 'lms-instructors-columns-tablet', 'data-lms-instructors-columns-tablet', columns(4), {col: 4}),
            select('Mobile columns', 'lms-instructors-columns-mobile', 'data-lms-instructors-columns-mobile', columns(2), {col: 4}),
            {name: 'Gap (px)', key: 'lms-instructors-gap', htmlAttr: 'data-lms-instructors-gap', inputtype: NumberInput, inline: false, col: 6, data: {min: 0, max: 120, step: 1}},
            select('Title', 'lms-instructors-show-title', 'data-lms-instructors-show-title', yesNo, {col: 6}),
            select('Statistics', 'lms-instructors-show-stats', 'data-lms-instructors-show-stats', yesNo, {col: 6})
        ],
        init(node) { ensure(node); preview(node); }, onChange(node) { ensure(node); preview(node); return node; }
    });
    Vvveb.ComponentsGroup.LMS ||= [];
    if (!Vvveb.ComponentsGroup.LMS.includes('lms/instructors')) Vvveb.ComponentsGroup.LMS.push('lms/instructors');
    Vvveb.Builder.loadControlGroups();
    installPreviewLifecycle();

    function installPreviewLifecycle() {
        const component = Vvveb.Components.get('lms/instructors');
        if (component) component.afterDrop = node => { ensure(node); preview(node); return node; };
        const hydrate = doc => {
            if (!doc?.querySelectorAll) return;
            doc.querySelectorAll('[data-lms-instructors]').forEach(node => { ensure(node); preview(node); });
        };
        window.addEventListener('vvveb.iframe.loaded', event => hydrate(event.detail));
        hydrate(Vvveb.Builder.frameDoc);
    }

    function slot() { return '<div class="lms-instructors-slot" data-lms-instructors="1" data-lms-instructors-limit="6" data-lms-instructors-order="courses" data-lms-instructors-columns-desktop="3" data-lms-instructors-columns-tablet="2" data-lms-instructors-columns-mobile="1" data-lms-instructors-gap="20" data-lms-instructors-show-title="1" data-lms-instructors-show-stats="1"><span hidden>Instructors</span></div>'; }
    function ensure(node) { if (node.hasAttribute('data-lms-instructors-instance')) return; const id = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`; node.setAttribute('data-lms-instructors-instance', `lms-instructors-${id}`); }
    function preview(node) {
        if (!node?.attachShadow) return; const root = node.shadowRoot || node.attachShadow({mode: 'open'}); const limit = integer(node, 'data-lms-instructors-limit', 6, 1, 24);
        root.innerHTML = extension.hasInstructors ? `<style>${css(node)}</style><div class="items">${extension.previewHtml || ''}</div>` : '<div class="empty">No public instructors are available.</div>';
        root.querySelectorAll('.lms-instructor-card').forEach((card, index) => { card.hidden = index >= limit; });
    }
    function css(node) { const d=integer(node,'data-lms-instructors-columns-desktop',3,1,6),t=integer(node,'data-lms-instructors-columns-tablet',2,1,4),m=integer(node,'data-lms-instructors-columns-mobile',1,1,2),g=integer(node,'data-lms-instructors-gap',20,0,120); const hide=[node.getAttribute('data-lms-instructors-show-title')==='0'?'.lms-instructor-title':'',node.getAttribute('data-lms-instructors-show-stats')==='0'?'.lms-instructor-stats':''].filter(Boolean).join(','); return `:host{display:block}.items{display:grid;grid-template-columns:repeat(${d},minmax(0,1fr));gap:${g}px}.lms-instructor-card{display:flex;align-items:center;gap:16px;padding:20px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.lms-instructor-avatar{display:grid;flex:0 0 56px;width:56px;height:56px;place-items:center;border-radius:50%;background:#991b1b;color:#fff;text-decoration:none}.lms-instructor-card h3,.lms-instructor-card p{margin:0}.lms-instructor-card a{color:inherit;text-decoration:none}.lms-instructor-title{color:#64748b}.lms-instructor-stats{display:flex;gap:10px;color:#991b1b}${hide?`${hide}{display:none}`:''}@media(max-width:991px){.items{grid-template-columns:repeat(${t},minmax(0,1fr))}}@media(max-width:575px){.items{grid-template-columns:repeat(${m},minmax(0,1fr))}}`; }
    function integer(node,a,f,min,max){const v=Number.parseInt(node.getAttribute(a)||'',10);return Number.isFinite(v)?Math.max(min,Math.min(max,v)):f;} function columns(max){return Array.from({length:max},(_,i)=>option(String(i+1),String(i+1)));}
})();
