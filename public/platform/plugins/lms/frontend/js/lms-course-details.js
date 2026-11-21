(function () {
    const root = document.querySelector('[data-course-detail]');

    if (!root) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const enrollModal = root.querySelector('[data-enroll-modal]');
    const promoModal = root.querySelector('[data-promo-modal]');
    const promoFrame = promoModal?.querySelector('[data-promo-frame]');
    const promoVideo = promoModal?.querySelector('[data-promo-video]');
    const couponForm = root.querySelector('[data-coupon-form]');
    const couponMessage = root.querySelector('[data-coupon-message]');

    const openModal = modal => {
        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
    };

    const closeModal = modal => {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!root.querySelector('.ainpa-course-modal.is-open')) {
            document.documentElement.style.overflow = '';
        }
    };

    const openPromoModal = () => {
        if (promoFrame?.dataset.src) {
            promoFrame.src = promoFrame.dataset.src;
        }

        openModal(promoModal);
        promoVideo?.play().catch(() => {});
    };

    const closePromoModal = () => {
        promoVideo?.pause();
        if (promoFrame) {
            promoFrame.removeAttribute('src');
        }

        closeModal(promoModal);
    };

    root.querySelectorAll('[data-course-tab]').forEach(button => {
        button.addEventListener('click', () => {
            const target = button.dataset.courseTab;

            root.querySelectorAll('[data-course-tab]').forEach(item => item.classList.toggle('is-active', item === button));
            root.querySelectorAll('[data-course-panel]').forEach(panel => {
                panel.classList.toggle('is-active', panel.dataset.coursePanel === target);
            });
        });
    });

    root.querySelectorAll('[data-course-tab-trigger]').forEach(button => {
        button.addEventListener('click', () => {
            const tab = root.querySelector(`[data-course-tab="${button.dataset.courseTabTrigger}"]`);
            tab?.click();
            tab?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    root.querySelectorAll('[data-toggle-section]').forEach(button => {
        button.addEventListener('click', () => {
            const section = button.closest('.ainpa-course-section');
            if (!section) return;

            const isOpen = section.classList.toggle('is-open');
            button.setAttribute('aria-expanded', String(isOpen));
        });
    });

    root.querySelector('[data-expand-all]')?.addEventListener('click', event => {
        const sections = [...root.querySelectorAll('.ainpa-course-section')];
        const shouldOpen = sections.some(section => !section.classList.contains('is-open'));

        sections.forEach(section => {
            section.classList.toggle('is-open', shouldOpen);
            section.querySelector('[data-toggle-section]')?.setAttribute('aria-expanded', String(shouldOpen));
        });
        event.currentTarget.textContent = shouldOpen ? 'Collapse All' : 'Expand All';
    });

    root.querySelectorAll('[data-open-enroll]').forEach(button => {
        button.addEventListener('click', () => openModal(enrollModal));
    });

    root.querySelectorAll('[data-open-promo]').forEach(button => {
        button.addEventListener('click', openPromoModal);
    });

    root.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => closeModal(enrollModal));
    });

    root.querySelectorAll('[data-close-promo-modal]').forEach(button => {
        button.addEventListener('click', closePromoModal);
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') {
            return;
        }

        closeModal(enrollModal);
        closePromoModal();
    });

    const enrollmentRequestUrl = new URL(window.location.href);

    if (enrollmentRequestUrl.searchParams.get('enroll') === '1') {
        openModal(enrollModal);
        enrollmentRequestUrl.searchParams.delete('enroll');
        window.history.replaceState(
            window.history.state,
            '',
            `${enrollmentRequestUrl.pathname}${enrollmentRequestUrl.search}${enrollmentRequestUrl.hash}`
        );
    }


    document.querySelectorAll('[data-training-mode-form]').forEach(function (form) {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const message = form.querySelector('[data-training-mode-message]');
            const button = form.querySelector('button[type="submit"]');
            const select = form.querySelector('[name="training_mode"]');

            button.disabled = true;
            message.style.color = '#6b7280';
            message.textContent = 'Saving...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ training_mode: select.value }),
                });
                const payload = await response.json();

                if (! response.ok || ! payload.ok) {
                    throw new Error(payload.message || 'Training type could not be saved.');
                }

                message.style.color = '#15803d';
                message.textContent = payload.data?.message || 'Saved.';

                const priceLabel = select.value === 'in_person' ? (root.dataset.priceInperson || '') : (root.dataset.priceOnline || '');
                const priceElement = document.querySelector('.ainpa-course-price-line strong');
                if (priceLabel !== '' && priceElement) { priceElement.textContent = priceLabel; }
            } catch (error) {
                message.style.color = '#b91c1c';
                message.textContent = error.message || 'Training type could not be saved.';
            }

            button.disabled = false;
        });
    });

    if (couponForm) {
        couponForm.addEventListener('submit', async event => {
            event.preventDefault();

            const endpoint = root.dataset.couponEndpoint;
            const input = couponForm.querySelector('[name="coupon_code"]');
            const button = couponForm.querySelector('button[type="submit"]');
            const code = input?.value?.trim() || '';

            couponMessage.className = 'ainpa-course-coupon-message';

            if (code.length < 3) {
                couponMessage.textContent = 'Please enter a valid course code.';
                couponMessage.classList.add('is-error');
                return;
            }

            button.disabled = true;
            button.textContent = 'Checking...';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ coupon_code: code, training_mode: (couponForm.querySelector('input[name="enrollment_training_mode"]:checked') || {}).value || '' }),
                });

                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || payload.error || 'Course code could not be applied.');
                }

                couponMessage.textContent = payload.data?.message || 'Course code applied successfully.';
                couponMessage.classList.add('is-success');

                if (payload.data?.player_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.data.player_url;
                    }, 700);
                }
            } catch (error) {
                couponMessage.textContent = error.message || 'Course code could not be applied.';
                couponMessage.classList.add('is-error');
            } finally {
                button.disabled = false;
                button.textContent = 'Apply Code';
            }
        });
    }
})();

    document.querySelectorAll('[data-lms-more]').forEach(function (button) {
        button.addEventListener('click', function () {
            const container = button.closest('.lms-details-description');
            const more = container ? container.querySelector('.lms-details-more') : null;
            if (! more) { return; }
            const isOpen = ! more.hasAttribute('hidden');
            if (isOpen) { more.setAttribute('hidden', ''); button.textContent = 'Read more'; }
            else { more.removeAttribute('hidden'); button.textContent = 'Show less'; }
        });
    });