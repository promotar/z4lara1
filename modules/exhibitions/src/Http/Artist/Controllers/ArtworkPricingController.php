<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Pricing\ArtworkPricingService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkPricingController
{
    public function __construct(private readonly ArtworkPricingService $service, private readonly ArtistDashboardService $dashboard) {}

    public function show(Request $r, string $artwork): View
    {
        return view('exhibitions::artist.artworks.pricing', $this->service->ownerWorkspace((int) $r->user()->id, $artwork) + ['evidenceRoles' => $this->service->evidenceRoles()] + $this->nav((int) $r->user()->id));
    }

    public function submit(Request $r, string $artwork): RedirectResponse
    {
        $v = $r->validate(['requested_amount' => ['required', 'string', 'max:24'], 'currency' => ['required', Rule::in(['JOD'])], 'artist_explanation' => ['required', 'string', 'max:8000'], 'material_cost' => ['nullable', 'string', 'max:1000'], 'artist_experience' => ['nullable', 'string', 'max:2000'], 'previous_exhibitions' => ['nullable', 'string', 'max:3000'], 'awards_certificates' => ['nullable', 'string', 'max:3000'], 'prior_sales' => ['nullable', 'string', 'max:3000'], 'comparable_works' => ['nullable', 'string', 'max:3000'], 'market_references' => ['nullable', 'string', 'max:3000'], 'frame_condition_edition' => ['nullable', 'string', 'max:2000']]);
        try {
            $this->service->submit((int) $r->user()->id, $artwork, $v['requested_amount'], $v['currency'], $v['artist_explanation'], array_diff_key($v, array_flip(['requested_amount', 'currency', 'artist_explanation'])));
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['requested_amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Requested price submitted for manual review.');
    }

    public function evidence(Request $r, string $artwork): RedirectResponse
    {
        $v = $r->validate(['role' => ['required', Rule::in($this->service->evidenceRoles())], 'file' => ['required', 'file', 'max:20480']]);
        try {
            $this->service->uploadEvidence((int) $r->user()->id, $artwork, $v['file'], $v['role']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back()->with('status', 'Private pricing evidence uploaded.');
    }

    public function accept(Request $r, string $artwork): RedirectResponse
    {
        $v = $r->validate(['acceptance_note' => ['required', 'string', 'max:4000']]);
        try {
            $this->service->acceptAdjustment((int) $r->user()->id, $artwork, $v['acceptance_note']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['acceptance_note' => $e->getMessage()]);
        }

        return back()->with('status', 'Proposed adjustment accepted; final reviewer approval is still required.');
    }

    public function download(Request $r, string $artwork, string $evidence): StreamedResponse
    {
        $row = $this->service->ownerEvidence((int) $r->user()->id, $artwork, $evidence);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function nav(int $user): array
    {
        return ['context' => $this->dashboard->overview($user)['context'], 'navCounts' => $this->dashboard->navigationCounts($user), 'activeTab' => 'artworks'];
    }
}
