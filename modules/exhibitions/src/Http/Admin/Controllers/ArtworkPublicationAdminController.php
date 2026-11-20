<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;

final class ArtworkPublicationAdminController extends Controller
{
    public function __construct(private readonly ArtworkPublicationService $service) {}

    public function index(Request $request): View
    {
        $state = (string) $request->query('state', '');
        $rows = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->leftJoin('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
            ->whereIn('artwork.state', ['approved', 'published', 'archived'])
            ->when($state !== '', fn ($q) => $q->where('publication.state', $state))
            ->select('artwork.*', 'application.artistic_name', 'application.legal_name', 'publication.state as publication_state', 'publication.store_product_public_id', 'publication.attempt_count', 'publication.failure_code', 'publication.failure_message', 'publication.last_success_at')
            ->orderByDesc('artwork.updated_at')->paginate(30)->withQueryString();

        return view('exhibitions::admin.artworks.publication-index', ['rows' => $rows, 'state' => $state]);
    }

    public function show(string $artwork): View
    {
        $row = DB::table('exhibition_artworks')->where('public_id', $artwork)->first() ?? abort(404);

        return view('exhibitions::admin.artworks.publication-show', [
            'artwork' => $row,
            'pricing' => DB::table('exhibition_artwork_pricing')->where('artwork_id', $row->id)->where('state', 'approved')->first(),
            'publication' => DB::table('exhibition_artwork_publications')->where('artwork_id', $row->id)->first(),
            'events' => DB::table('exhibition_artwork_publication_events as event')->leftJoin('exhibition_artwork_publications as publication', 'publication.id', '=', 'event.publication_id')->where('publication.artwork_id', $row->id)->select('event.*')->orderByDesc('event.occurred_at')->get(),
        ]);
    }

    public function publish(Request $request, string $artwork): RedirectResponse
    {
        return $this->action($request, fn ($reason) => $this->service->publish((int) $request->user()->id, $artwork, $reason, $request->string('idempotency_key')->toString() ?: null), 'Publication synchronization completed.');
    }

    public function reconcile(Request $request, string $artwork): RedirectResponse
    {
        return $this->action($request, fn ($reason) => $this->service->reconcile((int) $request->user()->id, $artwork, $reason), 'Reconciliation completed.');
    }

    public function withdraw(Request $request, string $artwork): RedirectResponse
    {
        return $this->action($request, fn ($reason) => $this->service->withdraw((int) $request->user()->id, $artwork, $reason), 'Artwork withdrawal synchronized.');
    }

    private function action(Request $request, callable $callback, string $message): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'string', 'max:191']]);
        try {
            $callback($validated['reason']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['publication' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
