{{-- Store navigation is owned by the platform admin sidebar. --}}
<dialog
    class="z4-store-confirm-dialog"
    data-z4-confirm-dialog
    aria-labelledby="z4-store-confirm-title"
    aria-describedby="z4-store-confirm-message"
>
    <div class="z4-store-confirm-dialog__accent" aria-hidden="true"></div>
    <div class="z4-store-confirm-dialog__content">
        <div class="z4-store-confirm-dialog__heading">
            <span class="z4-store-confirm-dialog__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="img">
                    <path d="M12 8v5m0 3.5v.01M10.3 4.4 3.2 17a2 2 0 0 0 1.74 3h14.12a2 2 0 0 0 1.74-3L13.7 4.4a1.96 1.96 0 0 0-3.4 0Z"/>
                </svg>
            </span>
            <div>
                <span class="z4-store-confirm-dialog__eyebrow">{{ __('store::ui.auto_a9fe822ae1') }}</span>
                <h2 id="z4-store-confirm-title" data-z4-confirm-title>{{ __('store::ui.auto_7592a1521d') }}</h2>
            </div>
        </div>
        <p id="z4-store-confirm-message" data-z4-confirm-message>{{ __('store::ui.auto_c297dbacdc') }}</p>
        <div class="z4-store-confirm-dialog__verification" data-z4-confirm-verification hidden>
            <span class="z4-store-confirm-dialog__verification-label" data-z4-confirm-verification-label>{{ __('store::ui.auto_c27b336ddf') }}</span>
            <strong class="z4-store-confirm-dialog__required-value" data-z4-confirm-required></strong>
            <label class="z4-store-confirm-dialog__input-wrap">
                <span class="sr-only" data-z4-confirm-input-label>{{ __('store::ui.auto_c5fa8bd1db') }}</span>
                <input
                    class="z4-store-input"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    data-z4-confirm-input
                    aria-describedby="z4-store-confirm-error"
                    placeholder="{{ __('store::ui.auto_467ae2b8b1') }}"
                >
            </label>
            <small id="z4-store-confirm-error" class="z4-store-confirm-dialog__error" data-z4-confirm-error hidden>{{ __('store::ui.auto_41a913b273') }}</small>
        </div>
    </div>
    <div class="z4-store-confirm-dialog__actions">
        <button class="z4-store-btn z4-store-btn-ghost" type="button" data-z4-confirm-cancel>{{ __('store::ui.auto_ea4788705e') }}</button>
        <button class="z4-store-btn z4-store-confirm-dialog__confirm" type="button" data-z4-confirm-accept>{{ __('store::ui.auto_f2a6c498fb') }}</button>
    </div>
</dialog>
