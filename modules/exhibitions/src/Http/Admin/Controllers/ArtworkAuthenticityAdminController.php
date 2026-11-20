<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Authenticity\ArtworkAuthenticityService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkAuthenticityAdminController extends Controller
{
    public function __construct(private readonly ArtworkAuthenticityService $service) {}

    public function index(): View
    {
        return view('exhibitions::admin.artworks.authenticity-index', ['rows' => DB::table('exhibition_artworks as a')->leftJoin('exhibition_artwork_authenticity as x', 'x.artwork_id', '=', 'a.id')->select('a.public_id', 'a.title', 'a.state as artwork_state', 'x.state as authenticity_state')->orderByDesc('a.updated_at')->paginate(30)]);
    }

    public function show(string $artwork): View
    {
        return view('exhibitions::admin.artworks.authenticity-show', $this->service->reviewerWorkspace($artwork) + ['decisions' => $this->service->decisions()]);
    }

    public function decide(Request $request, string $artwork): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', Rule::in($this->service->decisions())], 'reason' => ['required', 'string', 'max:6000'], 'review_notes' => ['nullable', 'string', 'max:6000'], 'request_instructions' => ['nullable', 'string', 'max:6000'], 'evidence_references' => ['nullable', 'array'], 'evidence_references.*' => ['uuid', 'distinct']]);
        try {
            $this->service->review((int) $request->user()->id, $artwork, $v['decision'], $v['reason'], $v['review_notes'] ?? null, $v['request_instructions'] ?? null, $v['evidence_references'] ?? []);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'Manual authenticity decision recorded immutably.');
    }

    public function download(string $artwork, string $evidence): StreamedResponse
    {
        $row = $this->service->evidenceForReviewer($artwork, $evidence);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }
}
