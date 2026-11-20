<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('store::ui.auto_bfc7b93415') }}</title>
    <style>
        body{font:14px/1.45 Arial,sans-serif;color:#171717;margin:32px}.head{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:24px 0}h1,h2{margin:0 0 8px}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}.totals{margin:18px 0 0 auto;width:320px}.totals div{display:flex;justify-content:space-between;padding:5px}.total{font-weight:bold;border-top:2px solid #111}.notes{margin-top:24px;padding:14px;border:1px solid #ddd}@media print{body{margin:10mm}.no-print{display:none}}
    </style>
</head>
<body>
@php($digits = strtoupper($order->currency) === 'JOD' ? 3 : 2)
@php($money = fn($minor) => app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int)$minor, $order->currency))
<button class="no-print" onclick="window.print()">{{ __('store::ui.auto_13dba24862') }}</button>
<header class="head"><div><h1>{{ __('store::ui.auto_8cf99ec090') }}</h1><div>{{ \Illuminate\Support\Carbon::parse($order->placed_at)->format('Y-m-d H:i') }}</div></div><div><strong>{{ strtoupper($order->status) }}</strong><br>{{ __('store::ui.auto_469ded86e4') }}</div></header>
<div class="grid"><section><h2>{{ __('store::ui.auto_ce26601dac') }}</h2><div>{{ $order->customer_name }}</div><div>{{ $order->delivery_phone ?: $order->customer_phone }}</div></section><section><h2>{{ __('store::ui.auto_065ab3a28c') }}</h2><div>{{ collect([$order->governorate,$order->city,$order->area,$order->district,$order->street,$order->building,$order->floor,$order->apartment,$order->address_line_1,$order->landmark])->filter()->unique()->implode('، ') }}</div>@if($order->shipping_zone_name)<div>{{ $order->shipping_zone_name }}</div>@endif @if($order->shipping_estimate)<div>{{ $order->shipping_estimate }}</div>@endif</section></div>
<table><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>{{ __('store::ui.auto_f396b6453f') }}</th><th>{{ __('store::ui.auto_694e8d1f2e') }}</th><th>{{ __('store::ui.auto_6c957f72dc') }}</th><th>{{ __('store::ui.auto_96b0141273') }}</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->variant_name ?: 'Default' }} / {{ $item->sku ?: '—' }}</td><td>{{ $item->quantity }}</td><td>{{ $money($item->unit_price_minor) }}</td><td>{{ $money($item->line_total_minor) }}</td></tr>@endforeach</tbody></table>
<div class="totals"><div><span>{{ __('store::ui.auto_2194aaec8c') }}</span><b>{{ $money($order->subtotal_minor) }}</b></div>@if((int) $order->discount_minor > 0)<div><span>{{ $order->coupon_code ? 'Coupon '.$order->coupon_code : 'Discount' }}</span><b>− {{ $money($order->discount_minor) }}</b></div>@endif @if((int)$order->tax_minor > 0)<div><span>{{ $order->tax_name }}{{ $order->prices_include_tax ? ' (included)' : '' }}</span><b>{{ $money($order->tax_minor) }}</b></div>@endif<div><span>{{ __('store::ui.auto_065ab3a28c') }}</span><b>{{ $money($order->shipping_minor) }}</b></div><div class="total"><span>{{ __('store::ui.auto_96b0141273') }}</span><b>{{ $money($order->total_minor) }}</b></div></div>
@if($order->customer_notes || $order->delivery_notes)<section class="notes"><h2>{{ __('store::ui.auto_7dcca1322b') }}</h2><p>{{ $order->delivery_notes }}</p><p>{{ $order->customer_notes }}</p></section>@endif
</body>
</html>
