<x-app-layout>
@php
    $periodQuery = [
        'period' => $report['period']['preset'],
        'date_from' => $report['period']['from_date'],
        'date_to' => $report['period']['to_date'],
    ];
@endphp
<main class="z4-store-admin z4-store-reports-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._alerts')
        <header class="z4-store-heading">
            <div><span class="z4-store-eyebrow">{{ __('store::ui.auto_14e4343e58') }}</span><h1>{{ __('store::ui.auto_c91c7b93c2') }}</h1><p>{{ __('store::ui.auto_c6088dfad1') }}</p></div>
            <a class="z4-store-btn z4-store-btn-ghost" target="_blank" href="{{ route('admin.plugins.store.reports.print', $periodQuery) }}">{{ __('store::ui.auto_002b7a1722') }}</a>
        </header>

        <form method="get" class="z4-store-filter-bar" data-report-filter>
            <label><span>{{ __('store::ui.auto_1901606ea0') }}</span><select class="z4-store-select" name="period">
                @foreach(['today' => 'Today', 'last7' => 'Last 7 Days', 'last30' => 'Last 30 Days', 'this_month' => 'This Month', 'previous_month' => 'Previous Month', 'custom' => 'Custom Date Range'] as $value => $label)
                    <option value="{{ $value }}" @selected($report['period']['preset'] === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label><span>{{ __('store::ui.auto_5da618e8e4') }}</span><input class="z4-store-input" type="date" name="date_from" value="{{ $report['period']['from_date'] }}"></label>
            <label><span>To</span><input class="z4-store-input" type="date" name="date_to" value="{{ $report['period']['to_date'] }}"></label>
            <button class="z4-store-btn z4-store-btn-primary" data-report-submit>{{ __('store::ui.auto_9639e32cab') }}</button>
        </form>

        <section id="sales" class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>1</span><div><h2>{{ __('store::ui.auto_4253e52d29') }}</h2><p>{{ __('store::ui.auto_126f8ffb68') }}</p></div><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.reports.export', ['type' => 'sales'] + $periodQuery) }}">CSV</a></header>
            <div class="z4-store-kpi-row z4-store-report-kpis">
                <div><small>{{ __('store::ui.auto_6d8e01879e') }}</small><strong>{{ $report['metrics']['sales_formatted'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_183ba2fac0') }}</small><strong>{{ $report['metrics']['realized_orders'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_90f21d3291') }}</small><strong>{{ $report['metrics']['average_order_formatted'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_9d5bf15117') }}</small><strong>{{ $report['metrics']['discounts_formatted'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_e943c4408a') }}</small><strong>{{ $report['metrics']['shipping_formatted'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_4b78ac8eb1') }}</small><strong>{{ $report['metrics']['tax_formatted'] }}</strong></div>
            </div>
            @forelse($report['sales_chart'] as $point)
                <div class="z4-store-chart-row"><span>{{ $point['label'] }}</span><div><i style="width: {{ $point['percent'] }}%"></i></div><strong>{{ $point['sales_formatted'] }}</strong></div>
            @empty
                <div class="z4-store-inline-empty">There are no sales in this period; no empty chart is displayed.</div>
            @endforelse
        </section>

        <section id="orders" class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>2</span><div><h2>{{ __('store::ui.auto_d9e2aa0323') }}</h2><p>{{ __('store::ui.auto_426d4d33a3') }}</p></div><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.reports.export', ['type' => 'orders'] + $periodQuery) }}">CSV</a></header>
            <div class="z4-store-kpi-row">
                <div><small>{{ __('store::ui.auto_d763c74595') }}</small><strong>{{ $report['metrics']['orders'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_a149e85a44') }}</small><strong>{{ $report['metrics']['cancelled_orders'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_fdfac28b5a') }}</small><strong>{{ $report['metrics']['returned_orders'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_33ceb220a0') }}</small><strong>{{ number_format($report['metrics']['completion_rate'], 1) }}%</strong></div>
            </div>
            <div class="z4-store-table-wrap"><table class="z4-store-table"><thead><tr><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_7442e29d7d') }}</th><th>{{ __('store::ui.auto_5a95a425f7') }}</th></tr></thead><tbody>
                @foreach($report['status_counts'] as $status => $count)
                    <tr><td>{{ ucfirst($status) }}</td><td>{{ $count }}</td><td>{{ $report['metrics']['orders'] ? number_format(($count / $report['metrics']['orders']) * 100, 1) : '0.0' }}%</td></tr>
                @endforeach
            </tbody></table></div>
        </section>

        <section id="products" class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>3</span><div><h2>{{ __('store::ui.auto_dda066e88e') }}</h2><p>{{ __('store::ui.auto_60fd7982b6') }}</p></div><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.reports.export', ['type' => 'products'] + $periodQuery) }}">CSV</a></header>
            <div class="z4-store-overview-grid">
                <div><h3>{{ __('store::ui.auto_4ab4766100') }}</h3>@forelse($report['best_products'] as $product)<div class="z4-store-report-line"><span><strong>{{ $product['name'] }}</strong><small>{{ __('store::ui.auto_e4d34a74ea') }}</small></span><b>{{ $product['sales_formatted'] }}</b></div>@empty<div class="z4-store-inline-empty">{{ __('store::ui.auto_8a059ade45') }}</div>@endforelse</div>
                <div><h3>{{ __('store::ui.auto_baeba823f7') }}</h3>@forelse($report['least_products'] as $product)<div class="z4-store-report-line"><span><strong>{{ $product['name'] }}</strong><small>{{ __('store::ui.auto_44d3e13ac9') }}</small></span><b>{{ $product['sales_formatted'] }}</b></div>@empty<div class="z4-store-inline-empty">{{ __('store::ui.auto_8a059ade45') }}</div>@endforelse</div>
            </div>
            <h3>{{ __('store::ui.auto_d092b4b85d') }}</h3>
            <div class="z4-store-table-wrap"><table class="z4-store-table"><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>{{ __('store::ui.auto_492f18b608') }}</th><th>SKU</th><th>{{ __('store::ui.auto_e5771a362d') }}</th><th>{{ __('store::ui.auto_11ff9f68af') }}</th></tr></thead><tbody>
                @forelse($report['best_variants'] as $variant)<tr><td>{{ $variant['product_name'] }}</td><td>{{ $variant['variant_name'] ?: 'Default' }}</td><td>{{ $variant['sku'] }}</td><td>{{ $variant['quantity'] }}</td><td>{{ $variant['sales_formatted'] }}</td></tr>@empty<tr><td colspan="5">{{ __('store::ui.auto_ed77bae26a') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section id="inventory" class="z4-store-form-card">
            <header class="z4-store-section-heading"><span>4</span><div><h2>{{ __('store::ui.auto_c52210499d') }}</h2><p>{{ __('store::ui.auto_083d734602') }}</p></div><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.reports.export', ['type' => 'inventory'] + $periodQuery) }}">CSV</a></header>
            <div class="z4-store-kpi-row">
                <div><small>{{ __('store::ui.auto_011b75e510') }}</small><strong>{{ $report['metrics']['low_stock_products'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_e990b6285e') }}</small><strong>{{ $report['metrics']['out_of_stock_products'] }}</strong></div>
                <div><small>{{ __('store::ui.auto_452ed96ef2') }}</small><strong>{{ $report['metrics']['inventory_quantity'] }}</strong></div>
            </div>
            <div class="z4-store-table-wrap"><table class="z4-store-table"><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>SKU</th><th>{{ __('store::ui.auto_d42b96c033') }}</th><th>{{ __('store::ui.auto_942d4e37dd') }}</th><th>{{ __('store::ui.auto_78945de8de') }}</th><th>{{ __('store::ui.auto_2a63f55598') }}</th><th></th></tr></thead><tbody>
                @forelse($report['inventory'] as $item)<tr><td>{{ $item['product_name'] }}</td><td>{{ $item['sku'] }}</td><td>{{ $item['on_hand'] }}</td><td>{{ $item['reserved'] }}</td><td>{{ $item['available'] }}</td><td>{{ $item['safety_stock'] }}</td><td><a href="{{ route('admin.plugins.store.inventory.index', ['item' => $item['public_id']]) }}">{{ __('store::ui.auto_34e34c43ec') }}</a></td></tr>@empty<tr><td colspan="7">{{ __('store::ui.auto_ca09887e18') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</main>
<style>
.z4-store-reports-page .z4-store-heading {
    margin-bottom: 24px;
}

.z4-store-reports-page .z4-store-filter-bar {
    margin-bottom: 18px;
}

.z4-store-reports-page .z4-store-form-card {
    padding: 22px;
}

.z4-store-reports-page .z4-store-form-card + .z4-store-form-card {
    margin-top: 18px;
}

.z4-store-reports-page .z4-store-section-heading {
    margin-bottom: 18px;
}

.z4-store-reports-page .z4-store-section-heading > div {
    min-width: 0;
}

.z4-store-reports-page .z4-store-section-heading > .z4-store-btn {
    align-self: flex-start;
    flex: 0 0 auto;
    margin-inline-start: auto;
}

.z4-store-reports-page .z4-store-kpi-row {
    gap: 12px;
    margin-bottom: 18px;
}

.z4-store-reports-page .z4-store-kpi-row > div {
    padding: 14px;
}

.z4-store-reports-page .z4-store-kpi-row strong {
    font-size: 22px;
    margin-top: 5px;
}

.z4-store-reports-page .z4-store-overview-grid {
    gap: 18px;
    margin-bottom: 18px;
}

.z4-store-reports-page .z4-store-inline-empty {
    margin-top: 14px;
    padding: 18px;
}

.z4-store-reports-page .z4-store-table-wrap {
    max-width: 100%;
    overflow: hidden;
}

.z4-store-reports-page .z4-store-table {
    min-width: 0;
    table-layout: fixed;
}

.z4-store-reports-page .z4-store-table th,
.z4-store-reports-page .z4-store-table td {
    overflow-wrap: anywhere;
    padding: 13px 12px;
}

.z4-store-reports-page .z4-store-table th {
    font-size: 11px;
}

.z4-store-reports-page .z4-store-table td {
    font-size: 12px;
}

.z4-store-reports-page #orders .z4-store-table th:first-child {
    width: 44%;
}

.z4-store-reports-page #products .z4-store-table th:first-child {
    width: 30%;
}

.z4-store-reports-page #products .z4-store-table th:nth-child(2) {
    width: 24%;
}

.z4-store-reports-page #inventory .z4-store-table th:first-child {
    width: 27%;
}

.z4-store-reports-page #inventory .z4-store-table th:nth-child(2) {
    width: 19%;
}

.z4-store-reports-page #inventory .z4-store-table th:last-child {
    width: 8%;
}

@media (min-width: 721px) {
    .z4-store-reports-page #inventory .z4-store-kpi-row {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.z4-store-report-kpis {
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    background: #b00000;
    border-radius: 99px;
    display: block;
    height: 100%;
}

.z4-store-report-line {
    align-items: center;
    border-bottom: 1px solid #ececf2;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    padding: 12px 0;
}

.z4-store-report-line span {
    display: flex;
    flex-direction: column;
}

@media (max-width: 700px) {
    .z4-store-reports-page .z4-store-form-card {
        padding: 18px;
    }

    .z4-store-reports-page .z4-store-section-heading {
        flex-wrap: wrap;
    }

    .z4-store-reports-page .z4-store-table-wrap {
        overflow-x: auto;
    }

    .z4-store-reports-page .z4-store-table {
        min-width: 700px;
    }

    .z4-store-report-kpis {
        grid-template-columns: 1fr 1fr;
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
