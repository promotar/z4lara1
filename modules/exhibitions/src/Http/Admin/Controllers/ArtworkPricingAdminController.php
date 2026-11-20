<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Pricing\ArtworkPricingService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkPricingAdminController
{
    public function __construct(private readonly ArtworkPricingService $service) {}

    public function index(): View
    {
        return view('exhibitions::admin.artworks.pricing-index', ['rows' => DB::table('exhibition_artworks as a')->leftJoin('exhibition_artwork_pricing as p', 'p.artwork_id', '=', 'a.id')->select('a.public_id', 'a.title', 'a.state as artwork_state', 'p.state as pricing_state', 'p.requested_amount', 'p.proposed_amount', 'p.approved_amount_snapshot', 'p.currency')->orderByDesc('a.updated_at')->paginate(30)]);
    }

    public function show(string $artwork): View
    {
        return view('exhibitions::admin.artworks.pricing-show', $this->service->reviewerWorkspace($artwork) + ['actions' => $this->service->reviewActions()]);
    }

    public function decide(Request $r, string $artwork): RedirectResponse
    {
        $v = $r->validate(['action' => ['required', Rule::in($this->service->reviewActions())], 'proposed_amount' => ['nullable', 'string', 'max:24'], 'reason' => ['required', 'string', 'max:8000'], 'evidence_references' => ['nullable', 'array'], 'evidence_references.*' => ['uuid', 'distinct']]);
        try {
            $this->service->review((int) $r->user()->id, $artwork, $v['action'], $v['proposed_amount'] ?? null, $v['reason'], $v['evidence_references'] ?? []);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }

        return back()->with('status', 'Manual pricing decision recorded.');
    }

    public function download(string $artwork, string $evidence): StreamedResponse
    {
        $row = $this->service->reviewerEvidence($artwork, $evidence);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }
}
