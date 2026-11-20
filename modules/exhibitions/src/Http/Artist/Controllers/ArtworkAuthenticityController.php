<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Authenticity\ArtworkAuthenticityService;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkAuthenticityController extends Controller
{
    public function __construct(private readonly ArtworkAuthenticityService $service, private readonly ArtistDashboardService $dashboard) {}

    public function show(Request $request, string $artwork): View
    {
        return view('exhibitions::artist.artworks.authenticity', $this->service->workspace((int) $request->user()->id, $artwork) + ['rolesByBasis' => $this->service->rolesByBasis()] + $this->nav((int) $request->user()->id));
    }

    public function save(Request $request, string $artwork): RedirectResponse
    {
        $provenanceFields = ['provenance_event_date', 'provenance_event_type', 'provenance_owner_name', 'provenance_location', 'provenance_description', 'provenance_document_reference'];
        $hasProvenance = collect($provenanceFields)->contains(fn (string $field): bool => trim((string) $request->input($field, '')) !== '');
        $requiredIfProvenance = $hasProvenance ? 'required' : 'nullable';
        $v = $request->validate([
            'ownership_basis' => ['required', 'in:owner,authorized'],
            'inspiration_disclosure' => ['required', 'string', 'max:6000'],
            'digital_tools_disclosure' => ['required', 'string', 'max:6000'],
            'current_physical_location' => ['required', 'string', 'max:500'],
            'evidence_role' => ['required', Rule::in($this->service->rolesForBasis((string) $request->input('ownership_basis')))],
            'evidence_file' => ['nullable', 'file', 'max:20480'],
            'provenance_event_date' => [$requiredIfProvenance, 'date'],
            'provenance_event_type' => [$requiredIfProvenance, 'string', 'max:64'],
            'provenance_owner_name' => ['nullable', 'string', 'max:255'],
            'provenance_location' => ['nullable', 'string', 'max:500'],
            'provenance_description' => [$requiredIfProvenance, 'string', 'max:6000'],
            'provenance_document_reference' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if (isset($v['evidence_file'])) {
                $this->service->addEvidence((int) $request->user()->id, $artwork, $v['evidence_file'], $v['evidence_role'], $v['ownership_basis']);
            }
            if ($hasProvenance) {
                $this->service->addProvenance((int) $request->user()->id, $artwork, [
                    'event_date' => $v['provenance_event_date'],
                    'event_type' => $v['provenance_event_type'],
                    'owner_name' => $v['provenance_owner_name'] ?? null,
                    'location' => $v['provenance_location'] ?? null,
                    'description' => $v['provenance_description'],
                    'document_reference' => $v['provenance_document_reference'] ?? null,
                ]);
            }
            $this->service->declare((int) $request->user()->id, $artwork, [
                'owns_artwork' => $v['ownership_basis'] === 'owner',
                'may_display_and_sell' => true,
            ] + $v);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['workspace' => $e->getMessage()]);
        }

        return back()->with('status', 'All ownership, evidence, and provenance changes were saved.');
    }

    public function declare(Request $request, string $artwork): RedirectResponse
    {
        $v = $request->validate(['ownership_basis' => ['required', 'in:owner,authorized'], 'inspiration_disclosure' => ['required', 'string', 'max:6000'], 'digital_tools_disclosure' => ['required', 'string', 'max:6000'], 'current_physical_location' => ['required', 'string', 'max:500']]);
        try {
            $this->service->declare((int) $request->user()->id, $artwork, [
                'owns_artwork' => $v['ownership_basis'] === 'owner',
                'may_display_and_sell' => true,
            ] + $v);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['declaration' => $e->getMessage()]);
        }

        return back()->with('status', 'Ownership declarations recorded for manual review.');
    }

    public function evidence(Request $request, string $artwork): RedirectResponse
    {
        $basis = $this->service->ownershipBasis((int) $request->user()->id, $artwork);
        if ($basis === null) {
            throw ValidationException::withMessages(['role' => 'Choose your relationship to the artwork before adding evidence.']);
        }
        $v = $request->validate(['role' => ['required', Rule::in($this->service->rolesForBasis($basis))], 'file' => ['required', 'file', 'max:20480']]);
        try {
            $this->service->addEvidence((int) $request->user()->id, $artwork, $v['file'], $v['role'], $basis);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back()->with('status', 'Private evidence uploaded. Any exact-file match is only an informational reviewer signal.');
    }

    public function provenance(Request $request, string $artwork): RedirectResponse
    {
        $v = $request->validate(['event_date' => ['required', 'date'], 'event_type' => ['required', 'string', 'max:64'], 'owner_name' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:500'], 'description' => ['required', 'string', 'max:6000'], 'document_reference' => ['nullable', 'string', 'max:500']]);
        try {
            $this->service->addProvenance((int) $request->user()->id, $artwork, $v);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['provenance' => $e->getMessage()]);
        }

        return back()->with('status', 'Provenance event appended without changing earlier history.');
    }

    public function download(Request $request, string $artwork, string $evidence): StreamedResponse
    {
        $row = $this->service->evidenceForOwner((int) $request->user()->id, $artwork, $evidence);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function nav(int $user): array
    {
        return ['context' => $this->dashboard->overview($user)['context'], 'navCounts' => $this->dashboard->navigationCounts($user), 'activeTab' => 'artworks'];
    }
}
