(() => {
    const root = document.querySelector('[data-store-template-studio]');
    if (!root) return;

    const html = root.querySelector('[name="html_code"]');
    const css = root.querySelector('[name="css_code"]');
    const js = root.querySelector('[name="js_code"]');
    const frame = root.querySelector('[data-template-preview]');
    const productsNode = root.querySelector('[data-template-preview-products]');
    let products = [];
    try { products = JSON.parse(productsNode?.textContent || '[]'); } catch (_) { products = []; }

    root.querySelectorAll('[data-template-tab]').forEach(button => button.addEventListener('click', () => {
        root.querySelectorAll('[data-template-tab]').forEach(item => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('[data-template-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.templatePanel === button.dataset.templateTab));
    }));

    root.querySelectorAll('[data-preview-size]').forEach(button => button.addEventListener('click', () => {
        root.querySelectorAll('[data-preview-size]').forEach(item => item.classList.toggle('is-active', item === button));
        frame.classList.remove('is-tablet', 'is-mobile');
        if (button.dataset.previewSize !== 'desktop') frame.classList.add(`is-${button.dataset.previewSize}`);
        requestAnimationFrame(syncHeight);
    }));

    root.querySelectorAll('[data-template-token]').forEach(button => button.addEventListener('click', async () => {
        const token = button.dataset.templateToken || '';
        if (!token || !html || html.readOnly) return;
        const start = html.selectionStart ?? html.value.length;
        const end = html.selectionEnd ?? start;
        html.setRangeText(token, start, end, 'end');
        html.focus();
        try { await navigator.clipboard?.writeText(token); } catch (_) {}
        render();
    }));

    [html, css, js].filter(Boolean).forEach(field => field.addEventListener('input', debounce(render, 120)));
    root.querySelector('[data-refresh-template]')?.addEventListener('click', render);
    root.querySelector('[data-delete-template]')?.addEventListener('click', () => {
        if (window.confirm('Delete this product template permanently?')) root.querySelector('[data-delete-template-form]')?.submit();
    });

    function render() {
        if (!frame) return;
        updateLineCounts();
        const markup = renderMarkup(html?.value || '', products);
        const script = (js?.value || '').replace(/<\/script/gi, '<\\/script');
        frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;min-height:0;background:#fff}*{box-sizing:border-box}${css?.value || ''}</style></head><body>${markup}<script>${script}<\/script></body></html>`;
        frame.addEventListener('load', syncHeight, { once: true });
    }

    function renderMarkup(source, items) {
        let output = source.replace(/\{\{#products\}\}([\s\S]*?)\{\{\/products\}\}/gi, (_, inner) => items.map(product => renderProduct(inner, product)).join(''));
        if (items.length === 1) output = renderProduct(output, items[0]);
        return replace(output, { site_name: document.title || 'Store', catalog_title: 'Template preview', results_count: String(items.length) });
    }

    function renderProduct(source, raw) {
        const product = normalized(raw);
        let output = collection(source, 'categories', product.category_items, item => ({ name: item.name || '', slug: item.slug || '', url: `/store/categories/${encodeURIComponent(item.slug || '')}` }));
        output = collection(output, 'tags', product.tag_items, item => ({ name: item, slug: item }));
        ['has_image', 'on_sale', 'is_featured', 'available', 'is_artwork'].forEach(name => {
            const active = product[name] === '1';
            output = output.replace(new RegExp(`\\{\\{#${name}\\}\\}([\\s\\S]*?)\\{\\{\\/${name}\\}\\}`, 'gi'), (_, body) => active ? body : '');
            output = output.replace(new RegExp(`\\{\\{\\^${name}\\}\\}([\\s\\S]*?)\\{\\{\\/${name}\\}\\}`, 'gi'), (_, body) => active ? '' : body);
        });
        return replace(output, product);
    }

    function normalized(raw) {
        const image = raw.image || {};
        const categories = Array.isArray(raw.categories) ? raw.categories : [];
        const tags = Array.isArray(raw.tags) ? raw.tags.map(tag => typeof tag === 'string' ? tag : (tag.name || tag.slug || '')) : [];
        const artwork = raw.artwork || {};
        const available = Boolean(raw.available), sale = Boolean(raw.on_sale), featured = Boolean(raw.is_featured);
        return Object.fromEntries(Object.entries({
            public_id: raw.public_id || '', name: raw.name || '', slug: raw.slug || '', url: `/store/products/${encodeURIComponent(raw.slug || '')}`,
            quick_view_url: `/store/products/${encodeURIComponent(raw.slug || '')}/quick-view`, type_code: raw.type_code || 'simple', short_description: strip(raw.short_description), description: strip(raw.description),
            artist_name: raw.artist_name || '', brand: raw.brand_name || '', brand_slug: raw.brand_slug || '', brand_url: raw.brand_slug ? `/store/brands/${encodeURIComponent(raw.brand_slug)}` : '',
            sku: raw.sku || '', variant_id: raw.variant_public_id || '', price: raw.price_formatted || '', price_minor: String(raw.price_minor || 0), regular_price: raw.regular_price_formatted || '', regular_price_minor: String(raw.regular_price_minor || 0), currency: raw.currency || 'JOD',
            image: image.url || '', image_alt: image.alt || raw.name || '', categories: categories.map(item => item.name).filter(Boolean).join(', '), tags: tags.filter(Boolean).join(', '), category_items: categories, tag_items: tags,
            availability: available ? 'in-stock' : 'out-of-stock', availability_label: available ? 'In stock' : 'Out of stock', stock_quantity: raw.available_quantity ?? '', badge: sale ? 'Sale' : (featured ? 'Featured' : ''), published_at: raw.published_at || '',
            completion_year: artwork.completion_year || '', work_type: artwork.work_type || '', edition_type: artwork.edition_type || '', artwork_category: artwork.category || '', artistic_style: artwork.artistic_style || '', technique: artwork.technique || '',
            has_image: image.url ? '1' : '0', on_sale: sale ? '1' : '0', is_featured: featured ? '1' : '0', available: available ? '1' : '0', is_artwork: raw.type_code === 'artwork' ? '1' : '0'
        }).map(([key, value]) => [key, Array.isArray(value) ? value : escapeHtml(String(value ?? ''))]));
    }

    function collection(source, name, items, map) {
        return source.replace(new RegExp(`\\{\\{#${name}\\}\\}([\\s\\S]*?)\\{\\{\\/${name}\\}\\}`, 'gi'), (_, body) => items.map(item => replace(body, Object.fromEntries(Object.entries(map(item)).map(([key, value]) => [key, escapeHtml(String(value ?? ''))])))).join(''));
    }

    function replace(source, values) {
        return source.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (token, key) => Object.prototype.hasOwnProperty.call(values, key.toLowerCase()) ? values[key.toLowerCase()] : token);
    }

    function strip(value) {
        const node = document.createElement('div');
        node.innerHTML = String(value || '');
        return (node.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function escapeHtml(value) {
        return value.replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    }

    function syncHeight() {
        try {
            const height = Math.min(2000, Math.max(320, frame.contentDocument.documentElement.scrollHeight, frame.contentDocument.body.scrollHeight));
            frame.style.height = `${height}px`;
        } catch (_) { frame.style.height = '520px'; }
    }

    function updateLineCounts() {
        [['html', html], ['css', css], ['js', js]].forEach(([name, field]) => {
            const target = root.querySelector(`[data-line-count="${name}"]`);
            if (target) target.textContent = `${field?.value ? field.value.split(/\r?\n/).length : 0} lines`;
        });
    }

    function debounce(callback, delay) {
        let timer;
        return () => { window.clearTimeout(timer); timer = window.setTimeout(callback, delay); };
    }

    render();
})();
