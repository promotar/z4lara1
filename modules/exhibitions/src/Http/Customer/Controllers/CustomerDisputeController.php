<?php

namespace Modules\Exhibition\Http\Customer\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Disputes\DisputeCaseService;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleGateway;

final class CustomerDisputeController extends Controller
{
    public function __construct(
        private readonly DisputeCaseService $service,
        private readonly StoreLifecycleGateway $store,
    ) {}

    public function index(Request $request): View
    {
        $rows = DB::table('exhibition_dispute_cases as dispute')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'dispute.artwork_id')
            ->where('dispute.customer_user_id', $request->user()->id)
            ->select('dispute.public_id', 'dispute.subject', 'dispute.type', 'dispute.priority', 'dispute.state', 'dispute.updated_at', 'artwork.title')
            ->orderByDesc('dispute.updated_at')
            ->paginate(30);

        return view('exhibitions::customer.disputes.index', compact('rows'));
    }

    public function create(): View
    {
        return view('exhibitions::customer.disputes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_reference' => ['required', 'string', 'max:191'],
            'type' => ['required', 'in:shipping_delay,refusal_of_delivery,return_request,shipping_damage,artwork_differs_from_description,loss,ownership_authenticity_dispute,other'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'subject' => ['required', 'string', 'max:191'],
            'summary' => ['required', 'string', 'max:5000'],
        ]);
        $userId = (int) $request->user()->id;
        $orderReference = trim($validated['order_reference']);

        if (! $this->store->customerOwnsOrder($orderReference, $userId)) {
            throw ValidationException::withMessages([
                'order_reference' => 'The order could not be verified for this account.',
            ]);
        }

        $fulfillment = DB::table('exhibition_fulfillments')
            ->where('customer_user_id', $userId)
            ->where(function ($query) use ($orderReference): void {
                $query->where('store_order_public_id', $orderReference)
                    ->orWhere('store_order_number', $orderReference);
            })
            ->orderBy('store_unit_number')
            ->first();

        if (! $fulfillment) {
            throw ValidationException::withMessages([
                'order_reference' => 'The order could not be verified for this account.',
            ]);
        }

        $sourceReference = 'customer:'.$userId.':'.$fulfillment->store_order_public_id;

        try {
            $case = $this->service->create(
                'customer',
                $sourceReference,
                $fulfillment->public_id,
                $validated['type'],
                $validated['priority'],
                $validated['subject'],
                $validated['summary'],
                'customer-case:'.$userId.':'.$fulfillment->store_order_public_id,
                $userId,
                $userId,
            );
            $this->service->message(
                $case->public_id,
                $userId,
                'customer',
                'support',
                $validated['summary'],
                'customer-opening:'.$case->public_id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['case' => $exception->getMessage()]);
        }

        return redirect()->route('exhibitions.cases.show', $case->public_id);
    }

    public function show(Request $request, string $case): View
    {
        $record = DB::table('exhibition_dispute_cases as dispute')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'dispute.artwork_id')
            ->where('dispute.public_id', $case)
            ->where('dispute.customer_user_id', $request->user()->id)
            ->select(
                'dispute.public_id',
                'dispute.subject',
                'dispute.type',
                'dispute.priority',
                'dispute.state',
                'dispute.summary',
                'dispute.final_decision',
                'dispute.decision_reason',
                'dispute.created_at',
                'dispute.updated_at',
                'dispute.id',
                'artwork.title',
            )
            ->first() ?? abort(404);
        $messages = DB::table('exhibition_dispute_messages')
            ->where('case_id', $record->id)
            ->where(function ($query): void {
                $query->whereIn('audience', ['customer', 'all'])->orWhere('sender_role', 'customer');
            })
            ->orderBy('created_at')
            ->get();

        return view('exhibitions::customer.disputes.show', ['case' => $record, 'messages' => $messages]);
    }

    public function message(Request $request, string $case): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $record = DB::table('exhibition_dispute_cases')
            ->where('public_id', $case)
            ->where('customer_user_id', $request->user()->id)
            ->first() ?? abort(404);

        try {
            $this->service->message(
                $record->public_id,
                (int) $request->user()->id,
                'customer',
                'support',
                $validated['body'],
                $validated['idempotency_key'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['case' => $exception->getMessage()]);
        }

        return back()->with('status', 'Message sent to platform support.');
    }
}
