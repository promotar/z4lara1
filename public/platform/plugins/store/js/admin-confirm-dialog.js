(function () {
    'use strict';

    function initializeConfirmDialog() {
        var dialog = document.querySelector('[data-z4-confirm-dialog]');

        if (!dialog || dialog.dataset.z4ConfirmReady === 'true') {
            return;
        }

        var title = dialog.querySelector('[data-z4-confirm-title]');
        var message = dialog.querySelector('[data-z4-confirm-message]');
        var cancelButton = dialog.querySelector('[data-z4-confirm-cancel]');
        var confirmButton = dialog.querySelector('[data-z4-confirm-accept]');
        var verification = dialog.querySelector('[data-z4-confirm-verification]');
        var verificationLabel = dialog.querySelector('[data-z4-confirm-verification-label]');
        var requiredValue = dialog.querySelector('[data-z4-confirm-required]');
        var inputLabel = dialog.querySelector('[data-z4-confirm-input-label]');
        var confirmationInput = dialog.querySelector('[data-z4-confirm-input]');
        var confirmationError = dialog.querySelector('[data-z4-confirm-error]');
        var pending = null;
        var confirmed = false;

        dialog.dataset.z4ConfirmReady = 'true';

        function confirmationSettings(form, submitter) {
            var source = submitter && submitter.hasAttribute('data-z4-confirm-trigger')
                ? submitter
                : (form.hasAttribute('data-z4-confirm') ? form : null);

            if (!source) {
                return null;
            }

            return {
                title: source.dataset.confirmTitle || form.dataset.confirmTitle || 'Delete this item?',
                message: source.dataset.confirmMessage || form.dataset.confirmMessage || 'This action will remove the item from daily work.',
                label: source.dataset.confirmLabel || form.dataset.confirmLabel || 'Delete',
                requiredValue: source.dataset.confirmRequire || form.dataset.confirmRequire || '',
                inputName: source.dataset.confirmInput || form.dataset.confirmInput || 'confirmation',
                verificationLabel: source.dataset.confirmVerificationLabel || form.dataset.confirmVerificationLabel || 'Enter the order number to confirm',
                inputLabel: source.dataset.confirmInputLabel || form.dataset.confirmInputLabel || 'Order number confirmation',
                placeholder: source.dataset.confirmPlaceholder || form.dataset.confirmPlaceholder || 'Type the order number exactly',
                error: source.dataset.confirmError || form.dataset.confirmError || 'The order number does not match.',
                tone: source.dataset.confirmTone || form.dataset.confirmTone || 'default'
            };
        }

        function matchesRequiredValue() {
            if (!pending || !pending.settings.requiredValue) {
                return true;
            }

            return confirmationInput.value.trim() === pending.settings.requiredValue;
        }

        function synchronizeVerification(showError) {
            var matches = matchesRequiredValue();

            if (!pending || !pending.settings.requiredValue) {
                return;
            }

            confirmButton.disabled = !matches;
            confirmationInput.setAttribute('aria-invalid', matches || confirmationInput.value === '' ? 'false' : 'true');
            confirmationError.hidden = !showError || matches || confirmationInput.value === '';
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var submitter = event.submitter || null;
            var settings;
            var actionMenu;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (form.dataset.z4ConfirmBypass === 'true') {
                delete form.dataset.z4ConfirmBypass;
                return;
            }

            settings = confirmationSettings(form, submitter);

            if (!settings) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            actionMenu = submitter ? submitter.closest('details') : null;

            pending = {
                form: form,
                submitter: submitter,
                restoreTarget: actionMenu ? actionMenu.querySelector('summary') : submitter,
                settings: settings
            };
            confirmed = false;

            if (actionMenu) {
                actionMenu.open = false;
            }

            title.textContent = settings.title;
            message.textContent = settings.message;
            confirmButton.textContent = settings.label;
            confirmButton.classList.toggle('is-danger', settings.tone === 'danger');
            confirmButton.removeAttribute('aria-busy');

            verification.hidden = settings.requiredValue === '';
            verificationLabel.textContent = settings.verificationLabel;
            requiredValue.textContent = settings.requiredValue;
            inputLabel.textContent = settings.inputLabel;
            confirmationInput.placeholder = settings.placeholder;
            confirmationInput.value = '';
            confirmationInput.setAttribute('aria-invalid', 'false');
            confirmationError.textContent = settings.error;
            confirmationError.hidden = true;
            confirmButton.disabled = settings.requiredValue !== '';

            dialog.showModal();

            window.requestAnimationFrame(function () {
                (settings.requiredValue ? confirmationInput : cancelButton).focus();
            });
        }, true);

        cancelButton.addEventListener('click', function () {
            dialog.close('cancel');
        });

        confirmButton.addEventListener('click', function () {
            var form;
            var submitter;

            if (!pending || confirmButton.disabled) {
                return;
            }

            form = pending.form;
            submitter = pending.submitter;

            if (!matchesRequiredValue()) {
                synchronizeVerification(true);
                confirmationInput.focus();
                return;
            }

            if (pending.settings.requiredValue) {
                var confirmationField = form.elements.namedItem(pending.settings.inputName);
                if (confirmationField) {
                    confirmationField.value = pending.settings.requiredValue;
                }
            }

            confirmed = true;
            confirmButton.disabled = true;
            confirmButton.setAttribute('aria-busy', 'true');
            form.dataset.z4ConfirmBypass = 'true';
            dialog.close('confirm');

            if (submitter && submitter.form === form) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }

            window.setTimeout(function () {
                delete form.dataset.z4ConfirmBypass;
            }, 0);
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close('cancel');
            }
        });

        confirmationInput.addEventListener('input', function () {
            synchronizeVerification(true);
        });

        confirmationInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && matchesRequiredValue()) {
                event.preventDefault();
                confirmButton.click();
            }
        });

        dialog.addEventListener('close', function () {
            var restoreTarget = pending ? pending.restoreTarget : null;

            pending = null;
            verification.hidden = true;
            confirmationInput.value = '';
            confirmationInput.setAttribute('aria-invalid', 'false');
            confirmationError.hidden = true;
            confirmButton.disabled = false;
            confirmButton.removeAttribute('aria-busy');
            confirmButton.classList.remove('is-danger');

            if (!confirmed && restoreTarget && document.contains(restoreTarget)) {
                restoreTarget.focus();
            }

            confirmed = false;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeConfirmDialog);
    } else {
        initializeConfirmDialog();
    }
}());
