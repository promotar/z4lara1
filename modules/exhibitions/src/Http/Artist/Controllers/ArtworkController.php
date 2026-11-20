<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use App\Platform\Core\Localization\PlatformLocale;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Applications\ArtistAccessGate;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Application\Artworks\ArtworkTranslationService;
use Modules\Exhibition\Application\Authenticity\ArtworkAuthenticityService;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;
use Modules\Exhibition\Application\Media\ArtworkMediaService;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;
use Modules\Exhibition\Application\Review\ArtworkReviewService;
use Modules\Exhibition\Application\Pricing\ArtworkPricingService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;

final class ArtworkController extends Controller
{
    public function __construct(
        private readonly ArtworkService $artworks,
        private readonly ArtistDashboardService $dashboard,
        private readonly ArtistAccessGate $artistAccess,
        private readonly ArtworkAuthenticityService $authenticity,
        private readonly ArtworkPricingService $pricing,
        private readonly ExhibitionSettingsService $settings,
        private readonly ArtworkEffectiveAvailabilityService $availability,
        private readonly ArtworkMediaService $media,
        private readonly ArtworkInventoryService $inventory,
        private readonly ArtworkPublicationService $publication,
        private readonly ArtworkReviewService $review,
        private readonly ArtworkTranslationService $translations,
    ) {}

