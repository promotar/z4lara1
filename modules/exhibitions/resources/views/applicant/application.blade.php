@push('styles')
    <link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

@php
    $links = old('previous_work_links', $application ? json_decode($application->previous_work_links ?: '[]', true) : []);
    $socials = old('social_accounts', $application ? json_decode($application->social_accounts ?: '[]', true) : []);
    $value = fn (string $field, mixed $fallback = '') => old($field, $application?->{$field} ?? $fallback);
    $identityTypes = $identityDocuments->pluck('type');
    $storedIdentityType = $identityTypes->contains('identity_passport') ? 'passport' : ($identityTypes->contains(fn ($type) => str_contains($type, 'license')) ? 'driver_license' : ($identityDocuments->isNotEmpty() ? 'national_id' : ''));
    $selectedIdentityType = old('identity_document_type', $storedIdentityType);
    $stepOneFields = ['legal_name', 'birth_date', 'nationality', 'artistic_name', 'years_experience', 'contact_email', 'contact_phone', 'city', 'country'];
    $stepTwoFields = ['biography', 'qualifications', 'previous_exhibitions', 'awards_certificates', 'previous_work_links', 'previous_work_links.*', 'social_accounts', 'social_accounts.*'];
    $stepThreeFields = ['sample_artworks', 'sample_artworks.*', 'identity_document_type', 'identity_front', 'identity_back', 'identity_single', 'supporting_documents', 'supporting_documents.*'];
    $stepFourFields = ['accept_artist_agreement', 'ownership_declaration', 'application'];
    $initialWizardStep = $errors->hasAny($stepOneFields) ? 1 : ($errors->hasAny($stepTwoFields) ? 2 : ($errors->hasAny($stepThreeFields) ? 3 : ($errors->hasAny($stepFourFields) || $errors->any() ? 4 : 1)));
    $submitButtonLabel = match($application?->state) {
        'documents_required' => 'Submit response for review',
        'rejected' => 'Submit application again',
        default => 'Submit application',
    };
@endphp

