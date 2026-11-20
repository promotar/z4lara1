<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Disputes\DisputeCaseService;
use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Modules\Exhibition\Application\Security\SpreadsheetCellSanitizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtistEarningsController extends Controller
{
    public function __construct(
        private readonly ArtistDashboardAccess $access,
        private readonly ArtistDashboardService $dashboard,
        private readonly ArtistSettlementService $settlements,
        private readonly DisputeCaseService $disputes,
    ) {}

    public function index(Request $r): View
    {
        $user = (int) $r->user()->id;
        $context = $this->access->context($user);
        $navCounts = $this->dashboard->navigationCounts($user);
        $activeTab = 'earnings';
        if (! Schema::hasTable('exhibition_settlements')) {
            $rows = DB::table('exhibition_artworks')->whereRaw('1=0')->paginate(30);
            $totals = array_fill_keys(['gross', 'commission', 'deductions', 'pending', 'eligible', 'settled'], 0);

            return view('exhibitions::artist.dashboard.earnings', compact('rows', 'totals', 'context', 'navCounts', 'activeTab'));
        }$rows = $this->query($user)->orderByDesc('s.created_at')->paginate(30);
        $base = DB::table('exhibition_settlements')->where('artist_user_id', $user);
        $totals = ['gross' => (int) (clone $base)->sum('sale_price_minor'), 'commission' => (int) (clone $base)->sum('platform_commission_minor'), 'deductions' => (int) (clone $base)->selectRaw('COALESCE(SUM(order_discount_minor+payment_cost_minor+packaging_cost_minor+insurance_minor+refund_minor),0) v')->value('v'), 'pending' => (int) (clone $base)->whereNotIn('state', ['paid', 'reversed'])->sum('artist_net_minor'), 'eligible' => (int) (clone $base)->whereIn('state', ['eligible_for_settlement', 'payout_requested', 'approved', 'scheduled_for_payment', 'payment_sent'])->sum('artist_net_minor'), 'settled' => (int) (clone $base)->where('state', 'paid')->sum('artist_net_minor')];

        return view('exhibitions::artist.dashboard.earnings', compact('rows', 'totals', 'context', 'navCounts', 'activeTab'));
    }

    public function show(Request $r, string $s): View
    {
        $settlement = $this->query((int) $r->user()->id)->where('s.public_id', $s)->first() ?? abort(404);
        $ledger = DB::table('exhibition_settlement_ledger_entries')->where('settlement_id', $settlement->id)->orderBy('occurred_at')->get();
        $events = DB::table('exhibition_settlement_events')->where('settlement_id', $settlement->id)->orderBy('occurred_at')->get();
        $payout = DB::table('exhibition_payout_profiles as payouts')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'payouts.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('applications.core_user_id', $r->user()->id)
            ->select('payouts.method', 'payouts.masked_identifier', 'payouts.verified_at')
            ->first();
        $openCase = Schema::hasTable('exhibition_dispute_cases')
            ? DB::table('exhibition_dispute_cases')
                ->where('settlement_id', $settlement->id)
                ->where('type', 'payout_not_received')
                ->whereNotIn('state', ['resolved', 'closed'])
                ->first()
            : null;

        return view('exhibitions::artist.earnings.show', compact('settlement', 'ledger', 'events', 'payout', 'openCase'));
    }

    public function requestPayout(Request $request, string $settlement): RedirectResponse
    {
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        try {
            $this->settlements->requestPayout($settlement, (int) $request->user()->id, $validated['idempotency_key']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payout' => $exception->getMessage()]);
        }

        return back()->with('status', 'Your payout request was sent to the administration for review.');
    }

    public function confirmReceipt(Request $request, string $settlement): RedirectResponse
    {
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        try {
            $this->settlements->confirmReceipt($settlement, (int) $request->user()->id, $validated['idempotency_key']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payout' => $exception->getMessage()]);
        }

        return back()->with('status', 'Payment receipt confirmed.');
    }

    public function reportReceiptIssue(Request $request, string $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string', 'min:10', 'max:4000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $row = $this->query((int) $request->user()->id)->where('s.public_id', $settlement)->first() ?? abort(404);
        if ($row->state !== 'payment_sent') {
            throw ValidationException::withMessages(['payout' => 'A receipt problem can be opened only after the administration marks the payment as sent.']);
        }
        if (! Schema::hasTable('exhibition_dispute_cases')) {
            throw ValidationException::withMessages(['payout' => 'Payment case handling is not available. Contact platform support.']);
        }

        try {
            $case = $this->disputes->create(
                'artist',
                'payout-receipt:'.$row->public_id,
                $row->fulfillment_public_id,
                'payout_not_received',
                'high',
                'Payout not received',
                $validated['summary'],
                $validated['idempotency_key'],
                null,
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payout' => $exception->getMessage()]);
        }

        return redirect()->route('exhibitions.dashboard.cases.show', $case->public_id)->with('status', 'Your payout issue was sent to the administration.');
    }

    public function download(Request $r, string $s): StreamedResponse
    {
        $settlement = $this->query((int) $r->user()->id)->where('s.public_id', $s)->first() ?? abort(404);
        $ledger = DB::table('exhibition_settlement_ledger_entries')->where('settlement_id', $settlement->id)->orderBy('occurred_at')->get();

        return response()->streamDownload(function () use ($settlement, $ledger) {
            $out = fopen('php://output', 'w');
            fputcsv($out, SpreadsheetCellSanitizer::row(['Statement', $settlement->public_id]));
            fputcsv($out, SpreadsheetCellSanitizer::row(['Artwork', $settlement->title]));
            fputcsv($out, SpreadsheetCellSanitizer::row(['Type', 'Direction', 'Amount', 'Currency', 'Date', 'Description']));
            foreach ($ledger as $e) {
                fputcsv($out, SpreadsheetCellSanitizer::row([$e->entry_type, $e->direction, number_format($e->amount_minor / (10 ** $settlement->scale), $settlement->scale, '.', ''), $e->currency, $e->occurred_at, $e->description]));
            }fclose($out);
        }, 'artist-statement-'.$settlement->public_id.'.csv', PrivateResponseHeaders::for('text/csv'));
    }

    private function query(int $artist)
    {
        return DB::table('exhibition_settlements as s')->join('exhibition_fulfillments as f', 'f.id', '=', 's.fulfillment_id')->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')->where('s.artist_user_id', $artist)->select('s.*', 'a.title', 'f.public_id as fulfillment_public_id');
    }
}
