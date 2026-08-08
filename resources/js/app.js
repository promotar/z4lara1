import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();


/*
 * Auto text direction for mixed Arabic/English content.
 * This changes text direction only. It intentionally keeps text alignment inherited
 * so page layout and left/right alignment do not move.
 */
const autoDirectionSelector = [
    'a', 'button', 'label', 'legend',
    'p', 'span', 'small', 'strong', 'em',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'li', 'td', 'th', 'dt', 'dd',
    'blockquote', 'figcaption', 'summary',
    '[data-auto-dir]'
].join(',');

const skipAutoDirectionTags = new Set([
    'HTML', 'BODY', 'SCRIPT', 'STYLE', 'SVG', 'PATH',
    'FORM', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR',
    'UL', 'OL', 'NAV', 'MAIN', 'SECTION', 'ARTICLE'
]);

const layoutClassPattern = /(^|\s)(flex|inline-flex|grid|inline-grid|table|flow-root)(\s|$)/;

function hasDirectText(element) {
    return Array.from(element.childNodes).some((node) => (
        node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0
    ));
}

function shouldAutoDirection(element) {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    if (element.hasAttribute('dir')) {
        return false;
    }

    if (skipAutoDirectionTags.has(element.tagName)) {
        return false;
    }

    if (layoutClassPattern.test(element.className || '')) {
        return false;
    }

    return element.matches(autoDirectionSelector) || hasDirectText(element);
}

function applyAutoDirection(root = document) {
    root.querySelectorAll('input[type="text"], input[type="search"], input[type="email"], textarea').forEach((field) => {
        if (!field.hasAttribute('dir')) {
            field.setAttribute('dir', 'auto');
            field.dataset.autoDirection = 'true';
        }
    });

    root.querySelectorAll(autoDirectionSelector).forEach((element) => {
        if (shouldAutoDirection(element)) {
            element.setAttribute('dir', 'auto');
            element.dataset.autoDirection = 'true';
        }
    });

    root.querySelectorAll('div').forEach((element) => {
        if (shouldAutoDirection(element)) {
            element.setAttribute('dir', 'auto');
            element.dataset.autoDirection = 'true';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyAutoDirection();

    document.querySelectorAll('[data-auth-toggle-password]').forEach((button) => {
        const targetId = button.getAttribute('aria-controls');
        const input = targetId ? document.getElementById(targetId) : null;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.textContent = shouldShow ? 'Hide' : 'Show';
            button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
        });
    });

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    applyAutoDirection(node);
                }
            });
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
