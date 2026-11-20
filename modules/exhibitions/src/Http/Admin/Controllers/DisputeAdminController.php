<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Disputes\DisputeCaseService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DisputeAdminController extends Controller
{
    public function __construct(private readonly DisputeCaseService $service) {}

    public function index(Request $r): View
    {
        $state = (string) $r->query('state', '');
        $type = (string) $r->query('type', '');
        $rows = DB::table('exhibition_dispute_cases as c')->join('exhibition_artworks as a', 'a.id', '=', 'c.artwork_id')->when($state !== '', fn ($q) => $q->where('c.state', $state))->when($type !== '', fn ($q) => $q->where('c.type', $type))->select('c.*', 'a.title')->orderByRaw("FIELD(c.priority,'critical','high','normal','low')")->orderBy('c.response_due_at')->paginate(30)->withQueryString();

        return view('exhibitions::admin.disputes.index', compact('rows', 'state', 'type'));
    }

    public function show(string $case): View
    {
        $c = DB::table('exhibition_dispute_cases as c')->join('exhibition_artworks as a', 'a.id', '=', 'c.artwork_id')->where('c.public_id', $case)->select('c.*', 'a.title')->first() ?? abort(404);

        return view('exhibitions::admin.disputes.show', ['case' => $c, 'messages' => DB::table('exhibition_dispute_messages')->where('case_id', $c->id)->orderBy('created_at')->get(), 'events' => DB::table('exhibition_dispute_events')->where('case_id', $c->id)->orderByDesc('occurred_at')->get(), 'evidence' => DB::table('exhibition_dispute_evidence')->where('case_id', $c->id)->orderBy('created_at')->get()]);
    }

    public function action(Request $r, string $c): RedirectResponse
    {
        $v = $r->validate(['action' => ['required', 'in:assign,request_customer,request_artist,decide,close,reopen,message'], 'reason' => ['required', 'string', 'max:5000'], 'decision' => ['nullable', 'string', 'max:100'], 'idempotency_key' => ['required', 'string', 'max:191'], 'owner' => ['nullable', 'integer']]);

        return $this->run(function () use ($r, $c, $v) {
            $u = (int) $r->user()->id;

            return match ($v['action']) {
                'assign' => $this->service->assign($c, $u, (int) ($v['owner'] ?: $u), $v['reason'], $v['idempotency_key']),'request_customer' => $this->service->requestInformation($c, $u, 'customer', $v['reason'], $v['idempotency_key']),'request_artist' => $this->service->requestInformation($c, $u, 'artist', $v['reason'], $v['idempotency_key']),'decide' => $this->service->decide($c, $u, (string) $v['decision'], $v['reason'], ['financial' => ['settlement_hold' => 'release_after_decision'], 'inventory' => ['decision' => $v['decision']], 'fulfillment' => ['decision' => $v['decision']]], $v['idempotency_key']),'close' => $this->service->close($c, $u, $v['reason'], $v['idempotency_key']),'reopen' => $this->service->reopen($c, $u, $v['reason'], $v['idempotency_key']),'message' => $this->service->message($c, $u, 'support', 'internal', $v['reason'], $v['idempotency_key'])
            };
        }, 'Case action recorded.');
    }

    public function evidence(Request $r, string $c): RedirectResponse
    {
        $v = $r->validate(['audience' => ['required', 'in:internal,customer,artist,all'], 'role' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);

        return $this->run(fn () => $this->service->evidence($c, (int) $r->user()->id, 'support', $v['audience'], $v['role'], $v['file']), 'Private case evidence added.');
    }

    public function download(string $c, string $e): BinaryFileResponse
    {
        $row = DB::table('exhibition_dispute_evidence as e')->join('exhibition_dispute_cases as c', 'c.id', '=', 'e.case_id')->where('c.public_id', $c)->where('e.public_id', $e)->select('e.*')->first() ?? abort(404);

        return response()->download(Storage::disk($row->disk)->path($row->path), $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function run(callable $op, string $msg): RedirectResponse
    {
        try {
            $op();
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['case' => $e->getMessage()]);
        }

        return back()->with('status', $msg);
    }
}
