<x-app-layout>
@php
    $digits = strtoupper($order->currency) === 'JOD' ? 3 : 2;
    $money = fn ($minor) => app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)
        ->format((int) $minor, $order->currency);
    $storeDate = app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class);
    $countryCode = strtoupper(trim((string) ($order->country_code ?? '')));
    $countryName = match ($countryCode) {
        'JO' => 'Jordan (JO)',
        default => $countryCode !== '' ? $countryCode : null,
    };
    $deliveryAddressFields = collect([
        'Country' => $countryName,
        'Governorate' => $order->governorate,
        'City' => $order->city,
        'Area' => $order->area,
        'District' => $order->district,
        'Street' => $order->street,
        'Building' => $order->building,
        'Floor' => $order->floor,
        'Apartment' => $order->apartment,
        'Postal code' => $order->postal_code,
        'Address' => $order->address_line_1,
        'Additional address' => $order->address_line_2,
        'Landmark' => $order->landmark,
    ])->filter(static fn ($value) => filled($value));
    $pendingCancellationRequest = collect($events)
        ->where('event_type', 'cancellation_requested')
        ->last();
    if ($pendingCancellationRequest && collect($events)->contains(
        static fn ($event) => in_array($event->event_type, ['status_changed', 'cancellation_rejected', 'cancellation_accepted'], true) && (int) $event->id > (int) $pendingCancellationRequest->id
    )) {
        $pendingCancellationRequest = null;
    }
    $cancellationRejectionCount = collect($events)->where('event_type', 'cancellation_rejected')->count();
    $pendingReturnRequest = collect($events)->where('event_type', 'return_requested')->last();
    if ($pendingReturnRequest && collect($events)->contains(
        static fn ($event) => in_array($event->event_type, ['status_changed', 'return_rejected'], true) && (int) $event->id > (int) $pendingReturnRequest->id
    )) {
        $pendingReturnRequest = null;
    }
    $pendingReturnMetadata = $pendingReturnRequest && filled($pendingReturnRequest->metadata)
        ? (json_decode((string) $pendingReturnRequest->metadata, true) ?: [])
        : [];
    $requestedReturnCondition = (string) ($pendingReturnMetadata['condition'] ?? 'unknown');
    $returnConditionLabels = [
        'resellable' => 'Unopened / resellable',
        'opened' => 'Opened',
        'damaged' => 'Damaged or defective',
        'unknown' => 'Other condition',
    ];
    $returnToStockDefault = (bool) app(\Modules\Store\Application\Settings\StoreSettingsService::class)
        ->current()['returned_products_restore_stock'];
