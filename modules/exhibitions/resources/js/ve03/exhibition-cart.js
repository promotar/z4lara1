function node(name, className, text) {
    const element = document.createElement(name);
    if (className) element.className = className;
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
}

export class ExhibitionCart {
    constructor(root, config, callbacks = {}) {
        this.root = root;
        this.config = config;
        this.callbacks = callbacks;
        this.openButton = root.querySelector('[data-ve03-cart-open]');
        this.count = root.querySelector('[data-ve03-cart-count]');
        this.drawer = root.querySelector('[data-ve03-cart-drawer]');
        this.closeButton = root.querySelector('[data-ve03-cart-close]');
        this.backdrop = root.querySelector('[data-ve03-cart-backdrop]');
        this.items = root.querySelector('[data-ve03-cart-items]');
        this.subtotal = root.querySelector('[data-ve03-cart-subtotal]');
        this.warning = root.querySelector('[data-ve03-cart-warning]');
        this.checkout = root.querySelector('[data-ve03-checkout]');
        this.feedback = root.querySelector('[data-ve03-cart-feedback]');
        this.previousFocus = null;
        this.summary = { count: 0, items: [], subtotal: '0.000 JOD' };
        this.heldVariants = new Set();
        this.available = Boolean(config.cart_summary_url && config.cart_add_url && config.cart_remove_url && config.checkout_start_url);
        this.classicAvailable = Boolean(config.classic_cart_add_url && config.classic_cart_url);
        // Artist placement mode reuses the public viewer runtime without cart markup.
        // Treat a missing cart trigger as an intentional no-cart surface.
        if (!this.openButton) {
            this.available = false;
            return;
        }
        if (!this.available) {
            this.openButton.hidden = true;
            return;
        }
        this.openButton.addEventListener('click', () => this.open());
        this.closeButton.addEventListener('click', () => this.close());
        this.backdrop.addEventListener('click', () => this.close());
        this.checkout.addEventListener('click', () => this.beginCheckout());
        this.onKeydown = (event) => {
            if (event.key === 'Escape' && !this.drawer.hidden) {
                event.preventDefault();
                this.close();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
        this.refresh().catch(() => this.showFeedback('Cart status is temporarily unavailable.', true));
    }

    canAdd() {
        return this.available || this.classicAvailable;
    }

    containsVariant(variant) {
        if (!variant) return false;
        return this.heldVariants.has(String(variant))
            || (Array.isArray(this.summary?.items) ? this.summary.items : [])
            .some((item) => String(item?.variant_public_id || '') === String(variant));
    }

    headers() {
        return {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.config.csrf_token,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    async request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { ...this.headers(), ...(options.headers || {}) },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || `Cart request returned HTTP ${response.status}.`);
        return payload;
    }

    async refresh() {
        const payload = await this.request(this.config.cart_summary_url, { method: 'GET' });
        this.render(payload);
        return payload;
    }

    async add(payload) {
        const variant = payload?.artwork?.store_variant_public_id;
        if (!variant) throw new Error('This artwork is not available for purchase.');
        if (!this.available && this.classicAvailable) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.config.classic_cart_add_url;
            form.hidden = true;
            [
                ['_token', this.config.csrf_token],
                ['variant_public_id', variant],
                ['quantity', '1'],
            ].forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value || '';
                form.append(input);
            });
            document.body.append(form);
            form.submit();
            return { outcome: 'redirecting', message: 'Opening your cart…' };
        }
        if (!this.available) throw new Error('Purchasing is temporarily unavailable.');
        this.heldVariants.add(String(variant));
        try {
            const result = await this.request(this.config.cart_add_url, {
                method: 'POST',
                body: JSON.stringify({ variant_public_id: variant, source: payload.source }),
            });
            this.render(result.cart);
            this.showFeedback(result.message || 'Artwork added to cart.');
            this.callbacks.onAdded?.(result);
            return result;
        } catch (error) {
            this.heldVariants.delete(String(variant));
            throw error;
        }
    }

    async remove(variant, button) {
        button.disabled = true;
        try {
            const url = this.config.cart_remove_url.replace('__VARIANT__', encodeURIComponent(variant));
            const result = await this.request(url, { method: 'DELETE' });
            this.heldVariants.delete(String(variant));
            this.render(result.cart);
            this.showFeedback(result.message || 'Artwork removed from cart.');
            this.callbacks.onRemoved?.(result);
        } catch (error) {
            button.disabled = false;
            this.showFeedback(error instanceof Error ? error.message : 'Artwork could not be removed.', true);
        }
    }

    async open() {
        if (!this.available || !this.drawer.hidden) return;
        this.previousFocus = document.activeElement;
        this.drawer.hidden = false;
        this.backdrop.hidden = false;
        this.openButton.setAttribute('aria-expanded', 'true');
        this.root.dataset.cartOpen = 'true';
        this.callbacks.onOpened?.();
        this.closeButton.focus();
        try {
            await this.refresh();
        } catch (error) {
            this.showFeedback(error instanceof Error ? error.message : 'Cart status is unavailable.', true);
        }
    }

    close() {
        if (this.drawer.hidden) return;
        this.drawer.hidden = true;
        this.backdrop.hidden = true;
        this.openButton.setAttribute('aria-expanded', 'false');
        delete this.root.dataset.cartOpen;
        this.callbacks.onClosed?.();
        this.previousFocus?.focus?.();
        this.previousFocus = null;
    }

    render(summary) {
        this.summary = summary || { count: 0, items: [], subtotal: '0.000 JOD' };
        const cartItems = Array.isArray(this.summary.items) ? this.summary.items : [];
        this.heldVariants = new Set(cartItems.map((item) => String(item?.variant_public_id || '')).filter(Boolean));
        this.count.textContent = String(Number(this.summary.count || 0));
        this.count.hidden = cartItems.length === 0;
        this.subtotal.textContent = this.summary.subtotal || '0.000 JOD';
        this.items.replaceChildren();
        if (cartItems.length === 0) {
            const empty = node('div', 'z4-ve03-cart-empty');
            empty.append(node('strong', null, 'Your artwork cart is empty'), node('p', null, 'Open an available artwork and choose Add to cart.'));
            this.items.append(empty);
        } else {
            cartItems.forEach((item) => this.items.append(this.line(item)));
        }
        const hasUnavailable = cartItems.some((item) => item.availability !== 'available');
        this.warning.hidden = !hasUnavailable;
        this.checkout.disabled = cartItems.length === 0 || hasUnavailable;
    }

    line(item) {
        const article = node('article', 'z4-ve03-cart-line');
        if (item.image_url) {
            const image = node('img');
            image.src = item.image_url;
            image.alt = item.title || 'Artwork';
            article.append(image);
        }
        const copy = node('div');
        copy.append(
            node('span', `z4-ve03-cart-status is-${item.availability}`, item.availability === 'available' ? 'Available' : 'Unavailable'),
            node('strong', null, item.title || 'Artwork'),
            node('small', null, item.artist || 'Artist'),
            node('b', null, item.price || ''),
        );
        const remove = node('button', null, 'Remove');
        remove.type = 'button';
        remove.addEventListener('click', () => this.remove(item.variant_public_id, remove));
        article.append(copy, remove);
        return article;
    }

    async beginCheckout() {
        if (this.checkout.disabled) return;
        this.checkout.disabled = true;
        this.checkout.textContent = 'Opening checkout…';
        try {
            const result = await this.request(this.config.checkout_start_url, {
                method: 'POST',
                body: JSON.stringify({ return_context: this.callbacks.returnContext?.() }),
            });
            this.callbacks.onCheckout?.();
            location.assign(result.checkout_url);
        } catch (error) {
            this.checkout.disabled = false;
            this.checkout.textContent = 'Proceed to checkout';
            this.showFeedback(error instanceof Error ? error.message : 'Checkout could not be opened.', true);
        }
    }

    showFeedback(message, error = false) {
        this.feedback.hidden = !message;
        this.feedback.textContent = message || '';
        this.feedback.classList.toggle('is-error', error);
    }

    dispose() {
        document.removeEventListener('keydown', this.onKeydown);
    }
}
