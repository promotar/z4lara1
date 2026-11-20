<x-frontend-layout>
    @php($storeDate = app(\Modules\Store\Application\Settings\StoreDateTimeFormatter::class))
    <div class="z4sf z4-account z4-account-v2" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <main class="z4sf-container z4-account-shell-v2">
            @include('store::storefront.account._alerts')
            <nav class="z4-account-breadcrumb" aria-label="{{ __('store::ui.breadcrumb') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.home') }}</a><span aria-hidden="true">›</span><span>{{ __('store::ui.store-account') }}</span>
            </nav>
            <h1 class="z4-account-title">{{ __('store::ui.store-account') }}</h1>

            <div class="z4-account-frame">
                @include('store::storefront.account._sidebar')
                <div class="z4-account-content">
                    <section class="z4-account-section z4-account-purchases">
                        <header class="z4-account-section-heading">
                            <div>
                                <h2>{{ __('store::ui.my-purchases') }}</h2>
                                @if($orders->total() > 0)
                                    <span>{{ $orders->total() }} {{ \Illuminate\Support\Str::plural('order', $orders->total()) }}</span>
                                @endif
                            </div>
                            <a class="z4-account-primary-button" href="{{ route('store.index') }}">{{ __('store::ui.continue-shopping') }}</a>
                        </header>

                        @if($orders->isEmpty())
                            <div class="z4-account-empty-v2">
                                <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
                                <strong>{{ __('store::ui.no-purchases-yet') }}</strong>
                                <p>{{ __('store::ui.your-orders-will-appear-here-after-checkout') }}</p>
                                <a class="z4-account-primary-button" href="{{ route('store.index') }}">{{ __('store::ui.start-shopping') }}</a>
                            </div>
                        @else
                            <div class="z4-account-orders-list" aria-label="{{ __('store::ui.all-orders') }}">
                                @foreach($orders as $order)
                                    @php($orderStatus = \Modules\Store\Domain\Orders\OrderStatus::from($order->status))
                                    <article>
                                        <div>
                                            <small>{{ __('store::ui.order') }}</small>
                                            <strong>{{ $order->order_number }}</strong>
                                        </div>
                                        <div>
                                            <small>{{ __('store::ui.date') }}</small>
                                            <strong>{{ $storeDate->format($order->placed_at, 'M d, Y') }}</strong>
                                        </div>
                                        <div>
                                            <small>{{ __('store::ui.items') }}</small>
                                            <strong>{{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}</strong>
                                        </div>
                                        <div>
                                            <small>{{ __('store::ui.total') }}</small>
                                            <strong>{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $order->total_minor, $order->currency) }}</strong>
                                        </div>
                                        <div>
                                            <small>{{ __('store::ui.status') }}</small>
                                            <span class="z4-account-status-badge is-{{ $orderStatus->value }}">{{ $orderStatus->accountLabel() }}</span>
                                        </div>
                                        <a class="z4-account-order-details-link" href="{{ route('store.account.orders.show', $order->public_id) }}">
                                            {{ __('store::ui.view-details') }} <span aria-hidden="true">›</span>
                                        </a>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if($orders->hasPages())
                            <x-platform-pagination :paginator="$orders" />
                        @endif
                    </section>
                </div>
            </div>
        </main>
    </div>
</x-frontend-layout>
