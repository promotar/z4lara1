@php
    $returnStatusLabels = [
        'requested' => 'Requested',
        'evidence_required' => __('store::ui.store_auto_9db799b7a0'),
        'under_review' => __('store::ui.store_auto_53db20860c'),
        'approved' => 'Approved · Awaiting Shipment',
        'in_transit' => __('store::ui.store_auto_d3d288348e'),
        'received' => __('store::ui.store_auto_ac5bb077c3'),
        'inspection' => __('store::ui.store_auto_b4faa7ed95'),
        'returned' => __('store::ui.store_auto_fb8c97cc60'),
        'refund_details_required' => __('store::ui.store_auto_654b742157'),
        'refund_details_submitted' => __('store::ui.store_auto_21d0c29d83'),
        'refund_sent' => __('store::ui.store_auto_63c980de45'),
        'refund_received' => __('store::ui.store_auto_74b048522d'),
        'closed' => __('store::ui.store_auto_03f4a47830'),
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'customer_cancelled' => 'Cancelled by Customer',
    ];
    $returnConditionLabels = [
        'resellable' => 'Unopened / resellable',
        'opened' => 'Opened',
        'damaged' => 'Damaged or defective',
        'missing_parts' => 'Missing parts or accessories',
        'not_as_described' => 'Not as described',
        'unknown' => 'Other condition',
    ];
    $returnResolutionLabels = [
        'refund' => 'Refund requested',
        'exchange' => 'Exchange requested',
        'store_credit' => 'Store credit requested',
        'full_refund' => __('store::ui.store_auto_c0a3c3e9b5'),
        'partial_refund' => __('store::ui.store_auto_77fd2b4393'),
    ];
    $returnSteps = [__('store::ui.store_auto_15c2d85f1f'), __('store::ui.store_auto_c7b2a4d55f'), __('store::ui.store_auto_457dd55184'), 'Authorization', __('store::ui.store_auto_ea9cf7e47f'), __('store::ui.store_auto_ac5bb077c3'), __('store::ui.store_auto_b4faa7ed95'), __('store::ui.store_auto_382ab52293'), __('store::ui.store_auto_76f0ed934d'), __('store::ui.store_auto_03f4a47830')];
    $returnStageMap = [
        'requested' => 0, 'evidence_required' => 1, 'under_review' => 2, 'approved' => 3,
        'in_transit' => 4, 'received' => 5, 'inspection' => 6, 'returned' => 7,
        'refund_details_required' => 8, 'refund_details_submitted' => 8,
        'refund_sent' => 8, 'refund_received' => 8, 'closed' => 9,
    ];
    $currentReturnIndex = $returnStageMap[(string) $return_process->status] ?? -1;
    $refundMethodLabel = ($return_process->refund_destination_method ?? null) === 'cliq' ? 'CliQ' : 'Bank Transfer';
    $storeAddress = (string) (app(\Modules\Store\Application\Settings\StoreSettingsService::class)->current()['store_address'] ?? '');
@endphp

