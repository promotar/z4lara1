@if (session('success'))
    <div class="z4-store-alert z4-store-alert-success" role="status" aria-live="polite" tabindex="-1"><strong>{{ __('store::ui.auto_2bcf4d0012') }}</strong> {{ session('success') }}</div>
@endif
@if (session('warning'))
    <div class="z4-store-alert z4-store-alert-warning" role="alert" tabindex="-1"><strong>{{ __('store::ui.auto_447c69376c') }}</strong> {{ session('warning') }}</div>
@endif
@if (isset($errors) && $errors->any())
    <div class="z4-store-alert z4-store-alert-danger" role="alert" tabindex="-1">
        @if (count(array_diff(array_keys($errors->messages()), ['store'])) > 0)
            <strong>{{ __('store::ui.auto_2d7d932a4d') }}</strong>
            <span>{{ __('store::ui.auto_ae3d9ae51c') }}</span>
        @else
            <strong>{{ __('store::ui.auto_118ecea223') }}</strong>
        @endif
        @if ($errors->has('store'))
            <ul>@foreach ($errors->get('store') as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
    </div>
@endif
<script>
(() => {
    const init = () => {
    const root = document.querySelector('[data-z4-store-foundation]');
    if (!root || root.dataset.storeExperienceReady) return;
    root.dataset.storeExperienceReady = 'true';

    let hasUnsavedChanges = false;
    const setDirty = () => { hasUnsavedChanges = true; };

    root.querySelectorAll('form[data-dirty-form]').forEach((form) => {
        form.addEventListener('input', setDirty);
        form.addEventListener('change', setDirty);
    });

    const actionMenus = [...root.querySelectorAll('details.z4-store-actions-menu')];
    const positionActionMenu = (menu) => {
        const summary = menu.querySelector(':scope > summary');
        const panel = menu.querySelector(':scope > div');
        if (!summary || !panel || !menu.open) return;

        const trigger = summary.getBoundingClientRect();
        const panelWidth = Math.min(Math.max(panel.offsetWidth, 184), window.innerWidth - 24);
        const panelHeight = panel.offsetHeight;
        const left = Math.max(12, Math.min(trigger.right - panelWidth, window.innerWidth - panelWidth - 12));
        const below = trigger.bottom + 8;
        const top = below + panelHeight <= window.innerHeight - 12
            ? below
            : Math.max(12, trigger.top - panelHeight - 8);

        panel.style.setProperty('--z4-action-menu-left', `${left}px`);
        panel.style.setProperty('--z4-action-menu-top', `${top}px`);
        panel.style.setProperty('--z4-action-menu-width', `${panelWidth}px`);
    };
    const closeActionMenu = (menu) => {
        menu.open = false;
        menu.querySelector(':scope > summary')?.setAttribute('aria-expanded', 'false');
    };
    const closeAllActionMenus = () => actionMenus.forEach(closeActionMenu);
    actionMenus.forEach((menu) => {
        const summary = menu.querySelector(':scope > summary');
        summary?.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
        menu.addEventListener('toggle', () => {
            summary?.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
            if (!menu.open) return;
            actionMenus.forEach((candidate) => {
                if (candidate !== menu) closeActionMenu(candidate);
            });
            positionActionMenu(menu);
        });
    });
    closeAllActionMenus();
    window.addEventListener('pageshow', closeAllActionMenus);
    window.addEventListener('resize', () => actionMenus.filter((menu) => menu.open).forEach(positionActionMenu));
    window.addEventListener('scroll', () => actionMenus.filter((menu) => menu.open).forEach(positionActionMenu), true);
    root.addEventListener('click', (event) => {
        const selectedAction = event.target.closest('details.z4-store-actions-menu [role="menuitem"]');
        if (selectedAction) {
            closeActionMenu(selectedAction.closest('details.z4-store-actions-menu'));
        }
        actionMenus.forEach((menu) => {
            if (menu.open && !menu.contains(event.target)) closeActionMenu(menu);
        });
    });
    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openMenu = actionMenus.find((menu) => menu.open);
        if (!openMenu) return;
        const summary = openMenu.querySelector(':scope > summary');
        closeActionMenu(openMenu);
        summary?.focus();
    });

    root.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.storeSubmitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.storeSubmitting = 'true';
            if (form.matches('[data-dirty-form]')) hasUnsavedChanges = false;

            const controls = form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');
            controls.forEach((control) => {
                control.disabled = true;
                control.setAttribute('aria-busy', 'true');
            });
            if (event.submitter && !event.submitter.disabled) {
                event.submitter.disabled = true;
                event.submitter.setAttribute('aria-busy', 'true');
            }
        });
    });

    const clearFieldError = (field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const errorId = field.getAttribute('aria-describedby');
        if (!errorId) return;
        const error = document.getElementById(errorId);
        if (error?.classList.contains('z4-store-field-error')) error.remove();
        field.removeAttribute('aria-describedby');
    };
    root.addEventListener('invalid', (event) => event.target.classList.add('is-invalid'), true);
    root.addEventListener('input', (event) => clearFieldError(event.target), true);
    root.addEventListener('change', (event) => clearFieldError(event.target), true);

    const problem = root.querySelector('.z4-store-alert-danger');
    const serverErrors = @json(isset($errors) ? $errors->messages() : []);
    const namedFields = [...root.querySelectorAll('[name]')];
    const fieldNameFor = (key) => key.split('.').reduce(
        (name, part, index) => index === 0 ? part : `${name}[${part}]`,
        '',
    );
    const fieldFor = (key) => {
        const expectedName = fieldNameFor(key);
        const exact = namedFields.filter((field) => field.name === expectedName);
        const selectable = exact.find((field) => !field.hidden && field.type !== 'hidden');
        if (selectable) return selectable;
        if (exact.length) return exact[0];

        const parts = key.split('.');
        while (parts.length > 1) {
            parts.pop();
            const prefix = fieldNameFor(parts.join('.'));
            const related = namedFields.find((field) => field.name.startsWith(`${prefix}[`) && field.type !== 'hidden');
            if (related) return related;
        }

        return null;
    };
    let firstInvalidField = null;
    Object.entries(serverErrors).forEach(([key, messages], errorIndex) => {
        if (key === 'store' || !messages.length) return;
        const field = fieldFor(key);
        if (!field) return;
        const errorId = `z4-store-field-error-${errorIndex}`;
        const container = field.type === 'radio'
            ? field.closest('fieldset')
            : field.closest('label, fieldset, [data-created-variant], [data-option-axis], [data-product-policies]');
        if (!container) return;

        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', errorId);
        const message = document.createElement('small');
        message.className = 'z4-store-field-error';
        message.id = errorId;
        message.setAttribute('role', 'alert');
        message.textContent = messages[0];
        container.append(message);
        firstInvalidField ??= field;
    });

    if (firstInvalidField) {
        requestAnimationFrame(() => {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (!firstInvalidField.hidden && firstInvalidField.type !== 'hidden') {
                firstInvalidField.focus({ preventScroll: true });
            }
        });
    } else if (problem) {
        problem.focus({ preventScroll: true });
    }

    window.addEventListener('beforeunload', (event) => {
        if (!hasUnsavedChanges) return;
        event.preventDefault();
        event.returnValue = '';
    });
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init, { once: true })
        : init();
})();
</script>
