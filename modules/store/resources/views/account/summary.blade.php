<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('store::ui.auto_fdb0c388de') }}</h2>
            <p>{{ __('store::ui.auto_ab5e2b985e') }}</p>
        </div>
        <div class="z4-account-store-actions">
            @isset($artworksUrl)
                <a
                    class="z4-account-store-icon-action"
                    href="{{ $artworksUrl }}"
                    aria-label="{{ __('store::ui.auto_034cf9756f') }}"
                    title="{{ __('store::ui.auto_034cf9756f') }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="8.5" cy="9" r="1.5" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M5.5 17L10 12.5L13 15.5L15 13.5L18.5 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            @endisset

            @isset($virtualExhibitionUrl)
                <a
                    class="z4-account-store-icon-action"
                    href="{{ $virtualExhibitionUrl }}"
                    aria-label="{{ __('store::ui.auto_8c7cdc2ce6') }}"
                    title="{{ __('store::ui.auto_8c7cdc2ce6') }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 20V6.5L12 3L20 6.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 7L12 10.5L20 7M12 10.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 13.5H9.5V16H7V13.5ZM14.5 13.5H17V16H14.5V13.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </a>
            @endisset

            <a class="account-section-action" href="{{ $ordersUrl }}">{{ __('store::ui.auto_2309d9606f') }}</a>
        </div>
    </div>

    <div class="account-item-list">
        @forelse ($orders as $order)
            <a class="account-item" href="{{ route('store.account.orders.show', $order->public_id) }}">
                <span>
                    <strong>{{ $order->order_number }}</strong>
                    <small>{{ $order->placed_at ? \Illuminate\Support\Carbon::parse($order->placed_at)->format('d M Y') : __('store::ui.account_date_unavailable') }}</small>
                </span>
                <span>{{ \Modules\Store\Domain\Orders\OrderStatus::tryFrom($order->status)?->label() ?? ucfirst($order->status) }} · {{ $order->total_formatted }}</span>
            </a>
        @empty
            <p class="account-empty-state">{{ __('store::ui.auto_3774d95617') }}</p>
        @endforelse
    </div>
</section>