    public function create(Request $request): View
    {
        $this->assertNewArtworkSubmissionsEnabled();
        $coreUserId = (int) $request->user()->id;
        abort_unless($this->artistAccess->canCreateArtwork($coreUserId), 403);
        $overview = $this->dashboard->overview($coreUserId);
        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $locale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;

        return view('exhibitions::artist.artworks.quick', [
            'artwork' => null,
            'locale' => $locale,
            'locales' => $locales,
            'defaultLocale' => PlatformLocale::DEFAULT,
            'translation' => null,
            'baseArtwork' => null,
            'draftKey' => (string) Str::uuid(),
            'primaryMedia' => null,
            'mediaCount' => 0,
            'hasPrimaryImage' => false,
            'declarationConfirmed' => false,
            'autoApprove' => $this->artistAutoApproves($coreUserId),
            'categories' => $this->artworkCategories(),
            'categoryLabels' => self::CATEGORY_LABELS,
            'sellingAllowed' => $this->artistAccess->canSubmitArtworkForSale($coreUserId),
        ] + $this->navigation($coreUserId));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function duplicate(Request $request, string $artwork): RedirectResponse
    {
        $this->assertNewArtworkSubmissionsEnabled();
        $coreUserId = (int) $request->user()->id;
        abort_unless($this->artistAccess->canCreateArtwork($coreUserId), 403);

        try {
            $copy = $this->artworks->duplicate($coreUserId, $artwork);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('exhibitions.dashboard.artworks.edit', $copy->public_id)
            ->with('status', 'Artwork duplicated as a private draft. Review the copy before submitting it.');
    }

    public function show(Request $request, string $artwork): View
    {
        $row = $this->artworks->owned((int) $request->user()->id, $artwork);
        $effectiveAvailability = $this->availability->forArtwork((int) $row->id);
        $row->effective_availability = match ($effectiveAvailability) {
            'sold' => 'Sold',
            'reserved' => 'Reserved',
            'available' => 'Available',
            default => filled($row->availability)
                ? ucwords(str_replace('_', ' ', (string) $row->availability))
                : '—',
        };

        return view('exhibitions::artist.artworks.show', ['artwork' => $row, 'revisions' => $this->revisions($row)] + $this->navigation((int) $request->user()->id));
    }

    public function edit(Request $request, string $artwork): View
    {
        $row = $this->artworks->owned((int) $request->user()->id, $artwork);
        abort_unless(in_array($row->state, ['draft', 'submitted', 'changes_required', 'rejected'], true), 409);

        $media = DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->where('state', 'ready');
        $authenticity = Schema::hasTable('exhibition_artwork_authenticity')
            ? DB::table('exhibition_artwork_authenticity')->where('artwork_id', $row->id)->first()
            : null;
        $authenticityEvidenceCount = Schema::hasTable('exhibition_artwork_evidence')
            ? DB::table('exhibition_artwork_evidence')->where('artwork_id', $row->id)->where('state', 'ready')->count()
            : 0;
        $authenticityEvidence = Schema::hasTable('exhibition_artwork_evidence')
            ? DB::table('exhibition_artwork_evidence')->where('artwork_id', $row->id)->where('state', 'ready')->orderBy('uploaded_at')->get()
            : collect();
        $authenticityProvenance = Schema::hasTable('exhibition_artwork_provenance')
            ? DB::table('exhibition_artwork_provenance')->where('artwork_id', $row->id)->orderBy('event_date')->orderBy('id')->get()
            : collect();

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;
        $locale = (string) $request->query('locale', $defaultLocale);
        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }

        $baseArtwork = $row;
        $translation = $this->translations->resolve((int) $row->id, $locale);
        $displayArtwork = $this->translations->apply(clone $row, $translation);

        return view('exhibitions::artist.artworks.quick', [
            'artwork' => $displayArtwork,
            'baseArtwork' => $baseArtwork,
            'locale' => $locale,
            'locales' => $locales,
            'defaultLocale' => PlatformLocale::DEFAULT,
            'translation' => $translation,
            'translatedLocales' => $this->translations->translatedLocales((int) $row->id),
            'draftKey' => $row->draft_key,
            'primaryMedia' => (clone $media)->where('is_primary', true)->first(),
            'mediaCount' => (clone $media)->count(),
            'hasPrimaryImage' => (clone $media)->where('is_primary', true)->exists(),
            'declarationConfirmed' => (bool) ($authenticity?->owns_artwork),
            'autoApprove' => $this->artistAutoApproves((int) $request->user()->id),
            'categories' => $this->artworkCategories(),
            'categoryLabels' => self::CATEGORY_LABELS,
            'sellingAllowed' => $this->artistAccess->canSubmitArtworkForSale((int) $request->user()->id),
        ] + $this->navigation((int) $request->user()->id));
    }

    public function update(Request $request, string $artwork): RedirectResponse
    {
        return $this->persist($request, $artwork);
    }

    public function autosave(Request $request, ?string $artwork = null): JsonResponse
    {
        if ($artwork === null) {
            $this->assertNewArtworkSubmissionsEnabled();
            abort_unless($this->artistAccess->canCreateArtwork((int) $request->user()->id), 403);
        }
        $request->merge(['intent' => 'save']);
        $validated = $request->validate($this->rules(false, $request) + ['lock_version' => ['nullable', 'integer', 'min:1']]);
        try {
            $saved = $this->artworks->save((int) $request->user()->id, $validated, $validated['draft_key'], $artwork, isset($validated['lock_version']) ? (int) $validated['lock_version'] : null);
        } catch (DomainException $exception) {
            return response()->json(['state' => 'conflict', 'message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'state' => 'saved', 'message' => 'Private draft saved.', 'public_id' => $saved->public_id, 'lock_version' => (int) $saved->lock_version,
            'autosave_url' => route('exhibitions.dashboard.artworks.autosave.update', $saved->public_id),
            'edit_url' => route('exhibitions.dashboard.artworks.edit', $saved->public_id),
        ]);
    }

    public function preview(Request $request, string $artwork): View
    {
        $row = $this->artworks->owned((int) $request->user()->id, $artwork);
        $primaryMedia = DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->where('state', 'ready')->orderByDesc('is_primary')->orderBy('sort_order')->first();
        $publication = Schema::hasTable('exhibition_artwork_publications')
            ? DB::table('exhibition_artwork_publications')->where('artwork_id', $row->id)->where('state', 'published')->first()
            : null;

        return view('exhibitions::artist.artworks.preview', ['artwork' => $row, 'primaryMedia' => $primaryMedia, 'publication' => $publication] + $this->navigation((int) $request->user()->id));
    }

    public function destroy(Request $request, string $artwork): RedirectResponse
    {
        $coreUserId = (int) $request->user()->id;
        $row = $this->artworks->owned($coreUserId, $artwork);

        $request->validate(['confirmation' => ['required', 'in:delete-artwork']]);

        if ($message = $this->artworkDeletionBlocker((int) $row->id)) {
            return back()->withErrors(['artwork_delete' => $message]);
        }

        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $row->id)->first();
        if ($publication && filled($publication->store_product_public_id) && $publication->state !== 'withdrawn') {
            try {
                $result = $this->publication->withdraw($coreUserId, $artwork, 'Artist deleted the artwork; it was removed from the Store.');
            } catch (DomainException $exception) {
                return back()->withErrors(['artwork_delete' => 'The Store listing could not be withdrawn, so the artwork was not deleted. '.$exception->getMessage()]);
            }

            if ($result->state !== 'withdrawn') {
                return back()->withErrors(['artwork_delete' => 'The Store listing could not be withdrawn, so the artwork was not deleted.']);
            }
        }

        $mediaRows = DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->get();

        try {
            DB::transaction(function () use ($row): void {
                $locked = DB::table('exhibition_artworks')->where('id', $row->id)->lockForUpdate()->first() ?? abort(404);
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

        foreach ($mediaRows as $media) {
            $paths = array_filter([
                $media->original_path,
                $media->display_path,
                $media->thumbnail_path,
                $media->ve03_room_path,
                $media->ve03_near_path,
                $media->ve03_popup_path,
            ]);

            if ($paths !== []) {
                try {
                    Storage::disk($media->disk)->delete($paths);
                } catch (Throwable) {
                    // Leftover files are harmless; the artwork record is already deleted.
                }
            }
        }

        return redirect()->route('exhibitions.dashboard.artworks')->with('status', 'Artwork deleted.');
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

        if (DB::table('exhibition_virtual_placements')->where('artwork_id', $artworkId)->exists()
            || DB::table('exhibition_virtual_slot_occupancies')->where('artwork_id', $artworkId)->exists()
            || DB::table('exhibition_virtual_placement_requests')->where('artwork_id', $artworkId)->exists()) {
            return 'This artwork is still placed in a virtual exhibition and cannot be deleted.';
        }

        return null;
    }

    private function persist(Request $request, ?string $publicId = null): RedirectResponse
    {
        if ($publicId === null) {
            $this->assertNewArtworkSubmissionsEnabled();
            abort_unless($this->artistAccess->canCreateArtwork((int) $request->user()->id), 403);
        }
        $submit = $request->input('intent') === 'submit';
        $continueToMedia = $request->input('intent') === 'save_and_media';
        $continueToAuthenticity = $request->input('intent') === 'save_and_authenticity';
        if ($submit && ! $this->artistAccess->canSubmitArtworkForSale((int) $request->user()->id)) {
            return redirect()->route('exhibitions.verification.show')
                ->with('warning', 'Complete your seller setup before submitting artwork for sale. You can continue saving private drafts from your dashboard.');
        }
        if (trim((string) $request->input('category')) === '__other__') {
            $request->merge(['category' => trim((string) $request->input('category_new', ''))]);
        }
        if ($submit) {
            $this->applyArtistDefaults($request);
        }
        $validated = $request->validate($this->rules($submit, $request));
        if (($validated['availability'] ?? null) !== 'sold') {
            $validated['sold_buyer_name'] = null;
        }

        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : PlatformLocale::DEFAULT;
        $locale = (string) $request->input('locale', $defaultLocale);
        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }
        $translationMode = $locale !== PlatformLocale::DEFAULT;

        if ($translationMode && $publicId !== null) {
            $existing = $this->artworks->owned((int) $request->user()->id, $publicId);
            $this->translations->save((int) $existing->id, $locale, $validated, (int) $request->user()->id);

            return redirect()
                ->route('exhibitions.dashboard.artworks.edit', ['artwork' => $publicId, 'locale' => $locale])
                ->with('status', 'تم حفظ ترجمة العمل الفني.');
        }

        try {
            $artwork = $this->artworks->save((int) $request->user()->id, $validated, $validated['draft_key'], $publicId);

            if ($translationMode && $publicId === null) {
                $this->translations->save((int) $artwork->id, $locale, $validated, (int) $request->user()->id);
            }
            if ($request->hasFile('primary_images')) {
                $hasFront = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('role', 'front')->whereNotIn('state', ['deleted', 'replaced'])->exists();
                $items = [];
                foreach ((array) $request->file('primary_images') as $index => $file) {
                    if ($file === null) {
                        continue;
                    }
                    $items[] = ['role' => (! $hasFront && $index === 0) ? 'front' : 'detail', 'file' => $file];
                }
                if ($items !== []) {
                    try {
                        $this->media->uploadItems((int) $request->user()->id, (string) $artwork->public_id, $items);
                    } catch (DomainException $exception) {
                        $minDimension = (int) config('exhibitions.media.minimum_dimension', 400);
                        $maxDimension = (int) config('exhibitions.media.maximum_dimension', 20000);
                        throw ValidationException::withMessages([
                            'primary_images' => "تعذّر رفع الصور. المسموح: العرض والارتفاع بين {$minDimension} و{$maxDimension} بكسل. السبب: {$exception->getMessage()}",
                        ]);
                    }
                }
            }
            if (filled($validated['ownership_basis'] ?? null)) {
                if (isset($validated['evidence_file'])) {
                    $this->authenticity->addEvidence(
                        (int) $request->user()->id,
                        (string) $artwork->public_id,
                        $validated['evidence_file'],
                        (string) $validated['evidence_role'],
                        (string) $validated['ownership_basis'],
                    );
                }
                if ($this->hasProvenancePayload($validated)) {
                    $this->authenticity->addProvenance((int) $request->user()->id, (string) $artwork->public_id, [
                        'event_date' => $validated['provenance_event_date'],
                        'event_type' => $validated['provenance_event_type'],
                        'owner_name' => $validated['provenance_owner_name'] ?? null,
                        'location' => $validated['provenance_location'] ?? null,
                        'description' => $validated['provenance_description'],
                        'document_reference' => $validated['provenance_document_reference'] ?? null,
                    ]);
                }
                $this->authenticity->declare((int) $request->user()->id, (string) $artwork->public_id, [
                    'owns_artwork' => $validated['ownership_basis'] === 'owner',
                    'may_display_and_sell' => true,
                    'inspiration_disclosure' => $validated['inspiration_disclosure'],
                    'digital_tools_disclosure' => $validated['digital_tools_disclosure'],
                    'current_physical_location' => $validated['current_physical_location'],
                ]);
            }
            if ($submit) {
                $this->pricing->syncArtworkRequestedPrice($artwork->public_id);
                $artwork = $this->artworks->submit((int) $request->user()->id, $artwork->public_id);
                $this->autoApproveIfGranted($artwork);
            }
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['artwork' => $exception->getMessage()]);
        }

        $this->applyAvailabilityEffects($request, $artwork);

        if ($continueToMedia) {
            return redirect()->route('exhibitions.dashboard.artworks.media.index', $artwork->public_id)
                ->with('status', 'Private draft saved. Add the artwork images now.');
        }
        if ($continueToAuthenticity) {
            return redirect()->route('exhibitions.dashboard.artworks.authenticity.show', $artwork->public_id)
                ->with('status', 'Private draft saved. Complete the ownership disclosures and evidence now.');
        }

        if ($translationMode) {
            return redirect()
                ->route('exhibitions.dashboard.artworks.edit', ['artwork' => $artwork->public_id, 'locale' => $locale])
                ->with('status', 'تم حفظ ترجمة العمل الفني.');
        }

        return redirect()->route('exhibitions.dashboard.artworks.show', $artwork->public_id)
            ->with('status', $submit
                ? ($this->artistAutoApproves((int) $request->user()->id)
                    ? 'Artwork submitted and approved automatically — automatic approval is enabled for your account.'
                    : 'Artwork submitted for manual review.')
                : 'Artwork draft saved.');
    }

    private const CATEGORY_LABELS = [
        'Painting' => 'لوحة',
        'Sculpture' => 'نحت',
        'Mixed media' => 'وسائط مختلطة',
        'Photography' => 'تصوير فوتوغرافي',
    ];

    /** @return list<string> */
    private function artworkCategories(): array
    {
        $categories = $this->settings->value('exhibitions.admin_settings.artwork_categories', ['Painting', 'Sculpture', 'Mixed media', 'Photography']);
        if (! is_array($categories) || $categories === []) {
            $categories = ['Painting', 'Sculpture', 'Mixed media', 'Photography'];
        }

        return array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $categories)));
    }

    /**
     * Artists normally wait for manual review. When an administrator granted
     * automatic approval, the submission is approved (and synchronized) at once.
     */
    private function autoApproveIfGranted(object $artwork): void
    {
        $identity = DB::table('exhibition_artist_identities')->where('id', $artwork->artist_identity_id)->first();
        if ($identity === null || ! (bool) ($identity->auto_approve_artworks ?? false)) {
            return;
        }

        try {
            $this->review->approveAutomatically(
                (int) ($identity->auto_approve_granted_by ?? $artwork->artist_identity_id),
                (string) $artwork->public_id,
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function artistAutoApproves(int $coreUserId): bool
    {
        $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->first();

        return (bool) ($identity->auto_approve_artworks ?? false);
    }

    private function applyAvailabilityEffects(Request $request, object $artwork): void
    {
        $availability = (string) ($request->input('availability') ?? '');
        if (! in_array($availability, ['sold', 'unavailable'], true)) {
            return;
        }
        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $artwork->id)->first();
        if ($publication === null || ! in_array((string) $publication->state, ['published', 'pending', 'sync_failed'], true)) {
            return;
        }
        try {
            if ($availability === 'unavailable') {
                $this->publication->withdraw((int) $request->user()->id, (string) $artwork->public_id, 'Artist marked the artwork unavailable; removed from Store.');
            } else {
                foreach (DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $artwork->id)->where('state', 'available')->get() as $item) {
                    $this->inventory->override((int) $request->user()->id, (string) $item->public_id, 'sold', 'Artist marked the artwork as sold outside the platform.', 'artist-sold:'.$item->public_id.':'.$publication->lock_version);
                }
                DB::table('exhibition_artworks')->where('id', $artwork->id)->update(['sold_at' => now(), 'updated_at' => now()]);
            }
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['availability' => 'حُفظت البيانات، لكن تعذّرت مزامنة الستور: '.$exception->getMessage()]);
        }
    }

    private function applyArtistDefaults(Request $request): void
    {
        if ($request->boolean('ownership_confirmed')) {
            $request->merge([
                'ownership_basis' => 'owner',
                'inspiration_disclosure' => 'Artist confirmation (simplified form): the artwork is the artist own original creation.',
                'digital_tools_disclosure' => 'Artist confirmation (simplified form): details were not provided.',
                'current_physical_location' => 'With the artist (studio)',
            ]);
        }
        $defaults = [
            'completion_year' => (int) date('Y'),
            'work_type' => 'Artwork',
            'edition_type' => 'original',
            'category' => 'Artwork',
            'technique' => 'Not specified',
            'material' => 'Not specified',
            'orientation' => 'other',
            'dimension_unit' => 'cm',
            'condition' => 'new',
            'availability' => 'available',
            'preparation_days' => 7,
            'currency' => 'JOD',
        ];
        foreach ($defaults as $key => $value) {
            if (! $request->filled($key)) {
                $request->merge([$key => $value]);
            }
        }
        if ($request->input('edition_type') === 'limited' && ! $request->filled('edition_number')) {
            $request->merge(['edition_number' => 1]);
        }
    }

    private function assertNewArtworkSubmissionsEnabled(): void
    {
        abort_unless((bool) $this->settings->value('exhibitions.artwork_submission.enabled', true), 403, 'New artwork submissions are currently closed.');
    }

    /** @return array<string, mixed> */
    private function rules(bool $submission, Request $request): array
    {
        $required = $submission ? 'required' : 'nullable';
        $year = (int) date('Y');
        $declarationStarted = $submission || collect([
            'ownership_basis',
            'inspiration_disclosure',
            'digital_tools_disclosure',
            'current_physical_location',
            'evidence_role',
        ])->contains(fn (string $field): bool => $request->filled($field)) || $request->hasFile('evidence_file');
        $declarationRequired = $declarationStarted ? 'required' : 'nullable';
        $provenanceStarted = collect([
            'provenance_event_date',
            'provenance_event_type',
            'provenance_owner_name',
            'provenance_location',
            'provenance_description',
            'provenance_document_reference',
        ])->contains(fn (string $field): bool => $request->filled($field));
        $provenanceRequired = $provenanceStarted ? 'required' : 'nullable';
        $evidenceRoles = $this->authenticity->rolesForBasis((string) $request->input('ownership_basis'));

        return [
            'draft_key' => ['required', 'uuid'], 'intent' => ['required', Rule::in(['save', 'save_and_media', 'save_and_authenticity', 'submit'])],
            'title' => [$required, 'string', 'max:180'], 'completion_year' => [$required, 'integer', 'between:1000,'.$year],
            'work_type' => [$required, 'string', 'max:80'], 'collection_name' => ['nullable', 'string', 'max:160'],
            'edition_type' => [$submission ? 'required' : 'nullable', Rule::in(['original', 'limited'])],
            'edition_number' => ['nullable', 'required_if:edition_type,limited', 'integer', 'min:1', 'lte:edition_total'],
            'edition_total' => ['nullable', 'required_if:edition_type,limited', 'integer', 'between:1,100000'],
            'category' => [$required, 'string', 'max:100'], 'category_new' => ['nullable', 'string', 'max:100'], 'artistic_style' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:160'], 'technique' => [$required, 'string', 'max:160'],
            'material' => [$required, 'string', 'max:160'], 'surface' => ['nullable', 'string', 'max:120'],
            'primary_colors' => ['nullable', 'string', 'max:500'], 'orientation' => [$required, Rule::in(['portrait', 'landscape', 'square', 'panoramic', 'other'])],
            'width' => ['nullable', 'numeric', 'gt:0', 'max:100000'], 'height' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'depth' => ['nullable', 'numeric', 'gte:0', 'max:100000'], 'dimension_unit' => [$submission ? 'required' : 'nullable', Rule::in(['cm', 'in'])],
            'weight' => ['nullable', 'numeric', 'gte:0', 'max:100000'], 'weight_unit' => ['nullable', Rule::in(['kg', 'lb'])],
            'is_framed' => ['nullable', 'boolean'], 'frame_material' => ['nullable', 'required_if:is_framed,1', 'string', 'max:120'],
            'condition' => [$required, Rule::in(['new', 'excellent', 'good', 'fair', 'restored'])], 'ready_to_hang' => ['nullable', 'boolean'],
            'short_description' => ['nullable', 'string', 'max:500'], 'detailed_description' => ['nullable', 'string', 'max:10000'],
            'artwork_story' => ['nullable', 'string', 'max:10000'], 'inspiration_source' => ['nullable', 'string', 'max:5000'],
            'care_instructions' => ['nullable', 'string', 'max:3000'],
            'requested_price' => [$required, 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => [$submission ? 'required' : 'nullable', Rule::in(['JOD'])], 'frame_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999.99'],
            'packaging_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999.99'],
            'availability' => [$required, Rule::in(['available', 'sold', 'unavailable', 'made_to_order', 'temporarily_unavailable'])], 'sold_buyer_name' => ['nullable', 'required_if:availability,sold', 'string', 'max:180'],
            'preparation_days' => [$required, 'integer', 'between:0,365'], 'shipping_notes' => ['nullable', 'string', 'max:3000'],
            'ownership_basis' => [$declarationRequired, Rule::in(['owner', 'authorized'])],
            'inspiration_disclosure' => [$declarationRequired, 'string', 'max:6000'],
            'digital_tools_disclosure' => [$declarationRequired, 'string', 'max:6000'],
            'current_physical_location' => [$declarationRequired, 'string', 'max:500'],
            'evidence_role' => ['nullable', 'required_with:evidence_file', Rule::in($evidenceRoles)],
            'evidence_file' => ['nullable', 'file', 'max:20480'],
            'primary_images' => ['nullable', 'array', 'max:8'], 'primary_images.*' => ['nullable', 'file', 'max:20480'],
            'provenance_event_date' => [$provenanceRequired, 'date'],
            'provenance_event_type' => [$provenanceRequired, 'string', 'max:64'],
            'provenance_owner_name' => ['nullable', 'string', 'max:255'],
            'provenance_location' => ['nullable', 'string', 'max:500'],
            'provenance_description' => [$provenanceRequired, 'string', 'max:6000'],
            'provenance_document_reference' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function hasProvenancePayload(array $validated): bool
    {
        return filled($validated['provenance_event_date'] ?? null)
            || filled($validated['provenance_event_type'] ?? null)
            || filled($validated['provenance_description'] ?? null);
    }

    private function revisions(object $artwork): mixed
    {
        return DB::table('exhibition_artwork_revisions')->where('artwork_id', $artwork->id)->orderByDesc('revision_number')->get();
    }

    /** @return array<string, mixed> */
    private function navigation(int $coreUserId): array
    {
        return ['context' => $this->dashboard->overview($coreUserId)['context'], 'navCounts' => $this->dashboard->navigationCounts($coreUserId), 'activeTab' => 'artworks'];
    }
}
