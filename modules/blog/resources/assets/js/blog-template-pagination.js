(() => {
    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-blog-pagination-load-more]');

        if (!link || link.getAttribute('aria-disabled') === 'true') {
            return;
        }

        const wrapper = link.closest('[data-blog-template-rendered]');
        const instance = wrapper?.getAttribute('data-blog-template-rendered');
        const url = link.getAttribute('href');

        if (!wrapper || !instance || !url) {
            return;
        }

        event.preventDefault();
        link.setAttribute('aria-busy', 'true');
        link.classList.add('is-loading');

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'text/html'},
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Blog page request failed with ${response.status}`);
            }

            const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const selector = `[data-blog-template-rendered="${cssEscape(instance)}"]`;
            const nextWrapper = nextDocument.querySelector(selector);
            const currentResults = wrapper.querySelector('[data-blog-template-results]');
            const nextResults = nextWrapper?.querySelector('[data-blog-template-results]');

            if (!currentResults || !nextResults) {
                throw new Error('Blog pagination response did not contain the expected template instance.');
            }

            const currentItems = currentResults.querySelector('[data-blog-template-items]');
            const nextItems = nextResults.querySelector('[data-blog-template-items]');
            if (currentItems && nextItems) {
                [...nextItems.children].forEach(node => currentItems.append(document.importNode(node, true)));
            } else {
                [...nextResults.childNodes].forEach(node => currentResults.append(document.importNode(node, true)));
            }

            const currentNavigation = wrapper.querySelector('[data-blog-template-pagination]');
            const nextNavigation = nextWrapper.querySelector('[data-blog-template-pagination]');
            if (nextNavigation) {
                currentNavigation?.replaceWith(document.importNode(nextNavigation, true));
            } else {
                currentNavigation?.remove();
            }
            document.dispatchEvent(new CustomEvent('blog:template-updated', {detail: {root: wrapper}}));
        } catch (error) {
            window.location.assign(url);
        } finally {
            link.removeAttribute('aria-busy');
            link.classList.remove('is-loading');
        }
    });

    function cssEscape(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(value);
        }

        return String(value).replace(/[^a-zA-Z0-9_-]/g, character => `\\${character}`);
    }
})();
