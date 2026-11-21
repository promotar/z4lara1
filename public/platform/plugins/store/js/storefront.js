(() => {
    'use strict';

    const init = (root) => {
        root.querySelectorAll('[data-store-filter-toggle]').forEach((button) => {
            const target = root.querySelector(`#${button.getAttribute('aria-controls')}`);
            if (!target) return;
            button.addEventListener('click', () => {
                const open = target.classList.toggle('is-open');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        root.querySelectorAll('[data-store-auto-submit]').forEach((select) => {
            select.addEventListener('change', () => {
                const form = select.form;
                if (!form) return;

                const sourceId = select.dataset.storePreserveForm;
                const source = sourceId ? root.querySelector(`#${sourceId}`) : null;
                if (source) {
                    form.querySelectorAll('[data-store-preserved-filter]').forEach((field) => field.remove());
                    const values = new FormData(source);
                    values.delete('sort');
                    values.delete('page');
                    values.forEach((value, name) => {
                        if (typeof value !== 'string' || value === '') return;
                        const field = document.createElement('input');
                        field.type = 'hidden';
                        field.name = name;
                        field.value = value;
                        field.dataset.storePreservedFilter = 'true';
                        form.append(field);
                    });
                }

                form.requestSubmit();
            });
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || root.querySelector('input[name="_token"]')?.value
            || '';
        const toast = root.querySelector('[data-store-toast]');
        let toastTimer;

        const announce = (message, error = false) => {
            if (!toast || !message) return;
            window.clearTimeout(toastTimer);
            toast.textContent = message;
            toast.hidden = false;
            toast.classList.toggle('is-error', error);
            requestAnimationFrame(() => toast.classList.add('is-visible'));
            toastTimer = window.setTimeout(() => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => { toast.hidden = true; }, 180);
            }, 3200);
        };

        const paintCartCount = (count) => {
            document.querySelectorAll('[data-store-cart-count]').forEach((node) => {
                node.textContent = String(Math.max(0, Number(count || 0)));
            });
        };

        const paintCartControl = (control, state) => {
            if (!control) return;
            const quantity = Math.max(0, Number(state?.cart_quantity ?? control.dataset.quantity ?? 0));
            const maximum = Math.max(0, Number(state?.maximum_quantity ?? control.dataset.maximum ?? 0));
            const canAdd = typeof state?.can_add === 'boolean'
                ? state.can_add
                : maximum > quantity;
            const busy = control.dataset.busy === '1';
            control.dataset.quantity = String(quantity);
            control.dataset.maximum = String(maximum);

            const form = control.querySelector('[data-store-card-add-form]');
            const stepper = control.querySelector('[data-store-cart-stepper]');
            const output = control.querySelector('[data-store-cart-quantity]');
            if (form) form.hidden = quantity > 0;
            if (stepper) stepper.hidden = quantity === 0;
            if (output) output.textContent = String(quantity);
            control.querySelectorAll('button').forEach((button) => { button.disabled = busy; });
            const add = control.querySelector('[data-store-card-add-form] button');
            if (add) add.disabled = busy || !canAdd;
            const minus = control.querySelector('[data-store-cart-minus]');
            if (minus) minus.disabled = busy || quantity === 0;
            const plus = control.querySelector('[data-store-cart-plus]');
            if (plus) plus.disabled = busy || !canAdd || maximum === 0 || quantity >= maximum;

            const variant = control.dataset.variantId || '';
            root.querySelectorAll('[data-store-quick-variant] option').forEach((option) => {
                if (option.value !== variant) return;
                option.dataset.quantity = String(quantity);
                option.dataset.maximum = String(maximum);
                option.dataset.canAdd = canAdd ? '1' : '0';
            });
        };

        const paintVariant = (state) => {
            const variant = state?.variant_public_id;
            if (!variant) return;
            root.querySelectorAll('[data-store-cart-control]').forEach((control) => {
                if (control.dataset.variantId === variant) paintCartControl(control, state);
            });
        };

        const mutateCart = async (control, method, url, values = {}) => {
            if (!control || !url || control.dataset.busy === '1') return;
            control.dataset.busy = '1';
            paintCartControl(control);
            try {
                const body = new URLSearchParams(values);
                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: method === 'GET' ? undefined : body,
                });
                const payload = await response.json().catch(() => ({}));
                if (payload.item) paintVariant(payload.item);
                if (typeof payload.cart_count !== 'undefined') paintCartCount(payload.cart_count);
                announce(payload.message || (response.ok ? 'Cart updated.' : 'Could not update the cart.'), !response.ok);
            } catch {
                announce('Could not update the cart. Please try again.', true);
            } finally {
                control.dataset.busy = '0';
                paintCartControl(control);
            }
        };

        root.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-store-card-add-form]');
            if (!form || !root.contains(form)) return;
            event.preventDefault();
            const control = form.closest('[data-store-cart-control]');
            mutateCart(control, 'POST', control?.dataset.addUrl, {
                variant_public_id: control?.dataset.variantId || '',
                quantity: '1',
            });
        });

        root.addEventListener('click', (event) => {
            const plus = event.target.closest('[data-store-cart-plus]');
            const minus = event.target.closest('[data-store-cart-minus]');
            if (!plus && !minus) return;
            const control = (plus || minus).closest('[data-store-cart-control]');
            const quantity = Math.max(0, Number(control?.dataset.quantity || 0));
            if (plus) {
                mutateCart(control, 'POST', control?.dataset.addUrl, {
                    variant_public_id: control?.dataset.variantId || '',
                    quantity: '1',
                });
                return;
            }
            if (quantity <= 1) {
                mutateCart(control, 'DELETE', control?.dataset.removeUrl);
            } else {
                mutateCart(control, 'PATCH', control?.dataset.updateUrl, { quantity: String(quantity - 1) });
            }
        });

        const cartStateUrl = root.dataset.storeCartStateUrl;
        if (cartStateUrl) {
            fetch(cartStateUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : Promise.reject())
                .then((payload) => {
                    paintCartCount(payload.cart_count);
                    root.querySelectorAll('[data-store-cart-control]').forEach((control) => {
                        const quantity = Number(payload.quantities?.[control.dataset.variantId] || 0);
                        paintCartControl(control, {
                            cart_quantity: quantity,
                            maximum_quantity: Number(control.dataset.maximum || 0),
                            can_add: quantity < Number(control.dataset.maximum || 0),
                        });
                    });
                })
                .catch(() => {});
        }

        const quickDialog = root.querySelector('[data-store-quick-dialog]');
        const quickContent = quickDialog?.querySelector('[data-store-quick-content]');
        const closeQuickView = () => {
            if (!quickDialog) return;
            if (typeof quickDialog.close === 'function') quickDialog.close();
            else quickDialog.removeAttribute('open');
        };

        root.querySelector('[data-store-quick-close]')?.addEventListener('click', closeQuickView);
        quickDialog?.addEventListener('click', (event) => {
            if (event.target === quickDialog) closeQuickView();
        });

        root.addEventListener('click', async (event) => {
            const trigger = event.target.closest('[data-store-quick-view]');
            if (!trigger || !quickDialog || !quickContent) return;
            event.preventDefault();
            quickContent.innerHTML = '<div class="z4sf-quick-loading" aria-live="polite">Loading product…</div>';
            if (typeof quickDialog.showModal === 'function') quickDialog.showModal();
            else quickDialog.setAttribute('open', '');
            try {
                const response = await fetch(trigger.dataset.storeQuickView, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error();
                quickContent.innerHTML = await response.text();
                quickDialog.querySelector('[data-store-quick-variant]')?.dispatchEvent(new Event('change', { bubbles: true }));
            } catch {
                quickContent.innerHTML = `<div class="z4sf-quick-error"><p>Could not load this product.</p><a href="${trigger.href}">Open product page</a></div>`;
            }
        });

        root.addEventListener('change', (event) => {
            const select = event.target.closest('[data-store-quick-variant]');
            if (!select) return;
            const option = select.selectedOptions[0];
            const product = select.closest('[data-store-quick-product]');
            const control = product?.querySelector('[data-store-cart-control]');
            if (!option || !control) return;

            control.dataset.variantId = option.value;
            control.dataset.updateUrl = option.dataset.updateUrl || '';
            control.dataset.removeUrl = option.dataset.removeUrl || '';
            const variantInput = control.querySelector('input[name="variant_public_id"]');
            if (variantInput) variantInput.value = option.value;
            paintCartControl(control, {
                cart_quantity: Number(option.dataset.quantity || 0),
                maximum_quantity: Number(option.dataset.maximum || 0),
                can_add: option.dataset.canAdd === '1',
            });

            const price = product.querySelector('[data-store-quick-price]');
            if (price) {
                price.replaceChildren();
                const strong = document.createElement('strong');
                strong.textContent = option.dataset.price || '';
                price.append(strong);
                if (option.dataset.regularPrice) {
                    const del = document.createElement('del');
                    del.textContent = option.dataset.regularPrice;
                    price.append(del);
                }
            }
            const image = product.querySelector('[data-store-quick-image]');
            if (image && option.dataset.image) {
                image.src = option.dataset.image;
                image.alt = option.dataset.imageAlt || image.alt;
            }
        });

        root.querySelectorAll('[data-store-gallery]').forEach((gallery) => {
            const image = gallery.querySelector('[data-store-main-image]');
            if (!image) return;

            const thumbs = Array.from(gallery.querySelectorAll('[data-store-gallery-thumb]'));
            const counter = gallery.querySelector('[data-store-gallery-current]');
            let activeIndex = Math.max(0, thumbs.findIndex((thumb) => thumb.classList.contains('is-active')));

            const selectImage = (index) => {
                if (thumbs.length === 0) return;
                activeIndex = (index + thumbs.length) % thumbs.length;
                const activeThumb = thumbs[activeIndex];
                image.src = activeThumb.dataset.src || image.src;
                image.alt = activeThumb.dataset.alt || image.alt;
                thumbs.forEach((candidate, candidateIndex) => {
                    const active = candidateIndex === activeIndex;
                    candidate.classList.toggle('is-active', active);
                    candidate.setAttribute('aria-current', active ? 'true' : 'false');
                });
                if (counter) counter.textContent = String(activeIndex + 1);
                activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            };

            thumbs.forEach((thumb, index) => {
                thumb.addEventListener('click', () => {
                    selectImage(index);
                });
            });
            gallery.querySelector('[data-store-gallery-previous]')?.addEventListener('click', () => selectImage(activeIndex - 1));
            gallery.querySelector('[data-store-gallery-next]')?.addEventListener('click', () => selectImage(activeIndex + 1));
        });

        root.querySelectorAll('[data-store-product]').forEach((product) => {
            const value = product.querySelector('[data-store-variant-value]');
            const price = product.querySelector('[data-store-product-price]');
            const stock = product.querySelector('[data-store-product-stock]');
            const salePeriod = product.querySelector('[data-store-sale-period]');
            const salePeriodValue = product.querySelector('[data-store-sale-period-value]');
            const stockQuantity = product.querySelector('[data-store-stock-quantity]');
            const stockQuantityValue = product.querySelector('[data-store-stock-quantity-value]');
            const cartCapacity = product.querySelector('[data-store-cart-capacity]');
            const cartCapacityMessage = product.querySelector('[data-store-cart-capacity-message]');
            const viewCart = product.querySelector('[data-store-view-cart]');
            const quantityControl = product.querySelector('[data-store-quantity]');
            const quantity = product.querySelector('[data-store-quantity] input');
            const submit = product.querySelector('[data-store-add-button]');
            const addForm = product.querySelector('[data-store-add-form]');
            const skuLabels = root.querySelectorAll('[data-store-product-sku]');
            const mainImage = root.querySelector('[data-store-main-image]');
            const galleryThumbs = Array.from(root.querySelectorAll('[data-store-gallery-thumb]'));
            const galleryCounter = root.querySelector('[data-store-gallery-current]');
            const fallbackImage = mainImage?.getAttribute('src') || '';
            const fallbackImageAlt = mainImage?.getAttribute('alt') || '';
            const variants = Array.from(product.querySelectorAll('[data-store-variant]'));
            const optionInputs = Array.from(product.querySelectorAll('[data-store-option]'));
            const variantOptions = new Map(variants.map((variant) => {
                try {
                    return [variant, JSON.parse(variant.dataset.options || '{}')];
                } catch {
                    return [variant, {}];
                }
            }));

            const showVariant = (variant) => {
                if (!variant) {
                    if (value) value.value = '';
                    if (quantity) quantity.disabled = true;
                    quantityControl?.querySelectorAll('button').forEach((button) => {
                        button.disabled = true;
                    });
                    if (cartCapacity) cartCapacity.hidden = true;
                    if (viewCart) viewCart.hidden = true;
                    if (submit) {
                        submit.disabled = true;
                        submit.textContent = submit.dataset.selectLabel || '';
                    }
                    return;
                }

                if (value) value.value = variant.dataset.variantId || '';
                const maximum = Math.max(0, Number(variant.dataset.maximum || 0));
                const cartQuantity = Math.max(0, Number(variant.dataset.cartQuantity || 0));
                const remaining = Math.max(0, Number(variant.dataset.remaining || (maximum - cartQuantity)));
                const available = variant.dataset.available === '1';
                const atLimit = cartQuantity > 0 && remaining === 0;
                const canAdd = available && remaining > 0;
                if (quantity) {
                    quantity.max = String(Math.max(1, remaining));
                    quantity.value = Math.min(Number(quantity.value || 1), Number(quantity.max || 99));
                    quantity.disabled = !canAdd;
                }
                quantityControl?.querySelectorAll('button').forEach((button) => {
                    button.disabled = !canAdd;
                });
                if (cartCapacity && cartCapacityMessage) {
                    cartCapacity.hidden = cartQuantity === 0;
                    const template = atLimit
                        ? (cartCapacity.dataset.limitTemplate || '')
                        : (cartCapacity.dataset.remainingTemplate || '');
                    cartCapacityMessage.textContent = template
                        .replace('{quantity}', String(cartQuantity))
                        .replace('{remaining}', String(remaining));
                }
                if (viewCart) viewCart.hidden = !atLimit;
                if (price) {
                    price.replaceChildren();
                    const strong = document.createElement('strong');
                    strong.textContent = variant.dataset.price || '';
                    price.append(strong);
                    if (variant.dataset.regularPrice) {
                        const regular = document.createElement('del');
                        regular.textContent = variant.dataset.regularPrice;
                        price.append(regular);
                    }
                }
                if (salePeriod && salePeriodValue) {
                    salePeriodValue.textContent = variant.dataset.salePeriod || '';
                    salePeriod.hidden = !variant.dataset.salePeriod;
                }
                const tracksQuantity = variant.dataset.trackingMode === 'tracked';
                if (stock) {
                    stock.classList.toggle('is-available', available);
                    stock.classList.toggle('is-unavailable', !available);
                    stock.hidden = tracksQuantity && available;
                    stock.textContent = available
                        ? (tracksQuantity
                            ? (stock.dataset.availableLabel || '')
                            : (stock.dataset.untrackedLabel || stock.dataset.availableLabel || ''))
                        : (stock.dataset.unavailableLabel || '');
                }
                if (stockQuantity && stockQuantityValue) {
                    stockQuantityValue.textContent = tracksQuantity ? (variant.dataset.maximum || '0') : '';
                    stockQuantity.hidden = !tracksQuantity;
                }
                if (variant.dataset.sku) {
                    skuLabels.forEach((sku) => {
                        sku.textContent = variant.dataset.sku;
                    });
                }
                if (mainImage) {
                    const selectedImage = variant.dataset.image || fallbackImage;
                    mainImage.src = selectedImage;
                    mainImage.alt = variant.dataset.imageAlt || fallbackImageAlt;
                    galleryThumbs.forEach((thumb, index) => {
                        const active = thumb.dataset.src === selectedImage;
                        thumb.classList.toggle('is-active', active);
                        thumb.setAttribute('aria-current', active ? 'true' : 'false');
                        if (active && galleryCounter) galleryCounter.textContent = String(index + 1);
                    });
                }
                if (submit) {
                    submit.disabled = !canAdd;
                    submit.textContent = !available
                        ? (submit.dataset.unavailableLabel || '')
                        : (atLimit ? (submit.dataset.limitLabel || '') : (submit.dataset.addLabel || ''));
                }
            };

            const refreshVariant = () => {
                const selection = {};
                optionInputs.forEach((input) => {
                    if (input.checked) selection[input.dataset.attributeCode] = input.value;
                });
                const selectedAxes = Object.keys(selection);
                const matchingVariant = variants.find((variant) => {
                    const options = variantOptions.get(variant) || {};
                    const optionAxes = Object.keys(options);

                    return selectedAxes.length === optionAxes.length
                        && optionAxes.every((axis) => selection[axis] === options[axis]);
                });

                showVariant(matchingVariant);
            };

            optionInputs.forEach((input) => {
                const globallyAvailable = variants.some((variant) => {
                    const options = variantOptions.get(variant) || {};

                    return variant.dataset.available === '1'
                        && options[input.dataset.attributeCode] === input.value;
                });
                input.disabled = !globallyAvailable;
                input.closest('label')?.classList.toggle('is-unavailable', !globallyAvailable);
                input.addEventListener('change', refreshVariant);
            });
            if (optionInputs.length > 0) refreshVariant();

            addForm?.addEventListener('submit', () => {
                if (!submit || submit.disabled) return;
                submit.disabled = true;
                submit.textContent = submit.dataset.addingLabel || submit.textContent;
            });
        });

        root.querySelectorAll('[data-store-quantity]').forEach((control) => {
            const input = control.querySelector('input');
            if (!input) return;
            control.querySelector('[data-store-quantity-minus]')?.addEventListener('click', () => {
                input.value = Math.max(Number(input.min || 1), Number(input.value || 1) - 1);
            });
            control.querySelector('[data-store-quantity-plus]')?.addEventListener('click', () => {
                input.value = Math.min(Number(input.max || 99), Number(input.value || 1) + 1);
            });
        });

        root.querySelectorAll('[data-store-artwork-detail]').forEach((artwork) => {
            const favorite = artwork.querySelector('[data-store-artwork-favorite]');
            const favoriteKey = `z4.favorite.artwork.${artwork.dataset.artworkId || 'unknown'}`;
            if (favorite) {
                const paintFavorite = () => {
                    const saved = localStorage.getItem(favoriteKey) === '1';
                    favorite.setAttribute('aria-pressed', saved ? 'true' : 'false');
                    favorite.textContent = saved ? '♥' : '♡';
                };
                paintFavorite();
                favorite.addEventListener('click', () => {
                    localStorage.setItem(favoriteKey, localStorage.getItem(favoriteKey) === '1' ? '0' : '1');
                    paintFavorite();
                });
            }

            const share = artwork.querySelector('[data-store-artwork-share]');
            const status = artwork.querySelector('[data-store-share-status]');
            share?.addEventListener('click', async () => {
                try {
                    if (navigator.share) {
                        await navigator.share({ title: document.title, url: location.href });
                    } else {
                        await navigator.clipboard.writeText(location.href);
                        if (status) status.textContent = 'Artwork link copied.';
                    }
                } catch (error) {
                    if (error?.name !== 'AbortError' && status) {
                        status.textContent = 'Could not share the link. Please copy it from the address bar.';
                    }
                }
            });

            const fullImage = artwork.querySelector('[data-store-full-image]');
            const mainImage = artwork.querySelector('[data-store-main-image]');
            artwork.querySelectorAll('[data-store-gallery-thumb]').forEach((thumbnail) => {
                thumbnail.addEventListener('click', () => {
                    if (fullImage && mainImage) fullImage.href = mainImage.currentSrc || mainImage.src;
                });
            });
        });

        root.querySelectorAll('[data-store-tabs]').forEach((tabs) => {
            const triggers = Array.from(tabs.querySelectorAll('[data-store-tab]'));
            const panels = Array.from(tabs.querySelectorAll('[data-store-tab-panel]'));

            const activate = (trigger) => {
                triggers.forEach((candidate) => {
                    const active = candidate === trigger;
                    candidate.setAttribute('aria-selected', active ? 'true' : 'false');
                    candidate.tabIndex = active ? 0 : -1;
                });
                panels.forEach((panel) => {
                    panel.hidden = panel.id !== trigger.getAttribute('aria-controls');
                });
            };

            triggers.forEach((trigger, index) => {
                trigger.addEventListener('click', () => activate(trigger));
                trigger.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    const targetIndex = event.key === 'Home'
                        ? 0
                        : event.key === 'End'
                            ? triggers.length - 1
                            : (index + (event.key === 'ArrowLeft' ? 1 : -1) + triggers.length) % triggers.length;
                    activate(triggers[targetIndex]);
                    triggers[targetIndex].focus();
                });
            });
        });
    };

    const boot = () => document.querySelectorAll('[data-z4-store]').forEach(init);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
})();