<section class="z4-store-form-card z4-return-workflow" aria-labelledby="return-workflow-title">
    <header class="z4-return-workflow-header">
        <div>
            <span class="z4-store-eyebrow">{{ __('store::ui.auto_2d702f4505') }}</span>
            <h2 id="return-workflow-title">{{ $returnStatusLabels[$return_process->status] ?? \Illuminate\Support\Str::headline($return_process->status) }}</h2>
            <p dir="auto"><strong>{{ __('store::ui.auto_fedf7161ca') }}</strong> {{ $return_process->reason }}</p>
            <p><strong>{{ __('store::ui.auto_ad4873a810') }}</strong> {{ $returnConditionLabels[$return_process->reported_condition] ?? \Illuminate\Support\Str::headline($return_process->reported_condition) }}</p>
            <p><strong>{{ __('store::ui.auto_4377bd504e') }}</strong> {{ $returnResolutionLabels[$return_process->preferred_resolution] ?? \Illuminate\Support\Str::headline($return_process->preferred_resolution) }}</p>
        </div>
        <div class="z4-return-workflow-badges">
            <span class="z4-order-status is-{{ $return_process->status }}">{{ $returnStatusLabels[$return_process->status] ?? $return_process->status }}</span>
            @if($return_process->rma_number)<strong>{{ __('store::ui.auto_7f081f2e58') }}</strong>@endif
        </div>
    </header>

    @if($currentReturnIndex >= 0)
        <ol class="z4-return-progress" aria-label="{{ __('store::ui.auto_980056754e') }}">
            @foreach($returnSteps as $stepIndex => $label)
                <li @class(['is-complete' => $stepIndex < $currentReturnIndex, 'is-current' => $stepIndex === $currentReturnIndex])><span>{{ $stepIndex + 1 }}</span><small>{{ $label }}</small></li>
            @endforeach
        </ol>
    @endif

    @if($return_process_attachments->isNotEmpty())
        <div class="z4-return-evidence-grid">
            @foreach($return_process_attachments as $attachment)
                <a href="{{ route('admin.plugins.store.orders.return-attachments.show', [$order->public_id, $attachment->public_id]) }}" target="_blank" rel="noopener">
                    <span>{{ str_starts_with((string) $attachment->mime_type, 'video/') ? '▶' : (str_contains((string) $attachment->mime_type, 'pdf') ? 'PDF' : 'IMG') }}</span>
                    <strong>{{ $attachment->original_name }}</strong>
                    <small>{{ \Illuminate\Support\Str::headline($attachment->kind) }} · {{ number_format($attachment->size_bytes / 1024, 0) }} KB</small>
                </a>
            @endforeach
        </div>
    @endif

    @if($return_process->carrier || $return_process->tracking_number)
        <dl class="z4-return-shipment-summary">
            <div><dt>{{ __('store::ui.auto_914419aa32') }}</dt><dd>{{ $return_process->carrier ?: '—' }}</dd></div>
            <div><dt>{{ __('store::ui.auto_5068c162a6') }}</dt><dd>{{ $return_process->tracking_number ?: '—' }}</dd></div>
            <div><dt>{{ __('store::ui.auto_747cf5c858') }}</dt><dd>{{ $return_process->shipped_at ? $storeDate->format($return_process->shipped_at, 'Y-m-d H:i').' · '.$storeDate->countryTimeLabel() : '—' }}</dd></div>
        </dl>
    @endif

    @can('store.orders.return')
        <div class="z4-return-stage-actions">
            @if($return_process->status === 'requested')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="approve_process">
                    <h3>{{ __('store::ui.auto_5e0d8f8e0f') }}</h3>
                    <p>{{ __('store::ui.auto_c77ae6c08b') }}</p>
                    <label><span>{{ __('store::ui.auto_50a90786f4') }}</span><textarea class="z4-store-textarea" name="message" rows="3">Upload clear photos of the product, packaging, label or serial number, and an optional short video showing the issue.</textarea></label>
                    <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_651009e703') }}</button>
                </form>
            @elseif($return_process->status === 'evidence_required')
                <div class="z4-return-waiting"><h3>{{ __('store::ui.auto_3cd8eb67d4') }}</h3><p>{{ __('store::ui.auto_85e7ccef1a') }}</p></div>
            @elseif($return_process->status === 'under_review')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="authorize_return">
                    <h3>{{ __('store::ui.auto_00dc62b566') }}</h3>
                    <label><span>{{ __('store::ui.auto_bf65c3bda7') }}</span><textarea class="z4-store-textarea" name="return_address" required rows="3">{{ old('return_address', $storeAddress) }}</textarea></label>
                    <label><span>{{ __('store::ui.auto_e8328b8565') }}</span><textarea class="z4-store-textarea" name="return_instructions" required rows="4">{{ old('return_instructions', 'Pack the product securely with all accessories and include the RMA number on the parcel.') }}</textarea></label>
                    <label><span>{{ __('store::ui.auto_7fb73ef394') }}</span><input class="z4-store-input" type="date" name="shipping_deadline" required min="{{ now()->addDay()->toDateString() }}" value="{{ old('shipping_deadline', now()->addDays(7)->toDateString()) }}"></label>
                    <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_c9ee0a2316') }}</button>
                </form>
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="request_more_evidence">
                    <h3>{{ __('store::ui.auto_cb1ea56c8e') }}</h3>
                    <label><span>{{ __('store::ui.auto_245ce30c06') }}</span><textarea class="z4-store-textarea" name="message" required rows="3"></textarea></label>
                    <button class="z4-store-btn z4-store-btn-ghost">{{ __('store::ui.auto_c7c7146cbe') }}</button>
                </form>
                @include('store::admin.orders._return_reject_form')
            @elseif($return_process->status === 'approved')
                <div class="z4-return-waiting"><h3>{{ __('store::ui.auto_eb52dd6233') }}</h3><p>{{ __('store::ui.auto_3b4e2138f1') }}</p><p><strong>{{ __('store::ui.auto_7ae97bdfba') }}</strong> {{ $return_process->shipping_deadline ? $storeDate->format($return_process->shipping_deadline, 'Y-m-d') : 'Not set' }}</p></div>
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">@csrf<input type="hidden" name="action" value="expire"><h3>{{ __('store::ui.auto_d4209961e2') }}</h3><label><span>{{ __('store::ui.auto_fffcb4d1e1') }}</span><textarea class="z4-store-textarea" name="reason" rows="2"></textarea></label><button class="z4-store-btn z4-store-btn-ghost">{{ __('store::ui.auto_283d6eccf7') }}</button></form>
            @elseif($return_process->status === 'in_transit')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">@csrf<input type="hidden" name="action" value="mark_received"><h3>{{ __('store::ui.auto_eecdc21d34') }}</h3><p>{{ __('store::ui.auto_228e2edbcd') }}</p><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_da89eb8562') }}</button></form>
            @elseif($return_process->status === 'received')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">@csrf<input type="hidden" name="action" value="start_inspection"><h3>{{ __('store::ui.auto_2e13bbfb7f') }}</h3><p>{{ __('store::ui.auto_5d92cc4ae5') }}</p><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_2e13bbfb7f') }}</button></form>
            @elseif($return_process->status === 'inspection')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="accept_after_inspection">
                    <h3>{{ __('store::ui.auto_ecf00b94db') }}</h3>
                    <label><span>{{ __('store::ui.auto_9ac3b9ff76') }}</span><select class="z4-store-select" name="inspection_condition" required>@foreach($returnConditionLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label><span>{{ __('store::ui.auto_e31c672021') }}</span><textarea class="z4-store-textarea" name="inspection_notes" required rows="4"></textarea></label>
                    <label><span>{{ __('store::ui.auto_3b16b4c032') }}</span><select class="z4-store-select" name="resolution_type" required><option value="full_refund">{{ __('store::ui.auto_c0a3c3e9b5') }}</option><option value="partial_refund">{{ __('store::ui.auto_77fd2b4393') }}</option><option value="exchange">{{ __('store::ui.auto_992374d8e2') }}</option><option value="store_credit">{{ __('store::ui.auto_69b90b8712') }}</option></select></label>
                    <label><span>{{ __('store::ui.auto_9ef04f1c10') }}</span><input class="z4-store-input" type="number" name="refund_amount" min="0" step="{{ strtoupper($order->currency) === 'JOD' ? '0.001' : '0.01' }}"></label>
                    <label class="z4-store-check"><input type="checkbox" name="restore_inventory" value="1"><span><strong>{{ __('store::ui.auto_80971bc0b3') }}</strong><small>{{ __('store::ui.auto_278c8b9619') }}</small></span></label>
                    <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_0885ef4ce3') }}</button>
                </form>
            @elseif($return_process->status === 'returned')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="complete_resolution">
                    <h3>{{ __('store::ui.auto_463294b162') }}</h3>
                    <p>{{ __('store::ui.auto_5a6a969616') }} <strong>{{ $returnResolutionLabels[$return_process->resolution_type] ?? \Illuminate\Support\Str::headline($return_process->resolution_type) }}</strong></p>
                    @if(in_array($return_process->resolution_type, ['full_refund','partial_refund'], true))
                        <label><span>{{ __('store::ui.auto_46c6d4ec11') }}</span><input class="z4-store-input" type="number" name="refund_amount" min="0" step="{{ strtoupper($order->currency) === 'JOD' ? '0.001' : '0.01' }}" value="{{ $return_process->refund_amount_minor ? number_format($return_process->refund_amount_minor / (strtoupper($order->currency) === 'JOD' ? 1000 : 100), strtoupper($order->currency) === 'JOD' ? 3 : 2, '.', '') : '' }}"></label>
                        <p>{{ __('store::ui.auto_9b32301ecb') }}</p>
                    @endif
                    <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_226d3b731e') }}</button>
                </form>
            @elseif($return_process->status === 'refund_details_required')
                <div class="z4-return-waiting"><h3>{{ __('store::ui.auto_97d06016d9') }}</h3><p>{{ __('store::ui.auto_b92779704d') }}</p></div>
            @elseif($return_process->status === 'refund_details_submitted')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
                    @csrf<input type="hidden" name="action" value="record_refund_sent">
                    <h3>{{ __('store::ui.auto_ace988ab4a') }}</h3>
                    <p>{{ __('store::ui.auto_083c53cc39') }}</p>
                    <dl class="z4-refund-admin-details">
                        <div><dt>{{ __('store::ui.auto_bc4bee1370') }}</dt><dd>{{ $refund_destination['beneficiary_name'] ?? '—' }}</dd></div>
                        @if($return_process->refund_destination_method === 'bank_transfer')
                            <div><dt>{{ __('store::ui.auto_eb45bd2725') }}</dt><dd>{{ $refund_destination['bank_name'] ?? '—' }}</dd></div>
                            <div><dt>IBAN</dt><dd dir="auto">{{ $refund_destination['iban'] ?? '—' }}</dd></div>
                            @if(isset($refund_destination['account_number']))<div><dt>{{ __('store::ui.auto_0f26e4f64d') }}</dt><dd dir="auto">{{ $refund_destination['account_number'] }}</dd></div>@endif
                        @else
                            @if(isset($refund_destination['cliq_alias']))<div><dt>{{ __('store::ui.auto_2897304c76') }}</dt><dd dir="auto">{{ $refund_destination['cliq_alias'] }}</dd></div>@endif
                            @if(isset($refund_destination['cliq_phone']))<div><dt>{{ __('store::ui.auto_566d18ebcb') }}</dt><dd dir="auto">{{ $refund_destination['cliq_phone'] }}</dd></div>@endif
                        @endif
                        <div><dt>{{ __('store::ui.auto_dd8366c780') }}</dt><dd>{{ $money($return_process->refund_amount_minor) }}</dd></div>
                    </dl>
                    <label><span>{{ __('store::ui.auto_2c11a7ab63') }}</span><input class="z4-store-input" name="refund_transfer_reference" required maxlength="128"></label>
                    <label><span>{{ __('store::ui.auto_80c5d19004') }}</span><textarea class="z4-store-textarea" name="refund_transfer_note" rows="3" maxlength="2000"></textarea></label>
                    <button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_efcd6c0f25') }}</button>
                </form>
            @elseif($return_process->status === 'refund_sent')
                <div class="z4-return-waiting"><h3>{{ __('store::ui.auto_2669e857b1') }}</h3><p>{{ __('store::ui.auto_7d74a5f4fd') }}</p><p><strong>{{ __('store::ui.auto_c6e3434870') }}</strong> <span dir="auto">{{ $return_process->refund_transfer_reference }}</span></p><p>{{ __('store::ui.auto_52cf3f4eab') }}</p></div>
            @elseif($return_process->status === 'refund_received')
                <form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">@csrf<input type="hidden" name="action" value="close"><h3>{{ __('store::ui.auto_f7eee17105') }}</h3><label><span>{{ __('store::ui.auto_d4eca6d65e') }}</span><textarea class="z4-store-textarea" name="message" rows="2"></textarea></label><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_f4dbb893bd') }}</button></form>
            @else
                <div class="z4-return-waiting"><h3>{{ $returnStatusLabels[$return_process->status] ?? \Illuminate\Support\Str::headline($return_process->status) }}</h3>@if($return_process->admin_response)<p dir="auto">{{ $return_process->admin_response }}</p>@endif</div>
            @endif
        </div>
    @endcan

    @if($return_process_events->isNotEmpty())
        <ol class="z4-return-event-list">
            @foreach($return_process_events as $event)
                <li><span></span><div><strong>{{ $returnStatusLabels[$event->to_status] ?? \Illuminate\Support\Str::headline($event->event_type) }}</strong><p dir="auto">{{ $event->message }}</p><small>{{ $storeDate->format($event->created_at, 'Y-m-d H:i') }} · {{ $storeDate->countryTimeLabel() }}@if($event->actor_name) · {{ $event->actor_name }}@endif</small></div></li>
            @endforeach
        </ol>
    @endif
</section>
