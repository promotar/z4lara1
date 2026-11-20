@php
    $returnStatusLabels = [
        'requested' => __('store::ui.store_auto_4ca473a370'), 'evidence_required' => __('store::ui.store_auto_9db799b7a0'),
        'under_review' => __('store::ui.store_auto_53db20860c'), 'approved' => __('store::ui.store_auto_fce1d56f55'),
        'in_transit' => __('store::ui.store_auto_d3d288348e'), 'received' => __('store::ui.store_auto_a807f98f2f'),
        'inspection' => __('store::ui.store_auto_5511efed96'), 'returned' => __('store::ui.store_auto_fb8c97cc60'),
        'refund_details_required' => __('store::ui.store_auto_654b742157'),
        'refund_details_submitted' => __('store::ui.store_auto_21d0c29d83'),
        'refund_sent' => __('store::ui.store_auto_63c980de45'), 'refund_received' => __('store::ui.store_auto_74b048522d'),
        'closed' => __('store::ui.store_auto_df20e23370'), 'rejected' => __('store::ui.store_auto_51ed80512a'),
        'expired' => __('store::ui.store_auto_7476ba3b0f'), 'customer_cancelled' => __('store::ui.store_auto_9642f5d352'),
    ];
    $returnSteps = [__('store::ui.store_auto_15c2d85f1f'), __('store::ui.store_auto_c7b2a4d55f'), __('store::ui.store_auto_457dd55184'), __('store::ui.store_auto_6f8063417a'), __('store::ui.store_auto_ea9cf7e47f'), __('store::ui.store_auto_ac5bb077c3'), __('store::ui.store_auto_b4faa7ed95'), __('store::ui.store_auto_382ab52293'), __('store::ui.store_auto_76f0ed934d'), __('store::ui.store_auto_03f4a47830')];
    $returnStageMap = [
        'requested' => 0, 'evidence_required' => 1, 'under_review' => 2, 'approved' => 3,
        'in_transit' => 4, 'received' => 5, 'inspection' => 6, 'returned' => 7,
        'refund_details_required' => 8, 'refund_details_submitted' => 8,
        'refund_sent' => 8, 'refund_received' => 8, 'closed' => 9,
    ];
    $returnIndex = $returnStageMap[(string) $return_process->status] ?? -1;
    $returnResolutionLabels = [
        'refund' => __('store::ui.store_auto_76f0ed934d'), 'exchange' => __('store::ui.store_auto_992374d8e2'), 'store_credit' => __('store::ui.store_auto_69b90b8712'),
        'full_refund' => __('store::ui.store_auto_c0a3c3e9b5'), 'partial_refund' => __('store::ui.store_auto_77fd2b4393'),
    ];
    $returnConditionLabels = [
        'resellable' => 'Unopened / resellable', 'opened' => 'Opened',
        'damaged' => __('store::ui.store_auto_8351074517'), 'missing_parts' => __('store::ui.store_auto_9165052189'),
        'not_as_described' => __('store::ui.store_auto_01ba926245'), 'unknown' => __('store::ui.store_auto_6621409844'),
    ];
    $refundMethodLabel = ($return_process->refund_destination_method ?? null) === 'cliq' ? 'CliQ' : __('store::ui.store_auto_3726d2bc67');
    $selectedRefundMethod = old('refund_destination_method', 'bank_transfer');
@endphp

