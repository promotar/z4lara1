@php
    $stateLabels = [
        'active' => __('exhibitions::ui.account_state_active'),
        'approved' => __('exhibitions::ui.account_state_approved'),
        'pending' => __('exhibitions::ui.account_state_pending'),
        'rejected' => __('exhibitions::ui.account_state_rejected'),
        'submitted' => __('exhibitions::ui.account_state_submitted'),
        'not_active' => __('exhibitions::ui.account_not_active'),
        'not_submitted' => __('exhibitions::ui.account_not_submitted'),
    ];
    $identityKey = $identityState ?: 'not_active';
    $applicationKey = $applicationState ?: 'not_submitted';
    $identityLabel = $stateLabels[$identityKey] ?? ucwords(str_replace('_', ' ', $identityKey));
    $applicationLabel = $stateLabels[$applicationKey] ?? ucwords(str_replace('_', ' ', $applicationKey));
@endphp
<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('exhibitions::ui.auto_2c80a44092') }}</h2>
            <p>{{ __('exhibitions::ui.auto_74388ea627') }}</p>
        </div>
        <a class="account-section-action" href="{{ $studioUrl }}">{{ __('exhibitions::ui.auto_27218f238a') }}</a>
    </div>

    <dl class="account-summary-grid">
        <div><dt>{{ __('exhibitions::ui.auto_6ce0096786') }}</dt><dd>{{ $identityLabel }}</dd></div>
        <div><dt>{{ __('exhibitions::ui.auto_e498749f3c') }}</dt><dd>{{ $applicationLabel }}</dd></div>
        <div><dt>{{ __('exhibitions::ui.auto_44eac6ddda') }}</dt><dd>{{ $artworkCount }}</dd></div>
    </dl>
</section>
