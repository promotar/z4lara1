export function popupSectionsFromPayload(payload) {
    const labels = { artwork: 'Artwork', details: 'Details', artist: 'Artist', delivery: 'Delivery' };
    return Object.entries(payload?.sections || payload?.tabs || {})
        .filter(([key, fields]) => labels[key] && fields && Object.keys(fields).length > 0)
        .map(([id, fields]) => ({ id, label: labels[id], fields }));
}

function element(name, className, text) {
    const node = document.createElement(name);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
}

export class ArtworkPopup {
    constructor(root, dialog, onClosed, onAddToCart) {
        this.root = root;
        this.dialog = dialog;
        this.onClosed = onClosed;
        this.onAddToCart = onAddToCart;
        this.body = dialog.querySelector('[data-ve03-popup-body]');
        this.closeButton = dialog.querySelector('[data-ve03-popup-close]');
        this.previousFocus = null;
        this.currentPlacementId = null;
        this.closeReason = 'user';
        this.closeButton.addEventListener('click', () => this.close('user'));
        this.onCancel = (event) => {
            event.preventDefault();
            this.close('user');
        };
        this.onDialogClose = () => this.finishClose();
        this.dialog.addEventListener('cancel', this.onCancel);
        this.dialog.addEventListener('close', this.onDialogClose);
    }

    async open(placement) {
        if (!placement?.artwork?.popup_url || this.dialog.open) return false;
        this.currentPlacementId = String(placement.id || '');
        this.closeReason = 'user';
        this.previousFocus = document.activeElement;
        this.setViewerInert(true);
        this.body.replaceChildren(element('p', 'z4-ve03-popup-loading', 'Loading artwork details…'));
        this.dialog.showModal();
        this.closeButton.focus();
        try {
            const response = await fetch(placement.artwork.popup_url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Artwork details returned HTTP ${response.status}.`);
            this.render(await response.json());
            return true;
        } catch (error) {
            this.body.replaceChildren(element('p', 'z4-ve03-popup-error', error instanceof Error ? error.message : 'Artwork details are unavailable.'));
            return false;
        }
    }

    render(payload) {
        const artwork = payload?.artwork || {};
        const layout = element('div', 'z4-ve03-popup-layout');
        const image = element('img', 'z4-ve03-popup-image');
        image.src = artwork.image_url || '';
        image.alt = artwork.title || 'Artwork';
        layout.append(image);

        const copy = element('div', 'z4-ve03-popup-copy');
        copy.append(
            element('span', 'z4-ve03-popup-availability', artwork.availability || ''),
            element('h2', null, artwork.title || 'Artwork'),
            element('p', 'z4-ve03-popup-artist', artwork.artist || ''),
            element('p', null, artwork.short_description || ''),
        );
        const sections = popupSectionsFromPayload(payload);
        if (sections.length > 0) copy.append(this.sections(sections));
        const footer = element('footer', 'z4-ve03-popup-footer');
        footer.append(element('strong', null, this.price(artwork.price)));
        if (artwork.store_variant_public_id && this.onAddToCart) {
            const purchase = element('div', 'z4-ve03-popup-purchase');
            const addButton = element('button', null, 'Add to cart');
            const feedback = element('span', null, 'Original artwork · quantity 1');
            addButton.type = 'button';
            addButton.addEventListener('click', async () => {
                if (!this.onAddToCart || addButton.disabled) return;
                addButton.disabled = true;
                addButton.textContent = 'Adding…';
                feedback.textContent = '';
                try {
                    const result = await this.onAddToCart(payload);
                    addButton.textContent = result?.outcome === 'already_in_cart' ? 'Already in cart' : 'Added to cart';
                    feedback.textContent = result?.message || 'Your selection is saved. Keep exploring or open the cart.';
                } catch (error) {
                    addButton.disabled = false;
                    addButton.textContent = 'Try again';
                    feedback.textContent = error instanceof Error ? error.message : 'This artwork could not be added.';
                }
            });
            purchase.append(addButton, feedback);
            footer.append(purchase);
        }
        copy.append(footer);
        layout.append(copy);
        this.body.replaceChildren(layout);
    }

    sections(sections) {
        const wrapper = element('div', 'z4-ve03-popup-sections');
        sections.forEach((section) => {
            const panel = element('section', 'z4-ve03-popup-section');
            const heading = element('h3', null, section.label);
            heading.id = `ve03-section-${section.id}`;
            panel.setAttribute('aria-labelledby', heading.id);
            const list = element('dl');
            Object.entries(section.fields).forEach(([label, value]) => {
                const row = element('div');
                row.append(element('dt', null, label), element('dd', null, value));
                list.append(row);
            });
            panel.append(heading, list);
            wrapper.append(panel);
        });
        return wrapper;
    }

    price(price) {
        if (!price || !Number.isFinite(Number(price.minor))) return 'Price unavailable';
        const divisor = 10 ** Number(price.scale || 0);
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: price.currency || 'JOD',
            minimumFractionDigits: Number(price.scale || 0),
        }).format(Number(price.minor) / divisor);
    }

    close(reason = 'user') {
        this.closeReason = reason;
        if (this.dialog.open) this.dialog.close();
    }

    closeForAvailability(placementId) {
        if (!this.dialog.open || String(placementId || '') !== this.currentPlacementId) return false;
        this.close('availability');
        return true;
    }

    finishClose() {
        const reason = this.closeReason || 'user';
        this.setViewerInert(false);
        this.previousFocus?.focus?.();
        this.previousFocus = null;
        this.currentPlacementId = null;
        this.closeReason = 'user';
        this.onClosed?.(reason);
    }

    setViewerInert(inert) {
        [...this.root.children].filter((child) => child !== this.dialog).forEach((child) => {
            child.inert = inert;
        });
    }

    dispose() {
        if (this.dialog.open) this.close('dispose');
        this.dialog.removeEventListener('cancel', this.onCancel);
        this.dialog.removeEventListener('close', this.onDialogClose);
    }
}
