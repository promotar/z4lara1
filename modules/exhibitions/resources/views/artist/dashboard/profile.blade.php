@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artist-profile-4" data-exhibitions-stylesheet>@endpush
<x-frontend-layout>
<main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-dashboard-profile>
    <div class="z4-exhibitions-container">
        @include('exhibitions::artist.dashboard._nav',[
            'title' => __('exhibitions::ui.artist_page_profile_title'),
            'subtitle'=>($navCounts['store_available'] ?? false) ? __('exhibitions::ui.artist_page_profile_subtitle') : __('exhibitions::ui.artist_page_profile_subtitle_no_store'),
        ])

        @if(session('profile_status'))<div class="z4-exhibitions-alert is-success">{{ session('profile_status') }}</div>@endif
        @if(session('password_status'))<div class="z4-exhibitions-alert is-success">{{ session('password_status') }}</div>@endif
        @if($errors->profile->any())<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_e067a5ebc4') }}</strong><p>{{ $errors->profile->first() }}</p></div>@endif
        @if($errors->updatePassword->any())<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_6973b70caf') }}</strong><p>{{ $errors->updatePassword->first() }}</p></div>@endif

        @php
            $previousWorkLinks = old('previous_work_links_text', implode(PHP_EOL, json_decode($context->previous_work_links ?: '[]', true) ?: []));
            $socialAccounts = old('social_accounts_text', implode(PHP_EOL, json_decode($context->social_accounts ?: '[]', true) ?: []));
        @endphp
        <div class="z4-artist-profile-grid">
            <div class="z4-artist-profile-editor">
                <section class="z4-exhibitions-panel z4-artist-profile-main">
                    <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_236d8306b7') }}</h2><p>{{ __('exhibitions::ui.auto_3520833671') }}</p></div></div>
                    <form method="POST" action="{{ route('exhibitions.dashboard.profile.update') }}" class="z4-exhibitions-form z4-artist-profile-form">
                        @csrf
                        <fieldset>
                            <legend>{{ __('exhibitions::ui.auto_3bf2638fc7') }}</legend>
                            <div class="z4-artist-profile-form-grid">
                                <label><span>{{ __('exhibitions::ui.auto_5ddd3d5ceb') }}</span><input name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="255" required autocomplete="name" @if($errors->profile->has('name')) aria-invalid="true" @endif><small class="z4-field-hint">{{ __('exhibitions::ui.auto_32b9dbbcc3') }}</small></label>
                                <label><span>{{ __('exhibitions::ui.auto_512e40eeb4') }}</span><input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" maxlength="255" required autocomplete="email" @if($errors->profile->has('email')) aria-invalid="true" @endif><small class="z4-field-hint">{{ __('exhibitions::ui.auto_3de015116c') }}</small></label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>{{ __('exhibitions::ui.auto_9a2f794d82') }}</legend>
                            <div class="z4-artist-profile-form-grid">
                                <label><span>{{ __('exhibitions::ui.auto_bb7cf40f9d') }}</span><input name="artistic_name" value="{{ old('artistic_name', $context->artistic_name) }}" maxlength="160" required @if($errors->profile->has('artistic_name')) aria-invalid="true" @endif></label>
                                <label><span>{{ __('exhibitions::ui.auto_01ed8a4015') }}</span><input type="email" name="contact_email" value="{{ old('contact_email', $context->contact_email) }}" maxlength="254" required @if($errors->profile->has('contact_email')) aria-invalid="true" @endif></label>
                                <label><span>{{ __('exhibitions::ui.auto_17dbcc4e60') }}</span><input name="contact_phone" value="{{ old('contact_phone', $context->contact_phone) }}" maxlength="40" required autocomplete="tel" @if($errors->profile->has('contact_phone')) aria-invalid="true" @endif></label>
                                <label><span>{{ __('exhibitions::ui.auto_b00613f337') }}</span><input type="number" name="years_experience" value="{{ old('years_experience', $context->years_experience) }}" min="0" max="100" @if($errors->profile->has('years_experience')) aria-invalid="true" @endif></label>
                                <label><span>{{ __('exhibitions::ui.auto_57d056ed09') }}</span><input name="city" value="{{ old('city', $context->city) }}" maxlength="120" required @if($errors->profile->has('city')) aria-invalid="true" @endif></label>
                                <label><span>{{ __('exhibitions::ui.auto_59716c9749') }}</span><input name="country" value="{{ old('country', $context->country) }}" maxlength="120" required @if($errors->profile->has('country')) aria-invalid="true" @endif></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_9100c7317c') }}</span><textarea name="biography" rows="7" maxlength="5000" required @if($errors->profile->has('biography')) aria-invalid="true" @endif>{{ old('biography', $context->biography) }}</textarea></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_96af06d6c1') }}</span><textarea name="qualifications" rows="4" maxlength="4000">{{ old('qualifications', $context->qualifications) }}</textarea></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_e36a40d358') }}</span><textarea name="previous_exhibitions" rows="4" maxlength="4000">{{ old('previous_exhibitions', $context->previous_exhibitions) }}</textarea></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_0b7b4b09a4') }}</span><textarea name="awards_certificates" rows="4" maxlength="4000">{{ old('awards_certificates', $context->awards_certificates) }}</textarea></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_8badde89ac') }}</span><textarea name="previous_work_links_text" rows="3" placeholder="https://example.com/portfolio&#10;https://example.com/exhibition">{{ $previousWorkLinks }}</textarea><small class="z4-field-hint">{{ __('exhibitions::ui.auto_79c2ec6417') }}</small></label>
                                <label class="is-wide"><span>{{ __('exhibitions::ui.auto_8b90aad75b') }}</span><textarea name="social_accounts_text" rows="3" placeholder="https://instagram.com/your-profile">{{ $socialAccounts }}</textarea><small class="z4-field-hint">{{ __('exhibitions::ui.auto_79c2ec6417') }}</small></label>
                            </div>
                        </fieldset>
                        <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_3cd04d7644') }}</button>
                    </form>
                </section>

                <section class="z4-exhibitions-panel z4-artist-profile-main">
                    <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_dc647eb65e') }}</h2><p>{{ __('exhibitions::ui.auto_b4b590bc5f') }}</p></div></div>
                    <form method="POST" action="{{ route('exhibitions.dashboard.profile.password') }}" class="z4-exhibitions-form z4-artist-password-form">
                        @csrf
                        <label><span>{{ __('exhibitions::ui.auto_d9c2d86a66') }}</span><input type="password" name="current_password" required autocomplete="current-password" @if($errors->updatePassword->has('current_password')) aria-invalid="true" @endif></label>
                        <div class="z4-artist-profile-form-grid">
                            <label><span>{{ __('exhibitions::ui.auto_3544848f82') }}</span><input type="password" name="password" required autocomplete="new-password" @if($errors->updatePassword->has('password')) aria-invalid="true" @endif></label>
                            <label><span>{{ __('exhibitions::ui.auto_6ab96a5df5') }}</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
                        </div>
                        <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_7e347ed93c') }}</button>
                    </form>
                </section>
            </div>

            <aside class="z4-artist-profile-sidebar" aria-label="{{ __('exhibitions::ui.auto_dd51a600c1') }}">
                <section class="z4-exhibitions-panel z4-artist-profile-card">
                    <div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_ed7ebcaf05') }}</h2><p>{{ __('exhibitions::ui.auto_e4772ef13d') }}</p></div></div>
                    <div class="z4-artist-profile-record"><strong>{{ $context->legal_name ?: __('exhibitions::ui.artist_legal_name_missing') }}</strong><span>{{ $context->nationality ?: __('exhibitions::ui.artist_nationality_missing') }}</span><small>{{ __('exhibitions::ui.auto_088b20d99c') }}</small></div>
                    <div class="z4-artist-profile-status-row"><div><strong>{{ $case ? __('exhibitions::ui.verification_state_'.$case->state) : __('exhibitions::ui.verification_not_started') }}</strong><small>{{ __('exhibitions::ui.artist_documents_current', ['current' => $documents->where('status','current')->count(), 'superseded' => $documents->where('status','superseded')->count()]) }}</small></div><span class="z4-verification-pill {{ $case?->state === 'verified' ? 'is-complete' : 'is-waiting' }}">{{ $case?->state === 'verified' ? __('exhibitions::ui.complete') : __('exhibitions::ui.pending') }}</span></div>
                    <a class="z4-exhibitions-button z4-artist-profile-action" href="{{ route('exhibitions.verification.identity') }}">{{ __('exhibitions::ui.auto_677f771ec7') }}</a>
                </section>

                <section class="z4-exhibitions-panel z4-artist-profile-card">
                    <div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_7c88d216d3') }}</h2><p>{{ __('exhibitions::ui.auto_64ee0ffe4e') }}</p></div></div>
                    @if($agreement)<div class="z4-artist-profile-record"><strong>{{ $agreement->accepted_title }}</strong><span>{{ __('exhibitions::ui.artist_version', ['version' => $agreement->accepted_version]) }}</span><small>{{ $agreement->accepted_at ? __('exhibitions::ui.artist_accepted_at', ['date' => $agreement->accepted_at]) : __('exhibitions::ui.artist_awaiting_acceptance') }}</small></div>@else<div class="z4-artist-profile-empty">{{ __('exhibitions::ui.auto_962daa5f78') }}</div>@endif
                </section>

                <section class="z4-exhibitions-panel z4-artist-profile-card">
                    <div class="z4-exhibitions-section-title"><span>05</span><div><h2>{{ __('exhibitions::ui.auto_9180f9a5a1') }}</h2><p>{{ __('exhibitions::ui.auto_1a59f60b0f') }}</p></div></div>
                    <div class="z4-artist-profile-record"><strong>{{ __('exhibitions::ui.artist_platform_commission_rate', ['rate' => number_format($commission->basisPoints/100,2)]) }}</strong><span>{{ $commission->source === 'artist_custom' ? __('exhibitions::ui.artist_custom_terms') : __('exhibitions::ui.artist_platform_commission_setting') }}</span><small>{{ __('exhibitions::ui.auto_afd0acd036') }}</small></div>
                </section>

                @if($navCounts['store_available'] ?? false)
                <section class="z4-exhibitions-panel z4-artist-profile-card">
                    <div class="z4-exhibitions-section-title"><span>06</span><div><h2>{{ __('exhibitions::ui.auto_14af772635') }}</h2><p>{{ __('exhibitions::ui.auto_404de7275f') }}</p></div></div>
                    @if($payout)<div class="z4-artist-profile-status-row"><div><strong>{{ __('exhibitions::ui.payout_method_'.$payout->method) }}</strong><span>{{ $payout->masked_identifier }}</span><small>{{ $payout->verified_at ? __('exhibitions::ui.verified') : __('exhibitions::ui.verification_pending') }}</small></div><span class="z4-verification-pill {{ $payout->verified_at ? 'is-complete' : 'is-ready' }}">{{ $payout->verified_at ? __('exhibitions::ui.verified') : __('exhibitions::ui.pending') }}</span></div>@else<div class="z4-artist-profile-empty"><strong>{{ __('exhibitions::ui.auto_2465631709') }}</strong><span>{{ __('exhibitions::ui.auto_4fe214617d') }}</span></div>@endif
                    <a class="z4-exhibitions-button z4-artist-profile-action" href="{{ route('exhibitions.verification.payout') }}">{{ $payout ? __('exhibitions::ui.artist_manage_payout') : __('exhibitions::ui.artist_add_payout') }}</a>
                </section>
                @endif
            </aside>
        </div>
    </div>
</main>
</x-frontend-layout>
