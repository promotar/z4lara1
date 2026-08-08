document.querySelectorAll('[data-blog-template="default-slider"]').forEach((template) => {
    if (template.dataset.blogTemplateReady === '1') return;
    template.dataset.blogTemplateReady = '1';

    template.querySelectorAll('.dbt-slider__track article').forEach((slide, index) => {
        slide.setAttribute('aria-label', `Slide ${index + 1}`);
    });
});