<section class="z4-account-section z4-account-return-workflow" id="return-process">
    <header class="z4-account-section-heading z4-return-customer-heading">
        <div><p>{{ __('store::ui.return-process') }}</p><h2>{{ $returnStatusLabels[$return_process->status] ?? \Illuminate\Support\Str::headline($return_process->status) }}</h2><span>{{ __('store::ui.store_auto_32eb837de1', ['id' => $return_process->public_id]) }}</span></div>
        @if($return_process->rma_number)<strong class="z4-return-rma">RMA: {{ $return_process->rma_number }}</strong>@endif
    </header>

    @if($returnIndex >= 0)
        <ol class="z4-return-progress" aria-label="{{ __('store::ui.return-progress') }}">
            @foreach($returnSteps as $stepIndex => $label)
                <li @class(['is-complete' => $stepIndex < $returnIndex, 'is-current' => $stepIndex === $returnIndex])><span>{{ $stepIndex + 1 }}</span><small>{{ $label }}</small></li>
            @endforeach
        </ol>
    @endif

    <div class="z4-return-customer-summary">
        <div><small>{{ __('store::ui.your-reason-2') }}</small><p dir="auto">{{ $return_process->reason }}</p></div>
        <div><small>{{ __('store::ui.reported-condition') }}</small><p>{{ $returnConditionLabels[$return_process->reported_condition] ?? \Illuminate\Support\Str::headline($return_process->reported_condition) }}</p></div>
        <div><small>{{ __('store::ui.preferred-solution') }}</small><p>{{ $returnResolutionLabels[$return_process->preferred_resolution] ?? \Illuminate\Support\Str::headline($return_process->preferred_resolution) }}</p></div>
    </div>

    @if($return_process->admin_response)
        <div class="z4-account-cancellation-reason {{ $return_process->status === 'rejected' ? 'is-rejected' : '' }}" role="note"><span class="material-symbols-outlined" aria-hidden="true">support_agent</span><div><strong>{{ __('store::ui.store-response-2') }}</strong><p dir="auto">{{ $return_process->admin_response }}</p></div></div>
    @endif

    @if($return_process->status === 'evidence_required')
        <form class="z4-return-customer-form" method="post" enctype="multipart/form-data" action="{{ route('store.account.orders.return-request.evidence', $order->public_id) }}">
            @csrf
            <h3>{{ __('store::ui.upload-product-condition-evidence') }}</h3><p>{{ __('store::ui.upload-clear-photos-of-the-product-and-packaging-a-short-vid') }}</p>
            <label><span>{{ __('store::ui.store_auto_8f2b26487f') }}</span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
            <label><span>{{ __('store::ui.short-video') }}</span><input type="file" name="video" accept="video/mp4,video/webm,video/quicktime"></label>
            <label><span>{{ __('store::ui.additional-explanation') }}</span><textarea name="message" rows="4" maxlength="2000"></textarea></label>
            <button class="z4-account-primary-button" type="submit">{{ __('store::ui.submit-evidence') }}</button>
        </form>
    @elseif($return_process->status === 'under_review')
        <div class="z4-return-customer-notice"><strong>{{ __('store::ui.your-evidence-is-under-review') }}</strong><p>{{ __('store::ui.the-store-may-authorize-shipment-or-request-additional-evide') }}</p></div>
    @elseif($return_process->status === 'approved')
        <div class="z4-return-authorization"><h3>{{ __('store::ui.return-authorization') }}</h3><p><strong>{{ __('store::ui.return-address') }}</strong><br><span dir="auto">{{ $return_process->return_address }}</span></p><p><strong>{{ __('store::ui.instructions') }}</strong><br><span dir="auto">{{ $return_process->return_instructions }}</span></p><p><strong>{{ __('store::ui.shipping-deadline') }}</strong> {{ $return_process->shipping_deadline ? $storeDate->format($return_process->shipping_deadline, 'M d, Y') : '—' }}</p></div>
        <form class="z4-return-customer-form" method="post" enctype="multipart/form-data" action="{{ route('store.account.orders.return-request.shipment', $order->public_id) }}">
            @csrf<h3>{{ __('store::ui.submit-return-shipment') }}</h3>
            <label><span>{{ __('store::ui.shipping-company') }}</span><input type="text" name="carrier" required maxlength="128"></label>
            <label><span>{{ __('store::ui.tracking-number') }}</span><input type="text" name="tracking_number" required maxlength="128"></label>
            <label><span>{{ __('store::ui.store_auto_e6a11011bb') }}</span><input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
            <button class="z4-account-primary-button" type="submit">{{ __('store::ui.confirm-shipment') }}</button>
        </form>
    @elseif($return_process->status === 'in_transit')
        <div class="z4-return-customer-notice"><strong>{{ __('store::ui.your-return-is-in-transit') }}</strong><p>{{ $return_process->carrier }} · {{ __('store::ui.store_auto_91d9419d06') }} {{ $return_process->tracking_number }}</p></div>
    @elseif($return_process->status === 'received')
        <div class="z4-return-customer-notice"><strong>{{ __('store::ui.the-store-received-your-parcel') }}</strong><p>{{ __('store::ui.inspection-will-begin-shortly') }}</p></div>
    @elseif($return_process->status === 'inspection')
        <div class="z4-return-customer-notice"><strong>{{ __('store::ui.your-product-is-being-inspected') }}</strong><p>{{ __('store::ui.the-result-and-final-resolution-will-appear-here') }}</p></div>
    @elseif($return_process->status === 'returned')
        <div class="z4-return-customer-notice is-success"><strong>{{ __('store::ui.return-accepted') }}</strong><p>{{ $return_process->inspection_notes }}</p><p>{{ __('store::ui.the-store-is-preparing-the-approved-resolution') }}</p></div>
    @elseif($return_process->status === 'refund_details_required')
        <form class="z4-return-customer-form z4-refund-destination-form" method="post" action="{{ route('store.account.orders.return-request.refund-destination', $order->public_id) }}">
            @csrf
            <div class="z4-refund-form-heading"><div><span>{{ __('store::ui.secure-refund-setup') }}</span><h3>{{ __('store::ui.store_auto_0f2246ffe2', ['amount' => $money($return_process->refund_amount_minor)]) }}</h3></div><span class="material-symbols-outlined" aria-hidden="true">lock</span></div>
            <p>{{ __('store::ui.your-details-are-encrypted-and-used-only-to-send-this-refund') }}</p>
            <div class="z4-refund-methods">
                <label><input type="radio" name="refund_destination_method" value="bank_transfer" @checked($selectedRefundMethod === 'bank_transfer')><span><strong>{{ __('store::ui.bank-transfer') }}</strong><small>{{ __('store::ui.receive-the-refund-in-your-bank-account') }}</small></span></label>
                <label><input type="radio" name="refund_destination_method" value="cliq" @checked($selectedRefundMethod === 'cliq')><span><strong>{{ __('store::ui.cliq') }}</strong><small>{{ __('store::ui.receive-it-using-your-cliq-alias-or-phone') }}</small></span></label>
            </div>
            <label><span>{{ __('store::ui.beneficiary-name') }}</span><input type="text" name="beneficiary_name" value="{{ old('beneficiary_name', auth()->user()->name) }}" required maxlength="255"></label>
            <div class="z4-refund-fields-grid z4-refund-method-fields z4-refund-bank-fields">
                <label><span>{{ __('store::ui.bank-name') }}</span><input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="255"></label>
                <label><span>{{ __('store::ui.iban') }}</span><input type="text" name="iban" value="{{ old('iban') }}" maxlength="64" autocomplete="off"></label>
                <label><span>{{ __('store::ui.store_auto_60c03ce9d7') }}</span><input type="text" name="account_number" value="{{ old('account_number') }}" maxlength="64" autocomplete="off"></label>
            </div>
            <div class="z4-refund-fields-grid z4-refund-method-fields z4-refund-cliq-fields">
                <label><span>{{ __('store::ui.cliq-alias') }}</span><input type="text" name="cliq_alias" value="{{ old('cliq_alias') }}" maxlength="128" autocomplete="off"></label>
                <label><span>{{ __('store::ui.cliq-phone') }}</span><input type="tel" name="cliq_phone" value="{{ old('cliq_phone') }}" maxlength="64" autocomplete="off"></label>
            </div>
            <small class="z4-refund-help z4-refund-bank-help">{{ __('store::ui.enter-the-bank-name-and-iban-the-account-number-is-optional') }}</small>
            <small class="z4-refund-help z4-refund-cliq-help">{{ __('store::ui.enter-a-cliq-alias-or-the-phone-number-registered-with-cliq') }}</small>
            <button class="z4-account-primary-button" type="submit">{{ __('store::ui.submit-refund-details-securely') }}</button>
        </form>
    @elseif($return_process->status === 'refund_details_submitted')
        <div class="z4-return-customer-notice"><strong>{{ __('store::ui.refund-details-submitted') }}</strong><p>{{ __('store::ui.store_auto_4bb37a973a', ['method' => $refundMethodLabel]) }}</p></div>
    @elseif($return_process->status === 'refund_sent')
        <div class="z4-return-customer-notice is-success"><strong>{{ __('store::ui.refund-sent') }}</strong><p>{{ __('store::ui.store_auto_f4e64c862a', ['amount' => $money($return_process->refund_amount_minor), 'method' => $refundMethodLabel]) }}</p><p>{{ __('store::ui.transfer-reference') }} <strong dir="auto">{{ $return_process->refund_transfer_reference }}</strong></p></div>
        <form class="z4-refund-confirm-form" method="post" action="{{ route('store.account.orders.return-request.refund-received', $order->public_id) }}">@csrf<label><input type="checkbox" name="refund_received_confirmation" value="1" required><span>{{ __('store::ui.i-confirm-that-i-received-the-refund-in-my-account') }}</span></label><button class="z4-account-primary-button" type="submit">{{ __('store::ui.confirm-refund-received') }}</button></form>
    @elseif($return_process->status === 'refund_received')
        <div class="z4-return-customer-notice is-success"><strong>{{ __('store::ui.receipt-confirmed') }}</strong><p>{{ __('store::ui.you-confirmed-receiving-the-refund-the-store-can-now-close-t') }}</p></div>
    @elseif($return_process->status === 'closed')
        <div class="z4-return-customer-notice is-success"><strong>{{ __('store::ui.return-case-closed') }}</strong><p>{{ $return_process->inspection_notes }}</p>@if($return_process->refund_amount_minor)<p>{{ __('store::ui.store_auto_04bd41b88f', ['amount' => $money($return_process->refund_amount_minor), 'method' => $return_process->refund_method]) }}</p>@endif</div>
    @endif

    @if($return_process_attachments->isNotEmpty())
        <div class="z4-return-evidence-grid">@foreach($return_process_attachments as $attachment)<a href="{{ route('store.account.orders.return-attachments.show', [$order->public_id, $attachment->public_id]) }}" target="_blank" rel="noopener"><span>{{ str_starts_with((string) $attachment->mime_type, 'video/') ? '▶' : (str_contains((string) $attachment->mime_type, 'pdf') ? 'PDF' : 'IMG') }}</span><strong>{{ $attachment->original_name }}</strong><small>{{ \Illuminate\Support\Str::headline($attachment->kind) }}</small></a>@endforeach</div>
    @endif

    @if(in_array($return_process->status, ['requested','evidence_required','under_review','approved'], true))
        <form method="post" action="{{ route('store.account.orders.return-request.cancel', $order->public_id) }}">@csrf @method('delete')<button class="z4-account-secondary-button" type="submit">{{ __('store::ui.cancel-return-request') }}</button></form>
    @endif

    @if($return_process_events->isNotEmpty())
        <ol class="z4-return-event-list">@foreach($return_process_events as $event)<li><span></span><div><strong>{{ $returnStatusLabels[$event->to_status] ?? \Illuminate\Support\Str::headline($event->event_type) }}</strong><p dir="auto">{{ $event->message }}</p><small>{{ $storeDate->format($event->created_at, 'M d, Y \a\t H:i') }} · {{ $storeDate->countryTimeLabel() }}</small></div></li>@endforeach</ol>
    @endif
</section>
