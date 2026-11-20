<x-guest-layout>
    <link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
    <style>
        .z4-registration-progress li { position: relative; }
        .z4-registration-progress li.has-errors > span { background: #b42318 !important; border-color: #b42318 !important; color: #fff !important; }
        .z4-step-error { position: absolute; top: -6px; inset-inline-end: -6px; width: 18px; height: 18px; border-radius: 50%; background: #b42318; color: #fff; font: 700 11px/18px sans-serif; text-align: center; }
    </style>

    <div class="z4-exhibitions-admin z4-artist-registration" data-artist-registration>
        <div class="z4-exhibitions-container z4-exhibitions-form-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_ed73b89c57') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_d4d58f033b') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_56a71d76df') }}</p>
                </div>
                <span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_1d433beefa') }}</span>
            </header>

            @if($errors->any())
                <div class="z4-exhibitions-alert is-error" role="alert">
                    <strong>{{ __('exhibitions::ui.auto_481871569a') }}</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @php
                $stepOneFields = ['full_name', 'email', 'password', 'birth_date', 'nationality', 'years_experience', 'identity_document_type', 'identity_front', 'identity_back', 'identity_single'];
                $stepTwoFields = ['contact_phone', 'city', 'country_code', 'biography', 'qualifications', 'previous_exhibitions', 'awards_certificates'];
                $stepThreeFields = ['previous_work_links.*', 'sample_artworks', 'sample_artworks.*', 'agreement_template', 'accept_artist_agreement', 'terms', 'ownership_declaration'];
                $initialWizardStep = $errors->hasAny($stepOneFields) ? 1 : ($errors->hasAny($stepTwoFields) ? 2 : ($errors->hasAny($stepThreeFields) || $errors->any() ? 3 : 1));
                $stepOneHasErrors = $errors->hasAny($stepOneFields);
                $stepTwoHasErrors = $errors->hasAny($stepTwoFields);
                $stepThreeHasErrors = $errors->any() && ! $stepOneHasErrors && ! $stepTwoHasErrors;
                $selectedCountryCode = old('country_code', $detectedCountryCode);
                $selectedCountry = collect($countries)->firstWhere('code', $selectedCountryCode) ?? collect($countries)->firstWhere('code', 'JO');
                $birthDateValue = (string) old('birth_date', '');
                $birthDateDisplay = '';
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthDateValue, $birthDateParts) === 1) {
                    $birthDateDisplay = $birthDateParts[3].'/'.$birthDateParts[2].'/'.$birthDateParts[1];
                }
                $previousWorkLinks = old('previous_work_links', ['']);
                $previousWorkLinks = is_array($previousWorkLinks) ? array_slice(array_values($previousWorkLinks), 0, 10) : [''];
                $previousWorkLinks = $previousWorkLinks === [] ? [''] : $previousWorkLinks;
                $selectedIdentityType = old('identity_document_type', '');
            @endphp

            <form method="POST" enctype="multipart/form-data" class="z4-exhibitions-form" action="{{ route('exhibitions.artist.register.store') }}" data-registration-wizard data-initial-step="{{ $initialWizardStep }}" data-has-errors="{{ $errors->any() ? '1' : '0' }}">
                @csrf

                <ol class="z4-registration-progress" aria-label="{{ __('exhibitions::ui.auto_dc297c86de') }}" data-wizard-progress>
                    <li data-wizard-progress-step @class(['has-errors' => $stepOneHasErrors])><span>1</span><small>{{ __('exhibitions::ui.auto_8b28d99c82') }}</small>@if($stepOneHasErrors)<em class="z4-step-error" aria-label="Contains errors">!</em>@endif</li>
                    <li data-wizard-progress-step @class(['has-errors' => $stepTwoHasErrors])><span>2</span><small>{{ __('exhibitions::ui.auto_5522bec500') }}</small>@if($stepTwoHasErrors)<em class="z4-step-error" aria-label="Contains errors">!</em>@endif</li>
                    <li data-wizard-progress-step @class(['has-errors' => $stepThreeHasErrors])><span>3</span><small>{{ __('exhibitions::ui.auto_ccf0c9395a') }}</small>@if($stepThreeHasErrors)<em class="z4-step-error" aria-label="Contains errors">!</em>@endif</li>
                </ol>

                <p class="z4-wizard-status" aria-live="polite" data-wizard-status></p>

                <div class="z4-wizard-step-group" data-wizard-step="1" data-wizard-title="{{ __('exhibitions::ui.auto_ab3ff99e33') }}">
                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-1">
                    <div class="z4-exhibitions-section-title"><span>01</span><div><h2 id="artist-registration-step-1" tabindex="-1">{{ __('exhibitions::ui.auto_57a822b31f') }}</h2><p>{{ __('exhibitions::ui.auto_1cb0b21a4b') }}</p></div></div>
                    <div class="z4-exhibitions-fields">
                        <label><span>{{ __('exhibitions::ui.auto_f11b368cdd') }} <b>*</b></span><input name="full_name" value="{{ old('full_name') }}" maxlength="160" required autofocus autocomplete="name"><small>@error('full_name'){{ $message }}@enderror</small></label>
                        <label><span>{{ __('exhibitions::ui.auto_b357b524e7') }} <b>*</b></span><input type="email" name="email" value="{{ old('email') }}" maxlength="254" required autocomplete="username" data-registration-email><small>@error('email'){{ $message }}@enderror</small></label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_dc647eb65e') }} <b>*</b></span>
                            <span class="z4-password-field">
                                <input id="artist-password" type="password" name="password" minlength="8" required autocomplete="new-password" aria-describedby="artist-password-requirements" data-strong-password>
                                <button class="z4-password-toggle" type="button" data-password-toggle aria-controls="artist-password" aria-label="{{ __('exhibitions::ui.auto_a1cc7ba89c') }}" aria-pressed="false" title="{{ __('exhibitions::ui.auto_a1cc7ba89c') }}">
                                    <svg data-password-icon="show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    <svg data-password-icon="hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 6.1A10.8 10.8 0 0 1 12 6c6.25 0 9.75 6 9.75 6a15.9 15.9 0 0 1-2.3 3M6.2 7.2C3.65 9.05 2.25 12 2.25 12S5.75 18 12 18c1.7 0 3.15-.45 4.4-1.1M9.6 9.6A3.4 3.4 0 0 0 14.4 14.4"/></svg>
                                </button>
                            </span>
                            <small id="artist-password-requirements" class="z4-field-hint">{{ __('exhibitions::ui.auto_c0e698f3d3') }}</small>
                            @error('password')<small>{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_4c231e0da3') }} <b>*</b></span>
                            <span class="z4-password-field">
                                <input id="artist-password-confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" data-password-confirmation>
                                <button class="z4-password-toggle" type="button" data-password-toggle aria-controls="artist-password-confirmation" aria-label="{{ __('exhibitions::ui.auto_a1cc7ba89c') }}" aria-pressed="false" title="{{ __('exhibitions::ui.auto_a1cc7ba89c') }}">
                                    <svg data-password-icon="show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    <svg data-password-icon="hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 6.1A10.8 10.8 0 0 1 12 6c6.25 0 9.75 6 9.75 6a15.9 15.9 0 0 1-2.3 3M6.2 7.2C3.65 9.05 2.25 12 2.25 12S5.75 18 12 18c1.7 0 3.15-.45 4.4-1.1M9.6 9.6A3.4 3.4 0 0 0 14.4 14.4"/></svg>
                                </button>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-2">
                    <div class="z4-exhibitions-section-title"><span>02</span><div><h2 id="artist-registration-step-2" tabindex="-1">{{ __('exhibitions::ui.auto_412ea409d6') }}</h2><p>{{ __('exhibitions::ui.auto_90c5fa5b68') }}</p></div></div>
                    <div class="z4-exhibitions-fields">
                        <div class="z4-custom-date-field" data-date-picker data-max-date="{{ now()->subDay()->toDateString() }}">
                            <label for="artist-birth-date"><span>{{ __('exhibitions::ui.auto_4965e9e891') }} <b>*</b></span></label>
                            <div class="z4-date-picker-control">
                                <input id="artist-birth-date" type="text" value="{{ $birthDateDisplay }}" placeholder="{{ __('exhibitions::ui.auto_6ecb1ef5c3') }}" required autocomplete="off" inputmode="none" aria-haspopup="dialog" aria-expanded="false" data-date-display>
                                <button type="button" aria-label="{{ __('exhibitions::ui.auto_c84a259530') }}" aria-controls="artist-birth-date-popup" data-date-toggle>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3m10-3v3M4.5 9h15M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>
                                </button>
                            </div>
                            <input type="hidden" name="birth_date" value="{{ $birthDateValue }}" data-date-value>
                            <div id="artist-birth-date-popup" class="z4-date-picker-popup" role="dialog" aria-labelledby="artist-date-picker-title" data-date-popup hidden>
                                <header>
                                    <button type="button" aria-label="{{ __('exhibitions::ui.auto_22b653bd8d') }}" data-date-back hidden>&larr;</button>
                                    <strong id="artist-date-picker-title" data-date-title>{{ __('exhibitions::ui.auto_845a38afac') }}</strong>
                                    <button type="button" aria-label="{{ __('exhibitions::ui.auto_3af853a891') }}" data-date-close>&times;</button>
                                </header>
                                <div class="z4-date-picker-grid" data-date-grid></div>
                            </div>
                            <small>@error('birth_date'){{ $message }}@enderror</small>
                        </div>
                        <label><span>{{ __('exhibitions::ui.auto_e93d1142ee') }} <b>*</b></span><input name="nationality" value="{{ old('nationality') }}" maxlength="100" required><small>@error('nationality'){{ $message }}@enderror</small></label>
                        <label><span>{{ __('exhibitions::ui.auto_b00613f337') }} <b>*</b></span><input type="number" min="0" max="100" name="years_experience" value="{{ old('years_experience') }}" required><small>@error('years_experience'){{ $message }}@enderror</small></label>
                    </div>
                </section>

                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-identity">
                    <div class="z4-exhibitions-section-title"><span>03</span><div><h2 id="artist-registration-step-identity" tabindex="-1">{{ __('exhibitions::ui.auto_b989885a3d') }}</h2><p>{{ __('exhibitions::ui.auto_f056e5b227') }}</p></div></div>
                    <div class="z4-exhibitions-fields is-single" data-registration-identity>
                        <label><span>{{ __('exhibitions::ui.auto_208a23b4a6') }} <b>*</b></span><select name="identity_document_type" required data-identity-document-type><option value="">{{ __('exhibitions::ui.auto_f77a1e2dde') }}</option><option value="national_id" @selected($selectedIdentityType === 'national_id')>{{ __('exhibitions::ui.auto_16343fb460') }}</option><option value="passport" @selected($selectedIdentityType === 'passport')>{{ __('exhibitions::ui.auto_916df6dabc') }}</option><option value="driver_license" @selected($selectedIdentityType === 'driver_license')>{{ __('exhibitions::ui.auto_bd03b352eb') }}</option></select><small>@error('identity_document_type'){{ $message }}@enderror</small></label>
                        <div class="z4-exhibitions-fields" data-identity-two-sided hidden>
                            <label><span>{{ __('exhibitions::ui.auto_80b40814a3') }} <b>*</b></span><input type="file" name="identity_front" accept=".jpg,.jpeg,.png,.pdf" data-identity-front><small>JPG, PNG, or PDF up to 10 MB. @error('identity_front'){{ $message }}@enderror</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_b3b863b7f3') }} <b>*</b></span><input type="file" name="identity_back" accept=".jpg,.jpeg,.png,.pdf" data-identity-back><small>JPG, PNG, or PDF up to 10 MB. @error('identity_back'){{ $message }}@enderror</small></label>
                        </div>
                        <label data-identity-single hidden><span>{{ __('exhibitions::ui.auto_ccffc9f372') }} <b>*</b></span><input type="file" name="identity_single" accept=".jpg,.jpeg,.png,.pdf" data-identity-single-file><small>JPG, PNG, or PDF up to 10 MB. @error('identity_single'){{ $message }}@enderror</small></label>
                    </div>
                </section>
                </div>

                <div class="z4-wizard-step-group" data-wizard-step="2" data-wizard-title="{{ __('exhibitions::ui.auto_64220cee6e') }}">
                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-3">
                    <div class="z4-exhibitions-section-title"><span>04</span><div><h2 id="artist-registration-step-3" tabindex="-1">{{ __('exhibitions::ui.auto_257954ef94') }}</h2><p>{{ __('exhibitions::ui.auto_5e56da89fb') }}</p></div></div>
                    <div class="z4-exhibitions-fields">
                        <label>
                            <span>{{ __('exhibitions::ui.auto_59716c9749') }} <b>*</b></span>
                            <select name="country_code" required autocomplete="country" data-country-select data-has-old-value="{{ old('country_code') ? 'true' : 'false' }}">
                                @foreach($countries as $country)
                                    <option value="{{ $country['code'] }}" label="{{ $country['name'] }}" data-flag-url="{{ route('exhibitions.assets.flag', strtolower($country['code'])) }}" data-dial-code="{{ $country['dial_code'] }}" data-phone-example="{{ $country['phone_example'] }}" data-timezones="{{ implode('|', $country['timezones']) }}" @selected($selectedCountryCode === $country['code'])>{{ $country['name'] }}</option>
                                @endforeach
                            </select>
                            <small>Detected automatically; you can change it. @error('country_code'){{ $message }}@enderror</small>
                        </label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_bcc254b55c') }} <b>*</b></span>
                            <span class="z4-phone-field">
                                <span class="z4-phone-prefix" data-phone-prefix aria-hidden="true"><img data-phone-flag src="{{ route('exhibitions.assets.flag', strtolower($selectedCountry['code'])) }}" alt=""><span dir="ltr" data-phone-code>{{ $selectedCountry['dial_code'] }}</span></span>
                                <input type="tel" name="contact_phone" value="{{ old('contact_phone') }}" minlength="5" maxlength="30" pattern="[0-9() .-]{5,30}" placeholder="{{ $selectedCountry['phone_example'] }}" required autocomplete="tel-national" inputmode="tel" data-registration-phone>
                            </span>
                            <small>Enter the national number; the international code is added automatically. @error('contact_phone'){{ $message }}@enderror</small>
                        </label>
                        <label><span>{{ __('exhibitions::ui.auto_57d056ed09') }} <b>*</b></span><input name="city" value="{{ old('city') }}" maxlength="120" required autocomplete="address-level2"><small>@error('city'){{ $message }}@enderror</small></label>
                    </div>
                </section>

                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-4">
                    <div class="z4-exhibitions-section-title"><span>05</span><div><h2 id="artist-registration-step-4" tabindex="-1">{{ __('exhibitions::ui.auto_d311b5d9d1') }}</h2><p>{{ __('exhibitions::ui.auto_b84fb50bf9') }}</p></div></div>
                    <div class="z4-exhibitions-fields is-single">
                        <label><span>{{ __('exhibitions::ui.auto_e2a085c96a') }} <b>*</b></span><textarea name="biography" rows="7" minlength="100" maxlength="5000" required>{{ old('biography') }}</textarea><small>100–5,000 characters. @error('biography'){{ $message }}@enderror</small></label>
                        <label><span>{{ __('exhibitions::ui.auto_339adbb480') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="qualifications" rows="4" maxlength="4000">{{ old('qualifications') }}</textarea><small>@error('qualifications'){{ $message }}@enderror</small></label>
                        <label><span>{{ __('exhibitions::ui.auto_e36a40d358') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="previous_exhibitions" rows="4" maxlength="4000">{{ old('previous_exhibitions') }}</textarea><small>@error('previous_exhibitions'){{ $message }}@enderror</small></label>
                        <label><span>{{ __('exhibitions::ui.auto_0b7b4b09a4') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span><textarea name="awards_certificates" rows="4" maxlength="4000">{{ old('awards_certificates') }}</textarea><small>@error('awards_certificates'){{ $message }}@enderror</small></label>
                    </div>
                </section>
                </div>

                <div class="z4-wizard-step-group" data-wizard-step="3" data-wizard-title="{{ __('exhibitions::ui.auto_8e67a0ce70') }}">
                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-5">
                    <div class="z4-exhibitions-section-title"><span>06</span><div><h2 id="artist-registration-step-5" tabindex="-1">{{ __('exhibitions::ui.auto_5f66ae34eb') }}</h2><p>{{ __('exhibitions::ui.auto_6881547352') }}</p></div></div>
                    <label class="z4-exhibitions-upload"><input type="file" name="sample_artworks[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf" data-sample-files><span>{{ __('exhibitions::ui.auto_035274cd62') }}</span></label>
                    @error('sample_artworks')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                    @error('sample_artworks.*')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                </section>

                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-6">
                    <div class="z4-exhibitions-section-title"><span>07</span><div><h2 id="artist-registration-step-6" tabindex="-1">{{ __('exhibitions::ui.auto_8badde89ac') }}</h2><p>{{ __('exhibitions::ui.auto_a668e46d37') }}</p></div></div>
                    <div class="z4-exhibitions-fields is-single">
                        <div class="z4-repeatable-links" data-repeatable-links data-max-items="10">
                            <div data-repeatable-list>
                                @foreach($previousWorkLinks as $index => $link)
                                    <label data-repeatable-item>
                                        <span>{{ __('exhibitions::ui.auto_59089582b6') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span>
                                        <span class="z4-repeatable-input">
                                            <input type="url" name="previous_work_links[]" value="{{ $link }}" maxlength="2048" placeholder="https://">
                                            @if($index > 0)<button type="button" data-remove-link aria-label="{{ __('exhibitions::ui.auto_616209d01a') }}">&times;</button>@endif
                                        </span>
                                        <small>@error('previous_work_links.'.$index){{ $message }}@enderror</small>
                                    </label>
                                @endforeach
                            </div>
                            <button type="button" class="z4-add-link" data-add-link><span aria-hidden="true">+</span> {{ __('exhibitions::ui.auto_aa7249797c') }}</button>
                            <template data-link-template>
                                <label data-repeatable-item>
                                    <span>{{ __('exhibitions::ui.auto_59089582b6') }} <em>{{ __('exhibitions::ui.auto_ebb061953c') }}</em></span>
                                    <span class="z4-repeatable-input"><input type="url" name="previous_work_links[]" maxlength="2048" placeholder="https://"><button type="button" data-remove-link aria-label="{{ __('exhibitions::ui.auto_616209d01a') }}">&times;</button></span>
                                    <small></small>
                                </label>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="z4-exhibitions-panel" aria-labelledby="artist-registration-step-agreement">
                    <div class="z4-exhibitions-section-title"><span>08</span><div><h2 id="artist-registration-step-agreement" tabindex="-1">{{ __('exhibitions::ui.auto_eac562fc43') }}</h2><p>{{ __('exhibitions::ui.auto_eeae1ba636') }}</p></div></div>
                    @if($agreementTemplate)
                        <input type="hidden" name="agreement_template" value="{{ $agreementTemplate->public_id }}">
                        <div class="z4-exhibitions-agreement"><header><div><strong>{{ $agreementTemplate->title }}</strong><small>Version {{ $agreementTemplate->version }}</small></div></header><div class="z4-exhibitions-agreement-content">{!! nl2br(e($agreementTemplate->content)) !!}</div></div>
                        <label class="z4-exhibitions-consent"><input type="checkbox" name="accept_artist_agreement" value="1" required @checked(old('accept_artist_agreement'))><span>{{ __('exhibitions::ui.auto_d7523db86b') }} <b>*</b></span></label>
                        @error('accept_artist_agreement')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                        @error('agreement_template')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                    @else
                        <div class="z4-exhibitions-alert is-error">{{ __('exhibitions::ui.auto_6b54beac66') }}</div>
                    @endif
                </section>

                <section class="z4-exhibitions-panel z4-exhibitions-declarations" aria-labelledby="artist-registration-step-7">
                    <div class="z4-exhibitions-section-title"><span>09</span><div><h2 id="artist-registration-step-7" tabindex="-1">{{ __('exhibitions::ui.auto_94fff52a6f') }}</h2><p>{{ __('exhibitions::ui.auto_4213fe2643') }}</p></div></div>
                    <label><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>{{ __('exhibitions::ui.auto_3abbd962f6') }} <a href="{{ url('/pages/privacy-policy') }}" target="_blank" rel="noopener noreferrer">{{ __('exhibitions::ui.auto_fa2ead697d') }}</a>.</span></label>
                    <small>@error('terms'){{ $message }}@enderror</small>
                    <label><input type="checkbox" name="ownership_declaration" value="1" required @checked(old('ownership_declaration'))><span>{{ __('exhibitions::ui.auto_3aaed151f9') }}</span></label>
                    <small>@error('ownership_declaration'){{ $message }}@enderror</small>
                </section>
                </div>

                <div class="z4-exhibitions-actions z4-artist-registration-actions">
                    <a class="is-secondary" href="{{ route('login') }}">{{ __('exhibitions::ui.auto_9f0cd724c3') }}</a>
                    <div class="z4-wizard-navigation">
                        <button class="is-secondary z4-wizard-button" type="button" data-wizard-back>{{ __('exhibitions::ui.auto_0557fa923d') }}</button>
                        <button class="is-primary z4-wizard-button" type="button" data-wizard-next>{{ __('exhibitions::ui.auto_10ac3d0425') }}</button>
                        <button class="is-primary" type="submit" data-wizard-submit @disabled(!$agreementTemplate)>{{ __('exhibitions::ui.auto_3da9b4507a') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const wizard = document.querySelector('[data-registration-wizard]');

            if (wizard) {
                const steps = Array.from(wizard.querySelectorAll('[data-wizard-step]'));
                const progressSteps = Array.from(wizard.querySelectorAll('[data-wizard-progress-step]'));
                const status = wizard.querySelector('[data-wizard-status]');
                const backButton = wizard.querySelector('[data-wizard-back]');
                const nextButton = wizard.querySelector('[data-wizard-next]');
                const submitButton = wizard.querySelector('[data-wizard-submit]');
                const emailInput = wizard.querySelector('[data-registration-email]');
                const passwordInput = wizard.querySelector('[data-strong-password]');
                const passwordConfirmation = wizard.querySelector('[data-password-confirmation]');
                const birthDateDisplay = wizard.querySelector('[data-date-display]');
                const birthDateValue = wizard.querySelector('[data-date-value]');
                const phoneInput = wizard.querySelector('[data-registration-phone]');
                const sampleFiles = wizard.querySelector('[data-sample-files]');
                const identityRoot = wizard.querySelector('[data-registration-identity]');
                const identityType = identityRoot?.querySelector('[data-identity-document-type]');
                const identityTwoSided = identityRoot?.querySelector('[data-identity-two-sided]');
                const identitySingle = identityRoot?.querySelector('[data-identity-single]');
                const identityFront = identityRoot?.querySelector('[data-identity-front]');
                const identityBack = identityRoot?.querySelector('[data-identity-back]');
                const identitySingleFile = identityRoot?.querySelector('[data-identity-single-file]');
                let currentStep = Number.parseInt(wizard.dataset.initialStep || '1', 10) - 1;

                const syncIdentityFields = () => {
                    const passport = identityType?.value === 'passport';
                    const twoSided = Boolean(identityType?.value) && !passport;
                    if (identityTwoSided) identityTwoSided.hidden = !twoSided;
                    if (identitySingle) identitySingle.hidden = !passport;
                    if (identityFront) identityFront.required = twoSided;
                    if (identityBack) identityBack.required = twoSided;
                    if (identitySingleFile) identitySingleFile.required = passport;
                };
                identityType?.addEventListener('change', syncIdentityFields);
                syncIdentityFields();

                const showStep = (index, moveFocus = false) => {
                    currentStep = Math.max(0, Math.min(index, steps.length - 1));

                    steps.forEach((step, stepIndex) => {
                        step.hidden = stepIndex !== currentStep;
                    });

                    progressSteps.forEach((step, stepIndex) => {
                        step.classList.toggle('is-current', stepIndex === currentStep);
                        step.classList.toggle('is-complete', stepIndex < currentStep);
                        step.toggleAttribute('aria-current', stepIndex === currentStep);
                    });

                    backButton.hidden = currentStep === 0;
                    nextButton.hidden = currentStep === steps.length - 1;
                    submitButton.hidden = currentStep !== steps.length - 1;
                    status.textContent = `Step ${currentStep + 1} of ${steps.length}: ${steps[currentStep].dataset.wizardTitle}`;

                    if (moveFocus) {
                        steps[currentStep].querySelector('h2').focus({ preventScroll: true });
                        steps[currentStep].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                };

                const refreshCustomValidity = (step) => {
                    if (step.contains(emailInput)) {
                        emailInput.setCustomValidity(emailInput.validity.typeMismatch ? 'Enter a valid email address.' : '');
                    }

                    if (step.contains(passwordInput)) {
                        let passwordMessage = '';
                        if (passwordInput.value && passwordInput.value.length < 8) {
                            passwordMessage = 'Use at least 8 characters.';
                        } else if (passwordInput.value && !/[A-Z]/.test(passwordInput.value)) {
                            passwordMessage = 'Add at least one uppercase letter.';
                        } else if (passwordInput.value && !/[a-z]/.test(passwordInput.value)) {
                            passwordMessage = 'Add at least one lowercase letter.';
                        } else if (passwordInput.value && !/[0-9]/.test(passwordInput.value)) {
                            passwordMessage = 'Add at least one number.';
                        } else if (passwordInput.value && !/[^A-Za-z0-9\s]/.test(passwordInput.value)) {
                            passwordMessage = 'Add at least one symbol.';
                        }
                        passwordInput.setCustomValidity(passwordMessage);
                        passwordConfirmation.setCustomValidity(
                            passwordConfirmation.value && passwordConfirmation.value !== passwordInput.value
                                ? 'Passwords do not match.'
                                : '',
                        );
                    }

                    if (step.contains(birthDateDisplay)) {
                        birthDateDisplay.setCustomValidity(birthDateValue.value ? '' : 'Choose your date of birth.');
                    }

                    if (step.contains(phoneInput)) {
                        phoneInput.setCustomValidity(
                            phoneInput.value && !/^[0-9() .-]{5,30}$/.test(phoneInput.value)
                                ? 'Enter a valid national phone number.'
                                : '',
                        );
                    }

                    if (step.contains(sampleFiles)) {
                        const files = Array.from(sampleFiles.files || []);
                        let fileMessage = '';
                        if (files.length > 8) {
                            fileMessage = 'Choose no more than 8 sample files.';
                        } else if (files.some((file) => file.size > 8 * 1024 * 1024)) {
                            fileMessage = 'Each sample file must be 8 MB or smaller.';
                        } else if (files.some((file) => !/\.(jpe?g|png|webp|pdf)$/i.test(file.name))) {
                            fileMessage = 'Use JPG, PNG, WebP, or PDF sample files only.';
                        }
                        sampleFiles.setCustomValidity(fileMessage);
                    }
                };

                const validateStep = (step) => {
                    refreshCustomValidity(step);
                    const fields = Array.from(step.querySelectorAll('input:not([type="hidden"]), textarea, select'));
                    const invalidField = fields.find((field) => !field.disabled && !field.checkValidity());

                    if (!invalidField) {
                        return true;
                    }

                    invalidField.reportValidity();
                    invalidField.focus({ preventScroll: true });

                    return false;
                };

                // حفظ/استعادة المسودة (بدون كلمات المرور والملفات)
                const draftKey = 'exhibitions.artist-register.draft.v1';
                const draftFields = Array.from(wizard.querySelectorAll('input[name], textarea[name], select[name]')).filter((field) => {
                    if (['file', 'password', 'hidden'].includes(field.type)) return false;
                    if (['_token', 'password', 'password_confirmation'].includes(field.name)) return false;
                    if (field.name.endsWith('[]')) return false;
                    return true;
                });
                let draftTimer = null;
                const saveDraft = () => {
                    const data = {};
                    draftFields.forEach((field) => {
                        data[field.name] = field.type === 'checkbox' ? (field.checked ? 1 : 0) : field.value;
                    });
                    try { sessionStorage.setItem(draftKey, JSON.stringify(data)); } catch (error) {}
                };
                const scheduleDraft = () => {
                    window.clearTimeout(draftTimer);
                    draftTimer = window.setTimeout(saveDraft, 250);
                };
                draftFields.forEach((field) => {
                    field.addEventListener('input', scheduleDraft);
                    field.addEventListener('change', scheduleDraft);
                });
                if (wizard.dataset.hasErrors !== '1') {
                    try {
                        const saved = JSON.parse(sessionStorage.getItem(draftKey) || '{}');
                        draftFields.forEach((field) => {
                            if (!(field.name in saved)) return;
                            if (field.type === 'checkbox') field.checked = Boolean(saved[field.name]);
                            else if (field.value === '') field.value = saved[field.name];
                        });
                    } catch (error) {}
                }
                saveDraft();

                // تحقق ملفات الهوية قبل الإرسال (نوع/حجم)
                const identityFiles = [identityFront, identityBack, identitySingleFile].filter(Boolean);
                const validateIdentityFiles = () => {
                    identityFiles.forEach((input) => {
                        const file = input.files && input.files[0];
                        let message = '';
                        if (file) {
                            if (file.size > 10 * 1024 * 1024) message = 'Each identity document must be 10 MB or smaller.';
                            else if (!/\.(jpe?g|png|pdf)$/i.test(file.name)) message = 'Use JPG, PNG, or PDF identity documents only.';
                        }
                        input.setCustomValidity(message);
                    });
                };
                identityFiles.forEach((input) => input.addEventListener('change', validateIdentityFiles));
                wizard.classList.add('is-wizard-ready');
                showStep(currentStep);

                nextButton.addEventListener('click', () => {
                    if (validateStep(steps[currentStep])) {
                        showStep(currentStep + 1, true);
                    }
                });

                backButton.addEventListener('click', () => showStep(currentStep - 1, true));

                wizard.addEventListener('submit', (event) => {
                    validateIdentityFiles();
                    steps.forEach(refreshCustomValidity);
                    const invalidField = wizard.querySelector(':invalid');

                    if (!invalidField) {
                        try { sessionStorage.removeItem(draftKey); } catch (error) {}
                        return;
                    }

                    event.preventDefault();
                    showStep(steps.indexOf(invalidField.closest('[data-wizard-step]')), true);
                    invalidField.reportValidity();
                    invalidField.focus({ preventScroll: true });
                });

                [emailInput, passwordInput, passwordConfirmation, birthDateDisplay, phoneInput, sampleFiles].forEach((field) => {
                    field?.addEventListener('input', () => refreshCustomValidity(field.closest('[data-wizard-step]')));
                    field?.addEventListener('change', () => refreshCustomValidity(field.closest('[data-wizard-step]')));
                });
            }

            document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
                const input = document.getElementById(toggle.getAttribute('aria-controls'));

                if (!input) {
                    return;
                }

                toggle.addEventListener('click', () => {
                    const shouldShow = input.type === 'password';
                    const selectionStart = input.selectionStart;
                    const selectionEnd = input.selectionEnd;
                    const label = shouldShow ? 'Hide password' : 'Show password';

                    input.type = shouldShow ? 'text' : 'password';
                    toggle.setAttribute('aria-label', label);
                    toggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                    toggle.setAttribute('title', label);
                    toggle.querySelector('[data-password-icon="show"]').hidden = shouldShow;
                    toggle.querySelector('[data-password-icon="hide"]').hidden = !shouldShow;
                    input.focus({ preventScroll: true });

                    if (selectionStart !== null && selectionEnd !== null) {
                        input.setSelectionRange(selectionStart, selectionEnd);
                    }
                });
            });

            const datePicker = document.querySelector('[data-date-picker]');

            if (datePicker) {
                const displayInput = datePicker.querySelector('[data-date-display]');
                const valueInput = datePicker.querySelector('[data-date-value]');
                const toggleButton = datePicker.querySelector('[data-date-toggle]');
                const popup = datePicker.querySelector('[data-date-popup]');
                const backButton = datePicker.querySelector('[data-date-back]');
                const closeButton = datePicker.querySelector('[data-date-close]');
                const title = datePicker.querySelector('[data-date-title]');
                const grid = datePicker.querySelector('[data-date-grid]');
                const [maximumYear, maximumMonth, maximumDay] = (datePicker.dataset.maxDate || '').split('-').map(Number);
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                let selectedYear = null;
                let selectedMonth = null;
                let currentView = 'year';

                const parseValue = () => {
                    const parts = valueInput.value.split('-').map(Number);
                    if (parts.length === 3 && parts.every(Number.isInteger)) {
                        selectedYear = parts[0];
                        selectedMonth = parts[1];
                    }
                };

                const makeButton = (label, action, options = {}) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = label;
                    button.disabled = options.disabled === true;
                    button.classList.toggle('is-selected', options.selected === true);
                    button.addEventListener('click', action);
                    return button;
                };

                const renderYears = () => {
                    currentView = 'year';
                    title.textContent = 'Choose year';
                    backButton.hidden = true;
                    grid.className = 'z4-date-picker-grid is-years';
                    grid.replaceChildren();

                    for (let year = maximumYear; year >= 1900; year -= 1) {
                        grid.append(makeButton(String(year), () => {
                            selectedYear = year;
                            selectedMonth = null;
                            renderMonths();
                        }, { selected: year === selectedYear }));
                    }

                    requestAnimationFrame(() => grid.querySelector('.is-selected')?.scrollIntoView({ block: 'center' }));
                };

                const renderMonths = () => {
                    currentView = 'month';
                    title.textContent = `Choose month · ${selectedYear}`;
                    backButton.hidden = false;
                    grid.className = 'z4-date-picker-grid is-months';
                    grid.replaceChildren();

                    monthNames.forEach((monthName, index) => {
                        const month = index + 1;
                        const isFuture = selectedYear === maximumYear && month > maximumMonth;
                        grid.append(makeButton(monthName, () => {
                            selectedMonth = month;
                            renderDays();
                        }, { disabled: isFuture, selected: month === selectedMonth }));
                    });
                };

                const renderDays = () => {
                    currentView = 'day';
                    title.textContent = `Choose day · ${monthNames[selectedMonth - 1]} ${selectedYear}`;
                    backButton.hidden = false;
                    grid.className = 'z4-date-picker-grid is-days';
                    grid.replaceChildren();

                    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((weekday) => {
                        const heading = document.createElement('span');
                        heading.className = 'z4-date-picker-weekday';
                        heading.textContent = weekday;
                        grid.append(heading);
                    });

                    const firstWeekday = new Date(Date.UTC(selectedYear, selectedMonth - 1, 1)).getUTCDay();
                    for (let blank = 0; blank < firstWeekday; blank += 1) {
                        const spacer = document.createElement('span');
                        spacer.setAttribute('aria-hidden', 'true');
                        grid.append(spacer);
                    }

                    const monthLength = new Date(Date.UTC(selectedYear, selectedMonth, 0)).getUTCDate();
                    const lastAllowedDay = selectedYear === maximumYear && selectedMonth === maximumMonth
                        ? Math.min(monthLength, maximumDay)
                        : monthLength;

                    for (let day = 1; day <= monthLength; day += 1) {
                        const isFuture = day > lastAllowedDay;
                        grid.append(makeButton(String(day), () => {
                            const monthValue = String(selectedMonth).padStart(2, '0');
                            const dayValue = String(day).padStart(2, '0');
                            valueInput.value = `${selectedYear}-${monthValue}-${dayValue}`;
                            displayInput.value = `${dayValue}/${monthValue}/${selectedYear}`;
                            displayInput.dispatchEvent(new Event('change', { bubbles: true }));
                            closePicker();
                            displayInput.focus({ preventScroll: true });
                        }, { disabled: isFuture }));
                    }
                };

                const openPicker = () => {
                    parseValue();
                    popup.hidden = false;
                    displayInput.setAttribute('aria-expanded', 'true');
                    renderYears();
                    requestAnimationFrame(() => grid.querySelector('button:not(:disabled)')?.focus({ preventScroll: true }));
                };

                const closePicker = () => {
                    popup.hidden = true;
                    displayInput.setAttribute('aria-expanded', 'false');
                };

                toggleButton.addEventListener('click', () => popup.hidden ? openPicker() : closePicker());
                displayInput.addEventListener('click', openPicker);
                displayInput.addEventListener('beforeinput', (event) => event.preventDefault());
                displayInput.addEventListener('paste', (event) => event.preventDefault());
                displayInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closePicker();
                        return;
                    }
                    if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        openPicker();
                    }
                });
                closeButton.addEventListener('click', () => {
                    closePicker();
                    displayInput.focus({ preventScroll: true });
                });
                backButton.addEventListener('click', () => currentView === 'day' ? renderMonths() : renderYears());
                document.addEventListener('click', (event) => {
                    if (!popup.hidden && !event.composedPath().includes(datePicker)) {
                        closePicker();
                    }
                });
            }

            const countrySelect = document.querySelector('[data-country-select]');
            const phoneFlag = document.querySelector('[data-phone-flag]');
            const phoneCode = document.querySelector('[data-phone-code]');
            const phoneInput = document.querySelector('input[name="contact_phone"]');

            if (countrySelect && phoneFlag && phoneCode && phoneInput) {
                const syncPhonePrefix = () => {
                    const option = countrySelect.selectedOptions[0];
                    phoneFlag.src = option?.dataset.flagUrl || '';
                    phoneCode.textContent = option?.dataset.dialCode || '';
                    phoneInput.placeholder = option?.dataset.phoneExample || '';
                };

                if (countrySelect.dataset.hasOldValue !== 'true') {
                    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    const timezoneOption = Array.from(countrySelect.options).find((option) => (option.dataset.timezones || '').split('|').includes(timezone));
                    let localeRegion = '';

                    try {
                        localeRegion = new Intl.Locale(navigator.language).region || '';
                    } catch (error) {
                        localeRegion = (navigator.language.match(/[-_]([A-Z]{2})$/i) || [])[1] || '';
                    }

                    const localeOption = Array.from(countrySelect.options).find((option) => option.value === localeRegion.toUpperCase());
                    const detectedOption = timezoneOption || localeOption;
                    if (detectedOption) {
                        countrySelect.value = detectedOption.value;
                    }
                }

                syncPhonePrefix();
                countrySelect.addEventListener('change', syncPhonePrefix);
            }

            document.querySelectorAll('[data-repeatable-links]').forEach((group) => {
                const list = group.querySelector('[data-repeatable-list]');
                const addButton = group.querySelector('[data-add-link]');
                const template = group.querySelector('[data-link-template]');
                const maximum = Number.parseInt(group.dataset.maxItems || '10', 10);

                const syncAddButton = () => {
                    addButton.disabled = list.querySelectorAll('[data-repeatable-item]').length >= maximum;
                    addButton.hidden = addButton.disabled;
                };

                group.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-link]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('[data-repeatable-item]').remove();
                    syncAddButton();
                });

                addButton.addEventListener('click', () => {
                    if (list.querySelectorAll('[data-repeatable-item]').length >= maximum) {
                        return;
                    }

                    const item = template.content.firstElementChild.cloneNode(true);
                    list.appendChild(item);
                    syncAddButton();
                    item.querySelector('input').focus({ preventScroll: true });
                });

                syncAddButton();
            });
        })();
    </script>
</x-guest-layout>
