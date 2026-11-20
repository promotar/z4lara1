<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Admin\AdminArtistService;
use Modules\Exhibition\Application\Artists\ArtistAccountService;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;

final class ArtistAdminController extends Controller
{
    public function __construct(
        private readonly AdminArtistService $artists,
        private readonly ArtistAccountService $accounts,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $search = trim($request->string('search')->toString());

        return view('exhibitions::admin.artists.create', [
            'users' => $this->artists->candidates($search, 25),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'artistic_name' => ['nullable', 'string', 'max:160'],
        ]);
        try {
            $this->artists->addExistingUser(
                (int) $validated['user_id'],
                (int) $request->user()->id,
                $validated['artistic_name'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['user_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.exhibitions.workspace', ['area' => 'artists', 'tab' => 'active'])
            ->with('status', 'The existing platform user is now an active artist.');
    }

    public function activate(Request $request, string $artist): RedirectResponse
    {
        return $this->transition($request, $artist, ArtistAccountState::Active, 'Artist reactivated by an authorized administrator.');
    }

    public function deactivate(Request $request, string $artist): RedirectResponse
    {
        return $this->transition($request, $artist, ArtistAccountState::Suspended, 'Artist disabled by an authorized administrator.');
    }

    public function autoApproval(Request $request, string $artist): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $identity = DB::table('exhibition_artist_identities')->where('public_id', $artist)->first() ?? abort(404);
        $enabled = (bool) $validated['enabled'];

        DB::table('exhibition_artist_identities')->where('id', $identity->id)->update([
            'auto_approve_artworks' => $enabled,
            'auto_approve_granted_by' => $enabled ? (int) $request->user()->id : null,
            'auto_approve_granted_at' => $enabled ? now() : null,
            'updated_at' => now(),
        ]);

        return back()->with('status', $enabled
            ? 'Automatic approval granted: this artist new artworks are approved without manual review.'
            : 'Automatic approval removed: this artist new artworks return to manual review.');
    }

    public function destroy(Request $request, string $artist): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $request->validate(['confirmation' => ['required', 'in:remove-artist']]);

        return $this->transition($request, $artist, ArtistAccountState::Terminated, 'Artist removed by an authorized administrator.');
    }

    private function transition(Request $request, string $artist, ArtistAccountState $state, string $defaultReason): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $identity = DB::table('exhibition_artist_identities')->where('public_id', $artist)->first() ?? abort(404);
        try {
            $this->accounts->transition(
                (int) $identity->core_user_id,
                $state,
                (int) $request->user()->id,
                trim((string) ($validated['reason'] ?? '')) ?: $defaultReason,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['artist' => $exception->getMessage()]);
        }

        return back()->with('status', match ($state) {
            ArtistAccountState::Active => 'Artist reactivated. Existing artwork drafts were not republished.',
            ArtistAccountState::Suspended => 'Artist disabled and all eligible artworks moved to draft.',
            ArtistAccountState::Terminated => 'Artist access removed and all eligible artworks moved to draft.',
            default => 'Artist account updated.',
        });
    }
}
