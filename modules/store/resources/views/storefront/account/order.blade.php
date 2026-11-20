<x-frontend-layout>
    @php
        $money = fn($minor) => app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $minor, $order->currency);
        $storeDate = app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class);
        $status = \Modules\Store\Domain\Orders\OrderStatus::from($order->status);
        $countryNames = app(\Modules\Store\Application\Shipping\ShippingRegionCatalog::class)->countries();
        $deliveryAddress = collect([$order->address_line_1, $order->city, $order->governorate, $countryNames[$order->country_code] ?? $order->country_code])->filter();
        $paymentStatus = match ($order->payment_status) {'cod_pending' => __('store::ui.store_auto_c64d1e4ec7'), 'paid' => __('store::ui.store_auto_e0010a0a1a'), 'failed' => __('store::ui.store_auto_1305a2b0c6'), 'cancelled' => __('store::ui.store_auto_d27600e8af'), default => \Illuminate\Support\Str::headline($order->payment_status)};
        $shipmentDetails = collect([
            __('store::ui.store_auto_3d51ce233c') => $order->shipping_company,
            __('store::ui.store_auto_5055d1a444') => $order->courier_name,
            __('store::ui.store_auto_09dccf91c9') => $order->courier_phone,
            __('store::ui.store_auto_5068c162a6') => $order->tracking_number,
            __('store::ui.store_auto_a78c2337cf') => $order->shipping_notes,
        ])->filter(fn ($value) => filled($value));
        $returnConditionLabels = [
            'resellable' => __('store::ui.store_auto_9f5fb4f855'),
            'opened' => __('store::ui.store_auto_1a11b1adc3'),
            'damaged' => __('store::ui.store_auto_8351074517'),
            'unknown' => __('store::ui.store_auto_6621409844'),
        ];
    @endphp
    <div class="z4sf z4-account z4-account-v2" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <main class="z4sf-container z4-account-shell-v2">
            @include('store::storefront.account._alerts')
            <nav class="z4-account-breadcrumb" aria-label="{{ __('store::ui.breadcrumb') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.home') }}</a><span aria-hidden="true">›</span><a href="{{ route('store.account.orders.index') }}">{{ __('store::ui.my-purchases') }}</a><span aria-hidden="true">›</span><span>{{ $order->order_number }}</span>
            </nav>
            <h1 class="z4-account-title">{{ __('store::ui.store-account') }}</h1>

            <div class="z4-account-frame">
                @include('store::storefront.account._sidebar')
                <div class="z4-account-content">
                    <section class="z4-account-section" id="order-progress">
                        <header class="z4-account-section-heading z4-account-order-title">
                            <div><p>{{ __('store::ui.order-details') }}</p><h2>{{ $order->order_number }}</h2><span>{{ __('store::ui.store_auto_6abb4c373d', ['date' => $storeDate->format($order->placed_at, 'M d, Y \a\t H:i')]) }} · {{ $storeDate->countryTimeLabel() }}</span></div>
                            <span class="z4-account-status-badge is-{{ $status->value }}">{{ $status->accountLabel() }}</span>
                        </header>
                        <div class="z4-account-status-message">
                            <span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>
                            <div><strong>{{ $status->accountLabel() }}</strong><p>{{ $status->customerMessage() }}</p></div>
                        </div>
                        @if($status === \Modules\Store\Domain\Orders\OrderStatus::Cancelled && filled($order->cancellation_reason))
                            <div class="z4-account-cancellation-reason" role="note">
                                <span class="material-symbols-outlined" aria-hidden="true">info</span>
                                <div>
                                    <strong>{{ __('store::ui.cancellation-reason') }}</strong>
                                    <p>{{ $order->cancellation_reason }}</p>
                                </div>
                            </div>
                        @endif
                        @if($status === \Modules\Store\Domain\Orders\OrderStatus::Returned && filled($order->return_reason))
                            <div class="z4-account-cancellation-reason" role="note">
                                <span class="material-symbols-outlined" aria-hidden="true">assignment_return</span>
                                <div>
                                    <strong>{{ __('store::ui.store_auto_78d251636f') }}</strong>
                                    <p><b>{{ __('store::ui.store_auto_8937b0570b') }}:</b> {{ $order->return_reason }}</p>
                                    @if(filled($order->return_condition))
                                        <p><b>{{ __('store::ui.store_auto_b893d4c0e3') }}:</b> {{ $returnConditionLabels[$order->return_condition] ?? \Illuminate\Support\Str::headline($order->return_condition) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if($cancellation_request_pending)
                            <div class="z4-account-cancellation-reason" role="status">
                                <span class="material-symbols-outlined" aria-hidden="true">hourglass_top</span>
                                <div>
                                    <strong>{{ __('store::ui.cancellation-request-awaiting-approval') }}</strong>
                                    <p>{{ __('store::ui.your-request-was-sent-to-the-store-administration-the-order-') }}</p>
                                    @if(filled($cancellation_request_reason))<p><b>{{ __('store::ui.your-reason') }}</b> {{ $cancellation_request_reason }}</p>@endif
                                </div>
                            </div>
                        @endif
                        @if(!$cancellation_request_pending && filled($cancellation_rejection_reason))
                            <div class="z4-account-cancellation-reason is-rejected" role="note">
                                <span class="material-symbols-outlined" aria-hidden="true">feedback</span>
                                <div>
                                    <strong>{{ __('store::ui.cancellation-request-rejected') }}</strong>
                                    <p><b>{{ __('store::ui.store-response') }}</b> {{ $cancellation_rejection_reason }}</p>
                                    @if($cancellation_appeal_blocked)
                                        <p>{{ __('store::ui.further-cancellation-appeals-are-not-available-for-this-orde') }}</p>
                                    @else
                                        <p>{{ __('store::ui.you-may-submit-another-cancellation-request-if-needed') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if(!$return_process && $return_request_pending)
                            <div class="z4-account-cancellation-reason" role="status">
                                <span class="material-symbols-outlined" aria-hidden="true">hourglass_top</span>
                                <div>
                                    <strong>{{ __('store::ui.store_auto_688199868b') }}</strong>
                                    <p>{{ __('store::ui.store_auto_aedf41ab80') }}</p>
                                    @if(filled($return_request_condition))<p><b>{{ __('store::ui.store_auto_b893d4c0e3') }}:</b> {{ $returnConditionLabels[$return_request_condition] ?? \Illuminate\Support\Str::headline($return_request_condition) }}</p>@endif
                                    @if(filled($return_request_reason))<p><b>{{ __('store::ui.store_auto_41851fdf5e') }}:</b> {{ $return_request_reason }}</p>@endif
                                </div>
                            </div>
                        @endif
                        @if(!$return_process && !$return_request_pending && filled($return_rejection_reason) && $status === \Modules\Store\Domain\Orders\OrderStatus::Delivered)
                            <div class="z4-account-cancellation-reason is-rejected" role="note">
                                <span class="material-symbols-outlined" aria-hidden="true">feedback</span>
                                <div>
                                    <strong>{{ __('store::ui.store_auto_c5421eb1f7') }}</strong>
                                    <p><b>{{ __('store::ui.store_auto_74f77e353d') }}:</b> {{ $return_rejection_reason }}</p>
                                    <p>{{ __('store::ui.store_auto_8a8b4c53c5') }}</p>
                                </div>
                            </div>
                        @endif
                        <ol class="z4-account-event-timeline">
                            @forelse($timeline as $entry)
                                <li>
                                    <span class="material-symbols-outlined" aria-hidden="true">{{ in_array($entry->type, ['cancellation_rejected', 'return_rejected'], true) ? 'close' : (in_array($entry->status, ['cancelled','returned'], true) ? 'error' : 'check') }}</span>
                                    <div><strong>{{ in_array($entry->type, ['cancellation_requested', 'cancellation_rejected', 'return_requested', 'return_rejected'], true) ? $entry->label : ($entry->status ? \Modules\Store\Domain\Orders\OrderStatus::from($entry->status)->accountLabel() : $entry->label) }}</strong><p>{{ $entry->message }}</p><time>{{ $storeDate->format($entry->created_at, 'M d, Y \a\t H:i') }} · {{ $storeDate->countryTimeLabel() }}</time></div>
                                </li>
                            @empty
                                <li><span class="material-symbols-outlined" aria-hidden="true">check</span><div><strong>{{ $status->accountLabel() }}</strong><p>{{ $status->customerMessage() }}</p><time>{{ $storeDate->format($order->placed_at, 'M d, Y \a\t H:i') }} · {{ $storeDate->countryTimeLabel() }}</time></div></li>
                            @endforelse
                        </ol>
                    </section>

                    @if($return_process)
                        @include('store::storefront.account._return_workflow')
                    @endif

                    <div class="z4-account-detail-grid">
                        <section class="z4-account-section">
                            <header class="z4-account-section-heading is-compact"><h2>{{ __('store::ui.products') }}</h2></header>
                            <div class="z4-account-items">
                                @foreach($items as $item)
                                    <article>
                                        @if($item->image_url)<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($item->image_url) }}" alt="">@else<span class="z4-account-product-placeholder material-symbols-outlined" aria-hidden="true">image</span>@endif
                                        <div><strong>{{ $item->product_name }}</strong><span>{{ $item->variant_name ?: 'Default variant' }} · {{ $item->sku ?: '—' }}</span><span>Quantity: {{ $item->quantity }} · Unit price: {{ $money($item->unit_price_minor) }}</span></div>
                                        <b>{{ $money($item->line_total_minor) }}</b>
                                        <div class="z4-account-product-actions">
                                        @if($item->review)
                                            <section class="z4-account-submitted-review" aria-label="{{ __('store::ui.store_auto_3421ad0097') }}">
                                                <div class="z4-review-stars" aria-label="{{ __('store::ui.store_auto_070398c48c', ['rating' => $item->review->rating]) }}">
                                                    @for($star = 1; $star <= 5; $star++)<span @class(['is-active' => $star <= $item->review->rating]) aria-hidden="true">★</span>@endfor
                                                </div>
                                                <div>
                                                    <strong>{{ $item->review->title ?: __('store::ui.store_auto_3421ad0097') }}</strong>
                                                    <p dir="auto">{{ $item->review->body }}</p>
                                                    <small>{{ __('store::ui.store_auto_5f86ac02ed') }}</small>
                                                </div>
                                            </section>
                                        @elseif($item->can_review)
                                            <details class="z4-account-review-form">
                                                <summary>{{ __('store::ui.store_auto_c31732fda0') }}</summary>
                                                <form method="post" action="{{ route('store.account.orders.items.reviews.store', [$order->public_id, $item->public_id]) }}">
                                                    @csrf
                                                    <fieldset>
                                                        <legend>{{ __('store::ui.store_auto_dda9c06f33') }} *</legend>
                                                        <div class="z4-review-rating-input">
                                                            @for($rating = 5; $rating >= 1; $rating--)
                                                                <input id="review-{{ $item->public_id }}-{{ $rating }}" type="radio" name="rating" value="{{ $rating }}" required>
                                                                <label for="review-{{ $item->public_id }}-{{ $rating }}" title="{{ __('store::ui.store_auto_070398c48c', ['rating' => $rating]) }}">★</label>
                                                            @endfor
                                                        </div>
                                                    </fieldset>
                                                    <label>{{ __('store::ui.store_auto_f8a0aa69f5') }} <span>{{ __('store::ui.store_auto_ebb061953c') }}</span><input type="text" name="title" maxlength="160" value="{{ old('title') }}"></label>
                                                    <label>{{ __('store::ui.store_auto_3421ad0097') }} *<textarea name="body" required maxlength="2000" rows="4">{{ old('body') }}</textarea></label>
                                                    <p>{{ __('store::ui.store_auto_ca6f6399ba') }}</p>
                                                    <button class="z4-account-primary-button" type="submit">{{ __('store::ui.store_auto_c1d9cd26da') }}</button>
                                                </form>
                                            </details>
                                        @endif
                                        @if($can_request_return && $loop->first)
                                            <details class="z4-account-return-form">
                                                <summary>{{ __('store::ui.store_auto_1af342a10e') }}</summary>
                                                <form method="post" action="{{ route('store.account.orders.return-request', $order->public_id) }}">
                                                    @csrf
                                                    <label>
                                                        <span>{{ __('store::ui.store_auto_b893d4c0e3') }} *</span>
                                                        <select name="condition" required>
                                                            <option value="">{{ __('store::ui.store_auto_a724247f69') }}</option>
                                                            @foreach($returnConditionLabels as $value => $label)
                                                                <option value="{{ $value }}" @selected(old('condition') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>{{ __('store::ui.store_auto_8937b0570b') }} *</span>
                                                        <textarea name="reason" required maxlength="2000" rows="5" placeholder="{{ __('store::ui.store_auto_5848435a62') }}">{{ old('reason') }}</textarea>
                                                    </label>
                                                    <label>
                                                        <span>{{ __('store::ui.store_auto_390ee57b81') }} *</span>
                                                        <select name="preferred_resolution" required>
                                                            <option value="refund">{{ __('store::ui.store_auto_76f0ed934d') }}</option>
                                                            <option value="exchange">{{ __('store::ui.store_auto_992374d8e2') }}</option>
                                                            <option value="store_credit">{{ __('store::ui.store_auto_69b90b8712') }}</option>
                                                        </select>
                                                    </label>
                                                    <p>{{ __('store::ui.store_auto_0a71bc37c2') }}</p>
                                                    <button class="z4-account-danger-button" type="submit">{{ __('store::ui.store_auto_444b18ad72') }}</button>
                                                </form>
                                            </details>
                                        @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <dl class="z4-account-totals">
                                <div><dt>{{ __('store::ui.subtotal') }}</dt><dd>{{ $money($order->subtotal_minor) }}</dd></div>
                                <div><dt>{{ $order->coupon_code ? __('store::ui.store_auto_0b7b834704', ['code' => $order->coupon_code]) : __('store::ui.store_auto_104d9898c0') }}</dt><dd>{{ $money($order->discount_minor) }}</dd></div>
                                <div><dt>{{ __('store::ui.delivery') }}</dt><dd>{{ $money($order->shipping_minor) }}</dd></div>
                                <div class="is-total"><dt>{{ __('store::ui.total') }}</dt><dd>{{ $money($order->total_minor) }}</dd></div>
                            </dl>
                        </section>

                        <aside class="z4-account-detail-aside">
                            <section class="z4-account-section"><h2>{{ __('store::ui.delivery-address') }}</h2><address>{{ $deliveryAddress->implode(', ') }}</address><p>{{ $order->customer_phone }}</p>@if($order->shipping_zone_name)<p>Delivery zone: {{ $order->shipping_zone_name }}</p>@endif</section>
                            @if($shipmentDetails->isNotEmpty())
                                <section class="z4-account-section z4-account-shipment-information">
                                    <h2>{{ __('store::ui.shipping-information') }}</h2>
                                    <dl class="z4-account-info-list">
                                        @foreach($shipmentDetails as $label => $value)
                                            <div>
                                                <dt>{{ $label }}</dt>
                                                <dd>
                                                    @if($label === 'Courier phone')
                                                        <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) $value) }}">{{ $value }}</a>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>
                            @elseif(in_array($status, [
                                \Modules\Store\Domain\Orders\OrderStatus::Shipped,
                                \Modules\Store\Domain\Orders\OrderStatus::Delivered,
                                \Modules\Store\Domain\Orders\OrderStatus::Returned,
                            ], true))
                                <section class="z4-account-section z4-account-shipment-information">
                                    <h2>{{ __('store::ui.shipping-information') }}</h2>
                                    <p>{{ __('store::ui.shipment-details-will-appear-here-once-they-are-provided-by-') }}</p>
                                </section>
                            @endif
                            <section class="z4-account-section"><h2>{{ __('store::ui.payment') }}</h2><p><strong>{{ __('store::ui.cash-on-delivery') }}</strong></p><p>{{ $paymentStatus }}</p></section>
                            <section class="z4-account-section z4-account-actions">
                                <form method="post" action="{{ route('store.account.orders.reorder', $order->public_id) }}">@csrf<button class="z4-account-primary-button">{{ __('store::ui.reorder-with-current-prices') }}</button></form>
                                @if($invoice_available)<a class="z4-account-secondary-button" href="{{ route('store.account.orders.invoice', $order->public_id) }}">{{ __('store::ui.download-invoice') }}</a>@else<p>{{ __('store::ui.invoice-is-not-available-for-this-order') }}</p>@endif
                                @if($can_cancel)
                                    <details><summary>{{ __('store::ui.cancel-order') }}</summary><form method="post" action="{{ route('store.account.orders.cancel', $order->public_id) }}">@csrf<textarea name="reason" required maxlength="1000" placeholder="{{ __('store::ui.why-do-you-want-to-cancel') }}"></textarea><button class="z4-account-danger-button">{{ __('store::ui.confirm-cancellation') }}</button></form></details>
                                @elseif($can_request_cancel)
                                    <details><summary>{{ __('store::ui.request-cancellation') }}</summary><form method="post" action="{{ route('store.account.orders.cancellation-request', $order->public_id) }}">@csrf<textarea name="reason" required maxlength="1000" placeholder="{{ __('store::ui.tell-the-store-why-you-need-to-cancel') }}"></textarea><p>{{ __('store::ui.the-order-will-remain-active-while-the-store-administration-') }}</p><button class="z4-account-danger-button">{{ __('store::ui.send-request') }}</button></form></details>
                                @endif
                            </section>
                        </aside>
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-frontend-layout>
