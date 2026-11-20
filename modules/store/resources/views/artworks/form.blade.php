@php
    $editing = $artwork !== null;
    $value = fn (string $key, mixed $default = null) => old($key, $artwork?->{$key} ?? $default);
    $colors = old('primary_colors', $artwork?->primary_colors ? implode(', ', json_decode($artwork->primary_colors, true) ?: []) : '');
    $requestedPrice = old(
        'requested_price',
        $artwork?->requested_price_minor !== null
            ? (new \Modules\Store\Domain\Shared\Money((int) $artwork->requested_price_minor, (string) $artwork->currency))->decimal()
            : ''
    );
    $frameCost = old('frame_cost', $artwork?->frame_cost_minor !== null ? (new \Modules\Store\Domain\Shared\Money((int) $artwork->frame_cost_minor, (string) $artwork->currency))->decimal() : '');
    $packagingCost = old('packaging_cost', $artwork?->packaging_cost_minor !== null ? (new \Modules\Store\Domain\Shared\Money((int) $artwork->packaging_cost_minor, (string) $artwork->currency))->decimal() : '');
    $action = $admin
        ? ($editing ? route('admin.plugins.store.artworks.update', $artwork->product_public_id) : route('admin.plugins.store.artworks.store'))
        : ($editing ? route('store.seller.artworks.update', $artwork->product_public_id) : route('store.seller.artworks.store'));
    $back = $admin ? route('admin.plugins.store.products.create') : route('store.seller.artworks.index');
    $autosave = $admin
        ? ($editing ? route('admin.plugins.store.artworks.autosave.update', $artwork->product_public_id) : route('admin.plugins.store.artworks.autosave.store'))
        : ($editing ? route('store.seller.artworks.autosave.update', $artwork->product_public_id) : route('store.seller.artworks.autosave.store'));
    $serverErrorKeys = $errors->keys();
    $ownershipType = old('ownership_type', $artwork?->ownership_type ?? 'artist_account');
    $draftConflict = collect($errors->all())->contains(
        fn (string $error): bool => str_contains(strtolower($error), 'draft changed elsewhere')
    );
