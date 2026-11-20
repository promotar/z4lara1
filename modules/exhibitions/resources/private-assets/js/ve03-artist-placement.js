(() => {
    const root = document.querySelector('[data-ve03-placement]');
    if (!root) return;

    const manifestNode = document.querySelector('[data-placement-manifest]');
    const endpointNode = document.querySelector('[data-placement-endpoints]');
    let manifest = JSON.parse(manifestNode?.textContent || '{}');
    const endpoints = JSON.parse(endpointNode?.textContent || '{}');
    const wall = root.querySelector('[data-slot-wall]');
    const status = root.querySelector('[data-status]');
    const selection = root.querySelector('[data-selection]');
    const selectionDetail = root.querySelector('[data-selection-detail]');
    const confirmButton = root.querySelector('[data-confirm]');
    const cancelButton = root.querySelector('[data-cancel]');
    const presence = root.querySelector('[data-presence]');
    const presenceCountdown = root.querySelector('[data-presence-countdown]');
    const confirmPanel = root.querySelector('[data-confirm-panel]');
    const viewer = root.querySelector('[data-ve03-public-viewer]');
    const frameColors = root.querySelector('[data-frame-colors]');
    const artworkPicker = root.querySelector('[data-artwork-picker]');
    const pickerOpen = root.querySelector('[data-picker-open]');
    const pickerClose = root.querySelector('[data-picker-close]');
    const pickerCurrent = root.querySelector('[data-picker-current]');
    const artworkOptions = root.querySelector('[data-artwork-options]');
    let artworkButtons = [...root.querySelectorAll('[data-artwork-select]')];
    const selectedArtwork = root.querySelector('[data-selected-artwork]');
    const fitSummary = root.querySelector('[data-fit-summary]');
    let selectedSlot = null;
    let holdToken = null;
    let holdExpiresAt = null;
    let busy = false;
    let resumeCameraAfterPicker = false;
    let secondaryReleaseGuardUntil = 0;
    let rateLimitedUntil = 0;
    let rateLimitTimer = null;

    const allSlots = () => (manifest.rooms || [])
        .flatMap((room) => room.slots.map((slot) => ({ ...slot, room_title: room.title })))
        .sort((a, b) => Number(b.selectable) - Number(a.selectable) || a.room_title.localeCompare(b.room_title) || a.code.localeCompare(b.code));
    const endpoint = (template, token) => template.replace('__TOKEN__', encodeURIComponent(token));
    const artworkEndpoint = (artworkId) => endpoints.manifest.replace('__ARTWORK__', encodeURIComponent(artworkId));
    const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': endpoints.csrf });
    const retryAfterSeconds = (response) => {
        const value = String(response.headers.get('Retry-After') || '').trim();
        if (/^\d+$/.test(value)) return Math.max(1, Number(value));
        const retryAt = Date.parse(value);
        return Number.isFinite(retryAt) ? Math.max(1, Math.ceil((retryAt - Date.now()) / 1000)) : 60;
    };
    const request = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { ...headers(), ...(options.headers || {}) } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(response.status === 429
                ? 'Placement requests are temporarily limited to protect your account.'
                : (payload.message || 'The placement could not be updated.'));
            error.status = response.status;
            error.retryAfterSeconds = response.status === 429 ? retryAfterSeconds(response) : null;
            throw error;
        }
        return payload;
    };

    const secondsLeft = (value) => Math.max(0, Math.ceil((new Date(value).getTime() - Date.now()) / 1000));
    const clockLabel = (seconds) => `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    const selectedFrameStyle = () => root.querySelector('input[name="frame_style"]:checked')?.value || 'none';
    const selectedFrameColor = () => root.querySelector('input[name="frame_color"]:checked')?.value || 'black';
    const dispatchPreview = () => {
        frameColors.hidden = selectedFrameStyle() === 'none';
        viewer?.dispatchEvent(new CustomEvent('ve03-placement-frame-preview', { detail: selectedSlot ? {
            slot_code: selectedSlot,
            preview_url: manifest.artwork.preview_url,
            dimensions: manifest.artwork.dimensions,
            frame_style: selectedFrameStyle(),
            frame_color: selectedFrameColor(),
        } : null }));
    };

    function setStatus(message, error = false) {
        status.textContent = message || '';
        status.style.color = error ? '#9b1515' : '#315b47';
    }

    const rateLimitSecondsLeft = () => Math.max(0, Math.ceil((rateLimitedUntil - Date.now()) / 1000));
    const isRateLimited = () => rateLimitSecondsLeft() > 0;

    function applyRateLimit(error) {
        if (error?.status !== 429) return false;

        rateLimitedUntil = Math.max(rateLimitedUntil, Date.now() + (Number(error.retryAfterSeconds || 60) * 1000));
        clearTimeout(rateLimitTimer);
        render();
        artworkButtons.forEach((button) => { button.disabled = true; });
        confirmButton.disabled = true;

        const tick = () => {
            const seconds = rateLimitSecondsLeft();
            if (seconds > 0) {
                setStatus(`Too many placement attempts. Your current selection is safe; try again in ${seconds} second(s).`, true);
                rateLimitTimer = window.setTimeout(tick, 1_000);
                return;
            }

            rateLimitTimer = null;
            artworkButtons.forEach((button) => { button.disabled = false; });
            confirmButton.disabled = !holdToken;
            render();
            setStatus('You can continue with the placement now.');
        };
        tick();

        return true;
    }

    function showRequestError(error) {
        if (!applyRateLimit(error)) setStatus(error?.message || 'The placement could not be updated.', true);
    }

    function showPresence(slot) {
        if (!presence) return;
        presence.classList.add('is-visible');
        const tick = () => {
            const seconds = secondsLeft(slot.hold_expires_at);
            presenceCountdown.textContent = seconds > 0 ? `Expected release in ${clockLabel(seconds)}` : 'Checking availability…';
        };
        tick();
    }

    function render() {
        if (!wall) return;
        wall.replaceChildren();
        for (const slot of allSlots()) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `ve03-slot${selectedSlot === slot.code ? ' is-selected' : ''}`;
            button.dataset.state = slot.state;
            button.disabled = !slot.selectable || isRateLimited();
            button.innerHTML = `<strong>${escapeHtml(displayLabel(slot))}</strong><small>${escapeHtml(slot.room_title)}</small><small>${escapeHtml(slot.reason || stateLabel(slot.state))}</small>`;
            button.addEventListener('click', () => choose(slot));
            if (slot.state === 'held') {
                button.disabled = isRateLimited();
                button.addEventListener('click', () => showPresence(slot), { once: true });
            }
            wall.append(button);
        }
        updateArtworkPicker();
    }

    function updateArtworkPicker() {
        const slots = allSlots();
        const availableCount = slots.filter((slot) => slot.selectable).length;
        const incompatibleCount = slots.filter((slot) => slot.state === 'incompatible').length;
        if (selectedArtwork) selectedArtwork.textContent = manifest.artwork?.title || 'Selected artwork';
        if (pickerCurrent) pickerCurrent.textContent = manifest.artwork?.title || 'Selected artwork';
        if (fitSummary) fitSummary.textContent = `${availableCount} available position(s) · ${incompatibleCount} currently too tight`;
        artworkButtons.forEach((button) => {
            const active = button.dataset.artworkId === manifest.artwork?.id;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function stateLabel(state) {
        return ({ available: 'Available now', selected_by_you: 'Reserved for your preview', occupied_by_you: 'Your artwork is published here', occupied: 'Occupied by another artist', sold: 'SOLD · retained', held: 'Another artist is previewing', incompatible: 'Space too tight' })[state] || state;
    }

    function displayLabel(slot) {
        const parts = String(slot.code || '').split('-');
        const number = parts.at(-1);
        const orientation = parts.at(-2) === 'V' ? 'Portrait' : (parts.at(-2) === 'H' ? 'Landscape' : 'Artwork');
        return number ? `${orientation} display ${number}` : `${orientation} display`;
    }

    function escapeHtml(value) {
        const node = document.createElement('span');
        node.textContent = String(value || '');
        return node.innerHTML;
    }

    async function choose(slot) {
        if (slot.state === 'held') return showPresence(slot);
        if (!slot.selectable) return setStatus(slot.reason || 'This display is not available.', true);
        if (isRateLimited()) return setStatus(`Please wait ${rateLimitSecondsLeft()} second(s) before trying another placement action.`, true);
        if (busy) return;
        busy = true;
        setStatus('Securing this display for your preview…');
        try {
            if (holdToken && ! (await release(false))) return;
            const hold = await request(endpoints.hold, { method: 'POST', body: JSON.stringify({ artwork_public_id: manifest.artwork.id, environment_public_id: manifest.environment.id, slot_code: slot.code }) });
            selectedSlot = slot.code;
            holdToken = hold.token;
            holdExpiresAt = hold.expires_at;
            selection.textContent = displayLabel(slot);
            selectionDetail.textContent = `${slot.room_title} · reserved for your real-scale preview`;
            confirmButton.disabled = false;
            cancelButton.disabled = false;
            presence.classList.remove('is-visible');
            confirmPanel.hidden = false;
            dispatchPreview();
            confirmButton.focus();
            setStatus('Display secured. Confirm the placement or choose another display.');
            refreshManifest();
        } catch (error) {
            showRequestError(error);
            await refreshManifest();
        } finally { busy = false; }
    }

    async function release(update = true) {
        if (!holdToken) return true;
        if (isRateLimited()) {
            setStatus(`Please wait ${rateLimitSecondsLeft()} second(s) before cancelling this reservation.`, true);
            return false;
        }
        const token = holdToken;
        const releasedSlot = selectedSlot;
        try {
            await request(endpoint(endpoints.release, token), { method: 'POST', body: '{}' });
        } catch (error) {
            showRequestError(error);
            return false;
        }
        holdToken = null;
        holdExpiresAt = null;
        selectedSlot = null;
        confirmPanel.hidden = true;
        viewer?.dispatchEvent(new CustomEvent('ve03-placement-frame-preview', { detail: null }));
        confirmButton.disabled = true;
        cancelButton.disabled = true;
        selection.textContent = 'No display selected';
        selectionDetail.textContent = 'Select a green display in the room.';
        if (update) {
            setStatus('Selection cancelled. Continue exploring to choose another display.');
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-resume', { detail: { slot_code: releasedSlot } }));
        }
        if (update) await refreshManifest();

        return true;
    }

    async function switchArtwork(artworkId) {
        if (!artworkId || artworkId === manifest.artwork?.id || busy) return;
        if (isRateLimited()) return setStatus(`Please wait ${rateLimitSecondsLeft()} second(s) before changing the placement artwork.`, true);
        busy = true;
        artworkButtons.forEach((button) => { button.disabled = true; });
        setStatus('Checking safe wall space for the selected artwork…');
        try {
            if (holdToken && ! (await release(false))) return;
            const nextManifest = await request(artworkEndpoint(artworkId), { method: 'GET', headers: { 'Content-Type': 'application/json' } });
            manifest = nextManifest;
            presence?.classList.remove('is-visible');
            render();
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-manifest-updated', { detail: manifest }));
            setStatus(`Now showing available wall positions for ${manifest.artwork.title}.`);
        } catch (error) {
            showRequestError(error);
            await refreshManifest();
        } finally {
            busy = false;
            artworkButtons.forEach((button) => { button.disabled = isRateLimited(); });
        }
    }

    async function refreshManifest() {
        try {
            const requestedArtworkId = manifest.artwork.id;
            const refreshedManifest = await request(artworkEndpoint(requestedArtworkId), { method: 'GET', headers: { 'Content-Type': 'application/json' } });
            if (manifest.artwork.id !== requestedArtworkId) return;
            manifest = refreshedManifest;
            render();
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-manifest-updated', { detail: manifest }));
        } catch (error) {
            if (error?.status === 429) applyRateLimit(error);
        }
    }

    confirmButton.addEventListener('click', async () => {
        if (!holdToken || !selectedSlot) {
            setStatus('The display reservation is no longer active. Choose the display again.', true);
            return;
        }
        if (isRateLimited()) {
            setStatus(`Please wait ${rateLimitSecondsLeft()} second(s) before confirming this placement.`, true);
            return;
        }
        if (busy) return;
        busy = true;
        confirmButton.disabled = true;
        setStatus('Publishing the placement…');
        try {
            await request(endpoints.confirm, { method: 'POST', body: JSON.stringify({ artwork_public_id: manifest.artwork.id, environment_public_id: manifest.environment.id, slot_code: selectedSlot, hold_token: holdToken, frame_style: selectedFrameStyle(), frame_color: selectedFrameColor() }) });
            const publishedArtworkId = manifest.artwork.id;
            const publishedArtworkTitle = manifest.artwork.title;
            holdToken = null;
            holdExpiresAt = null;
            const publishedButton = artworkButtons.find((button) => button.dataset.artworkId === publishedArtworkId);
            publishedButton?.remove();
            artworkButtons = artworkButtons.filter((button) => button.dataset.artworkId !== publishedArtworkId);
            const releasedSlot = selectedSlot;
            selectedSlot = null;
            confirmPanel.hidden = true;
            cancelButton.disabled = true;
            selection.textContent = 'No display selected';
            selectionDetail.textContent = 'Choose another artwork, then select an available wall position.';
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-frame-preview', { detail: null }));
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-resume', { detail: { slot_code: releasedSlot } }));
            const nextArtworkId = artworkButtons[0]?.dataset.artworkId;
            if (nextArtworkId) {
                busy = false;
                await switchArtwork(nextArtworkId);
                setStatus(`${publishedArtworkTitle} was published. Continue with ${manifest.artwork.title} or choose another artwork.`);
            } else {
                if (selectedArtwork) selectedArtwork.textContent = 'All ready artworks are placed';
                if (fitSummary) fitSummary.textContent = 'Return to your dashboard whenever you are finished exploring.';
                setStatus(`${publishedArtworkTitle} was published. You have no other artworks ready for placement.`);
            }
        } catch (error) {
            showRequestError(error);
            await refreshManifest();
            confirmButton.disabled = !holdToken || isRateLimited();
        } finally { busy = false; }
    });
    cancelButton.addEventListener('click', () => release());
    const releasePointerForPicker = () => {
        if (document.pointerLockElement) document.exitPointerLock?.();
    };
    const openArtworkPicker = () => {
        resumeCameraAfterPicker = Boolean(document.pointerLockElement);
        releasePointerForPicker();
        artworkPicker.hidden = false;
        pickerOpen.hidden = true;
        root.classList.add('is-artwork-picker-open');
        artworkOptions?.focus({ preventScroll: true });
        artworkButtons.find((button) => button.classList.contains('is-active'))?.focus({ preventScroll: true });
    };
    const closeArtworkPicker = () => {
        artworkPicker.hidden = true;
        pickerOpen.hidden = false;
        root.classList.remove('is-artwork-picker-open');
        if (resumeCameraAfterPicker) {
            viewer?.dispatchEvent(new CustomEvent('ve03-placement-resume', { detail: { slot_code: null } }));
        }
        resumeCameraAfterPicker = false;
    };
    root.addEventListener('mousedown', (event) => {
        if (event.button === 2 && document.pointerLockElement) {
            // Exiting pointer lock can restore the cursor over an overlay
            // control and some browsers then emit a synthetic click there.
            // Keep the secondary-button gesture dedicated to releasing the
            // camera instead of forwarding it to Choose artwork.
            secondaryReleaseGuardUntil = Date.now() + 250;
        }
    }, true);
    root.addEventListener('click', (event) => {
        if (Date.now() < secondaryReleaseGuardUntil) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        const lockedCanvas = document.pointerLockElement;
        const confirmationOpen = confirmPanel?.hidden === false;
        if (event.button !== 0 || !artworkPicker.hidden || !lockedCanvas || event.target !== lockedCanvas || confirmationOpen) return;

        event.preventDefault();
        event.stopPropagation();
        openArtworkPicker();
    }, true);
    artworkButtons.forEach((button) => button.addEventListener('click', () => {
        const artworkId = button.dataset.artworkId;
        closeArtworkPicker();
        switchArtwork(artworkId);
    }));
    pickerOpen?.addEventListener('click', openArtworkPicker);
    pickerClose?.addEventListener('click', closeArtworkPicker);
    window.addEventListener('keydown', (event) => {
        if (event.key.toLowerCase() === 'q' && artworkPicker.hidden && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            event.stopImmediatePropagation();
            openArtworkPicker();
        } else if (event.key === 'Escape' && !artworkPicker.hidden) {
            event.preventDefault();
            event.stopImmediatePropagation();
            closeArtworkPicker();
        }
    }, true);
    artworkPicker?.addEventListener('pointerdown', (event) => {
        event.stopPropagation();
        releasePointerForPicker();
    });
    artworkPicker?.addEventListener('click', (event) => event.stopPropagation());
    artworkOptions?.addEventListener('wheel', (event) => event.stopPropagation(), { passive: true });
    artworkOptions?.addEventListener('touchstart', (event) => event.stopPropagation(), { passive: true });
    artworkOptions?.addEventListener('keydown', (event) => {
        if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(event.key)) {
            event.stopPropagation();
        }
    });
    root.querySelectorAll('input[name="frame_style"],input[name="frame_color"]').forEach((input) => {
        input.addEventListener('change', dispatchPreview);
    });
    root.addEventListener('ve03-placement-slot', (event) => {
        const slot = event.detail;
        if (!slot) return;
        if (slot.state === 'held') showPresence(slot);
        else if (slot.selectable) choose(slot);
        else setStatus(slot.reason || 'This display is not available.', true);
    });
    root.addEventListener('ve03-placement-preview-error', (event) => {
        setStatus(event.detail?.message || 'The artwork preview could not be loaded. Your placement has not been published.', true);
    });

    setInterval(async () => {
        if (!holdToken || busy) return;
        try {
            const hold = await request(endpoint(endpoints.refresh, holdToken), { method: 'POST', body: '{}' });
            holdExpiresAt = hold.expires_at;
        } catch (error) {
            if (applyRateLimit(error)) return;
            setStatus(error.message, true);
            holdToken = null;
            selectedSlot = null;
            confirmButton.disabled = true;
            cancelButton.disabled = true;
        }
    }, 60_000);
    setInterval(refreshManifest, 5_000);
    setInterval(() => {
        if (holdExpiresAt && secondsLeft(holdExpiresAt) === 0) refreshManifest();
        const held = allSlots().find((slot) => slot.state === 'held' && presence.classList.contains('is-visible'));
        if (held) showPresence(held);
    }, 1_000);
    window.addEventListener('pagehide', () => {
        clearTimeout(rateLimitTimer);
        if (!holdToken || !navigator.sendBeacon) return;
        const form = new FormData();
        form.append('_token', endpoints.csrf);
        navigator.sendBeacon(endpoint(endpoints.release, holdToken), form);
    });
    render();
})();