<x-app-layout>
    <main class="z4-exhibitions-admin" data-exhibitions-application-form>
        <div class="z4-exhibitions-container z4-exhibitions-form-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_df3e356ed7') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_80e3dce73d') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_d145ddfce7') }}</p>
                </div>
                <span class="z4-exhibitions-status">{{ $application ? ucfirst(str_replace('_', ' ', $application->state)) : 'New application' }}</span>
            </header>

            @if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
            @if($application?->state === 'documents_required')
                <div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_838b4a1a80') }}</strong> {{ $application->current_reason }} @if($application->next_action)<br>{{ $application->next_action }}@endif @if($application->response_deadline)<br><strong>{{ __('exhibitions::ui.auto_e4c056f0be') }}</strong> {{ $application->response_deadline }}@endif</div>
            @endif
            @if($application?->state === 'rejected')
                <div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_0f9d4cf0b1') }}</strong> {{ __('exhibitions::ui.auto_6251014ac5') }}<br><strong>{{ __('exhibitions::ui.auto_dd92957478') }}</strong> {{ $application->current_reason }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-error" role="alert"><strong>{{ __('exhibitions::ui.auto_481871569a') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" enctype="multipart/form-data" class="z4-exhibitions-form" action="{{ route('exhibitions.application.draft') }}" data-application-wizard data-initial-step="{{ $initialWizardStep }}" data-has-errors="{{ $errors->any() ? 'true' : 'false' }}" data-storage-key="artist-application-{{ auth()->id() }}" data-stored-samples="{{ $samples->count() }}" data-stored-identities="{{ $identityDocuments->count() }}" novalidate>
                @csrf

                <ol class="z4-registration-progress is-four-steps" aria-label="{{ __('exhibitions::ui.auto_c628a61dd0') }}" data-wizard-progress>
                    <li data-wizard-progress-step><span>1</span><small>{{ __('exhibitions::ui.auto_412ea409d6') }}</small></li>
                    <li data-wizard-progress-step><span>2</span><small>{{ __('exhibitions::ui.auto_5522bec500') }}</small></li>
                    <li data-wizard-progress-step><span>3</span><small>{{ __('exhibitions::ui.auto_298895c8d6') }}</small></li>
                    <li data-wizard-progress-step><span>4</span><small>{{ __('exhibitions::ui.auto_623cba9077') }}</small></li>
                </ol>
                <p class="z4-wizard-status" aria-live="polite" data-wizard-status></p>

                <div class="z4-wizard-step-group" data-wizard-step="1" data-wizard-title="{{ __('exhibitions::ui.auto_a5ab3467d2') }}">
                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-personal">
                        <div class="z4-exhibitions-section-title"><span>01</span><div><h2 id="artist-application-personal" tabindex="-1">{{ __('exhibitions::ui.auto_814593f379') }}</h2><p>{{ __('exhibitions::ui.auto_1bfd663ede') }}</p></div></div>
                        <div class="z4-exhibitions-fields">
                            <label><span>{{ __('exhibitions::ui.auto_0be82c0753') }} <b>*</b></span><input name="legal_name" value="{{ $value('legal_name') }}" maxlength="160" autocomplete="name" required autofocus><small>@error('legal_name'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_4965e9e891') }} <b>*</b></span><input type="date" name="birth_date" value="{{ $value('birth_date') }}" max="{{ now()->subDay()->toDateString() }}" required><small>@error('birth_date'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_e93d1142ee') }} <b>*</b></span><input name="nationality" value="{{ $value('nationality') }}" maxlength="100" required><small>@error('nationality'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_bb7cf40f9d') }} <b>*</b></span><input name="artistic_name" value="{{ $value('artistic_name') }}" maxlength="160" required><small>@error('artistic_name'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_b00613f337') }} <b>*</b></span><input type="number" min="0" max="100" name="years_experience" value="{{ $value('years_experience') }}" required><small>@error('years_experience'){{ $message }}@enderror</small></label>
                        </div>
                    </section>

                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-contact">
                        <div class="z4-exhibitions-section-title"><span>02</span><div><h2 id="artist-application-contact" tabindex="-1">{{ __('exhibitions::ui.auto_257954ef94') }}</h2><p>{{ __('exhibitions::ui.auto_89183a58fd') }}</p></div></div>
                        <div class="z4-exhibitions-fields">
                            <label><span>{{ __('exhibitions::ui.auto_ce8ae9da5b') }} <b>*</b></span><input type="email" name="contact_email" value="{{ $value('contact_email', auth()->user()->email) }}" maxlength="254" autocomplete="email" required><small>@error('contact_email'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_bcc254b55c') }} <b>*</b></span><input type="tel" name="contact_phone" value="{{ $value('contact_phone') }}" maxlength="40" placeholder="+962 ..." required><small>@error('contact_phone'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_57d056ed09') }} <b>*</b></span><input name="city" value="{{ $value('city') }}" maxlength="120" required><small>@error('city'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_59716c9749') }} <b>*</b></span><input name="country" value="{{ $value('country') }}" maxlength="120" required><small>@error('country'){{ $message }}@enderror</small></label>
                        </div>
                    </section>
                </div>

                <div class="z4-wizard-step-group" data-wizard-step="2" data-wizard-title="{{ __('exhibitions::ui.auto_5522bec500') }}">
                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-profile">
                        <div class="z4-exhibitions-section-title"><span>03</span><div><h2 id="artist-application-profile" tabindex="-1">{{ __('exhibitions::ui.auto_d311b5d9d1') }}</h2><p>Biography is required; the remaining fields help reviewers understand your practice.</p></div></div>
                        <div class="z4-exhibitions-fields is-single">
                            <label><span>{{ __('exhibitions::ui.auto_e2a085c96a') }} <b>*</b></span><textarea name="biography" rows="7" minlength="100" maxlength="5000" required>{{ $value('biography') }}</textarea><small>100–5,000 characters on submission. @error('biography'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_339adbb480') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="qualifications" rows="4" maxlength="4000">{{ $value('qualifications') }}</textarea></label>
                            <label><span>{{ __('exhibitions::ui.auto_e36a40d358') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="previous_exhibitions" rows="4" maxlength="4000">{{ $value('previous_exhibitions') }}</textarea></label>
                            <label><span>{{ __('exhibitions::ui.auto_0b7b4b09a4') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="awards_certificates" rows="4" maxlength="4000">{{ $value('awards_certificates') }}</textarea></label>
                        </div>
                        <div class="z4-exhibitions-fields">
                            @for($index = 0; $index < 3; $index++)
                                <label><span>Previous-work link {{ $index + 1 }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><input type="url" name="previous_work_links[]" value="{{ $links[$index] ?? '' }}" maxlength="2048" placeholder="https://"></label>
                            @endfor
                            @for($index = 0; $index < 3; $index++)
                                <label><span>Social account {{ $index + 1 }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><input type="url" name="social_accounts[]" value="{{ $socials[$index] ?? '' }}" maxlength="2048" placeholder="https://"></label>
                            @endfor
                        </div>
                    </section>
                </div>

                <div class="z4-wizard-step-group" data-wizard-step="3" data-wizard-title="{{ __('exhibitions::ui.auto_079259c9d2') }}">
                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-samples">
                        <div class="z4-exhibitions-section-title"><span>04</span><div><h2 id="artist-application-samples" tabindex="-1">{{ __('exhibitions::ui.auto_5f66ae34eb') }}</h2><p>{{ __('exhibitions::ui.auto_e749bf8457') }}</p></div></div>
                        <label class="z4-exhibitions-upload"><input type="file" name="sample_artworks[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" data-sample-files @required($samples->isEmpty())><span>{{ $samples->isEmpty() ? 'Choose at least one private sample file' : 'Add more private sample files' }}</span></label>
                        @error('sample_artworks')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                        @error('sample_artworks.*')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                        @if($samples->isNotEmpty())
                            <ul class="z4-exhibitions-file-list">@foreach($samples as $sample)<li><a href="{{ route('exhibitions.application.samples.download', $sample->public_id) }}">{{ $sample->original_name }}</a><span>{{ number_format($sample->size_bytes / 1024, 1) }} KB</span></li>@endforeach</ul>
                        @endif
                    </section>

                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-identity">
                        <div class="z4-exhibitions-section-title"><span>05</span><div><h2 id="artist-application-identity" tabindex="-1">{{ __('exhibitions::ui.auto_b989885a3d') }}</h2><p>{{ __('exhibitions::ui.auto_ee8f77dc43') }}</p></div></div>
                        <div class="z4-exhibitions-fields is-single" data-registration-identity data-has-front="{{ $identityTypes->contains(fn ($type) => str_ends_with($type, '_front')) ? 'true' : 'false' }}" data-has-back="{{ $identityTypes->contains(fn ($type) => str_ends_with($type, '_back')) ? 'true' : 'false' }}" data-has-single="{{ $identityTypes->contains('identity_passport') ? 'true' : 'false' }}">
                            <label><span>{{ __('exhibitions::ui.auto_208a23b4a6') }} <b>*</b></span><select name="identity_document_type" required data-identity-document-type><option value="">{{ __('exhibitions::ui.auto_f77a1e2dde') }}</option><option value="national_id" @selected($selectedIdentityType === 'national_id')>{{ __('exhibitions::ui.auto_16343fb460') }}</option><option value="passport" @selected($selectedIdentityType === 'passport')>{{ __('exhibitions::ui.auto_916df6dabc') }}</option><option value="driver_license" @selected($selectedIdentityType === 'driver_license')>{{ __('exhibitions::ui.auto_bd03b352eb') }}</option></select><small>@error('identity_document_type'){{ $message }}@enderror</small></label>
                            <div class="z4-exhibitions-fields" data-identity-two-sided hidden>
                                <label><span>{{ __('exhibitions::ui.auto_80b40814a3') }} <b>*</b></span><input type="file" name="identity_front" accept=".jpg,.jpeg,.png,.pdf" data-identity-front><small>JPG, PNG, or PDF up to 10 MB. @error('identity_front'){{ $message }}@enderror</small></label>
                                <label><span>{{ __('exhibitions::ui.auto_b3b863b7f3') }} <b>*</b></span><input type="file" name="identity_back" accept=".jpg,.jpeg,.png,.pdf" data-identity-back><small>JPG, PNG, or PDF up to 10 MB. @error('identity_back'){{ $message }}@enderror</small></label>
                            </div>
                            <label data-identity-single hidden><span>{{ __('exhibitions::ui.auto_afaac978de') }} <b>*</b></span><input type="file" name="identity_single" accept=".jpg,.jpeg,.png,.pdf" data-identity-single-file><small>Upload the photo/details page. JPG, PNG, or PDF up to 10 MB. @error('identity_single'){{ $message }}@enderror</small></label>
                        </div>
                        @if($identityDocuments->isNotEmpty())<ul class="z4-exhibitions-file-list">@foreach($identityDocuments as $document)<li><strong>{{ ucwords(str_replace(['identity_', '_'], ['', ' '], $document->type)) }}</strong><span>{{ $document->original_name }} · Uploaded securely</span></li>@endforeach</ul>@endif
                    </section>

                    @if($application?->state === 'documents_required')
                        <section class="z4-exhibitions-panel" aria-labelledby="artist-application-supporting-documents">
                            <div class="z4-exhibitions-section-title"><span>06</span><div><h2 id="artist-application-supporting-documents" tabindex="-1">{{ __('exhibitions::ui.auto_fee8f85998') }}</h2><p>{{ __('exhibitions::ui.auto_7ce4dcfdda') }}</p></div></div>
                            <label class="z4-exhibitions-upload"><input type="file" name="supporting_documents[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"><span>{{ __('exhibitions::ui.auto_b1f68e32b9') }}</span></label>
                            <small>Up to 8 files. JPG, PNG, WebP, PDF, DOC, or DOCX; maximum 10 MB per file.</small>
                            @error('supporting_documents')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                            @error('supporting_documents.*')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                            @if($supportingDocuments->isNotEmpty())
                                <ul class="z4-exhibitions-file-list">@foreach($supportingDocuments as $document)<li><a href="{{ route('exhibitions.application.supporting-documents.download', $document->public_id) }}">{{ $document->original_name }}</a><span>{{ number_format($document->size_bytes / 1024, 1) }} KB</span></li>@endforeach</ul>
                            @endif
                        </section>
                    @endif
                </div>

                <div class="z4-wizard-step-group" data-wizard-step="4" data-wizard-title="{{ __('exhibitions::ui.auto_970048665e') }}">
                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-review">
                        <div class="z4-exhibitions-section-title"><span>{{ $application?->state === 'documents_required' ? '07' : '06' }}</span><div><h2 id="artist-application-review" tabindex="-1">{{ __('exhibitions::ui.auto_5e1a79852f') }}</h2><p>{{ __('exhibitions::ui.auto_cde831ca8b') }}</p></div></div>
                        <dl class="z4-application-review-grid">
                            <div><dt>{{ __('exhibitions::ui.auto_bec632bf40') }}</dt><dd data-review-output="applicant">{{ __('exhibitions::ui.auto_34fac42b8d') }}</dd></div>
                            <div><dt>{{ __('exhibitions::ui.auto_bbaff12800') }}</dt><dd data-review-output="contact">{{ __('exhibitions::ui.auto_34fac42b8d') }}</dd></div>
                            <div><dt>{{ __('exhibitions::ui.auto_4038d5a859') }}</dt><dd data-review-output="experience">{{ __('exhibitions::ui.auto_34fac42b8d') }}</dd></div>
                            <div><dt>{{ __('exhibitions::ui.auto_91f3a2c0e4') }}</dt><dd data-review-output="files">{{ __('exhibitions::ui.auto_34fac42b8d') }}</dd></div>
                        </dl>
                    </section>

                    <section class="z4-exhibitions-panel" aria-labelledby="artist-application-agreement">
                        <div class="z4-exhibitions-section-title"><span>{{ $application?->state === 'documents_required' ? '08' : '07' }}</span><div><h2 id="artist-application-agreement" tabindex="-1">{{ __('exhibitions::ui.auto_eac562fc43') }}</h2><p>{{ __('exhibitions::ui.auto_f26fa6cc10') }}</p></div></div>
                        @if($agreementTemplate)
                            <div class="z4-exhibitions-agreement"><header><div><strong>{{ $agreementTemplate->accepted_title ?? $agreementTemplate->title }}</strong><small>Version {{ $agreementTemplate->accepted_version ?? $agreementTemplate->version }}</small></div></header><div class="z4-exhibitions-agreement-content">{!! nl2br(e($agreementTemplate->accepted_content ?? $agreementTemplate->content)) !!}</div></div>
                            <label class="z4-exhibitions-consent"><input type="checkbox" name="accept_artist_agreement" value="1" required @checked(old('accept_artist_agreement', (bool) $agreement?->accepted_at))><span>{{ __('exhibitions::ui.auto_d7523db86b') }} <b>*</b></span></label>
                            @error('accept_artist_agreement')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                        @else
                            <div class="z4-exhibitions-alert is-error">{{ __('exhibitions::ui.auto_11818a7f44') }}</div>
                        @endif
                    </section>

                    <section class="z4-exhibitions-panel z4-exhibitions-declarations">
                        <label><input type="checkbox" name="ownership_declaration" value="1" required @checked(old('ownership_declaration', (bool) $application?->ownership_declared_at))><span>{{ __('exhibitions::ui.auto_da59699a74') }} <b>*</b></span></label>
                        @error('ownership_declaration')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                    </section>
                </div>

                <div class="z4-exhibitions-actions z4-artist-application-actions">
                    <button class="is-secondary" type="submit" formnovalidate data-save-draft>{{ __('exhibitions::ui.auto_48ca50ce77') }}</button>
                    <div class="z4-wizard-navigation">
                        <button class="is-secondary z4-wizard-button" type="button" data-wizard-back>{{ __('exhibitions::ui.auto_0557fa923d') }}</button>
                        <button class="is-primary z4-wizard-button" type="button" data-wizard-next>{{ __('exhibitions::ui.auto_10ac3d0425') }}</button>
                        <button class="is-primary" type="submit" formaction="{{ route('exhibitions.application.submit') }}" data-wizard-submit @disabled(!$agreementTemplate)>{{ $submitButtonLabel }}</button>
                    </div>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-application-wizard]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-wizard-step]'));
    const progressSteps = Array.from(wizard.querySelectorAll('[data-wizard-progress-step]'));
    const status = wizard.querySelector('[data-wizard-status]');
    const backButton = wizard.querySelector('[data-wizard-back]');
    const nextButton = wizard.querySelector('[data-wizard-next]');
    const submitButton = wizard.querySelector('[data-wizard-submit]');
    const sampleFiles = wizard.querySelector('[data-sample-files]');
    const identityRoot = wizard.querySelector('[data-registration-identity]');
    const identityType = identityRoot?.querySelector('[data-identity-document-type]');
    const identityTwoSided = identityRoot?.querySelector('[data-identity-two-sided]');
    const identitySingle = identityRoot?.querySelector('[data-identity-single]');
    const identityFront = identityRoot?.querySelector('[data-identity-front]');
    const identityBack = identityRoot?.querySelector('[data-identity-back]');
    const identitySingleFile = identityRoot?.querySelector('[data-identity-single-file]');
    const storageKey = wizard.dataset.storageKey;
    const serverStep = Number.parseInt(wizard.dataset.initialStep || '1', 10);
    const savedStep = Number.parseInt(sessionStorage.getItem(storageKey) || '', 10);
    let currentStep = (wizard.dataset.hasErrors === 'true' || !Number.isInteger(savedStep) ? serverStep : savedStep) - 1;

    const fieldValue = (name) => wizard.querySelector(`[name="${name}"]`)?.value.trim() || '';
    const setReview = (key, parts) => {
        const output = wizard.querySelector(`[data-review-output="${key}"]`);
        if (output) output.textContent = parts.filter(Boolean).join(' · ') || 'Not provided';
    };

    const refreshReview = () => {
        const storedSamples = Number.parseInt(wizard.dataset.storedSamples || '0', 10);
        const selectedSamples = sampleFiles?.files?.length || 0;
        const storedIdentities = Number.parseInt(wizard.dataset.storedIdentities || '0', 10);
        const selectedIdentities = [identityFront, identityBack, identitySingleFile].filter((field) => field?.files?.length).length;
        const identityLabel = identityType?.selectedOptions?.[0]?.textContent?.trim() || '';

        setReview('applicant', [fieldValue('legal_name'), fieldValue('artistic_name')]);
        setReview('contact', [fieldValue('contact_email'), fieldValue('contact_phone'), fieldValue('city'), fieldValue('country')]);
        setReview('experience', [fieldValue('years_experience') ? `${fieldValue('years_experience')} years` : '', fieldValue('nationality')]);
        setReview('files', [
            `${storedSamples + selectedSamples} sample artwork${storedSamples + selectedSamples === 1 ? '' : 's'}`,
            identityLabel && identityType.value ? identityLabel : '',
            `${storedIdentities + selectedIdentities} identity file${storedIdentities + selectedIdentities === 1 ? '' : 's'}`,
        ]);
    };

    const syncIdentityFields = () => {
        const passport = identityType?.value === 'passport';
        const twoSided = Boolean(identityType?.value) && !passport;
        const hasFront = identityRoot?.dataset.hasFront === 'true';
        const hasBack = identityRoot?.dataset.hasBack === 'true';
        const hasSingle = identityRoot?.dataset.hasSingle === 'true';
        if (identityTwoSided) identityTwoSided.hidden = !twoSided;
        if (identitySingle) identitySingle.hidden = !passport;
        if (identityFront) identityFront.required = twoSided && !hasFront;
        if (identityBack) identityBack.required = twoSided && !hasBack;
        if (identitySingleFile) identitySingleFile.required = passport && !hasSingle;
    };

    const refreshSampleValidity = () => {
        if (!sampleFiles) return;
        const files = Array.from(sampleFiles.files || []);
        let message = '';
        if (files.length > 8) message = 'Choose no more than 8 sample files.';
        else if (files.some((file) => file.size > 8 * 1024 * 1024)) message = 'Each sample file must be 8 MB or smaller.';
        else if (files.some((file) => !/\.(jpe?g|png|webp|pdf)$/i.test(file.name))) message = 'Use JPG, PNG, WebP, or PDF sample files only.';
        sampleFiles.setCustomValidity(message);
    };

    const validateStep = (step) => {
        syncIdentityFields();
        refreshSampleValidity();
        const fields = Array.from(step.querySelectorAll('input:not([type="hidden"]), textarea, select'));
        const invalidField = fields.find((field) => !field.disabled && !field.checkValidity());
        if (!invalidField) return true;
        invalidField.reportValidity();
        invalidField.focus({ preventScroll: true });
        return false;
    };

    const showStep = (index, moveFocus = false) => {
        currentStep = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, stepIndex) => { step.hidden = stepIndex !== currentStep; });
        progressSteps.forEach((step, stepIndex) => {
            step.classList.toggle('is-current', stepIndex === currentStep);
            step.classList.toggle('is-complete', stepIndex < currentStep);
            if (stepIndex === currentStep) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
        });
        backButton.hidden = currentStep === 0;
        nextButton.hidden = currentStep === steps.length - 1;
        submitButton.hidden = currentStep !== steps.length - 1;
        status.textContent = `Step ${currentStep + 1} of ${steps.length}: ${steps[currentStep].dataset.wizardTitle}`;
        sessionStorage.setItem(storageKey, String(currentStep + 1));
        if (currentStep === steps.length - 1) refreshReview();
        if (moveFocus) {
            steps[currentStep].querySelector('h2')?.focus({ preventScroll: true });
            steps[currentStep].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    identityType?.addEventListener('change', () => { syncIdentityFields(); refreshReview(); });
    sampleFiles?.addEventListener('change', () => { refreshSampleValidity(); refreshReview(); });
    wizard.classList.add('is-wizard-ready');
    syncIdentityFields();
    showStep(currentStep);
    nextButton.addEventListener('click', () => { if (validateStep(steps[currentStep])) showStep(currentStep + 1, true); });
    backButton.addEventListener('click', () => showStep(currentStep - 1, true));

    wizard.addEventListener('submit', (event) => {
        if (event.submitter?.matches('[data-save-draft]')) return;
        syncIdentityFields();
        refreshSampleValidity();
        const invalidField = wizard.querySelector(':invalid');
        if (!invalidField) {
            sessionStorage.removeItem(storageKey);
            return;
        }
        event.preventDefault();
        const invalidStep = invalidField.closest('[data-wizard-step]');
        showStep(Math.max(0, steps.indexOf(invalidStep)), true);
        window.requestAnimationFrame(() => {
            invalidField.reportValidity();
            invalidField.focus({ preventScroll: true });
        });
    });
});
</script>
