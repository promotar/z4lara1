<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
<head><meta charset="utf-8"><title>{{ __('store::ui.auto_bed933fbb0') }}</title>
<style>
body{font-family:Arial,sans-serif;color:#182033;margin:32px}h1,h2{margin-bottom:6px}small{color:#667085}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:24px 0}.summary div{border:1px solid #d9dee8;padding:14px}.summary strong{display:block;font-size:20px;margin-top:6px}table{width:100%;border-collapse:collapse;margin:12px 0 28px}th,td{border-bottom:1px solid #d9dee8;text-align:left;padding:8px;font-size:12px}@media print{button{display:none}} 
</style></head>
<body>
<button onclick="window.print()">{{ __('store::ui.auto_13dba24862') }}</button>
<h1>{{ __('store::ui.auto_07bad90d75') }}</h1>
<small>{{ $report['period']['label'] }} · {{ $report['period']['from_date'] }} to {{ $report['period']['to_date'] }}</small>
<div class="summary">
    <div><small>{{ __('store::ui.auto_a42c22003f') }}</small><strong>{{ $report['metrics']['sales_formatted'] }}</strong></div>
    <div><small>{{ __('store::ui.auto_183ba2fac0') }}</small><strong>{{ $report['metrics']['realized_orders'] }}</strong></div>
    <div><small>{{ __('store::ui.auto_90f21d3291') }}</small><strong>{{ $report['metrics']['average_order_formatted'] }}</strong></div>
    <div><small>{{ __('store::ui.auto_9d5bf15117') }}</small><strong>{{ $report['metrics']['discounts_formatted'] }}</strong></div>
    <div><small>{{ __('store::ui.auto_e943c4408a') }}</small><strong>{{ $report['metrics']['shipping_formatted'] }}</strong></div>
    <div><small>{{ __('store::ui.auto_4b78ac8eb1') }}</small><strong>{{ $report['metrics']['tax_formatted'] }}</strong></div>
</div>
<h2>{{ __('store::ui.auto_c5924d9d61') }}</h2>
<table><thead><tr><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_7442e29d7d') }}</th></tr></thead><tbody>@foreach($report['status_counts'] as $status => $count)<tr><td>{{ ucfirst($status) }}</td><td>{{ $count }}</td></tr>@endforeach</tbody></table>
<h2>{{ __('store::ui.auto_4ab4766100') }}</h2>
<table><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>{{ __('store::ui.auto_e5771a362d') }}</th><th>{{ __('store::ui.auto_11ff9f68af') }}</th></tr></thead><tbody>@forelse($report['best_products'] as $product)<tr><td>{{ $product['name'] }}</td><td>{{ $product['quantity'] }}</td><td>{{ $product['sales_formatted'] }}</td></tr>@empty<tr><td colspan="3">{{ __('store::ui.auto_8a059ade45') }}</td></tr>@endforelse</tbody></table>
<h2>{{ __('store::ui.auto_011b75e510') }}</h2>
<table><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>SKU</th><th>{{ __('store::ui.auto_78945de8de') }}</th><th>{{ __('store::ui.auto_2a63f55598') }}</th></tr></thead><tbody>@forelse($report['low_stock'] as $item)<tr><td>{{ $item['product_name'] }}</td><td>{{ $item['sku'] }}</td><td>{{ $item['available'] }}</td><td>{{ $item['safety_stock'] }}</td></tr>@empty<tr><td colspan="4">{{ __('store::ui.auto_6d9cd24aef') }}</td></tr>@endforelse</tbody></table>
</body></html>
