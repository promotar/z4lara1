@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-review-layout-5" data-exhibitions-stylesheet>@endpush
<x-app-layout>
    <main class="z4-exhibitions-admin" data-exhibitions-admin-review><div class="z4-exhibitions-container">
        <header class="z4-exhibitions-heading"><div><a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.applications.index') }}">{{ __('exhibitions::ui.auto_8834dcb78b') }}</a><span class="z4-exhibitions-eyebrow">{{ $application->public_id }}</span><h1>{{ $applicant->name }}</h1><p>{{ $application->artistic_name ?: 'No artistic name yet' }} · {{ $applicant->email }}</p></div><span class="z4-exhibitions-status">{{ \Modules\Exhibition\Domain\Applications\ApplicationState::from($application->state)->label() }}</span></header>
        @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
        @if(in_array($application->state, ['approved','contract_pending','active'], true))<p class="z4-admin-review-action"><a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.applications.verification.show', $application->public_id) }}">{{ __('exhibitions::ui.auto_28357baaff') }}</a></p>@endif
        @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="z4-exhibitions-columns">
            <section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_4eb73e1b5d') }}</h2><p>{{ __('exhibitions::ui.auto_353ad6aebf') }}</p></div></div><dl class="z4-exhibitions-details">@foreach(['legal_name'=>'Legal name','birth_date'=>'Birth date','nationality'=>'Nationality','contact_email'=>'Contact email','contact_phone'=>'Phone','city'=>'City','country'=>'Country','years_experience'=>'Years of experience','biography'=>'Biography','qualifications'=>'Qualifications','previous_exhibitions'=>'Previous exhibitions','awards_certificates'=>'Awards and certificates'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $application->{$field} ?: '—' }}</dd></div>@endforeach</dl>
                <h3>{{ __('exhibitions::ui.auto_a2be256891') }}</h3>
                <ul class="z4-application-sample-grid z4-identity-document-grid">
                    @forelse($identityDocuments as $document)
                        @php
                            $identityUrl = route('admin.plugins.exhibitions.applications.verification.documents.download', [$application->public_id, $document->public_id]);
                            $identityLabel = ucwords(str_replace(['identity_', '_'], ['', ' '], $document->type));
                            $identityImage = in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
                        @endphp
                        <li class="z4-application-sample-card">
                            @if($identityImage)<a class="z4-application-sample-preview" href="{{ $identityUrl }}"><img src="{{ $identityUrl }}" alt="{{ __('exhibitions::ui.auto_444ec9d9a0') }}" loading="lazy"></a>@else<div class="z4-application-sample-file"><span>{{ $identityLabel }}</span></div>@endif
                            <div class="z4-application-sample-caption">
                                <a href="{{ $identityUrl }}">{{ $identityLabel }}</a>
                                <span title="{{ $document->original_name }}">{{ $document->original_name }} · {{ number_format($document->size_bytes / 1024, 1) }} KB</span>
                            </div>
                        </li>
                    @empty
                        <li class="z4-application-sample-empty">{{ __('exhibitions::ui.auto_16789418ab') }}</li>
                    @endforelse
                </ul>
                <div class="z4-exhibitions-alert {{ $agreement?->accepted_at ? 'is-success' : 'is-warning' }}"><strong>{{ __('exhibitions::ui.auto_d415f87334') }}</strong> {{ $agreement?->accepted_at ? 'Accepted during registration on '.$agreement->accepted_at : 'Not accepted yet' }}</div>
                <h3>{{ __('exhibitions::ui.auto_289d52226b') }}</h3>
                <ul class="z4-application-sample-grid">
                    @forelse($samples as $sample)
                        @php
                            $sampleDownloadUrl = route('admin.plugins.exhibitions.applications.samples.download', [$application->public_id, $sample->public_id]);
                            $isPreviewableImage = in_array($sample->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
                        @endphp
                        <li class="z4-application-sample-card">
                            @if($isPreviewableImage)
                                <a class="z4-application-sample-preview" href="{{ $sampleDownloadUrl }}" title="{{ __('exhibitions::ui.auto_f293bc117a') }}">
                                    <img src="{{ route('admin.plugins.exhibitions.applications.samples.preview', [$application->public_id, $sample->public_id]) }}" alt="{{ __('exhibitions::ui.auto_034f340810') }}" loading="lazy">
                                </a>
                            @else
                                <div class="z4-application-sample-file"><span>{{ __('exhibitions::ui.auto_34d659dc53') }}</span></div>
                            @endif
                            <div class="z4-application-sample-caption">
                                <a href="{{ $sampleDownloadUrl }}">{{ $sample->original_name }}</a>
                                <span>{{ $sample->mime_type }} · {{ number_format($sample->size_bytes / 1024, 1) }} KB</span>
                            </div>
                        </li>
                    @empty
                        <li class="z4-application-sample-empty">{{ __('exhibitions::ui.auto_02520ec3e8') }}</li>
                    @endforelse
                </ul>
                <h3>{{ __('exhibitions::ui.auto_03fb83c974') }}</h3><ul class="z4-exhibitions-file-list">@forelse($supportingDocuments as $document)<li><a href="{{ route('admin.plugins.exhibitions.applications.supporting-documents.download', [$application->public_id, $document->public_id]) }}">{{ $document->original_name }}</a><span>{{ $document->mime_type }} · {{ number_format($document->size_bytes / 1024, 1) }} KB</span></li>@empty<li>{{ __('exhibitions::ui.auto_eeb0e4dda2') }}</li>@endforelse</ul>
            </section>
            <aside class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_e9b3b3a261') }}</h2><p>{{ __('exhibitions::ui.auto_2fe5a428be') }}</p></div></div>
                @if($application->state === 'under_review')
                    <form method="POST" action="{{ route('admin.plugins.exhibitions.applications.transition', $application->public_id) }}" class="z4-exhibitions-form" x-data="{ decision: @js(old('state', '')) }">@csrf
                        <label><span>{{ __('exhibitions::ui.auto_5d30f54fc5') }}</span><select name="state" x-model="decision" required><option value="">{{ __('exhibitions::ui.auto_e3c8d50558') }}</option>@foreach($states as $state)<option value="{{ $state->value }}">{{ match($state) { \Modules\Exhibition\Domain\Applications\ApplicationState::Approved => 'Approve', \Modules\Exhibition\Domain\Applications\ApplicationState::Rejected => 'Reject', \Modules\Exhibition\Domain\Applications\ApplicationState::DocumentsRequired => 'Documents Required', default => $state->label() } }}</option>@endforeach</select></label>
                        <div x-show="decision === 'rejected'" x-cloak>
                            <label><span>{{ __('exhibitions::ui.auto_6f141f6934') }}</span><textarea name="reason" rows="5" maxlength="4000" :required="decision === 'rejected'" placeholder="Explain clearly why the artist application was not approved.">{{ old('reason') }}</textarea><small>{{ __('exhibitions::ui.auto_cbbf349580') }}</small></label>
                        </div>
                        <div x-show="decision === 'documents_required'" x-cloak>
                            <label><span>{{ __('exhibitions::ui.auto_e9bcf161d5') }}</span><textarea name="next_action" rows="5" maxlength="4000" :required="decision === 'documents_required'" placeholder="List exactly what is required, for example: passport copy, corrected birth date, certificate, or additional artwork sample.">{{ old('next_action') }}</textarea><small>{{ __('exhibitions::ui.auto_f912ab30b5') }}</small></label>
                            <label><span>{{ __('exhibitions::ui.auto_a1ad211dae') }}</span><input type="date" name="response_deadline" value="{{ old('response_deadline') }}"></label>
                        </div>
                        <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_db7444ef0f') }}</button>
                    </form>
                @elseif($application->state === 'submitted')
                    <div class="z4-exhibitions-alert is-warning">{{ __('exhibitions::ui.auto_7b935e01bb') }}</div>
                @elseif($application->state === 'documents_required')
                    <div class="z4-exhibitions-alert is-warning">{{ __('exhibitions::ui.auto_c70a99a8d5') }}</div>
                @else
                    <div class="z4-exhibitions-alert">{{ __('exhibitions::ui.auto_015894b21f') }}</div>
                @endif
            </aside>
        </div>
        @php($artistAccountState = $identity ? \Modules\Exhibition\Domain\Artists\ArtistAccountState::tryFrom((string) $identity->state) : null)
        @if($artistAccountState)
            <section id="artist-account-actions" class="z4-exhibitions-panel" data-artist-account-actions>
                <div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_dc9793ff87') }}</h2><p>{{ __('exhibitions::ui.auto_1ecae0ba88') }}</p></div></div>
                <div class="z4-exhibitions-columns">
                    <div>
                        <div class="z4-exhibitions-alert"><strong>{{ __('exhibitions::ui.auto_bb3d9cec39') }}</strong><p>{{ __('exhibitions::ui.auto_09c6b77c15') }}</p></div>
                        @if($accountHistory->isNotEmpty())
                            <h3>{{ __('exhibitions::ui.auto_bdba28cafe') }}</h3>
                            <ol class="z4-exhibitions-timeline">@foreach($accountHistory as $entry)<li><span></span><div><strong>{{ $entry->from_state === 'candidate' ? 'Applicant' : __('exhibitions::ui.state_'.$entry->from_state) }} → {{ __('exhibitions::ui.state_'.$entry->to_state) }}</strong><time>{{ $entry->occurred_at }}</time><p>{{ $entry->reason }}</p><small>By {{ $entry->actor_name ?: 'authorized administrator' }}</small></div></li>@endforeach</ol>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.plugins.exhibitions.applications.account-state', $application->public_id) }}" class="z4-exhibitions-form">@csrf
                        <label><span>{{ __('exhibitions::ui.auto_004bf6c9a4') }} <b>*</b></span><select name="state" required><option value="">{{ __('exhibitions::ui.auto_dbb403d9e3') }}</option>@foreach(\Modules\Exhibition\Domain\Artists\ArtistAccountState::cases() as $state)<option value="{{ $state->value }}" @disabled($state === $artistAccountState)>{{ $state->label() }}{{ $state === $artistAccountState ? ' (current)' : '' }}</option>@endforeach</select></label>
                        <label><span>{{ __('exhibitions::ui.auto_2c0b4672d4') }} <b>*</b></span><textarea name="reason" rows="5" maxlength="4000" required placeholder="Explain why this account action is being taken.">{{ old('reason') }}</textarea><small>{{ __('exhibitions::ui.auto_d39e0058b9') }}</small></label>
                        @error('account_state')<small class="z4-exhibitions-field-error">{{ $message }}</small>@enderror
                        <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_225e651b08') }}</button>
                    </form>
                </div>
            </section>
        @endif
        <section class="z4-exhibitions-panel"><div class="z4-exhibitions-section-title"><span>04</span><div><h2>{{ __('exhibitions::ui.auto_dd8e553d20') }}</h2><p>{{ __('exhibitions::ui.auto_5541dbdf57') }}</p></div></div><ol class="z4-exhibitions-timeline">@foreach($history as $entry)<li><span></span><div><strong>{{ \Modules\Exhibition\Domain\Applications\ApplicationState::from($entry->to_state)->label() }}</strong><time>{{ $entry->occurred_at }}</time>@if($entry->reason)<p>{{ $entry->reason }}</p>@endif @if($entry->next_action)<p><b>{{ __('exhibitions::ui.auto_abb398c082') }}</b> {{ $entry->next_action }}</p>@endif</div></li>@endforeach</ol></section>
    </div></main>
</x-app-layout>
