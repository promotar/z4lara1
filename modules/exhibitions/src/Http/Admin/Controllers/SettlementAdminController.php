<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Contracts\Payments\V1\PayoutOutcome;
use Modules\Exhibition\Contracts\Payments\V1\PayoutOutcomeReceiver;

final class SettlementAdminController extends Controller
{
    public function __construct(
        private readonly ArtistSettlementService $service,
        private readonly PayoutOutcomeReceiver $outcomes,
        private readonly CoreUserGateway $users,
    ) {}

    public function sales(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $orderState = trim((string) $request->query('order_state', ''));
        $settlementState = trim((string) $request->query('settlement_state', ''));

        $query = DB::table('exhibition_settlements as s')
            ->join('exhibition_fulfillments as f', 'f.id', '=', 's.fulfillment_id')
            ->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')
            ->leftJoin('exhibition_artist_identities as i', 'i.id', '=', 'a.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as p', function ($join): void {
                $join->on('p.core_user_id', '=', 's.artist_user_id')
                    ->whereRaw('p.id = (select max(p2.id) from exhibition_artist_applications p2 where p2.core_user_id = s.artist_user_id)');
            })
            ->select(
                's.*',
                'a.title',
                'a.public_id as artwork_public_id',
                'f.store_order_reference',
                'f.store_order_public_id',
                'f.store_order_number',
                'f.state as fulfillment_state',
                'p.artistic_name',
                'p.legal_name',
                'p.contact_email',
                DB::raw($this->orderStateExpression().' as order_state'),
                DB::raw("case when s.state = 'reversed' then 'refunded' when s.payment_collected_at is not null then 'paid' else 'pending' end as payment_status"),
            );

        $rows = $query
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('a.title', 'like', '%'.$search.'%')
                        ->orWhere('f.store_order_number', 'like', '%'.$search.'%')
                        ->orWhere('f.store_order_reference', 'like', '%'.$search.'%')
                        ->orWhere('p.artistic_name', 'like', '%'.$search.'%')
                        ->orWhere('p.legal_name', 'like', '%'.$search.'%')
                        ->orWhere('p.contact_email', 'like', '%'.$search.'%');
                });
            })
            ->when($settlementState !== '', fn ($query) => $query->where('s.state', $settlementState))
            ->when($orderState !== '', function ($query) use ($orderState): void {
                $query->whereRaw($this->orderStateExpression().' = ?', [$orderState]);
            })
            ->orderByDesc('s.created_at')
            ->paginate(30)
            ->withQueryString();

        $rows->getCollection()->each(function (object $row): void {
            $coreUser = $this->users->find((int) $row->artist_user_id);
            $row->artist_name = $row->artistic_name ?: ($row->legal_name ?: ($coreUser?->name ?: ($row->contact_email ?: 'Unknown artist')));
        });

        return view('exhibitions::admin.sales.index', compact('rows', 'search', 'orderState', 'settlementState'));
    }

    public function index(Request $request): View
    {
        $state = (string) $request->query('state', '');
        $rows = DB::table('exhibition_settlements as s')
            ->join('exhibition_fulfillments as f', 'f.id', '=', 's.fulfillment_id')
            ->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')
            ->when($state !== '', fn ($query) => $query->where('s.state', $state))
            ->select('s.*', 'a.title', 'f.store_order_reference')
            ->orderByDesc('s.updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('exhibitions::admin.settlements.index', compact('rows', 'state'));
    }

    public function show(string $settlement): View
    {
        $row = DB::table('exhibition_settlements as s')
            ->join('exhibition_fulfillments as f', 'f.id', '=', 's.fulfillment_id')
            ->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')
            ->where('s.public_id', $settlement)
            ->select('s.*', 'a.title', 'f.store_order_reference', 'f.state as fulfillment_state')
            ->first() ?? abort(404);
        $payout = DB::table('exhibition_payout_profiles as payouts')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'payouts.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('applications.core_user_id', $row->artist_user_id)
            ->select('payouts.method', 'payouts.masked_identifier', 'payouts.verified_at', 'payouts.encrypted_payload')
            ->first();
        $payoutDetails = [];
        if ($payout) {
            try {
                $payoutDetails = json_decode(Crypt::decryptString($payout->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $payoutDetails = [];
            }
        }

        return view('exhibitions::admin.settlements.show', [
            'settlement' => $row,
            'ledger' => DB::table('exhibition_settlement_ledger_entries')
                ->where('settlement_id', $row->id)
                ->orderBy('occurred_at')
                ->get(),
            'events' => DB::table('exhibition_settlement_events')
                ->where('settlement_id', $row->id)
                ->orderByDesc('occurred_at')
                ->get(),
            'attempts' => DB::table('exhibition_payout_attempts')
                ->where('settlement_id', $row->id)
                ->orderByDesc('attempt_number')
                ->get(),
            'balance' => $this->service->balance($row->id),
            'payout' => $payout,
            'payoutDetails' => $payoutDetails,
            'receiptCase' => Schema::hasTable('exhibition_dispute_cases')
                ? DB::table('exhibition_dispute_cases')
                    ->where('settlement_id', $row->id)
                    ->where('type', 'payout_not_received')
                    ->orderByDesc('created_at')
                    ->first()
                : null,
        ]);
    }

    public function action(Request $request, string $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:review,approve,decline,eligible,schedule,retry,send,hold,release,reverse'],
            'reason' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'provider' => ['nullable', 'required_if:action,schedule,retry', 'string', 'max:80'],
            'method' => ['nullable', 'required_if:action,schedule,retry', 'string', 'max:80'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'provider_reference' => ['nullable', 'required_if:action,send', 'string', 'max:191'],
        ]);

        return $this->run(function () use ($request, $settlement, $validated): object {
            $actor = (int) $request->user()->id;

            return match ($validated['action']) {
                'review' => $this->service->reviewEligibility($settlement, $actor, $validated['idempotency_key']),
                'approve' => $this->service->approve($settlement, $actor, $validated['reason'], $validated['idempotency_key']),
                'decline' => $this->service->declinePayoutRequest($settlement, $actor, $validated['reason'], $validated['idempotency_key']),
                'eligible' => $this->service->markEligible($settlement, $actor, $validated['idempotency_key']),
                'schedule', 'retry' => $this->service->schedulePayout(
                    $settlement,
                    $actor,
                    $validated['provider'],
                    $validated['method'],
                    $validated['idempotency_key'],
                ),
                'send' => $this->service->recordPaymentSent(
                    $settlement,
                    $actor,
                    $validated['provider_reference'],
                    $validated['reason'],
                    $validated['idempotency_key'],
                ),
                'hold' => $this->service->hold($settlement, $validated['reason'], $validated['idempotency_key'], $actor),
                'release' => $this->service->release($settlement, $validated['reason'], $validated['idempotency_key'], $actor),
                'reverse' => $this->service->reverse(
                    $settlement,
                    (int) ($validated['amount_minor'] ?? 0),
                    $validated['reason'],
                    $validated['idempotency_key'],
                    $actor,
                ),
            };
        }, 'Settlement action recorded.');
    }

    public function payoutOutcome(Request $request, string $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:paid,failed'],
            'attempt_key' => ['required', 'string', 'max:191'],
            'provider_reference' => ['required', 'string', 'max:191'],
            'failure_reason' => ['nullable', 'required_if:outcome,failed', 'string', 'max:4000'],
        ]);

        return $this->run(function () use ($request, $settlement, $validated): object {
            return $this->outcomes->receive(new PayoutOutcome(
                settlementPublicId: $settlement,
                attemptIdempotencyKey: $validated['attempt_key'],
                providerReference: $validated['provider_reference'],
                paid: $validated['outcome'] === 'paid',
                failureReason: $validated['failure_reason'] ?? null,
                actorUserId: (int) $request->user()->id,
            ));
        }, 'External payout outcome recorded.');
    }

    private function run(callable $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['settlement' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
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
