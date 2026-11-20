@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-seller-setup-1" data-exhibitions-stylesheet>@endpush
@php
    $currentIdentity = $documents->first(fn ($document) => str_starts_with($document->type, 'identity') && $document->status === 'current');
    $identityStage = $case->state === 'verified'
        ? ['Verified', 'Complete', 'is-complete', '✓']
        : ($currentIdentity ? ['Identity documents', 'Ready for review', 'is-ready', '→'] : ['Identity documents', 'Waiting for artist', 'is-waiting', '○']);
    $agreementStage = $agreement?->accepted_at
        ? ['Agreement accepted', 'Complete', 'is-complete', '✓']
        : ($agreement ? ['Agreement assigned', 'Waiting for artist', 'is-waiting', '○'] : ['Agreement unavailable', 'Platform setup required', 'is-blocked', '!']);
    $payoutStage = $payout?->verified_at
        ? ['Payout verified', 'Complete', 'is-complete', '✓']
        : ($payout ? ['Payout details', 'Ready for review', 'is-ready', '→'] : ['Payout details', 'Waiting for artist', 'is-waiting', '○']);
@endphp
<x-app-layout>
<main class="z4-exhibitions-admin" data-exhibitions-admin-verification><div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading"><div><a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.applications.show', $application->public_id) }}">&larr; {{ __('exhibitions::ui.approved_application') }}</a><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_dd51a600c1') }}</span><h1>{{ $applicant->name }}</h1><p>{{ __('exhibitions::ui.auto_9d2450c559') }}</p></div><span class="z4-exhibitions-status {{ $sellerSetup['ready'] ? 'is-ready' : '' }}">{{ __('exhibitions::ui.auto_5fd611360f') }}</span></header>
    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="z4-seller-readiness is-admin" aria-labelledby="seller-readiness-title">
        <div><span>{{ __('exhibitions::ui.auto_0006c09101') }}</span><h2 id="seller-readiness-title">{{ __('exhibitions::ui.auto_adc2f1ea5f') }}</h2><p>{{ __('exhibitions::ui.auto_56f6f76684') }}</p></div>
        <div class="z4-seller-readiness-score"><strong>{{ $sellerSetup['percentage'] }}%</strong><small>{{ __('exhibitions::ui.auto_d9235691b6') }}</small></div>
        <div class="z4-seller-readiness-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sellerSetup['percentage'] }}"><span style="width:{{ $sellerSetup['percentage'] }}%"></span></div>
    </section>

    <section class="z4-verification-stage-grid" aria-label="{{ __('exhibitions::ui.auto_09561d46db') }}">
        @foreach([$identityStage, $agreementStage, $payoutStage] as $stage)
            <article class="{{ $stage[2] }}"><span>{{ $stage[3] }}</span><div><strong>{{ $stage[0] }}</strong><small>{{ $stage[1] }}</small></div></article>
        @endforeach
    </section>

    @if(!$currentIdentity && !$payout && !$agreement?->accepted_at)
        <div class="z4-verification-guidance is-waiting"><strong>{{ __('exhibitions::ui.auto_2be13da63c') }}</strong><p>{{ __('exhibitions::ui.auto_5889ebd041') }}</p></div>
    @else
        <div class="z4-verification-guidance is-ready"><strong>{{ __('exhibitions::ui.auto_8001e42065') }}</strong><p>{{ __('exhibitions::ui.auto_5d68cc74ea') }}</p></div>
    @endif

    <div class="z4-exhibitions-columns z4-verification-primary-grid">
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_143e63916f') }}</h2><p>{{ __('exhibitions::ui.auto_f400e454a7') }}</p></div></div>
            <dl class="z4-exhibitions-details"><div><dt>{{ __('exhibitions::ui.auto_bbaff12800') }}</dt><dd>{{ $application->contact_email }} · {{ $application->contact_phone }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_d311b5d9d1') }}</dt><dd>{{ __('exhibitions::ui.auto_1c28e2e2f7') }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_db8d503322') }}</dt><dd>{{ $case->samples_verified_at ? 'Reviewed' : 'Awaiting review' }}</dd></div></dl>
            <div class="z4-verification-submission-list">
                <article><span class="z4-verification-submission-icon">ID</span><div><strong>{{ __('exhibitions::ui.auto_c78b4582f5') }}</strong><small>{{ $currentIdentity ? 'Submitted '.$currentIdentity->created_at : 'Not submitted yet' }}</small></div><b class="z4-verification-pill {{ $currentIdentity ? 'is-ready' : 'is-waiting' }}">{{ $currentIdentity ? 'Ready for review' : 'Waiting for artist' }}</b></article>
                <article><span class="z4-verification-submission-icon">AG</span><div><strong>{{ __('exhibitions::ui.auto_7c88d216d3') }}</strong><small>{{ $agreement?->accepted_at ? 'Accepted '.$agreement->accepted_at : ($agreement ? 'Assigned; artist acceptance pending' : 'No agreement assigned') }}</small></div><b class="z4-verification-pill {{ $agreement?->accepted_at ? 'is-complete' : 'is-waiting' }}">{{ $agreement?->accepted_at ? 'Accepted' : ($agreement ? 'Waiting for artist' : 'Not available') }}</b></article>
                <article><span class="z4-verification-submission-icon">PY</span><div><strong>{{ __('exhibitions::ui.auto_cffe07b0bb') }}</strong><small>{{ $payout ? 'Submitted '.$payout->submitted_at : 'Not submitted yet' }}</small></div><b class="z4-verification-pill {{ $payout?->verified_at ? 'is-complete' : ($payout ? 'is-ready' : 'is-waiting') }}">{{ $payout?->verified_at ? 'Verified' : ($payout ? 'Ready for review' : 'Waiting for artist') }}</b></article>
            </div>
            @if($documents->isNotEmpty())
                <h3>{{ __('exhibitions::ui.auto_0c270742f3') }}</h3><ul class="z4-exhibitions-file-list">@foreach($documents as $document)<li class="{{ $document->status === 'superseded' ? 'is-muted' : '' }}"><a href="{{ route('admin.plugins.exhibitions.applications.verification.documents.download', [$application->public_id,$document->public_id]) }}">{{ $document->original_name }}</a><span>{{ ucfirst($document->type) }} · v{{ $document->version }} · {{ ucfirst($document->status) }}</span></li>@endforeach</ul>
            @endif
        </section>

        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_081675c2d7') }}</h2><p>{{ __('exhibitions::ui.auto_678b6877ea') }}</p></div></div>
            @if(!$currentIdentity)
                <div class="z4-verification-waiting"><span>○</span><strong>{{ __('exhibitions::ui.auto_9055c6c6ee') }}</strong><p>{{ __('exhibitions::ui.auto_455fd52ab7') }}</p></div>
            @elseif(in_array($case->state, ['verified', 'rejected'], true))
                <div class="z4-verification-recorded"><strong>{{ \Modules\Exhibition\Domain\Verification\VerificationState::from($case->state)->label() }}</strong><p>{{ $case->decision_reason }}</p><small>{{ __('exhibitions::ui.auto_e3376b4669') }}</small></div>
            @else
                <div class="z4-verification-ready-note"><strong>{{ __('exhibitions::ui.auto_443546c2d9') }}</strong><span>{{ $currentIdentity->original_name }}</span></div>
                <form method="POST" action="{{ route('admin.plugins.exhibitions.applications.verification.decision',$application->public_id) }}" class="z4-exhibitions-form z4-verification-decision-form">@csrf
                    <label><span>{{ __('exhibitions::ui.auto_081675c2d7') }}</span><select name="state" required><option value="verified">{{ __('exhibitions::ui.auto_565e031a66') }}</option><option value="documents_required">{{ __('exhibitions::ui.auto_3963b0af7d') }}</option><option value="rejected">{{ __('exhibitions::ui.auto_5d1643b0f8') }}</option></select></label>
                    <div class="z4-exhibitions-checkbox-grid"><label><input type="checkbox" name="contact_verified" value="1" {{ $case->contact_verified_at ? 'checked' : '' }}><span>{{ __('exhibitions::ui.auto_c4ee6eba75') }}</span></label><label><input type="checkbox" name="profile_verified" value="1" {{ $case->artistic_profile_verified_at ? 'checked' : '' }}><span>{{ __('exhibitions::ui.auto_0430df95d5') }}</span></label><label><input type="checkbox" name="samples_verified" value="1" {{ $case->samples_verified_at ? 'checked' : '' }}><span>{{ __('exhibitions::ui.auto_b9bef00c2e') }}</span></label></div>
                    <label><span>{{ __('exhibitions::ui.auto_45b967ed59') }}</span><textarea name="reason" rows="3" required placeholder="Record why this decision is justified."></textarea></label>
                    <label><span>{{ __('exhibitions::ui.auto_c6b345c37f') }}</span><textarea name="instructions" rows="3" placeholder="Required only when requesting a correction or more documents."></textarea></label>
                    <label><span>{{ __('exhibitions::ui.auto_a1ad211dae') }}</span><input type="date" name="response_deadline"></label>
                    <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_80d4f5ae34') }}</button>
                </form>
            @endif
        </section>
    </div>

    <div class="z4-exhibitions-columns z4-verification-secondary-grid">
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_0fa0976e76') }}</h2><p>{{ __('exhibitions::ui.auto_dd7966184a') }}</p></div></div>
            @if($agreement)
                <div class="z4-exhibitions-summary"><strong>{{ $agreement->accepted_title }} · {{ $agreement->accepted_version }}</strong><small>{{ $agreement->accepted_at ? 'Accepted '.$agreement->accepted_at : 'Assigned; waiting for artist acceptance' }}</small></div>
            @else
                <div class="z4-verification-waiting is-setup"><span>!</span><strong>{{ __('exhibitions::ui.auto_0e39e2a0cd') }}</strong><p>{{ __('exhibitions::ui.auto_aebf41da51') }}</p></div>
                @cannot('exhibitions.manage')<p class="z4-verification-manager-note">{{ __('exhibitions::ui.auto_8cf6cd32a4') }}</p>@endcannot
            @endif
            @can('exhibitions.manage')<a class="z4-exhibitions-button z4-verification-settings-link" href="{{ route('admin.plugins.exhibitions.agreements.index') }}">{{ __('exhibitions::ui.auto_78084f44a7') }}</a>@endcan
        </section>

        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_3f4b9437fe') }}</h2><p>Full financial details remain encrypted; only the masked identifier is shown.</p></div></div>
            @if($payout)
                <div class="z4-exhibitions-summary"><strong>{{ __('exhibitions::ui.state_'.$payout->method) }}</strong><span>{{ $payout->masked_identifier }}</span><small>{{ $payout->verified_at ? __('exhibitions::ui.verified_at', ['date' => $payout->verified_at]) : __('exhibitions::ui.submitted_ready_for_review') }}</small></div>
                @if(!$payout->verified_at)<form method="POST" action="{{ route('admin.plugins.exhibitions.applications.verification.payout.verify',$application->public_id) }}">@csrf<button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_dd636a3f0f') }}</button></form>@endif
            @else
                <div class="z4-verification-waiting"><span>○</span><strong>{{ __('exhibitions::ui.auto_ef9c281401') }}</strong><p>{{ __('exhibitions::ui.auto_6618db2ea6') }}</p></div>
            @endif
            @if($identity && in_array($identity->state,['active','restricted','suspended','terminated'],true))<hr><h3>{{ __('exhibitions::ui.auto_1a480c6224') }}</h3><form method="POST" action="{{ route('admin.plugins.exhibitions.applications.account-state',$application->public_id) }}" class="z4-exhibitions-form">@csrf<label><span>{{ __('exhibitions::ui.auto_d4f166cc2b') }}</span><select name="state" required>@foreach(\Modules\Exhibition\Domain\Artists\ArtistAccountState::cases() as $state)<option value="{{ $state->value }}">{{ $state->label() }}</option>@endforeach</select></label><label><span>{{ __('exhibitions::ui.auto_2c0b4672d4') }}</span><textarea name="reason" required></textarea></label><button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_13b93b3be4') }}</button></form>@endif
        </section>
    </div>
</div></main>
</x-app-layout>
