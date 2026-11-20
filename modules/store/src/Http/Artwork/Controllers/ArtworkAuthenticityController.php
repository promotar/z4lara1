<?php

namespace Modules\Store\Http\Artwork\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Store\Application\Artwork\ArtworkAuthenticityService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkAuthenticityController extends Controller
{
    public function sellerShow(Request $request, string $artwork, ArtworkAuthenticityService $service): View
    {
        $this->assertSeller($request);

        return $this->view($service, (int) $request->user()->id, $artwork, false);
    }

    public function adminShow(Request $request, string $artwork, ArtworkAuthenticityService $service): View
    {
        $this->assertAdmin($request);

        return $this->view($service, (int) $request->user()->id, $artwork, true);
    }

    public function sellerSave(Request $request, string $artwork, ArtworkAuthenticityService $service): RedirectResponse
    {
        $this->assertSeller($request);

        return $this->save($request, $artwork, $service, false);
    }

    public function adminSave(Request $request, string $artwork, ArtworkAuthenticityService $service): RedirectResponse
    {
        $this->assertAdmin($request);

        return $this->save($request, $artwork, $service, true);
    }

    public function sellerEvidence(Request $request, string $artwork, ArtworkAuthenticityService $service): StreamedResponse
    {
        $this->assertSeller($request);

        return $this->download($service->evidenceFor((int) $request->user()->id, $artwork, $request->route('evidence')));
    }

    public function adminEvidence(Request $request, string $artwork, ArtworkAuthenticityService $service): StreamedResponse
    {
        $this->assertAdmin($request);

        return $this->download($service->evidenceFor((int) $request->user()->id, $artwork, $request->route('evidence'), true));
    }

    private function view(ArtworkAuthenticityService $service, int $actorId, string $artwork, bool $admin): View
    {
        return view('store::artworks.authenticity', $service->workspace($actorId, $artwork, $admin) + [
            'rolesByBasis' => $service->rolesByBasis(),
            'admin' => $admin,
        ]);
    }

    private function save(Request $request, string $artwork, ArtworkAuthenticityService $service, bool $admin): RedirectResponse
    {
        $provenanceFields = ['provenance_event_date', 'provenance_event_type', 'provenance_owner_name', 'provenance_location', 'provenance_description', 'provenance_document_reference'];
        $hasProvenance = collect($provenanceFields)->contains(fn (string $field): bool => trim((string) $request->input($field, '')) !== '');
        $requiredIfProvenance = $hasProvenance ? 'required' : 'nullable';
        $basis = (string) $request->input('ownership_basis');
        $data = $request->validate([
            'ownership_basis' => ['required', 'in:owner,authorized'],
            'inspiration_disclosure' => ['required', 'string', 'max:6000'],
            'digital_tools_disclosure' => ['required', 'string', 'max:6000'],
            'current_physical_location' => ['required', 'string', 'max:500'],
            'evidence_role' => ['nullable', Rule::in($service->rolesForBasis($basis))],
            'evidence_file' => ['nullable', 'file', 'max:20480', 'required_with:evidence_role'],
            'provenance_event_date' => [$requiredIfProvenance, 'date'],
            'provenance_event_type' => [$requiredIfProvenance, 'string', 'max:64'],
            'provenance_owner_name' => ['nullable', 'string', 'max:255'],
            'provenance_location' => ['nullable', 'string', 'max:500'],
            'provenance_description' => [$requiredIfProvenance, 'string', 'max:6000'],
            'provenance_document_reference' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $service->saveDeclaration((int) $request->user()->id, $artwork, $data, $admin);
            if (isset($data['evidence_file'])) {
                $service->addEvidence((int) $request->user()->id, $artwork, $data['evidence_file'], (string) $data['evidence_role'], $admin);
            }
            if ($hasProvenance) {
                $service->addProvenance((int) $request->user()->id, $artwork, [
                    'event_date' => $data['provenance_event_date'],
                    'event_type' => $data['provenance_event_type'],
                    'owner_name' => $data['provenance_owner_name'] ?? null,
                    'location' => $data['provenance_location'] ?? null,
                    'description' => $data['provenance_description'],
                    'document_reference' => $data['provenance_document_reference'] ?? null,
                ], $admin);
            }
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['authenticity' => $exception->getMessage()]);
        }

        return back()->with($admin ? 'success' : 'status', 'Ownership, authenticity evidence, and provenance were saved.');
    }

    private function download(object $evidence): StreamedResponse
    {
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertSeller(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ((method_exists($user, 'hasRole') && $user->hasRole('artist')) || $user->can('store.artworks.sell')), 403);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->can('store.products.create')
            || $request->user()?->can('store.products.update')
            || $request->user()?->can('store.artworks.review'),
            403,
        );
    }
}
