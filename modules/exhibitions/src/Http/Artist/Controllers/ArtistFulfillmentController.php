<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;

final class ArtistFulfillmentController
{
    public function __construct(private readonly ArtistDashboardService $dashboard) {}

    public function index(Request $r): View
    {
        $user = (int) $r->user()->id;
        if (! Schema::hasTable('exhibition_settlements')) {
            $rows = DB::table('exhibition_artworks')->whereRaw('1 = 0')->paginate(30);
            $totals = collect();

            return view('exhibitions::artist.fulfillment.index', ['rows' => $rows, 'totals' => $totals] + $this->nav($user));
        }

        $base = $this->salesQuery($user);
        $rowsQuery = (clone $base)
            ->select(
                'f.public_id',
                'f.state as fulfillment_state',
                's.public_id as settlement_public_id',
                's.state as settlement_state',
                's.currency',
                's.scale',
                's.sale_price_minor',
                's.platform_commission_minor',
                's.artist_net_minor',
                'a.title',
                DB::raw($this->orderStateExpression().' as order_state'),
                DB::raw("case when s.state = 'reversed' then 'refunded' when s.payment_collected_at is not null then 'paid' else 'pending' end as payment_status"),
                's.created_at as sold_at',
            );
        $rows = $rowsQuery
            ->orderByDesc('sold_at')
            ->paginate(30)
            ->withQueryString();
        $totals = (clone $base)
            ->selectRaw('s.currency, s.scale, COUNT(*) as sales_count, SUM(s.sale_price_minor) as gross_minor, SUM(s.artist_net_minor) as net_minor')
            ->groupBy('s.currency', 's.scale')
            ->orderBy('s.currency')
            ->get();

        return view('exhibitions::artist.fulfillment.index', ['rows' => $rows, 'totals' => $totals] + $this->nav($user));
    }

    public function show(Request $r, string $f): View
    {
        $user = (int) $r->user()->id;
        $rowQuery = $this->salesQuery($user)
            ->where('f.public_id', $f)
            ->select(
                'f.public_id',
                'f.state as fulfillment_state',
                's.public_id as settlement_public_id',
                's.state as settlement_state',
                's.currency',
                's.scale',
                's.sale_price_minor',
                's.order_discount_minor',
                's.platform_commission_minor',
                's.payment_cost_minor',
                's.packaging_cost_minor',
                's.shipping_cost_minor',
                's.insurance_minor',
                's.tax_minor',
                's.refund_minor',
                's.adjustment_minor',
                's.artist_net_minor',
                'a.title',
                DB::raw($this->orderStateExpression().' as order_state'),
                DB::raw("case when s.state = 'reversed' then 'refunded' when s.payment_collected_at is not null then 'paid' else 'pending' end as payment_status"),
                's.created_at as sold_at',
            );
        $row = $rowQuery->first() ?? abort(404);

        return view('exhibitions::artist.fulfillment.show', ['sale' => $row] + $this->nav($user));
    }

    private function nav(int $u): array
    {
        $o = $this->dashboard->overview($u);

        return ['context' => $o['context'], 'navCounts' => $this->dashboard->navigationCounts($u), 'activeTab' => 'sales'];
    }

    private function salesQuery(int $artist)
    {
        return DB::table('exhibition_settlements as s')
            ->join('exhibition_fulfillments as f', 'f.id', '=', 's.fulfillment_id')
            ->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')
            ->where('s.artist_user_id', $artist);
    }

    private function orderStateExpression(): string
    {
        return "case
            when f.state = 'store_reserved' then 'new'
            when f.state in ('sale_confirmed', 'artist_notification_sent') then 'confirmed'
            when f.state = 'preparing' then 'processing'
            when f.state in ('ready_for_pickup', 'collected', 'quality_inspection', 'ready_for_shipment') then 'ready'
            when f.state = 'return_requested' then 'returned'
            else f.state
        end";
    }
}