@endphp
<x-app-layout>
<main class="z4-store-admin z4-store-artwork-page" data-z4-store-foundation data-artwork-wizard data-draft-conflict="{{ $draftConflict ? '1' : '0' }}">
    <div class="z4-store-container">
        @if($admin) @include('store::admin._navigation') @endif
        <header class="z4-store-heading">
            <div><div class="z4-store-heading-meta"><a class="z4-store-back" href="{{ $back }}">&larr; Back</a><span class="z4-store-eyebrow">Store-owned artwork product</span></div><h1>{{ $editing ? 'Edit Artwork' : 'Add Artwork' }}</h1><p>Complete the same artwork, media, provenance, and authenticity workflow before {{ $admin ? 'direct publication' : 'manual review' }}.</p></div>
            @if($editing)<span class="z4-store-status">{{ ucwords(str_replace('_', ' ', $artwork->workflow_state)) }}</span>@endif
        </header>
        @if($errors->any())<div class="z4-store-alert is-danger"><strong>Please correct the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form class="z4-store-artwork-form" method="post" action="{{ $action }}" data-autosave-form data-autosave-url="{{ $autosave }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <input type="hidden" name="draft_key" value="{{ old('draft_key', $draftKey) }}">
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $artwork?->lock_version) }}" data-lock-version>
            <script type="application/json" data-server-error-fields>@json($serverErrorKeys)</script>
            <div class="z4-store-autosave-status" data-autosave-status role="status" aria-live="polite"><span data-autosave-message>{{ $editing ? 'Draft loaded. Changes save automatically.' : 'Start entering details. Changes save automatically.' }}</span><button type="button" data-autosave-retry hidden>Retry</button></div>
            <nav class="z4-store-artwork-stepper" aria-label="Artwork form steps" role="tablist">
                @foreach([1=>'Basics',2=>'Technical details',3=>'Story & authenticity',4=>'Price, delivery & images'] as $step=>$label)
                    <button id="artwork-step-tab-{{ $step }}" type="button" role="tab" aria-controls="artwork-step-{{ $step }}" data-step-target="{{ $step }}"><span>{{ $step }}</span><strong>{{ $label }}</strong></button>
                @endforeach
            </nav>

            <section id="artwork-step-1" class="z4-store-form-card z4-store-artwork-step" role="tabpanel" aria-labelledby="artwork-step-tab-1" tabindex="-1" data-step="1">
                <header class="z4-store-section-heading"><span>1</span><div><h2>Basic information</h2><p>Choose whether Store links this artwork to an artist account or manages it directly for the platform.</p></div></header>
                <div class="z4-store-form-grid">
                    @if($admin)
                        <label class="is-wide"><span>Artwork ownership <b>*</b></span><select class="z4-store-select" name="ownership_type" required data-artwork-ownership>
                            <option value="artist_account" @selected($ownershipType === 'artist_account')>Existing artist account</option>
                            <option value="platform" @selected($ownershipType === 'platform')>Platform-owned — no artist account</option>
                        </select><small>Platform-owned artwork is managed by Store and does not create artist earnings, notifications, or dashboard records.</small></label>
                        <label class="is-wide" data-artwork-owner-field><span>Artist account / seller <b>*</b></span><select class="z4-store-select" name="owner_user_id" data-artwork-owner>
                            <option value="">Choose the platform account that owns this artwork</option>
                            @foreach($sellerOptions as $seller)<option value="{{ $seller->id }}" data-artist-name="{{ $seller->name }}" @selected((int) old('owner_user_id', $artwork?->owner_user_id) === (int) $seller->id)>{{ $seller->name }} — {{ $seller->email }}</option>@endforeach
                        </select><small>Only accounts authorized by Store to sell artworks are listed. This owner is used by Exhibitions for virtual-display eligibility.</small></label>
                    @endif
                    <label class="is-wide"><span>Artwork name <b>*</b></span><input class="z4-store-input" name="title" maxlength="180" value="{{ $value('title') }}"></label>
                    @if($admin)<label class="is-wide"><span>Public artist / attribution name <b data-platform-required hidden>*</b></span><input class="z4-store-input" name="artist_name" maxlength="255" value="{{ $value('artist_name') }}" data-artwork-artist-name><small>For an artist account this defaults to its name. For platform ownership, enter the artist, collection, estate, or attribution shown publicly.</small></label>@endif
                    <label><span>Completion year <b>*</b></span><input class="z4-store-input" type="number" name="completion_year" min="1000" max="{{ date('Y') }}" value="{{ $value('completion_year') }}"></label>
                    <label><span>Work type <b>*</b></span><input class="z4-store-input" name="work_type" maxlength="80" value="{{ $value('work_type') }}" placeholder="Painting, sculpture, mixed media"></label>
                    <label><span>Collection</span><input class="z4-store-input" name="collection_name" maxlength="160" value="{{ $value('collection_name') }}"></label>
                    <label><span>Edition <b>*</b></span><select class="z4-store-select" name="edition_type" data-edition-type><option value="original" @selected($value('edition_type','original')==='original')>Original</option><option value="limited" @selected($value('edition_type')==='limited')>Limited edition</option></select></label>
                    <div class="z4-store-form-grid is-wide" data-limited-fields><label><span>Edition number</span><input class="z4-store-input" type="number" name="edition_number" min="1" value="{{ $value('edition_number') }}"></label><label><span>Total edition count</span><input class="z4-store-input" type="number" name="edition_total" min="1" value="{{ $value('edition_total') }}"></label></div>
                </div>
            </section>

            <section id="artwork-step-2" class="z4-store-form-card z4-store-artwork-step" role="tabpanel" aria-labelledby="artwork-step-tab-2" tabindex="-1" data-step="2" hidden>
                <header class="z4-store-section-heading"><span>2</span><div><h2>Technical details</h2><p>Describe the physical work accurately for catalog and delivery use.</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>Category <b>*</b></span><input class="z4-store-input" name="category" maxlength="100" value="{{ $value('category') }}"></label><label><span>Artistic style</span><input class="z4-store-input" name="artistic_style" maxlength="100" value="{{ $value('artistic_style') }}"></label>
                    <label><span>Subject</span><input class="z4-store-input" name="subject" maxlength="160" value="{{ $value('subject') }}"></label><label><span>Technique <b>*</b></span><input class="z4-store-input" name="technique" maxlength="160" value="{{ $value('technique') }}"></label>
                    <label><span>Material <b>*</b></span><input class="z4-store-input" name="material" maxlength="160" value="{{ $value('material') }}"></label><label><span>Surface</span><input class="z4-store-input" name="surface" maxlength="120" value="{{ $value('surface') }}"></label>
                    <label><span>Primary colors</span><input class="z4-store-input" name="primary_colors" maxlength="500" value="{{ $colors }}" placeholder="Blue, white, gold"></label><label><span>Orientation <b>*</b></span><select class="z4-store-select" name="orientation"><option value="">Choose</option>@foreach(['portrait','landscape','square','panoramic','other'] as $option)<option value="{{ $option }}" @selected($value('orientation')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
                    <label><span>Width <b>*</b></span><input class="z4-store-input" type="number" step="0.01" min="0.01" name="width" value="{{ $value('width') }}"></label><label><span>Height <b>*</b></span><input class="z4-store-input" type="number" step="0.01" min="0.01" name="height" value="{{ $value('height') }}"></label>
                    <label><span>Depth</span><input class="z4-store-input" type="number" step="0.01" min="0" name="depth" value="{{ $value('depth') }}"></label><label><span>Dimension unit</span><select class="z4-store-select" name="dimension_unit"><option value="cm" @selected($value('dimension_unit','cm')==='cm')>cm</option><option value="in" @selected($value('dimension_unit')==='in')>in</option></select></label>
                    <label><span>Weight</span><input class="z4-store-input" type="number" step="0.001" min="0" name="weight" value="{{ $value('weight') }}"></label><label><span>Weight unit</span><select class="z4-store-select" name="weight_unit"><option value="kg" @selected($value('weight_unit','kg')==='kg')>kg</option><option value="lb" @selected($value('weight_unit')==='lb')>lb</option></select></label>
                    <label class="z4-store-check"><input type="hidden" name="is_framed" value="0"><input type="checkbox" name="is_framed" value="1" data-framed @checked((bool)$value('is_framed'))><span><strong>Artwork is framed</strong></span></label>
                    <label data-frame-field><span>Frame material</span><input class="z4-store-input" name="frame_material" maxlength="120" value="{{ $value('frame_material') }}"></label>
                    <label><span>Condition <b>*</b></span><select class="z4-store-select" name="condition"><option value="">Choose</option>@foreach(['new','excellent','good','fair','restored'] as $option)<option value="{{ $option }}" @selected($value('condition')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
                    <label class="z4-store-check"><input type="hidden" name="ready_to_hang" value="0"><input type="checkbox" name="ready_to_hang" value="1" @checked((bool)$value('ready_to_hang'))><span><strong>Ready to hang</strong></span></label>
                </div>
            </section>

            <section id="artwork-step-3" class="z4-store-form-card z4-store-artwork-step" role="tabpanel" aria-labelledby="artwork-step-tab-3" tabindex="-1" data-step="3" hidden>
                <header class="z4-store-section-heading"><span>3</span><div><h2>Content</h2><p>Tell the artwork story accurately. Ownership and evidence are completed in the dedicated authenticity workspace.</p></div></header>
                <div class="z4-store-form-grid">
                    <label class="is-wide"><span>Short description <b>*</b></span><textarea class="z4-store-textarea" name="short_description" rows="3" maxlength="2000">{{ $value('short_description') }}</textarea></label>
                    <label class="is-wide"><span>Detailed description <b>*</b></span><textarea class="z4-store-textarea" name="detailed_description" rows="6" maxlength="50000">{{ $value('detailed_description') }}</textarea></label>
                    <label class="is-wide"><span>Artwork story</span><textarea class="z4-store-textarea" name="artwork_story" rows="4" maxlength="10000">{{ $value('artwork_story') }}</textarea></label>
                    <label class="is-wide"><span>Source of inspiration</span><textarea class="z4-store-textarea" name="inspiration_source" rows="3" maxlength="5000">{{ $value('inspiration_source') }}</textarea></label>
                    <label class="is-wide"><span>Care instructions</span><textarea class="z4-store-textarea" name="care_instructions" rows="3" maxlength="3000">{{ $value('care_instructions') }}</textarea></label>
                </div>
            </section>

            <section id="artwork-step-4" class="z4-store-form-card z4-store-artwork-step" role="tabpanel" aria-labelledby="artwork-step-tab-4" tabindex="-1" data-step="4" hidden>
                <header class="z4-store-section-heading"><span>4</span><div><h2>Pricing, delivery and images</h2><p>Frame and packaging are recorded separately while the approved product price remains Store-owned.</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>Requested price <b>*</b></span><input class="z4-store-input" name="requested_price" inputmode="decimal" value="{{ $requestedPrice }}" placeholder="0.000"></label>
                    <label><span>Currency</span><select class="z4-store-select" name="currency"><option value="JOD">JOD</option></select></label>
                    <label><span>Frame cost</span><input class="z4-store-input" name="frame_cost" inputmode="decimal" value="{{ $frameCost }}" placeholder="0.000"></label>
                    <label><span>Packaging cost</span><input class="z4-store-input" name="packaging_cost" inputmode="decimal" value="{{ $packagingCost }}" placeholder="0.000"></label>
                    <label><span>Availability <b>*</b></span><select class="z4-store-select" name="availability"><option value="">Choose</option>@foreach(['available'=>'Available','made_to_order'=>'Made to order','temporarily_unavailable'=>'Temporarily unavailable'] as $key=>$label)<option value="{{ $key }}" @selected($value('availability')===$key)>{{ $label }}</option>@endforeach</select></label>
                    <label><span>Preparation days <b>*</b></span><input class="z4-store-input" type="number" min="0" max="365" name="preparation_days" value="{{ $value('preparation_days') }}"></label>
                    <label class="is-wide"><span>Shipping notes</span><textarea class="z4-store-textarea" name="shipping_notes" rows="3" maxlength="3000">{{ $value('shipping_notes') }}</textarea></label>
                </div>
                <div class="z4-store-artwork-workflow-card"><div><strong>Artwork images</strong><p>Add front, detail, signature, back, edge, scale, and authenticity-certificate views. Private originals and optimized display copies are managed separately.</p>@if($editing)<small>{{ $mediaCount }} image(s) &middot; {{ $hasPrimaryImage ? 'Primary front selected' : 'Primary front required' }}</small>@endif</div>@if($editing)<a class="z4-store-button is-secondary" href="{{ route($admin ? 'admin.plugins.store.artworks.media.index' : 'store.seller.artworks.media.index', $artwork->product_public_id) }}">Manage artwork images</a>@else<button class="z4-store-button is-secondary" type="submit" name="intent" value="save_and_media" formnovalidate>Save draft &amp; add images</button>@endif</div>
                <div class="z4-store-artwork-workflow-card"><div><strong>Authenticity and provenance</strong><p>Record ownership basis, disclosures, current location, private evidence, and optional provenance history.</p>@if($editing)<small>{{ $authenticity ? ucwords(str_replace('_',' ', $authenticity->state)) : 'Declaration required' }} &middot; {{ $evidenceCount }} evidence file(s)</small>@endif</div>@if($editing)<a class="z4-store-button is-secondary" href="{{ route($admin ? 'admin.plugins.store.artworks.authenticity.show' : 'store.seller.artworks.authenticity.show', $artwork->product_public_id) }}">Manage authenticity evidence</a>@else<button class="z4-store-button is-secondary" type="submit" name="intent" value="save_and_authenticity" formnovalidate>Save draft &amp; add evidence</button>@endif</div>
            </section>

            <div class="z4-store-artwork-actions"><button type="submit" name="intent" value="{{ $mediaCount === 0 ? 'save_and_media' : 'save' }}" class="z4-store-button is-secondary" formnovalidate>{{ $mediaCount === 0 ? 'Save draft & continue to images' : 'Save private draft' }}</button><div><button type="button" class="z4-store-button is-secondary" data-step-back>Back</button><button type="button" class="z4-store-button" data-step-next>Next</button><button type="submit" name="intent" value="{{ $admin ? 'publish' : 'submit' }}" class="z4-store-button" data-submit>{{ $admin ? 'Publish artwork' : 'Submit for manual review' }}</button></div></div>
        </form>
    </div>
</main>
</x-app-layout>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-artwork-wizard]');
    const form = root?.querySelector('[data-autosave-form]');
    const steps = [...root?.querySelectorAll('[data-step]') || []];
    if (!form || !steps.length) return;

    const targets = [...root.querySelectorAll('[data-step-target]')];
    const back = form.querySelector('[data-step-back]');
    const next = form.querySelector('[data-step-next]');
    const submit = form.querySelector('[data-submit]');
    const edition = form.querySelector('[data-edition-type]');
    const ownership = form.querySelector('[data-artwork-ownership]');
    const owner = form.querySelector('[data-artwork-owner]');
    const ownerField = form.querySelector('[data-artwork-owner-field]');
    const artistName = form.querySelector('[data-artwork-artist-name]');
    const platformRequired = form.querySelector('[data-platform-required]');
    const limited = form.querySelector('[data-limited-fields]');
    const framed = form.querySelector('[data-framed]');
    const frame = form.querySelector('[data-frame-field]');
    const status = form.querySelector('[data-autosave-status]');
    const message = form.querySelector('[data-autosave-message]');
    const retry = form.querySelector('[data-autosave-retry]');
    const lock = form.querySelector('[data-lock-version]');
    const serverErrorsNode = form.querySelector('[data-server-error-fields]');
    const required = {
        1: ['title', 'completion_year', 'work_type', 'edition_type'],
        2: ['category', 'technique', 'material', 'orientation', 'width', 'height', 'dimension_unit', 'condition'],
        3: ['short_description', 'detailed_description'],
        4: ['requested_price', 'currency', 'availability', 'preparation_days'],
    };
    if (ownership) required[1].unshift('ownership_type');
    const navigationIntents = new Set(['save_and_media', 'save_and_authenticity', 'publish', 'submit']);
    const moneyPattern = /^(?:0|[1-9][0-9]*)(?:\.\d{1,3})?$/;
    const fieldSteps = new Map();
    steps.forEach(section => section.querySelectorAll('[name]').forEach(field => fieldSteps.set(field.name, Number(section.dataset.step))));
    const stepHash = step => `#artwork-step-${step}`;
    const stepFromHash = () => {
        const match = window.location.hash.match(/^#artwork-step-([1-4])$/);
        return match ? Number(match[1]) : null;
    };

    let serverErrorFields;
    try {
        serverErrorFields = new Set(JSON.parse(serverErrorsNode?.textContent || '[]'));
    } catch (_) {
        serverErrorFields = new Set();
    }

    let current = 1;
    let timer;
    let busy = false;
    let dirty = false;
    let submitting = false;
    let pendingSubmit = false;
    let navigationBlocked = root.dataset.draftConflict === '1';

    const state = (kind, text, canRetry = false) => {
        status.className = 'z4-store-autosave-status is-' + kind;
        message.textContent = text;
        retry.hidden = !canRetry;
    };

    const syncNavigation = () => {
        targets.forEach(target => {
            const targetStep = Number(target.dataset.stepTarget);
            target.disabled = navigationBlocked && targetStep > current;
        });
        next.disabled = navigationBlocked;
        submit.disabled = navigationBlocked;
        form.querySelectorAll('button[type="submit"][name="intent"]').forEach(button => {
            button.disabled = navigationBlocked;
        });
    };

    const show = (step, { updateHash = true, focusHeading = false } = {}) => {
        current = Math.max(1, Math.min(steps.length, Number(step) || 1));
        steps.forEach(section => section.hidden = Number(section.dataset.step) !== current);
        targets.forEach(target => {
            const selected = Number(target.dataset.stepTarget) === current;
            target.classList.toggle('is-current', selected);
            target.setAttribute('aria-selected', selected ? 'true' : 'false');
            target.tabIndex = selected ? 0 : -1;
            if (selected) target.setAttribute('aria-current', 'step');
            else target.removeAttribute('aria-current');
        });
        back.hidden = current === 1;
        next.hidden = current === steps.length;
        submit.hidden = current !== steps.length;
        if (updateHash && window.location.hash !== stepHash(current)) {
            history.replaceState(history.state, '', stepHash(current));
        }
        syncNavigation();
        if (focusHeading) {
            requestAnimationFrame(() => steps[current - 1].focus());
        }
    };

    const invalid = (field, text) => {
        field.setCustomValidity(text);
        field.setAttribute('aria-invalid', 'true');
        return field;
    };

    const validate = step => {
        const names = [...(required[step] || [])];
        if (step === 1 && ownership?.value === 'artist_account') names.push('owner_user_id');
        if (step === 1 && ownership?.value === 'platform') names.push('artist_name');
        if (step === 1 && edition.value === 'limited') names.push('edition_number', 'edition_total');
        if (step === 2 && framed.checked) names.push('frame_material');

        for (const name of names) {
            const field = form.elements[name];
            if (!field) continue;
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            if (serverErrorFields.has(name)) return invalid(field, 'Please correct this field before continuing.');
            if (String(field.value ?? '').trim() === '') return invalid(field, 'Please complete this required field.');
            if (!field.checkValidity()) return invalid(field, field.validationMessage || 'Please enter a valid value.');
        }

        for (const field of steps[step - 1].querySelectorAll('[name]')) {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            if (serverErrorFields.has(field.name)) return invalid(field, 'Please correct this field before continuing.');
            if (!field.checkValidity()) return invalid(field, field.validationMessage || 'Please enter a valid value.');
        }

        if (step === 1 && edition.value === 'limited') {
            const number = form.elements.edition_number;
            const total = form.elements.edition_total;
            if (Number(number.value) > Number(total.value)) return invalid(number, 'Edition number cannot exceed the total edition count.');
        }

        if (step === 4) {
            const price = form.elements.requested_price;
            if (!moneyPattern.test(price.value) || Number(price.value) <= 0) return invalid(price, 'Enter a price greater than zero with up to three decimal places.');
            for (const name of ['frame_cost', 'packaging_cost']) {
                const field = form.elements[name];
                if (field.value !== '' && !moneyPattern.test(field.value)) return invalid(field, 'Enter zero or a positive amount with up to three decimal places.');
            }
        }

        return null;
    };

    const revealInvalid = (step, field) => {
        show(step);
        requestAnimationFrame(() => {
            field.reportValidity();
            field.focus();
        });
    };

    const blockForConflict = () => {
        if (!navigationBlocked) return false;
        state('conflict', 'This draft changed elsewhere. Refresh the page before continuing.');
        status.setAttribute('tabindex', '-1');
        status.focus();
        return true;
    };

    const validateRange = (first, last) => {
        for (let step = first; step <= last; step++) {
            const field = validate(step);
            if (field) {
                revealInvalid(step, field);
                return false;
            }
        }
        return true;
    };

    const go = (target, options = {}) => {
        target = Number(target);
        if (target > current) {
            if (blockForConflict()) return;
            if (!validateRange(current, target - 1)) return;
        }
        show(target, options);
    };

    const conditional = () => {
        limited.hidden = edition.value !== 'limited';
        frame.hidden = !framed.checked;
        if (ownership && owner && ownerField && artistName) {
            const platformOwned = ownership.value === 'platform';
            ownerField.hidden = platformOwned;
            owner.disabled = platformOwned;
            owner.required = !platformOwned;
            artistName.required = platformOwned;
            if (platformRequired) platformRequired.hidden = !platformOwned;
        }
    };

    owner?.addEventListener('change', () => {
        if (!artistName || artistName.value.trim() !== '') return;
        artistName.value = owner.selectedOptions[0]?.dataset.artistName || '';
        artistName.dispatchEvent(new Event('input', { bubbles: true }));
    });
    ownership?.addEventListener('change', () => {
        conditional();
        if (ownership.value === 'artist_account' && owner && artistName && artistName.value.trim() === '') {
            artistName.value = owner.selectedOptions[0]?.dataset.artistName || '';
            artistName.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    targets.forEach((target, index) => {
        target.addEventListener('click', () => go(target.dataset.stepTarget, { focusHeading: true }));
        target.addEventListener('keydown', event => {
            const keyTargets = {
                ArrowLeft: Math.max(0, index - 1),
                ArrowRight: Math.min(targets.length - 1, index + 1),
                Home: 0,
                End: targets.length - 1,
            };
            if (!(event.key in keyTargets)) return;
            event.preventDefault();
            const nextTarget = targets[keyTargets[event.key]];
            go(nextTarget.dataset.stepTarget);
            if (current === Number(nextTarget.dataset.stepTarget)) nextTarget.focus();
        });
    });
    back.addEventListener('click', () => show(current - 1, { focusHeading: true }));
    next.addEventListener('click', () => go(current + 1, { focusHeading: true }));
    edition.addEventListener('change', conditional);
    framed.addEventListener('change', conditional);
    conditional();

    const firstServerErrorStep = [...serverErrorFields]
        .map(name => fieldSteps.get(name))
        .filter(Boolean)
        .sort((a, b) => a - b)[0] || null;
    const requestedStep = stepFromHash();
    if (firstServerErrorStep) {
        show(firstServerErrorStep, { focusHeading: true });
    } else {
        show(1, { updateHash: !requestedStep });
        if (requestedStep) go(requestedStep, { updateHash: false, focusHeading: true });
    }
    if (navigationBlocked) state('conflict', 'This draft changed elsewhere. Refresh the page before continuing.');
    window.addEventListener('hashchange', () => {
        const requested = stepFromHash();
        if (requested && requested !== current) go(requested, { updateHash: false, focusHeading: true });
    });

    const save = async () => {
        if (submitting || busy || !dirty || navigationBlocked) return;
        if (!navigator.onLine) {
            state('offline', 'Offline. Unsaved changes remain in this form.', true);
            return;
        }
        busy = true;
        dirty = false;
        state('saving', 'Saving private Store draft...');
        const data = new FormData(form);
        data.delete('_method');
        data.set('intent', 'save');
        try {
            const response = await fetch(form.dataset.autosaveUrl, {
                method: 'POST',
                body: data,
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const body = await response.json().catch(() => ({}));
            if (response.status === 409) {
                dirty = true;
                navigationBlocked = true;
                state('conflict', body.message || 'This draft changed elsewhere. Refresh before retrying.');
                syncNavigation();
                return;
            }
            if (!response.ok) {
                dirty = true;
                state('error', body.message || 'Draft could not be saved.', true);
                return;
            }
            form.dataset.autosaveUrl = body.autosave_url;
            lock.value = body.lock_version;
            history.replaceState(history.state, '', body.edit_url + stepHash(current));
            state('saved', 'Private Store draft saved.');
        } catch (_) {
            dirty = true;
            state('offline', 'Connection interrupted. Changes remain here.', true);
        } finally {
            busy = false;
            if (dirty && navigator.onLine && !navigationBlocked) timer = setTimeout(save, 2500);
        }
    };

    form.addEventListener('input', event => {
        if (navigationBlocked) {
            state('conflict', 'This draft changed elsewhere. Refresh the page before continuing.');
            return;
        }
        const field = event.target;
        if (field?.name) serverErrorFields.delete(field.name);
        field?.setCustomValidity?.('');
        field?.removeAttribute?.('aria-invalid');
        dirty = true;
        clearTimeout(timer);
        state('pending', 'Unsaved changes...');
        timer = setTimeout(save, 1200);
    });
    form.addEventListener('change', event => {
        if (navigationBlocked) {
            state('conflict', 'This draft changed elsewhere. Refresh the page before continuing.');
            return;
        }
        const field = event.target;
        if (field?.name) serverErrorFields.delete(field.name);
        field?.setCustomValidity?.('');
        field?.removeAttribute?.('aria-invalid');
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, 500);
    });
    form.addEventListener('submit', event => {
        const intent = event.submitter?.value || 'save';
        if (navigationIntents.has(intent)) {
            if (blockForConflict() || !validateRange(1, steps.length)) {
                event.preventDefault();
                return;
            }

            if (pendingSubmit) {
                event.preventDefault();
                return;
            }

            if (busy || dirty) {
                event.preventDefault();
                clearTimeout(timer);
                pendingSubmit = true;
                const submitter = event.submitter;

                (async () => {
                    while (busy) await new Promise(resolve => setTimeout(resolve, 50));
                    if (dirty && !navigationBlocked) await save();
                    pendingSubmit = false;

                    if (!busy && !dirty && !navigationBlocked) form.requestSubmit(submitter);
                })();

                return;
            }
        }
        submitting = true;
        clearTimeout(timer);
    });
    retry.addEventListener('click', () => {
        dirty = true;
        save();
    });
    window.addEventListener('offline', () => state('offline', 'Offline. Unsaved changes remain in this form.', true));
    window.addEventListener('online', () => {
        dirty = true;
        save();
    });
});
</script>
