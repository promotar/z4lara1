<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

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

final class ArtistDisputeController extends Controller
{
    public function __construct(private readonly DisputeCaseService $service) {}

    public function index(Request $r): View
    {
        $rows = DB::table('exhibition_dispute_cases as c')->join('exhibition_artworks as a', 'a.id', '=', 'c.artwork_id')->where('c.artist_user_id', $r->user()->id)->select('c.*', 'a.title')->orderByDesc('c.updated_at')->paginate(30);

        return view('exhibitions::artist.disputes.index', compact('rows'));
    }

    public function show(Request $r, string $c): View
    {
        $case = DB::table('exhibition_dispute_cases as c')->join('exhibition_artworks as a', 'a.id', '=', 'c.artwork_id')->where('c.public_id', $c)->where('c.artist_user_id', $r->user()->id)->select('c.*', 'a.title')->first() ?? abort(404);
        $messages = DB::table('exhibition_dispute_messages')->where('case_id', $case->id)->where(function ($q) {
            $q->whereIn('audience', ['artist', 'all'])->orWhere('sender_role', 'artist');
        })->orderBy('created_at')->get();
        $evidence = DB::table('exhibition_dispute_evidence')->where('case_id', $case->id)->whereIn('audience', ['artist', 'all'])->get();

        return view('exhibitions::artist.disputes.show', compact('case', 'messages', 'evidence'));
    }

    public function message(Request $r, string $c): RedirectResponse
    {
        $v = $r->validate(['body' => ['required', 'string', 'max:5000'], 'idempotency_key' => ['required', 'string', 'max:191']]);
        $case = DB::table('exhibition_dispute_cases')->where('public_id', $c)->where('artist_user_id', $r->user()->id)->first() ?? abort(404);
        try {
            $this->service->message($case->public_id, (int) $r->user()->id, 'artist', 'support', $v['body'], $v['idempotency_key']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['case' => $e->getMessage()]);
        }

        return back()->with('status', 'Response sent to platform support.');
    }

    public function download(Request $r, string $c, string $e): BinaryFileResponse
    {
        $row = DB::table('exhibition_dispute_evidence as e')->join('exhibition_dispute_cases as c', 'c.id', '=', 'e.case_id')->where('c.public_id', $c)->where('c.artist_user_id', $r->user()->id)->where('e.public_id', $e)->whereIn('e.audience', ['artist', 'all'])->select('e.*')->first() ?? abort(404);

        return response()->download(Storage::disk($row->disk)->path($row->path), $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }
}
