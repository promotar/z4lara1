<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Commission\ArtistCommissionTermsService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;

final class ArtistCommissionTermsAdminController extends Controller
{
    public function __construct(
        private readonly ArtistCommissionTermsService $terms,
        private readonly ExhibitionSettingsService $settings,
        private readonly CoreUserGateway $users,
    ) {}

    public function show(string $application): View
    {
        $artist = $this->terms->artist($application);
        $history = DB::table('exhibition_artist_commission_terms as terms')
            ->where('terms.artist_identity_id', $artist->identity_id)
            ->latest('terms.effective_from')
            ->latest('terms.id')
            ->get(['terms.*'])
            ->each(function (object $term): void {
                $term->actor_name = $term->created_by
                    ? $this->users->find((int) $term->created_by)?->name
                    : null;
            });

        return view('exhibitions::admin.artists.financial-terms', [
            'artist' => $artist,
            'history' => $history,
            'effectiveTerm' => $history->first(fn (object $term): bool => Carbon::parse($term->effective_from)->lte(now())),
            'scheduledTerm' => $history->filter(fn (object $term): bool => Carbon::parse($term->effective_from)->gt(now()))->sortBy('effective_from')->first(),
            'defaultRate' => (string) $this->settings->value('exhibitions.commission.default_rate', '20.000'),
        ]);
    }

    public function store(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate([
            'commission_source' => ['required', 'in:platform_default,custom'],
            'custom_commission' => ['nullable', 'required_if:commission_source,custom', 'regex:/^\d{1,3}(?:\.\d{1,3})?$/'],
            'effective_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        try {
            $this->terms->save(
                $application,
                $validated['commission_source'],
                $validated['custom_commission'] ?? null,
                $validated['effective_from'],
                $validated['reason'],
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['custom_commission' => $exception->getMessage()]);
        }

        return back()->with('status', 'Artist financial terms were scheduled and audited. Existing order snapshots were not changed.');
    }
}
