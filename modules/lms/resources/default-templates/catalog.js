document.querySelectorAll('[data-lms-catalog-template]').forEach((root) => {
    if (root.dataset.catalogEnhanced === '1') {
        return;
    }

    root.dataset.catalogEnhanced = '1';
    const form = root.querySelector('[data-catalog-filter-form]');
    const search = root.querySelector('[data-catalog-search]');
    let timer = null;

    const submit = () => {
        root.classList.add('is-submitting');
        form?.requestSubmit();
    };

    form?.addEventListener('submit', () => root.classList.add('is-submitting'));
    root.querySelectorAll('[data-catalog-select]').forEach((select) => {
        select.addEventListener('change', submit);
    });

    search?.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(submit, 450);
    });
});
