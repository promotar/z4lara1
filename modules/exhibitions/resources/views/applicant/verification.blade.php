@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-seller-setup-3" data-exhibitions-stylesheet>@endpush
@php
    $currentIdentity = $documents->first(fn ($document) => str_starts_with($document->type, 'identity') && $document->status === 'current');
    $identityStage = $case->state === 'verified'
        ? [__('exhibitions::ui.identity_verified'), __('exhibitions::ui.complete'), 'is-complete', '1']
        : ($currentIdentity ? [__('exhibitions::ui.identity_submitted'), __('exhibitions::ui.awaiting_review'), 'is-ready', '1'] : [__('exhibitions::ui.identity_document'), __('exhibitions::ui.action_required'), 'is-action', '1']);
    $agreementStage = $agreement?->accepted_at
        ? [__('exhibitions::ui.agreement_accepted'), __('exhibitions::ui.complete'), 'is-complete', '2']
        : ($agreement ? [__('exhibitions::ui.artist_agreement'), __('exhibitions::ui.action_required'), 'is-action', '2'] : [__('exhibitions::ui.artist_agreement'), __('exhibitions::ui.waiting_for_platform'), 'is-waiting', '2']);
    $payoutStage = $payout?->verified_at
        ? [__('exhibitions::ui.payout_verified'), __('exhibitions::ui.complete'), 'is-complete', '3']
        : ($payout ? [__('exhibitions::ui.payout_submitted'), __('exhibitions::ui.awaiting_review'), 'is-ready', '3'] : [__('exhibitions::ui.payout_information'), __('exhibitions::ui.action_required'), 'is-action', '3']);
    $canOpenAgreement = (bool) $currentIdentity;
    $canOpenPayout = $canOpenAgreement && (bool) $agreement?->accepted_at;
    $selectedPayoutMethod = old('method', $payout?->method ?? 'bank_transfer');
    $stages = [
        'identity' => [$identityStage, route('exhibitions.verification.identity')],
        'agreement' => [$agreementStage, $canOpenAgreement ? route('exhibitions.verification.agreement') : null],
        'payout' => [$payoutStage, $canOpenPayout ? route('exhibitions.verification.payout') : null],
    ];
    $screen = match ($step) {
        'agreement' => [__('exhibitions::ui.review_artist_agreement'), __('exhibitions::ui.read_accept_agreement')],
        'payout' => [__('exhibitions::ui.setup_payout_info'), __('exhibitions::ui.choose_receive_settlements')],
        default => $currentIdentity
            ? [__('exhibitions::ui.private_identity_docs'), __('exhibitions::ui.view_verified_documents')]
            : [__('exhibitions::ui.add_identity_document'), __('exhibitions::ui.upload_identity_evidence')],
    };
