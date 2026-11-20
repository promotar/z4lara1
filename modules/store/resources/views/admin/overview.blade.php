<x-app-layout>
@php
    $periodQuery = [
        'period' => $report['period']['preset'],
        'date_from' => $report['period']['from_date'],
        'date_to' => $report['period']['to_date'],
    ];
    $orderDates = ['date_from' => $report['period']['from_date'], 'date_to' => $report['period']['to_date']];
@endphp
<main class="z4-store-admin z4-store-overview-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_5f108f1952') }}</span>
                <h1>{{ __('store::ui.auto_8cb37eddea') }}</h1>
                <p>{{ __('store::ui.auto_f1a0c1d820') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                @can('store.templates.manage')
                    <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.templates.index') }}">{{ __('store::ui.auto_08cbbb9145') }}</a>
                @endcan
                @can('store.settings.view')
                    <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.settings.index') }}">{{ __('store::ui.auto_0f41c0a69c') }}</a>
                @endcan
                @can('store.orders.reports')
                    <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.reports.index', $periodQuery) }}">{{ __('store::ui.auto_258b30ca1d') }}</a>
                @endcan
            </div>
        </header>

        <form method="get" class="z4-store-filter-bar" data-report-filter>
            <label><span>{{ __('store::ui.auto_1901606ea0') }}</span><select class="z4-store-select" name="period">
                @foreach(['today' => __('store::ui.admin_range_today'), 'last7' => __('store::ui.admin_range_last7'), 'last30' => __('store::ui.admin_range_last30'), 'this_month' => __('store::ui.admin_range_this_month'), 'previous_month' => __('store::ui.admin_range_prev_month'), 'custom' => __('store::ui.admin_range_custom')] as $value => $label)
                    <option value="{{ $value }}" @selected($report['period']['preset'] === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label><span>{{ __('store::ui.auto_5da618e8e4') }}</span><input class="z4-store-input" type="date" name="date_from" value="{{ $report['period']['from_date'] }}"></label>
            <label><span>To</span><input class="z4-store-input" type="date" name="date_to" value="{{ $report['period']['to_date'] }}"></label>
            <button class="z4-store-btn z4-store-btn-primary" data-report-submit>{{ __('store::ui.auto_9639e32cab') }}</button>
        </form>

        <section class="z4-store-stat-grid z4-store-stat-grid-six" aria-label="{{ __('store::ui.auto_70d4014071') }}">
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.orders.index', ['status' => 'new'] + $orderDates) }}"><span class="z4-store-stat-icon is-purple">N</span><div><small>{{ __('store::ui.auto_9fc1013331') }}</small><strong>{{ $report['metrics']['new_orders'] }}</strong><span>{{ __('store::ui.auto_c3d4b3f321') }}</span></div></a>
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.orders.index', ['status' => 'processing'] + $orderDates) }}"><span class="z4-store-stat-icon is-orange">P</span><div><small>{{ __('store::ui.auto_4c78b6fad1') }}</small><strong>{{ $report['metrics']['processing_orders'] }}</strong><span>{{ __('store::ui.auto_1de5dd2ff8') }}</span></div></a>
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.orders.index', ['status' => 'delivered'] + $orderDates) }}"><span class="z4-store-stat-icon is-green">D</span><div><small>{{ __('store::ui.auto_ab08d3285f') }}</small><strong>{{ $report['metrics']['delivered_orders'] }}</strong><span>{{ __('store::ui.auto_a43a951820') }}</span></div></a>
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.reports.index', $periodQuery) }}#sales"><span class="z4-store-stat-icon is-blue">S</span><div><small>{{ __('store::ui.auto_9bf731e0f9') }}</small><strong>{{ $report['metrics']['sales_formatted'] }}</strong><span>{{ __('store::ui.auto_0f75719f87') }}</span></div></a>
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.reports.index', $periodQuery) }}#sales"><span class="z4-store-stat-icon is-purple">A</span><div><small>{{ __('store::ui.auto_ffbb5322a3') }}</small><strong>{{ $report['metrics']['average_order_formatted'] }}</strong><span>{{ __('store::ui.auto_128238f89f') }}</span></div></a>
            <a class="z4-store-stat-card" href="{{ route('admin.plugins.store.inventory.index') }}"><span class="z4-store-stat-icon is-orange">!</span><div><small>{{ __('store::ui.auto_04f711e76a') }}</small><strong>{{ $report['metrics']['low_stock_products'] }}</strong><span>{{ __('store::ui.auto_3469f8108f') }}</span></div></a>
        </section>

        <div class="z4-store-overview-grid">
            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>↗</span><div><h2>{{ __('store::ui.auto_6ca3e684f0') }}</h2><p>{{ __('store::ui.auto_5a0701e1f8') }}</p></div></header>
                @forelse($report['sales_chart'] as $point)
                    <div class="z4-store-chart-row"><span>{{ $point['label'] }}</span><div><i style="width: {{ $point['percent'] }}%"></i></div><strong>{{ $point['sales_formatted'] }}</strong></div>
                @empty
                    <div class="z4-store-inline-empty">{{ __('store::ui.auto_e85ff2e0c1') }}</div>
                @endforelse
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>!</span><div><h2>{{ __('store::ui.auto_011b75e510') }}</h2><p>{{ __('store::ui.auto_f0ccdf18eb') }}</p></div></header>
                @forelse($report['low_stock'] as $item)
                    <a class="z4-store-report-line" href="{{ route('admin.plugins.store.inventory.index', ['item' => $item['public_id']]) }}"><span><strong>{{ $item['product_name'] }}</strong><small>{{ $item['sku'] ?: 'No SKU' }}</small></span><b>{{ $item['available'] }}</b></a>
                @empty
                    <div class="z4-store-inline-empty">{{ __('store::ui.auto_377924559a') }}</div>
                @endforelse
            </section>
        </div>

        <section class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>O</span><div><h2>{{ __('store::ui.auto_fd3458547e') }}</h2><p>{{ __('store::ui.auto_acbe42869c') }}</p></div></header>
            <div class="z4-store-table-wrap"><table class="z4-store-table"><thead><tr><th>{{ __('store::ui.auto_a240fa2792') }}</th><th>{{ __('store::ui.auto_ce26601dac') }}</th><th>{{ __('store::ui.auto_44749712db') }}</th><th>{{ __('store::ui.auto_96b0141273') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th></th></tr></thead><tbody>
                @forelse($report['recent_orders'] as $order)
                    <tr><td><strong>{{ $order['order_number'] }}</strong></td><td>{{ $order['customer_name'] }}</td><td>{{ $order['placed_at'] }}</td><td>{{ $order['total_formatted'] }}</td><td>{{ ucfirst($order['status']) }}</td><td><a href="{{ route('admin.plugins.store.orders.show', $order['public_id']) }}">{{ __('store::ui.auto_4351cfebe4') }}</a></td></tr>
                @empty
                    <tr><td colspan="6">{{ __('store::ui.auto_6e2a37f7a9') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>★</span><div><h2>{{ __('store::ui.auto_4ab4766100') }}</h2><p>{{ __('store::ui.auto_e1a9ca4704') }}</p></div></header>
            <div class="z4-store-report-products">
                @forelse($report['best_products'] as $product)
                    @php($productImageUrl = app(\Modules\Store\Application\Media\StoreMediaUrl::class)->normalize($product['image_url']))
                    <a href="{{ route('admin.plugins.store.products.view', $product['public_id']) }}">
                        @if($productImageUrl)<img src="{{ $productImageUrl }}" alt="">@endif
                        <span><strong>{{ $product['name'] }}</strong><small>{{ __('store::ui.auto_fa9d3cbee3') }}</small></span><b>{{ $product['sales_formatted'] }}</b>
                    </a>
                @empty
                    <div class="z4-store-inline-empty">{{ __('store::ui.auto_6fc5f366ac') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</main>
<style>
.z4-store-overview-page .z4-store-form-card {
    padding: 22px;
}

.z4-store-overview-page .z4-store-form-card + .z4-store-form-card {
    margin-top: 18px;
}

.z4-store-overview-page .z4-store-section-heading {
    margin-bottom: 18px;
}

.z4-store-overview-page .z4-store-overview-grid {
    gap: 18px;
    margin-bottom: 18px;
}

.z4-store-overview-page .z4-store-inline-empty {
    margin-top: 14px;
    padding: 18px;
}

.z4-store-overview-page .z4-store-table-wrap {
    max-width: 100%;
    overflow: hidden;
}

.z4-store-overview-page .z4-store-table {
    min-width: 0;
    table-layout: fixed;
}

.z4-store-overview-page .z4-store-table th,
.z4-store-overview-page .z4-store-table td {
    overflow-wrap: anywhere;
    padding: 13px 12px;
}

.z4-store-overview-page .z4-store-table th {
    font-size: 11px;
}

.z4-store-overview-page .z4-store-table td {
    font-size: 12px;
}

.z4-store-overview-page .z4-store-table th:nth-child(1) {
    width: 24%;
}

.z4-store-overview-page .z4-store-table th:nth-child(2) {
    width: 20%;
}

.z4-store-overview-page .z4-store-table th:nth-child(3) {
    width: 19%;
}

.z4-store-overview-page .z4-store-table th:nth-child(4) {
    width: 15%;
}

.z4-store-overview-page .z4-store-table th:nth-child(5) {
    width: 14%;
}

.z4-store-overview-page .z4-store-table th:nth-child(6) {
    width: 8%;
}

.z4-store-stat-grid-six {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.z4-store-stat-card {
    color: inherit;
    text-decoration: none;
}

.z4-store-chart-row {
    align-items: center;
    display: grid;
    gap: 12px;
    grid-template-columns: 90px 1fr 130px;
    margin: 12px 0;
}

.z4-store-chart-row > div {
    background: #edf0f5;
    border-radius: 99px;
    height: 12px;
    overflow: hidden;
}

.z4-store-chart-row i {
    background: #5f45d6;
    border-radius: 99px;
    display: block;
    height: 100%;
}

.z4-store-report-line,
.z4-store-report-products > a {
    align-items: center;
    border-bottom: 1px solid #ececf2;
    color: inherit;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    padding: 12px 0;
    text-decoration: none;
}

.z4-store-report-line span,
.z4-store-report-products span {
    display: flex;
    flex-direction: column;
}

.z4-store-report-products img {
    border-radius: 8px;
    height: 48px;
    object-fit: cover;
    width: 48px;
}

.z4-store-report-products > a {
    justify-content: flex-start;
}

.z4-store-report-products > a b {
    margin-inline-start: auto;
}

@media (max-width: 900px) {
    .z4-store-stat-grid-six {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .z4-store-overview-page .z4-store-form-card {
        padding: 18px;
    }

    .z4-store-overview-page .z4-store-table-wrap {
        overflow-x: auto;
    }

    .z4-store-overview-page .z4-store-table {
        min-width: 760px;
    }
}

@media (max-width: 620px) {
    .z4-store-stat-grid-six {
        grid-template-columns: 1fr;
    }

    .z4-store-chart-row {
        grid-template-columns: 70px 1fr;
    }

    .z4-store-chart-row strong {
        grid-column: 2;
    }
}
</style>
<script>
(() => {
    const form = document.querySelector('[data-report-filter]');
    if (!form) return;

    const period = form.elements.period;
    const dates = [form.elements.date_from, form.elements.date_to].filter(Boolean);
    dates.forEach((input) => input.addEventListener('change', () => {
        period.value = 'custom';
    }));

    form.addEventListener('submit', () => {
        if (period.value !== 'custom') {
            dates.forEach((input) => {
                input.disabled = true;
            });
        }

        const button = form.querySelector('[data-report-submit]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Loading report...';
        }
    });
})();
</script>
</x-app-layout>
