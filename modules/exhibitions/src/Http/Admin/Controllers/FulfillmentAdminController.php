<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Fulfillment\ArtworkFulfillmentService;

final class FulfillmentAdminController extends Controller
{
    public function __construct(private readonly ArtworkFulfillmentService $service) {}

    public function index(Request $r): View
    {
        $state = (string) $r->query('state', '');
        $rows = DB::table('exhibition_fulfillments as f')->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')->when($state !== '', fn ($q) => $q->where('f.state', $state))->select('f.*', 'a.title')->selectRaw('exists(select 1 from exhibition_settlements s where s.fulfillment_id = f.id) as sale_committed')->orderByDesc('f.updated_at')->paginate(30)->withQueryString();

        return view('exhibitions::admin.fulfillment.index', compact('rows', 'state'));
    }

    public function show(string $fulfillment): View
    {
        $f = DB::table('exhibition_fulfillments as f')->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id')->where('f.public_id', $fulfillment)->select('f.*', 'a.title')->selectRaw('exists(select 1 from exhibition_settlements s where s.fulfillment_id = f.id) as sale_committed')->first() ?? abort(404);
        $allowed = ['sale_confirmed' => ['collected', 'damaged'], 'artist_notification_sent' => ['collected', 'damaged'], 'preparing' => ['collected', 'damaged'], 'ready_for_pickup' => ['collected', 'damaged'], 'collected' => ['quality_inspection', 'damaged', 'lost'], 'quality_inspection' => ['ready_for_shipment', 'damaged'], 'ready_for_shipment' => ['shipped', 'damaged', 'lost'], 'shipped' => ['delivered', 'delayed', 'damaged', 'lost'], 'delayed' => ['shipped', 'delivered', 'damaged', 'lost'], 'delivered' => ['return_requested'], 'return_requested' => ['returned']][$f->state] ?? [];
        if (in_array($f->state, ['artist_notification_sent', 'preparing', 'ready_for_pickup'], true) && ! $f->sale_committed) {
            $allowed = [];
        }

        return view('exhibitions::admin.fulfillment.show', ['fulfillment' => $f, 'allowedTransitions' => $allowed, 'events' => DB::table('exhibition_fulfillment_events')->where('fulfillment_id', $f->id)->orderByDesc('occurred_at')->get(), 'evidence' => DB::table('exhibition_fulfillment_evidence')->where('fulfillment_id', $f->id)->get()]);
    }

    public function transition(Request $r, string $f): RedirectResponse
    {
        $v = $r->validate(['state' => ['required', 'string', 'max:40'], 'notes' => ['required', 'string', 'max:4000'], 'idempotency_key' => ['required', 'string', 'max:191'], 'condition_summary' => ['nullable', 'string', 'max:4000'], 'certificate_included' => ['nullable', 'boolean'], 'carrier_reference' => ['nullable', 'string', 'max:191'], 'custody_holder' => ['nullable', 'string', 'max:80']]);

        return $this->run(fn () => $this->service->platformTransition((int) $r->user()->id, $f, $v['state'], $v['notes'], $v['idempotency_key'], $v), 'Fulfillment transition recorded.');
    }

    public function pickup(Request $r, string $f): RedirectResponse
    {
        $v = $r->validate(['pickup_scheduled_at' => ['required', 'date'], 'notes' => ['required', 'string', 'max:4000'], 'idempotency_key' => ['required', 'string', 'max:191']]);

        return $this->run(fn () => $this->service->schedulePickup((int) $r->user()->id, $f, $v['pickup_scheduled_at'], $v['notes'], $v['idempotency_key']), 'Pickup scheduled.');
    }

    public function evidence(Request $r, string $f): RedirectResponse
    {
        $v = $r->validate(['role' => ['required', 'in:collection_condition,inspection_condition,damage,return'], 'file' => ['required', 'file', 'max:20480']]);

        return $this->run(fn () => $this->service->addEvidence((int) $r->user()->id, $f, $v['role'], $v['file']), 'Private condition evidence added.');
    }

    private function run(callable $op, string $message): RedirectResponse
    {
        try {
            $op();
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['fulfillment' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
