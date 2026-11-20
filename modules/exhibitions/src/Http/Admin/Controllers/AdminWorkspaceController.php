<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Exhibition\Application\Admin\ExhibitionAdminService;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;

final class AdminWorkspaceController extends Controller
{
    public function __construct(
        private readonly ExhibitionAdminService $admin,
        private readonly ExhibitionSettingsService $settings,
        private readonly ArtworkPublicationService $publication,
        private readonly CoreUserGateway $users,
    ) {}

    public function workspace(Request $request, string $area): View
    {
        $definition = $this->admin->definition($area);
        abort_unless($request->user()->can($definition['permission']), 403);

        return view('exhibitions::admin.workspace', $this->admin->workspace($area, $request->only(['tab', 'search', 'sort', 'direction', 'per_page'])));
    }

    public function viewArtwork(Request $request, string $artwork): View
    {
        abort_unless($request->user()->can('exhibitions.review'), 403);

        $row = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', function ($join): void {
                $join->on('application.core_user_id', '=', 'identity.core_user_id')
                    ->whereRaw('application.id = (select max(a2.id) from exhibition_artist_applications a2 where a2.core_user_id = identity.core_user_id)');
            })
            ->where('artwork.public_id', $artwork)
            ->select(
                'artwork.*',
                'identity.core_user_id',
                'application.artistic_name',
                'application.legal_name',
                'application.contact_email',
            )
            ->first() ?? abort(404);

        $coreUser = $this->users->find((int) $row->core_user_id);
        $row->artist_name = $row->artistic_name ?: ($row->legal_name ?: ($coreUser?->name ?: ($row->contact_email ?: 'Unknown artist')));
        $row->artist_email = $row->contact_email ?: $coreUser?->email;

        $media = DB::table('exhibition_artwork_media')
            ->where('artwork_id', $row->id)
            ->where('state', 'ready')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();
        $publication = DB::table('exhibition_artwork_publications')
            ->where('artwork_id', $row->id)
            ->orderByDesc('id')
            ->first();
        $pricing = DB::table('exhibition_artwork_pricing')
            ->where('artwork_id', $row->id)
            ->first();

        return view('exhibitions::admin.artworks.view', [
            'artwork' => $row,
            'media' => $media,
            'primaryMedia' => $media->first(),
            'publication' => $publication,
            'pricing' => $pricing,
        ]);
    }

    public function destroyArtwork(Request $request, string $artwork): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $request->validate(['confirmation' => ['required', 'in:delete-artwork']]);

        $record = DB::table('exhibition_artworks')->where('public_id', $artwork)->first() ?? abort(404);
        if ($message = $this->artworkDeletionBlocker((int) $record->id)) {
            return back()->withErrors(['artwork_delete' => $message]);
        }

        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $record->id)->first();
        if ($publication && filled($publication->store_product_public_id) && $publication->state !== 'withdrawn') {
            try {
                $result = $this->publication->withdraw(
                    (int) $request->user()->id,
                    $artwork,
                    'Administrator confirmed permanent artwork deletion.',
                );
            } catch (DomainException $exception) {
                return back()->withErrors(['artwork_delete' => 'The Store listing could not be withdrawn, so the artwork was not deleted. '.$exception->getMessage()]);
            }

            if ($result->state !== 'withdrawn') {
                return back()->withErrors(['artwork_delete' => 'The Store listing could not be withdrawn, so the artwork was not deleted.']);
            }
        }

        try {
            DB::transaction(function () use ($record): void {
                $locked = DB::table('exhibition_artworks')->where('id', $record->id)->lockForUpdate()->first() ?? abort(404);
                if ($message = $this->artworkDeletionBlocker((int) $locked->id)) {
                    throw new DomainException($message);
                }

                DB::table('exhibition_artworks')->where('id', $locked->id)->delete();
            }, 3);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork_delete' => $exception->getMessage()]);
        } catch (QueryException) {
            return back()->withErrors(['artwork_delete' => 'This artwork is still referenced by protected operational data and was not deleted.']);
        }

        return back()->with('status', 'Artwork deleted. Sales, orders, settlements, and disputes were not affected.');
    }

    private function artworkDeletionBlocker(int $artworkId): ?string
    {
        if (DB::table('exhibition_fulfillments')->where('artwork_id', $artworkId)->exists()) {
            return 'This artwork has a sale or order history and cannot be deleted.';
        }
        if (DB::table('exhibition_dispute_cases')->where('artwork_id', $artworkId)->exists()) {
            return 'This artwork is linked to a dispute and cannot be deleted.';
        }
        if (DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artworkId)->whereIn('state', ['reserved', 'sold'])->exists()) {
            return 'This artwork has reserved or sold inventory and cannot be deleted.';
        }

        return null;
    }

    public function settings(Request $request, string $section = 'commission-earnings'): View
    {
        abort_unless($request->user()->can('exhibitions.manage_settings'), 403);
        $catalogue = $this->settings->catalogue();
        $definitions = collect($catalogue['definitions']);
        $sectionGroup = static function (string $label, string $description, object $fields): array {
            return compact('label', 'description') + ['fields' => $fields->all()];
        };
        $settingsSections = [
            'commission-earnings' => [
                'label' => 'Commission & Earnings',
                'description' => 'Set the percentage retained by the platform from each new artwork sale.',
                'groups' => [$sectionGroup(
                    'Platform Commission',
                    'The artist receives the remaining percentage automatically. Existing sales never change.',
                    $definitions->only(['exhibitions.commission.default_rate']),
                ) + ['source_group' => 'commission_earnings']],
            ],
            'settlement-policy' => [
                'label' => 'Payout Policy',
                'description' => 'Choose how long to wait after delivery before the artist can be paid.',
                'groups' => [$sectionGroup(
                    'Customer return period',
                    'Finance approval remains required before every artist payment.',
                    $definitions->only([
                        'exhibitions.settlement.eligibility_trigger',
                        'exhibitions.settlement.return_window_days',
                    ]),
                ) + ['source_group' => 'commission_earnings']],
            ],
            'artwork-rules' => [
                'label' => 'Artwork Rules',
                'description' => 'Control new applications, new artwork submissions, public artwork pages, and sales.',
                'groups' => [
                    $sectionGroup(__('exhibitions::ui.auto_faeaec9eda'), __('exhibitions::ui.auto_f890e39025'), $definitions->only([
                        'exhibitions.artist_applications.enabled',
                        'exhibitions.artwork_submission.enabled',
                        'exhibitions.public_artwork_pages.enabled',
                    ])) + ['source_group' => 'general'],
                    $sectionGroup(__('exhibitions::ui.auto_086e032128'), __('exhibitions::ui.auto_bd3da2f264'), $definitions->only([
                        'exhibitions.store.enabled',
                    ])) + ['source_group' => 'review_publishing'],
                ],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Choose the essential sales and payout updates sent to artists.',
                'groups' => [$sectionGroup(__('exhibitions::ui.auto_71932c2d0e'), __('exhibitions::ui.auto_0fa757dd41'), $definitions->only([
                    'exhibitions.notifications.artwork_sold.artist',
                    'exhibitions.notifications.payout_paid.artist',
                ])) + ['source_group' => 'notifications']],
            ],
        ];

        $sectionAliases = [
            'commission_earnings' => 'commission-earnings',
            'general' => 'artwork-rules',
            'artist_applications' => 'artwork-rules',
            'artwork_submission' => 'artwork-rules',
            'review_publishing' => 'artwork-rules',
            'store_integration' => 'artwork-rules',
            'media_authenticity' => 'artwork-rules',
            'lifecycle_data' => 'artwork-rules',
        ];
        $activeSectionKey = $sectionAliases[$section] ?? $section;
        abort_unless(isset($settingsSections[$activeSectionKey]), 404);

        return view('exhibitions::admin.settings', [
            'catalogue' => $catalogue,
            'settingsSections' => $settingsSections,
            'activeSectionKey' => $activeSectionKey,
            'activeSection' => $settingsSections[$activeSectionKey],
        ]);
    }

    public function updateSetting(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage_settings'), 403);
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
            'confirm_currency_change' => ['nullable', 'boolean'],
        ]);
        $changed = $this->settings->save(
            (int) $request->user()->id,
            $validated['settings'],
            $validated['reason'],
            (bool) ($validated['confirm_currency_change'] ?? false),
        );

        return back()->with('status', $changed === 0 ? 'No setting values changed.' : "$changed setting change(s) saved and audited.");
    }

    public function resetSettingsGroup(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage_settings'), 403);
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $changed = $this->settings->resetGroup((int) $request->user()->id, $validated['group'], $validated['reason']);

        return back()->with('status', "$changed setting change(s) reset to safe defaults and audited.");
    }

    public function purgeSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.purge_data'), 403);
        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $count = $this->settings->purgeSettings(
            (int) $request->user()->id,
            $validated['confirmation'],
            $validated['reason'],
        );

        return back()->with('status', "$count Exhibitions setting record(s) purged. Store and order data were not touched.");
    }
}