@endphp
<main class="z4-store-admin z4-store-order-detail">
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')
        <header class="z4-store-heading z4-order-heading">
            <div>
                <a class="z4-store-back" href="{{ route('admin.plugins.store.orders.index') }}">{{ __('store::ui.auto_769022a1af') }}</a>
                <span class="z4-store-eyebrow">{{ $storeDate->format($order->placed_at, 'Y-m-d H:i') }} · {{ $storeDate->countryTimeLabel() }}</span>
                <h1>{{ $order->order_number }}</h1>
                <p>{{ __('store::ui.auto_425fed5946') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                <span class="z4-order-status is-{{ $order->status }}">{{ \Modules\Store\Domain\Orders\OrderStatus::from($order->status)->label() }}</span>
                @can('store.orders.print')
                    @can('store.orders.customer.view')
                        <a class="z4-store-btn z4-store-btn-ghost" target="_blank" href="{{ route('admin.plugins.store.orders.print', $order->public_id) }}">{{ __('store::ui.auto_603c24bba7') }}</a>
                    @endcan
                @endcan
                @can('store.orders.notify')<form method="post" action="{{ route('admin.plugins.store.orders.notifications.resend', $order->public_id) }}">@csrf<button class="z4-store-btn z4-store-btn-ghost">{{ __('store::ui.auto_d71940f24e') }}</button></form>@endcan
            </div>
        </header>

        @if($pendingCancellationRequest && !in_array($order->status, ['cancelled', 'delivered', 'returned'], true))
            <section class="z4-store-form-card z4-cancellation-review" aria-labelledby="cancellation-review-title">
                <header>
                    <div>
                        <span class="z4-store-eyebrow">{{ __('store::ui.auto_af1d95298c') }}</span>
                        <h2 id="cancellation-review-title">{{ __('store::ui.auto_2b073e5fbb') }}</h2>
                        <p dir="auto">{{ $pendingCancellationRequest->message }}</p>
                    </div>
                    <span class="z4-cancellation-review-attempt">{{ __('store::ui.auto_dc5573e7ac') }}</span>
                </header>
                <div class="z4-cancellation-review-actions">
                    @can('store.orders.cancel')
                        <form method="post" action="{{ route('admin.plugins.store.orders.cancellation-request.decision', $order->public_id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accept">
                            <h3>{{ __('store::ui.auto_26c6977680') }}</h3>
                            <p>{{ __('store::ui.auto_d113c21a8b') }}</p>
                            @if($order->status === 'shipped')
                                <p><strong>{{ __('store::ui.auto_794db005f7') }}</strong> {{ __('store::ui.auto_fb490ca94a') }}</p>
                            @endif
                            <button class="z4-store-btn z4-store-btn-danger" type="submit">{{ __('store::ui.auto_65b95ec148') }}</button>
                        </form>
                    @endcan
                    @if($canUpdate)
                        <form method="post" action="{{ route('admin.plugins.store.orders.cancellation-request.decision', $order->public_id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <h3>{{ __('store::ui.auto_82d2c1d392') }}</h3>
                            <label>
                                <span>{{ __('store::ui.auto_d499a5a986') }}</span>
                                <textarea class="z4-store-textarea" name="reason" required maxlength="2000" rows="4" placeholder="Explain why this cancellation request cannot be accepted.">{{ old('reason') }}</textarea>
                            </label>
                            @if($cancellationRejectionCount >= 1)
                                <label class="z4-store-check">
                                    <input type="checkbox" name="prevent_further_appeals" value="1">
                                    <span><strong>{{ __('store::ui.auto_be85a9a654') }}</strong><small>{{ __('store::ui.auto_05a66a9665') }}</small></span>
                                </label>
                            @else
                                <p>{{ __('store::ui.auto_709d81c781') }}</p>
                            @endif
                            <button class="z4-store-btn z4-store-btn-ghost" type="submit">{{ __('store::ui.auto_e18e81b733') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        @if($return_process)
            @include('store::admin.orders._return_workflow')
        @endif

        <div class="z4-order-layout">
            <div class="z4-order-main">
                <div class="z4-order-information-grid">
                    <section class="z4-store-form-card">
                        <h2>{{ __('store::ui.auto_1d949d2235') }}</h2>
                        @if($canViewCustomer)
                            <dl class="z4-order-facts"><div><dt>{{ __('store::ui.auto_49ee308734') }}</dt><dd dir="auto">{{ $order->customer_name }}</dd></div><div><dt>{{ __('store::ui.auto_bcc254b55c') }}</dt><dd dir="auto">{{ $order->customer_phone }}</dd></div><div><dt>{{ __('store::ui.auto_ce8ae9da5b') }}</dt><dd dir="auto">{{ $order->customer_email ?: 'â€”' }}</dd></div><div><dt>{{ __('store::ui.auto_da4b78068d') }}</dt><dd>
                                @if($order->created_by)
                                    @can('users.manage')
                                        @if(\Illuminate\Support\Facades\Route::has('admin.users.index'))
                                            <a href="{{ route('admin.users.index', ['q' => $order->created_by]) }}">#{{ $order->created_by }}</a>
                                        @else
                                            #{{ $order->created_by }}
                                        @endif
                                    @else
                                        #{{ $order->created_by }}
                                    @endcan
                                @else
                                    Guest checkout
                                @endif
                            </dd></div></dl>
                        @else<p>{{ __('store::ui.auto_71f223e9d5') }}</p>@endif
                    </section>

                    <section class="z4-store-form-card">
                        <h2>{{ __('store::ui.auto_af0f5bdc5b') }}</h2>
                        @if($canViewCustomer)
                            @if($deliveryAddressFields->isNotEmpty())
                                <dl class="z4-order-facts">
                                    @foreach($deliveryAddressFields as $label => $value)
                                        <div><dt>{{ $label }}</dt><dd dir="auto">{{ $value }}</dd></div>
                                    @endforeach
                                </dl>
                            @else
                                <p>{{ __('store::ui.auto_85aa9045d3') }}</p>
                            @endif
                            <p dir="auto"><strong>{{ __('store::ui.auto_f3ec4c44d1') }}</strong> {{ $order->delivery_phone ?: $order->customer_phone }}</p>
                            @if($order->shipping_zone_name)<p dir="auto"><strong>{{ __('store::ui.auto_f9e53908b2') }}</strong> {{ $order->shipping_zone_name }}</p>@endif
                            @if($order->shipping_estimate)<p dir="auto"><strong>{{ __('store::ui.auto_36c0601f16') }}</strong> {{ $order->shipping_estimate }}</p>@endif
                            @if($order->delivery_notes)<p dir="auto">{{ $order->delivery_notes }}</p>@endif
                            @if($order->customer_notes)<p dir="auto"><strong>{{ __('store::ui.auto_6822dc02e3') }}</strong> {{ $order->customer_notes }}</p>@endif
                        @else
                            <p>{{ __('store::ui.auto_6b0fdbce39') }}</p>
                        @endif
                    </section>
                </div>

                <section class="z4-store-form-card">
                    <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_068f80c751') }}</h2><p>{{ __('store::ui.auto_8f20a1b51d') }}</p></div></header>
                    <div class="z4-store-table-scroll"><table class="z4-store-table"><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>{{ __('store::ui.auto_6fa2c797f7') }}</th><th>{{ __('store::ui.auto_3601146c4e') }}</th><th>{{ __('store::ui.auto_03ab340b3f') }}</th><th>{{ __('store::ui.auto_104d9898c0') }}</th><th>{{ __('store::ui.auto_96b0141273') }}</th></tr></thead><tbody>
                    @foreach($items as $item)
                    @php($itemImageUrl = app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($item->image_url))
                    <tr>
                        <td><div class="z4-order-product">
                            <span class="z4-order-product-media" aria-hidden="true">
                                <span>P</span>
                                @if($itemImageUrl)<img src="{{ $itemImageUrl }}" alt="" onerror="this.hidden=true">@endif
                            </span>
                            <div><strong dir="auto">{{ $item->product_name }}</strong><small>{{ $item->product_public_id }}</small></div>
                        </div></td>
                        <td>{{ $item->sku ?: '—' }}<small>{{ $item->variant_name ?: 'Default variant' }}</small></td>
                        <td>{{ $money($item->unit_price_minor) }}</td><td>{{ $item->quantity }}</td><td>{{ $money($item->discount_minor ?? 0) }}</td><td><strong>{{ $money($item->line_total_minor) }}</strong></td>
                    </tr>@endforeach
                    </tbody></table></div>
                    <dl class="z4-order-totals">
                        <div><dt>{{ __('store::ui.auto_2194aaec8c') }}</dt><dd>{{ $money($order->subtotal_minor) }}</dd></div>
                        <div><dt>{{ $order->coupon_code ? 'Coupon '.$order->coupon_code : 'Discount' }}</dt><dd>{{ $money($order->discount_minor) }}</dd></div>
                        @if((int)$order->tax_minor > 0)<div><dt>{{ $order->tax_name }}{{ $order->prices_include_tax ? ' (included)' : '' }}</dt><dd>{{ $money($order->tax_minor) }}</dd></div>@endif
                        <div><dt>{{ __('store::ui.auto_065ab3a28c') }}</dt><dd>{{ $money($order->shipping_minor) }}</dd></div>
                        <div class="is-total"><dt>{{ __('store::ui.auto_d4c56d0f6f') }}</dt><dd>{{ $money($order->total_minor) }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_81e91be8db') }}</dt><dd>{{ $money($order->payment_collected_minor) }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_5a1119ecb7') }}</dt><dd>{{ $money(max(0, $order->total_minor - $order->payment_collected_minor)) }}</dd></div>
                    </dl>
                </section>

                <section class="z4-store-form-card">
                    <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_3f0fdb0263') }}</h2><p>{{ __('store::ui.auto_fcc8fc3179') }}</p></div></header>
                    <ol class="z4-order-timeline">
                        @forelse($events as $event)
                            @php($metadata = $event->metadata ? json_decode($event->metadata, true) : [])
                            <li><span></span><div><header><strong>{{ str_replace('_', ' ', ucfirst($event->event_type)) }}</strong><time>{{ $storeDate->format($event->created_at, 'Y-m-d H:i:s') }} · {{ $storeDate->countryTimeLabel() }}</time></header>
                            @if($event->from_status || $event->to_status)<p>{{ $event->from_status ?: '—' }} → {{ $event->to_status ?: '—' }}</p>@endif
                            @if($event->message)<p dir="auto">{{ $event->message }}</p>@endif
                            <small>{{ $event->actor_name ?: $event->actor_type.($event->actor_id ? ': '.$event->actor_id : '') }}</small>
                            @if($metadata)<details><summary>{{ __('store::ui.auto_ce21470ab4') }}</summary><pre>{{ json_encode($metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                            </div></li>
                        @empty<li><div><strong>{{ __('store::ui.auto_54cc697595') }}</strong></div></li>@endforelse
                    </ol>
                </section>
            </div>

            <aside class="z4-order-sidebar">
                @if(false)
                <section class="z4-store-form-card">
                    <h2>{{ __('store::ui.auto_ce26601dac') }}</h2>
                    @if($canViewCustomer)
                        <dl class="z4-order-facts"><div><dt>{{ __('store::ui.auto_49ee308734') }}</dt><dd dir="auto">{{ $order->customer_name }}</dd></div><div><dt>{{ __('store::ui.auto_bcc254b55c') }}</dt><dd dir="auto">{{ $order->customer_phone }}</dd></div><div><dt>{{ __('store::ui.auto_ce8ae9da5b') }}</dt><dd dir="auto">{{ $order->customer_email ?: '—' }}</dd></div><div><dt>{{ __('store::ui.auto_da4b78068d') }}</dt><dd>
                            @if($order->created_by)
                                @can('users.manage')
                                    @if(\Illuminate\Support\Facades\Route::has('admin.users.index'))
                                        <a href="{{ route('admin.users.index', ['q' => $order->created_by]) }}">#{{ $order->created_by }}</a>
                                    @else
                                        #{{ $order->created_by }}
                                    @endif
                                @else
                                    #{{ $order->created_by }}
                                @endcan
                            @else
                                Guest checkout
                            @endif
                        </dd></div></dl>
                    @else<p>{{ __('store::ui.auto_71f223e9d5') }}</p>@endif
                </section>

                @endif

                @if($canUpdate && count($allowed_transitions) > 0)
                <section class="z4-store-form-card">
                    <h2>{{ __('store::ui.auto_57297718fd') }}</h2>
                    <form class="z4-order-status-form"
                          method="post"
                          action="{{ route('admin.plugins.store.orders.transition', $order->public_id) }}"
                          data-order-status-form
                          data-current-status="{{ $order->status }}">
                        @csrf
                        <label>
                            <span>{{ __('store::ui.auto_ec53a8c4f0') }}</span>
                            <select class="z4-store-select" name="status" data-order-status-select required>
                                <option value="{{ $order->status }}" selected disabled>{{ __('store::ui.auto_621c456a5b') }}</option>
                                @foreach($allowed_transitions as $target)
                                    @if($target->value === 'cancelled')
                                        @can('store.orders.cancel')
                                            <option value="{{ $target->value }}">{{ $target->label() }}</option>
                                        @endcan
                                    @elseif($target->value === 'returned')
                                        @can('store.orders.return')
                                            <option value="{{ $target->value }}">{{ $target->label() }}</option>
                                        @endcan
                                    @else
                                        <option value="{{ $target->value }}">{{ $target->label() }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>

                        <div class="z4-order-status-context" data-order-status-context="shipped" hidden>
                            <p>{{ __('store::ui.auto_339b488d44') }}</p>
                            <label><span>{{ __('store::ui.auto_3d51ce233c') }}</span><input class="z4-store-input" name="shipping_company" value="{{ old('shipping_company', $order->shipping_company) }}" disabled></label>
                            <label><span>{{ __('store::ui.auto_5055d1a444') }}</span><input class="z4-store-input" name="courier_name" value="{{ old('courier_name', $order->courier_name) }}" disabled></label>
                            <label><span>{{ __('store::ui.auto_09dccf91c9') }}</span><input class="z4-store-input" name="courier_phone" value="{{ old('courier_phone', $order->courier_phone) }}" disabled></label>
                            <label><span>{{ __('store::ui.auto_5068c162a6') }}</span><input class="z4-store-input" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" disabled></label>
                            <label><span>{{ __('store::ui.auto_a78c2337cf') }}</span><textarea class="z4-store-textarea" name="shipping_notes" disabled>{{ old('shipping_notes', $order->shipping_notes) }}</textarea></label>
                        </div>

                        <div class="z4-order-status-context" data-order-status-context="delivered" hidden>
                            <p>{{ __('store::ui.auto_369f737ed2') }}</p>
                            <label class="z4-store-check">
                                <input type="checkbox" name="payment_collected" value="1" disabled>
                                <span><strong>{{ __('store::ui.auto_3e1dfedc7d') }}</strong></span>
                            </label>
                            <label><span>{{ __('store::ui.auto_3ad4a4a203') }}</span><textarea class="z4-store-textarea" name="payment_note" disabled></textarea></label>
                        </div>

                        @can('store.orders.cancel')
                        <div class="z4-order-status-context is-danger" data-order-status-context="cancelled" hidden>
                            <p>{{ __('store::ui.auto_968e65c6b7') }}</p>
                            <label>
                                <span>{{ __('store::ui.auto_fffcb4d1e1') }}</span>
                                <select class="z4-store-select" name="cancellation_reason_code" data-required-when-active disabled>
                                    <option value="">{{ __('store::ui.auto_026723f298') }}</option>
                                    <option value="customer_request">{{ __('store::ui.auto_af1d95298c') }}</option>
                                    <option value="out_of_stock">{{ __('store::ui.auto_b55197a49e') }}</option>
                                    <option value="invalid_customer_data">{{ __('store::ui.auto_dc610da3f9') }}</option>
                                    <option value="unreachable_customer">{{ __('store::ui.auto_83d613dd3b') }}</option>
                                    <option value="undeliverable">{{ __('store::ui.auto_ee427fddf7') }}</option>
                                    <option value="other">{{ __('store::ui.auto_6311ae17c1') }}</option>
                                </select>
                            </label>
                            <label><span>{{ __('store::ui.auto_b72ac10807') }}</span><textarea class="z4-store-textarea" name="reason" data-required-when-active disabled></textarea></label>
                        </div>
                        @endcan

                        @can('store.orders.return')
                        <div class="z4-order-status-context" data-order-status-context="returned" hidden>
                            <p>{{ __('store::ui.auto_cd8ee64ef8') }}</p>
                            <label>
                                <span>{{ __('store::ui.auto_b893d4c0e3') }}</span>
                                <select class="z4-store-select" name="return_condition" data-required-when-active disabled>
                                    <option value="">{{ __('store::ui.auto_bea6f1fe07') }}</option>
                                    <option value="resellable">{{ __('store::ui.auto_6954c7048e') }}</option>
                                    <option value="opened">{{ __('store::ui.auto_1a11b1adc3') }}</option>
                                    <option value="damaged">{{ __('store::ui.auto_f7bd791aac') }}</option>
                                    <option value="unknown">{{ __('store::ui.auto_88183b946c') }}</option>
                                </select>
                            </label>
                            <label><span>{{ __('store::ui.auto_8937b0570b') }}</span><textarea class="z4-store-textarea" name="reason" data-required-when-active disabled></textarea></label>
                            <label class="z4-store-check">
                                <input type="checkbox" name="return_to_stock" value="1" disabled>
                                <span><strong>{{ __('store::ui.auto_9aaedfcecf') }}</strong></span>
                            </label>
                        </div>
                        @endcan

                        <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block"
                                type="submit"
                                data-order-status-submit
                                disabled>{{ __('store::ui.auto_f5359d1dd0') }}</button>
                    </form>
                </section>
                @endif

                @if($canUpdate && in_array($order->status, ['shipped', 'delivered', 'returned'], true))
                <section class="z4-store-form-card">
                    <h2>{{ __('store::ui.auto_339b488d44') }}</h2>
                    <p class="z4-store-muted">{{ __('store::ui.auto_c1bb1ca4c8') }}</p>
                    <form class="z4-store-form-grid"
                          method="post"
                          action="{{ route('admin.plugins.store.orders.shipment', $order->public_id) }}">
                        @csrf
                        @method('PATCH')
                        <label><span>{{ __('store::ui.auto_3d51ce233c') }}</span><input class="z4-store-input" name="shipping_company" value="{{ old('shipping_company', $order->shipping_company) }}"></label>
                        <label><span>{{ __('store::ui.auto_5055d1a444') }}</span><input class="z4-store-input" name="courier_name" value="{{ old('courier_name', $order->courier_name) }}"></label>
                        <label><span>{{ __('store::ui.auto_09dccf91c9') }}</span><input class="z4-store-input" name="courier_phone" value="{{ old('courier_phone', $order->courier_phone) }}"></label>
                        <label><span>{{ __('store::ui.auto_5068c162a6') }}</span><input class="z4-store-input" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}"></label>
                        <label class="is-wide"><span>{{ __('store::ui.auto_a78c2337cf') }}</span><textarea class="z4-store-textarea" name="shipping_notes">{{ old('shipping_notes', $order->shipping_notes) }}</textarea></label>
                        <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block is-wide">{{ __('store::ui.auto_e4b0acbde2') }}</button>
                    </form>
                </section>
                @endif

                @if($order->status === 'cancelled')
                    @can('store.orders.delete')
                        <section class="z4-store-form-card z4-order-actions">
                            <h2>{{ __('store::ui.auto_0d2291574c') }}</h2>
                            <p>{{ __('store::ui.auto_2cfe6bda6f') }}</p>
                            <form method="post"
                                  action="{{ route('admin.plugins.store.orders.destroy', $order->public_id) }}"
                                  data-z4-confirm
                                  data-confirm-title="{{ __('store::ui.auto_c1e4be4e43') }}"
                                  data-confirm-message="This will permanently remove the order, its products, timeline, coupon usage, and released inventory reservations."
                                  data-confirm-label="Delete permanently"
                                  data-confirm-require="{{ $order->order_number }}"
                                  data-confirm-input="confirmation"
                                  data-confirm-tone="danger">
                                @csrf @method('DELETE')
                                <input type="hidden" name="confirmation" value="">
                                <button class="z4-store-btn z4-store-btn-danger z4-store-btn-block" type="submit">{{ __('store::ui.auto_76bf2e10dc') }}</button>
                            </form>
                        </section>
                    @endcan
                @endif

                @can('store.orders.payment')
                <section class="z4-store-form-card">
                    <h2>{{ __('store::ui.auto_69f30555be') }}</h2>
                    <form class="z4-store-form-grid" method="post" action="{{ route('admin.plugins.store.orders.payment', $order->public_id) }}">@csrf @method('PATCH')
                        <label><span>{{ __('store::ui.auto_59758c8f7f') }}</span><select class="z4-store-select" name="payment_status">@foreach(['cod_pending','paid','failed','cancelled'] as $status)<option value="{{ $status }}" @selected($order->payment_status === $status)>{{ str_replace('_',' ',ucfirst($status)) }}</option>@endforeach</select></label>
                        <p class="z4-store-muted">{{ __('store::ui.auto_d443a07802') }}</p>
                        <label class="is-wide"><span>{{ __('store::ui.auto_3b0649c726') }}</span><textarea class="z4-store-textarea" name="payment_note">{{ $order->payment_note }}</textarea></label>
                        <button class="z4-store-btn z4-store-btn-ghost is-wide">{{ __('store::ui.auto_06933067aa') }}</button>
                    </form>
                </section>
                @endcan

                @can('store.orders.notes')
                <section class="z4-store-form-card"><h2>{{ __('store::ui.auto_08643dacea') }}</h2><form method="post" action="{{ route('admin.plugins.store.orders.notes.store', $order->public_id) }}">@csrf<textarea class="z4-store-textarea" name="note" rows="4" required></textarea><button class="z4-store-btn z4-store-btn-ghost z4-store-btn-block">{{ __('store::ui.auto_50cd1871f9') }}</button></form></section>
                @endcan

                @can('store.orders.notify')
                <section class="z4-store-form-card"><details><summary>{{ __('store::ui.auto_8607411ea0') }}</summary><form method="post" action="{{ route('admin.plugins.store.orders.notifications.contact', $order->public_id) }}">@csrf<textarea class="z4-store-textarea" name="message" rows="4" maxlength="2000" required placeholder="Customer-facing message only"></textarea><button class="z4-store-btn z4-store-btn-ghost z4-store-btn-block">{{ __('store::ui.auto_fa27c9d4b1') }}</button></form></details></section>
                @endcan

                @if($canOverride)
                <section class="z4-store-form-card"><details><summary>{{ __('store::ui.auto_7c0b537d8f') }}</summary><form method="post" action="{{ route('admin.plugins.store.orders.transition', $order->public_id) }}">@csrf<input type="hidden" name="override" value="1"><select class="z4-store-select" name="status">@foreach(\Modules\Store\Domain\Orders\OrderStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select><textarea class="z4-store-textarea" name="override_reason" required placeholder="Mandatory audit reason"></textarea><button class="z4-store-btn z4-store-btn-danger z4-store-btn-block">{{ __('store::ui.auto_af441169f8') }}</button></form></details></section>
                @endif
            </aside>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('[data-order-status-form]').forEach((form) => {
    const select = form.querySelector('[data-order-status-select]');
    const submit = form.querySelector('[data-order-status-submit]');
    const contexts = Array.from(form.querySelectorAll('[data-order-status-context]'));

    const refresh = () => {
        const target = select.value;
        const canSubmit = target !== '' && target !== form.dataset.currentStatus;

        contexts.forEach((context) => {
            const active = canSubmit && context.dataset.orderStatusContext === target;
            context.hidden = !active;

            context.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !active;
                if (field.hasAttribute('data-required-when-active')) {
                    field.required = active;
                }
            });
        });

        submit.disabled = !canSubmit;
        submit.textContent = target === 'cancelled' ? 'Cancel and release stock' : 'Update status';
        submit.classList.toggle('z4-store-btn-danger', target === 'cancelled');
        submit.classList.toggle('z4-store-btn-primary', target !== 'cancelled');
    };

    select.addEventListener('change', refresh);
    refresh();
});
</script>
</x-app-layout>
