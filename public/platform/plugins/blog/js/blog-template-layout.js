(() => {
    function initialize(root = document) {
        root.querySelectorAll('[data-blog-template-rendered][data-blog-template-layout="slider"]').forEach(wrapper => {
            const track = wrapper.querySelector('[data-blog-template-items]');
            if (!track || track.dataset.blogSliderReady === '1') return;

            track.dataset.blogSliderReady = '1';
            const controls = document.createElement('div');
            controls.className = 'blog-template-slider-controls';
            controls.setAttribute('aria-label', 'Blog slider controls');
            controls.innerHTML = '<button type="button" data-blog-slide="previous" aria-label="Previous slides">&#8592;</button><button type="button" data-blog-slide="next" aria-label="Next slides">&#8594;</button>';
            track.after(controls);

            controls.addEventListener('click', event => {
                const button = event.target.closest('[data-blog-slide]');
                if (!button) return;
                const direction = button.dataset.blogSlide === 'previous' ? -1 : 1;
                track.scrollBy({left: direction * Math.max(240, track.clientWidth), behavior: 'smooth'});
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialize());
    } else {
        initialize();
    }

    document.addEventListener('blog:template-updated', event => initialize(event.detail?.root || document));
})();