@endphp
<x-app-layout>
<main class="z4-exhibitions-admin" data-exhibitions-verification @if($step === 'payout') data-payout-only @endif><div class="z4-exhibitions-container z4-exhibitions-form-container">
    @if($step !== 'payout')
    <header class="z4-exhibitions-heading"><div><a class="z4-exhibitions-back" href="{{ route('exhibitions.application.status') }}">&larr; {{ __('exhibitions::ui.approved_application') }}</a><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_b82c0d2f2f') }}</span><h1>{{ $screen[0] }}</h1><p>{{ $screen[1] }}</p></div><span class="z4-exhibitions-status {{ $sellerSetup['ready'] ? 'is-ready' : '' }}">{{ $sellerSetup['percentage'] }}% complete</span></header>
    @endif
    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if(session('warning'))<div class="z4-exhibitions-alert is-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($step !== 'payout')
    <section class="z4-seller-readiness" aria-labelledby="seller-readiness-title">
        <div><span>{{ __('exhibitions::ui.auto_19169eecf2') }}</span><h2 id="seller-readiness-title">{{ __('exhibitions::ui.auto_adc2f1ea5f') }}</h2><p>{{ __('exhibitions::ui.auto_922d0b0c0d') }}</p></div>
        <div class="z4-seller-readiness-score"><strong>{{ $sellerSetup['percentage'] }}%</strong><small>{{ __('exhibitions::ui.artist_requirements_complete', ['completed' => $sellerSetup['completed'], 'total' => $sellerSetup['total']]) }}</small></div>
        <div class="z4-seller-readiness-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sellerSetup['percentage'] }}"><span style="width:{{ $sellerSetup['percentage'] }}%"></span></div>
    </section>
    @if($accountState && $accountState !== \Modules\Exhibition\Domain\Artists\ArtistAccountState::Active)<div class="z4-exhibitions-alert is-warning"><strong>Account {{ $accountState->label() }}</strong><p>Dashboard: {{ $accountCapabilities['dashboard'] ? 'available' : 'blocked' }} &middot; New artwork: {{ $accountCapabilities['create_artwork'] ? 'available' : 'blocked' }} &middot; Existing artwork: {{ $accountCapabilities['existing_artwork'] ? 'retained and visible' : 'not publicly available' }}.</p><p>Fulfillment obligations and settlement visibility remain available; historical sales and financial records are never erased.</p></div>@endif

    <nav class="z4-verification-stage-grid" aria-label="{{ __('exhibitions::ui.auto_09561d46db') }}">
        @foreach($stages as $stageKey => [$stage, $stageUrl])
            @if($stageUrl)
                <a href="{{ $stageUrl }}" class="z4-verification-stage-card {{ $stage[2] }} {{ $step === $stageKey ? 'is-current' : '' }}" @if($step === $stageKey) aria-current="step" @endif><span>{{ $stage[3] }}</span><div><strong>{{ $stage[0] }}</strong><small>{{ $stage[1] }}</small></div></a>
            @else
                <span class="z4-verification-stage-card is-locked" aria-disabled="true"><span>{{ $stage[3] }}</span><div><strong>{{ $stage[0] }}</strong><small>{{ __('exhibitions::ui.auto_72fe7be93e') }}</small></div></span>
            @endif
        @endforeach
    </nav>
    @endif

    @if($step === 'identity')
        @if($case->state === 'documents_required')<section class="z4-exhibitions-panel z4-exhibitions-next-action"><h2>{{ __('exhibitions::ui.auto_826ae437fc') }}</h2><p><strong>{{ __('exhibitions::ui.auto_ccb168b5a6') }}</strong> {{ $case->decision_reason }}</p><p>{{ $case->requested_instructions }}</p>@if($case->response_deadline)<p><strong>{{ __('exhibitions::ui.auto_5c3ffc5c71') }}</strong> {{ $case->response_deadline }}</p>@endif</section>@endif
        <div class="z4-verification-guidance {{ $currentIdentity ? 'is-ready' : 'is-action' }}">@if($case->state === 'verified')<strong>{{ __('exhibitions::ui.auto_ec7b623a35') }}</strong><p>{{ __('exhibitions::ui.auto_b82693ac91') }}</p>@elseif($currentIdentity)<strong>{{ __('exhibitions::ui.auto_dce7c8df02') }}</strong><p>{{ __('exhibitions::ui.auto_e0b5e43c3a') }}</p>@else<strong>{{ __('exhibitions::ui.auto_8e3d63179c') }}</strong><p>{{ __('exhibitions::ui.auto_4ebe2b3a27') }}</p>@endif</div>
        <section class="z4-exhibitions-panel z4-verification-step-panel">
            <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_8fe84fca38') }}</h2><p>{{ __('exhibitions::ui.auto_437a7d4698') }}</p></div></div>
            <b class="z4-verification-pill {{ $case->state === 'verified' ? 'is-complete' : ($currentIdentity ? 'is-ready' : 'is-action') }}">{{ $case->state === 'verified' ? __('exhibitions::ui.verified') : ($currentIdentity ? __('exhibitions::ui.submitted_awaiting_review') : __('exhibitions::ui.action_required')) }}</b>
            <ul class="z4-exhibitions-file-list">@forelse($documents as $document)<li class="{{ $document->status === 'superseded' ? 'is-muted' : '' }}"><a href="{{ $document->download_url }}">{{ $document->original_name }}</a><span>{{ ucfirst($document->type) }} · v{{ $document->version }} · {{ ucfirst($document->status) }}</span></li>@empty<li>{{ __('exhibitions::ui.auto_2e2566924c') }}</li>@endforelse</ul>
            <form id="z4-identity-upload-form" method="POST" enctype="multipart/form-data" action="{{ route('exhibitions.verification.documents.upload') }}" class="z4-exhibitions-form z4-verification-artist-form">@csrf<label><span>{{ __('exhibitions::ui.auto_208a23b4a6') }}</span><select name="type" required><option value="identity">{{ __('exhibitions::ui.auto_688a1c73df') }}</option><option value="additional">{{ __('exhibitions::ui.auto_7add905827') }}</option></select></label><label><span>{{ __('exhibitions::ui.auto_fe01e238e9') }}</span><input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" multiple required><small>{{ __('exhibitions::ui.auto_1822119808') }}</small></label></form>
        </section>
        <div class="z4-exhibitions-actions z4-verification-screen-actions"><button class="is-primary" type="submit" form="z4-identity-upload-form">{{ $currentIdentity ? __('exhibitions::ui.upload_replacement') : __('exhibitions::ui.next') }}</button></div>
    @elseif($step === 'agreement')
        <div class="z4-verification-guidance {{ $agreement?->accepted_at ? 'is-ready' : ($agreement ? 'is-action' : 'is-waiting') }}">@if($agreement?->accepted_at)<strong>{{ __('exhibitions::ui.auto_ee1abdf67c') }}</strong><p>{{ __('exhibitions::ui.auto_eaa2b6eb59') }}</p>@elseif($agreement)<strong>{{ __('exhibitions::ui.auto_28c4af0bcc') }}</strong><p>{{ __('exhibitions::ui.auto_d5046413f5') }}</p>@else<strong>{{ __('exhibitions::ui.auto_b0a44320e4') }}</strong><p>{{ __('exhibitions::ui.auto_33b56353b0') }}</p>@endif</div>
        <section class="z4-exhibitions-panel z4-verification-agreement-panel z4-verification-step-panel"><div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_eac562fc43') }}</h2><p>{{ __('exhibitions::ui.auto_e9820cab17') }}</p></div></div>
            @if($agreement)<div class="z4-exhibitions-agreement"><header><div><strong>{{ $agreement->accepted_title }}</strong><small>{{ __('exhibitions::ui.artist_version', ['version' => $agreement->accepted_version]) }}</small></div></header><div class="z4-exhibitions-agreement-content">{!! nl2br(e($agreement->accepted_content)) !!}</div></div>
                @if($agreement->accepted_at)<div class="z4-exhibitions-alert is-success">{{ __('exhibitions::ui.accepted_on_using', ['date' => $agreement->accepted_at, 'method' => str_replace('_',' ',$agreement->acceptance_method)]) }}</div>@else<form id="z4-agreement-accept-form" method="POST" action="{{ route('exhibitions.verification.agreement.accept') }}" class="z4-exhibitions-form z4-verification-artist-form">@csrf<label class="z4-exhibitions-consent"><input type="checkbox" name="accept_agreement" value="1" required><span>{{ __('exhibitions::ui.auto_387d61eec5') }}</span></label></form>@endif
            @else<div class="z4-verification-waiting is-agreement"><strong>{{ __('exhibitions::ui.auto_bf0e249c84') }}</strong><p>{{ __('exhibitions::ui.auto_face03087e') }}</p></div>@endif
        </section>
        @if($agreement && !$agreement->accepted_at)<div class="z4-exhibitions-actions z4-verification-screen-actions"><button class="is-primary" type="submit" form="z4-agreement-accept-form">{{ __('exhibitions::ui.auto_10ac3d0425') }}</button></div>@endif
    @else
        <section class="z4-exhibitions-panel z4-verification-step-panel z4-payout-only-card">
            <div class="z4-exhibitions-section-title z4-payout-only-title"><div><h1>{{ __('exhibitions::ui.auto_cffe07b0bb') }}</h1><p>{{ __('exhibitions::ui.auto_33b65983ce') }}</p></div></div>
            <b class="z4-verification-pill {{ $payout?->verified_at ? 'is-complete' : ($payout ? 'is-ready' : 'is-action') }}">{{ $payout?->verified_at ? __('exhibitions::ui.verified') : ($payout ? __('exhibitions::ui.payout_submitted_awaiting_review') : __('exhibitions::ui.action_required')) }}</b>
            @if($payout)<div class="z4-exhibitions-summary"><strong>{{ __('exhibitions::ui.payout_method_'.$payout->method) }}</strong><span>{{ $payout->masked_identifier }}</span><small>{{ $payout->verified_at ? __('exhibitions::ui.verified') : __('exhibitions::ui.awaiting_reviewer_verification') }}</small></div>@endif
            <form method="POST" action="{{ route('exhibitions.verification.payout.save') }}" class="z4-exhibitions-form z4-verification-artist-form" data-payout-form>
                @csrf
                <label><span>{{ __('exhibitions::ui.auto_3ebb0498b9') }}</span><select name="method" required data-payout-method><option value="bank_transfer" @selected($selectedPayoutMethod === 'bank_transfer' || $selectedPayoutMethod === 'iban')>{{ __('exhibitions::ui.auto_431647a0a8') }}</option><option value="cliq" @selected($selectedPayoutMethod === 'cliq')>{{ __('exhibitions::ui.auto_7e2ea8e62d') }}</option><option value="check" @selected($selectedPayoutMethod === 'check')>{{ __('exhibitions::ui.auto_8520b283b0') }}</option><option value="cash" @selected($selectedPayoutMethod === 'cash')>{{ __('exhibitions::ui.auto_069b30db06') }}</option><option value="wallet" @selected($selectedPayoutMethod === 'wallet')>{{ __('exhibitions::ui.auto_2049421a4e') }}</option></select></label>
                <label><span data-payout-name-label>{{ __('exhibitions::ui.auto_070ea8d9ff') }}</span><input name="account_name" maxlength="160" required autocomplete="name"></label>
                <div class="z4-payout-account-fields" data-payout-account-fields>
                    <label><span data-payout-identifier-label>{{ __('exhibitions::ui.auto_f18208e4b8') }}</span><input name="account_identifier" minlength="3" maxlength="120" autocomplete="off" placeholder="{{ __('exhibitions::ui.auto_b9eb8d7d01') }}" required data-payout-account-input></label>
                    <label class="z4-payout-provider-field" data-payout-provider-field><span data-payout-provider-label>{{ __('exhibitions::ui.auto_984482eb9f') }}</span><input name="bank_or_provider" maxlength="160" required data-payout-provider-input></label>
                </div>
                <div class="z4-payout-pickup-note" data-payout-pickup-note hidden><strong>{{ __('exhibitions::ui.auto_92c391cd12') }}</strong><p>{{ __('exhibitions::ui.auto_676568c459') }}</p></div>
                <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_c9cc8cce24') }}</button>
            </form>
            <script>
            (() => {
                const form = document.querySelector('[data-payout-form]');
                if (!form) return;
                const method = form.querySelector('[data-payout-method]');
                const accountFields = form.querySelector('[data-payout-account-fields]');
                const pickupNote = form.querySelector('[data-payout-pickup-note]');
                const nameLabel = form.querySelector('[data-payout-name-label]');
                const identifierLabel = form.querySelector('[data-payout-identifier-label]');
                const providerLabel = form.querySelector('[data-payout-provider-label]');
                const providerField = form.querySelector('[data-payout-provider-field]');
                const identifierInput = form.querySelector('[name="account_identifier"]');
                const providerInput = form.querySelector('[data-payout-provider-input]');
                const refresh = () => {
                    const pickup = method.value === 'check' || method.value === 'cash';
                    const wallet = method.value === 'wallet';
                    const cliq = method.value === 'cliq';
                    accountFields.hidden = pickup;
                    pickupNote.hidden = !pickup;
                    providerField.hidden = pickup || cliq;
                    nameLabel.textContent = pickup || cliq ? @json(__('exhibitions::ui.recipient_name')) : (wallet ? @json(__('exhibitions::ui.wallet_holder')) : @json(__('exhibitions::ui.account_holder')));
                    identifierLabel.textContent = cliq ? @json(__('exhibitions::ui.cliq_identifier')) : (wallet ? @json(__('exhibitions::ui.wallet_identifier')) : @json(__('exhibitions::ui.account_iban')));
                    identifierInput.placeholder = cliq ? @json(__('exhibitions::ui.cliq_placeholder')) : (wallet ? @json(__('exhibitions::ui.wallet_placeholder')) : @json(__('exhibitions::ui.iban_placeholder')));
                    providerLabel.textContent = wallet ? @json(__('exhibitions::ui.wallet_provider')) : @json(__('exhibitions::ui.bank_name'));
                    identifierInput.required = !pickup;
                    identifierInput.disabled = pickup;
                    providerInput.required = !pickup && !cliq;
                    providerInput.disabled = pickup || cliq;
                };
                method.addEventListener('change', refresh);
                refresh();
            })();
            </script>
        </section>
    @endif
</div></main>
</x-app-layout>
