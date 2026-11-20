document.querySelectorAll('[data-store-template="default-products"]').forEach((template) => {
    if (template.dataset.storeTemplateReady === '1') return;
    template.dataset.storeTemplateReady = '1';

    template.querySelectorAll('.spt-product').forEach((product, index) => {
        product.setAttribute('aria-posinset', String(index + 1));
        product.setAttribute('aria-setsize', String(template.querySelectorAll('.spt-product').length));
    });
});
