<?php

namespace Modules\Store\Http\Artwork\Controllers;

use App\Models\User;
use App\Platform\Core\Hooks\HookManager;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Store\Application\Artwork\ArtworkMediaService;
use Modules\Store\Application\Artwork\ArtworkProductService;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class ArtworkProductController extends Controller
{
    public function sellerIndex(Request $request, ArtworkProductService $artworks, HookManager $hooks): View
    {
        $this->assertSeller($request);

        $sellerNavigation = $hooks->applyFilters(
            'frontend.menu.items',
            [],
            'store.seller.workspace',
            null,
            $request->user(),
        );
        $sellerNavigation = is_array($sellerNavigation)
            && is_string($sellerNavigation['view'] ?? null)
            && is_array($sellerNavigation['data'] ?? null)
            && view()->exists($sellerNavigation['view'])
                ? $sellerNavigation
                : null;

        return view('store::artworks.index', [
            'artworks' => $artworks->owned((int) $request->user()->id),
            'sellerNavigation' => $sellerNavigation,
        ]);
    }

    public function sellerCreate(Request $request): View
    {
        $this->assertSeller($request);

        return $this->form(null, false);
    }

    public function sellerStore(Request $request, ArtworkProductService $artworks): RedirectResponse
    {
        $this->assertSeller($request);

        return $this->persist($request, $artworks, null, false);
    }

    public function sellerEdit(Request $request, string $artwork, ArtworkProductService $artworks): View
    {
        $this->assertSeller($request);
        $record = $artworks->findOwned((int) $request->user()->id, $artwork);
        abort_unless(in_array($record->workflow_state, ['draft', 'changes_required', 'rejected'], true), 409);

        return $this->form($record, false);
    }

    public function sellerUpdate(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        $this->assertSeller($request);

        return $this->persist($request, $artworks, $artwork, false);
    }

    public function sellerDuplicate(Request $request, string $artwork, ArtworkProductService $artworks, ArtworkMediaService $media): RedirectResponse
    {
        $this->assertSeller($request);
        try {
            $copy = DB::transaction(function () use ($request, $artwork, $artworks, $media): object {
                $copy = $artworks->duplicateOwned((int) $request->user()->id, (string) $request->user()->name, $artwork);
                $media->duplicateOwnedMedia((int) $request->user()->id, (string) $request->user()->name, $artwork, $copy->product_public_id);

                return $copy;
            }, 3);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('store.seller.artworks.edit', $copy->product_public_id)
            ->with('status', 'Artwork duplicated as a private Store draft. Confirm ownership before submitting it.');
    }

    public function sellerUnpublishForEdit(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        $this->assertSeller($request);
        $request->validate(['confirmation' => ['required', 'in:unpublish-for-edit']]);
        try {
            $record = $artworks->unpublishForEdit((int) $request->user()->id, (string) $request->user()->name, $artwork);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('store.seller.artworks.edit', $record->product_public_id)
            ->with('status', 'Artwork opened for editing and hidden from sale. Submit the completed changes for administration review.');
    }

    public function sellerArchive(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        $this->assertSeller($request);
        $request->validate(['confirmation' => ['required', 'in:archive-artwork']]);
        try {
            $artworks->archiveOwned((int) $request->user()->id, (string) $request->user()->name, $artwork);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('store.seller.artworks.index')->with('status', 'Artwork archived in Store.');
    }

    public function adminCreate(Request $request): View
    {
        abort_unless($request->user()?->can('store.products.create'), 403);

        return $this->form(null, true);
    }

    public function adminStore(Request $request, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless($request->user()?->can('store.products.create'), 403);

        return $this->persist($request, $artworks, null, true);
    }

    public function adminEdit(Request $request, string $artwork, ArtworkProductService $artworks): View
    {
        abort_unless($request->user()?->can('store.products.update'), 403);
        $record = $artworks->find($artwork);
        abort_unless(in_array($record->workflow_state, ['draft', 'changes_required', 'rejected'], true), 409);

        return $this->form($record, true);
    }

    public function adminUpdate(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless($request->user()?->can('store.products.update'), 403);

        return $this->persist($request, $artworks, $artwork, true);
    }

    public function adminUnpublishForEdit(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('store.products.update')
            && $request->user()?->can('store.products.publish'),
            403,
        );
        $request->validate(['confirmation' => ['required', 'in:unpublish-for-edit']]);

        try {
            $record = $artworks->unpublishForEditByAdmin(
                (int) $request->user()->id,
                (string) $request->user()->name,
                $artwork,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.store.artworks.edit', $record->product_public_id)
            ->with('success', 'Artwork opened for editing and temporarily hidden from sale. Publish the completed changes directly when ready.');
    }

    public function adminArchive(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless($request->user()?->can('store.products.archive'), 403);
        $request->validate(['confirmation' => ['required', 'in:archive-artwork']]);
        try {
            $artworks->archiveByAdmin((int) $request->user()->id, (string) $request->user()->name, $artwork);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.store.products.index')
            ->with('success', 'Artwork deleted safely. Its records remain available for restoration.');
    }

    public function adminRestore(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless($request->user()?->can('store.products.archive'), 403);
        $request->validate(['confirmation' => ['required', 'in:restore-artwork']]);
        try {
            $record = $artworks->restoreAsDraftByAdmin((int) $request->user()->id, (string) $request->user()->name, $artwork);
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.store.artworks.edit', $record->product_public_id)
            ->with('success', 'Artwork restored as a private draft. Review it before publishing again.');
    }

    public function sellerAutosave(Request $request, ArtworkProductService $artworks, ?string $artwork = null): JsonResponse
    {
        $this->assertSeller($request);

        return $this->autosave($request, $artworks, $artwork, false);
    }

    public function adminAutosave(Request $request, ArtworkProductService $artworks, ?string $artwork = null): JsonResponse
    {
        abort_unless($request->user()?->can('store.products.create') || $request->user()?->can('store.products.update'), 403);

        return $this->autosave($request, $artworks, $artwork, true);
    }

    public function reviewIndex(Request $request, ArtworkProductService $artworks): View
    {
        abort_unless($request->user()?->can('store.artworks.review') || $request->user()?->can('store.products.publish'), 403);

        return view('store::admin.artworks.review-index', ['artworks' => $artworks->reviewQueue()]);
    }

    public function reviewShow(Request $request, string $artwork, ArtworkProductService $artworks): View
    {
        abort_unless($request->user()?->can('store.artworks.review') || $request->user()?->can('store.products.publish'), 403);
        $record = $artworks->find($artwork);

        return view('store::admin.artworks.review-show', [
            'artwork' => $record,
            'media' => DB::table('store_product_media as media')
                ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'media.media_asset_id')
                ->where('media.product_id', $record->store_product_id)
                ->orderByDesc('media.is_primary')
                ->orderBy('media.position')
                ->get(['media.*', 'asset.path']),
            'reviews' => DB::table('store_artwork_reviews')->where('artwork_detail_id', $record->artwork_detail_id)->orderByDesc('decided_at')->get(),
            'authenticity' => DB::table('store_artwork_authenticity')->where('artwork_detail_id', $record->artwork_detail_id)->first(),
            'evidence' => DB::table('store_artwork_evidence')->where('artwork_detail_id', $record->artwork_detail_id)->where('state', 'ready')->orderBy('uploaded_at')->get(),
            'provenance' => DB::table('store_artwork_provenance')->where('artwork_detail_id', $record->artwork_detail_id)->orderBy('event_date')->get(),
        ]);
    }

    public function reviewDecision(Request $request, string $artwork, ArtworkProductService $artworks): RedirectResponse
    {
        abort_unless($request->user()?->can('store.artworks.review') || $request->user()?->can('store.products.publish'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'changes_required', 'reject'])],
            'notes' => ['nullable', 'required_unless:decision,approve', 'string', 'max:5000'],
        ]);
        try {
            $artworks->decide(
                (int) $request->user()->id,
                (string) $request->user()->name,
                $artwork,
                $data['decision'],
                $data['notes'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['artwork_review' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.store.artworks.review.index')
            ->with('success', 'Artwork review decision recorded in Store.');
    }

    private function form(?object $artwork, bool $admin): View
    {
        $draftKey = (string) ($artwork?->draft_key ?: Str::uuid());
        $mediaCount = $artwork ? DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->count() : 0;
        $hasPrimaryImage = $artwork ? DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->where('role', 'front')->where('is_primary', true)->exists() : false;
        $authenticity = $artwork ? DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artwork->artwork_detail_id)->first() : null;
        $evidenceCount = $artwork ? DB::table('store_artwork_evidence')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->count() : 0;
        $sellerOptions = $admin ? $this->sellerOptions($artwork?->product_public_id) : collect();

        return view('store::artworks.form', compact('artwork', 'admin', 'draftKey', 'mediaCount', 'hasPrimaryImage', 'authenticity', 'evidenceCount', 'sellerOptions'));
    }

    private function persist(
        Request $request,
        ArtworkProductService $artworks,
        ?string $productPublicId,
        bool $admin,
    ): RedirectResponse {
        $submit = $request->input('intent') === 'submit';
        $publish = $admin && $request->input('intent') === 'publish';
        $continueToMedia = $request->input('intent') === 'save_and_media';
        $continueToAuthenticity = $request->input('intent') === 'save_and_authenticity';
        $requiresCompleteArtwork = $submit || $publish || $continueToMedia || $continueToAuthenticity;
        $sellerOptions = $admin ? $this->sellerOptions($productPublicId) : collect();
        if ($admin && ! $request->has('ownership_type')) {
            $request->merge(['ownership_type' => 'artist_account']);
        }
        $data = $request->validate($this->rules($requiresCompleteArtwork, $admin, $sellerOptions->pluck('id')->map(fn ($id): int => (int) $id)->all()));
        if ($admin && ($data['ownership_type'] ?? null) === 'artist_account' && blank($data['artist_name'] ?? null)) {
            $data['artist_name'] = (string) ($sellerOptions->firstWhere('id', (int) $data['owner_user_id'])?->name ?? '');
        }
        $actorId = (int) $request->user()->id;
        $actorName = (string) $request->user()->name;
        try {
            $record = $artworks->save($actorId, $actorName, $data, $productPublicId, $admin);
            if ($submit) {
                $record = $artworks->submit((int) $record->owner_user_id, $record->product_public_id);
            } elseif ($publish) {
                $record = $artworks->publishDirectlyByAdmin($actorId, $actorName, $record->product_public_id);
            }
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['artwork' => $exception->getMessage()]);
        }

        if ($continueToMedia) {
            return redirect()->route($admin ? 'admin.plugins.store.artworks.media.index' : 'store.seller.artworks.media.index', $record->product_public_id)
                ->with($admin ? 'success' : 'status', 'Private Store draft saved. Add the artwork images now.');
        }
        if ($continueToAuthenticity) {
            return redirect()->route($admin ? 'admin.plugins.store.artworks.authenticity.show' : 'store.seller.artworks.authenticity.show', $record->product_public_id)
                ->with($admin ? 'success' : 'status', 'Private Store draft saved. Complete authenticity evidence now.');
        }

        if ($admin) {
            return $publish
                ? redirect()->route('admin.plugins.store.products.view', $record->product_public_id)->with('success', 'Artwork verified and published directly by the administration.')
                : redirect()->route('admin.plugins.store.artworks.edit', $record->product_public_id)->with('success', 'Artwork draft saved in Store.');
        }

        return redirect()->route('store.seller.artworks.index')
            ->with('status', $submit ? 'Artwork submitted for Store review.' : 'Artwork draft saved in Store.');
    }

    private function rules(bool $submission, bool $admin = false, array $sellerIds = []): array
    {
        $required = $submission ? 'required' : 'nullable';

        return [
            'draft_key' => ['required', 'uuid'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'intent' => ['required', Rule::in($admin ? ['save', 'save_and_media', 'save_and_authenticity', 'publish'] : ['save', 'save_and_media', 'save_and_authenticity', 'submit'])],
            'ownership_type' => $admin ? ['required', Rule::in(['artist_account', 'platform'])] : ['prohibited'],
            'owner_user_id' => $admin
                ? ['nullable', 'required_if:ownership_type,artist_account', 'prohibited_if:ownership_type,platform', 'integer', Rule::in($sellerIds)]
                : ['prohibited'],
            'title' => [$required, 'string', 'max:180'],
            'artist_name' => $admin
                ? ['nullable', 'required_if:ownership_type,platform', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'completion_year' => [$required, 'integer', 'between:1000,'.date('Y')],
            'work_type' => [$required, 'string', 'max:80'],
            'collection_name' => ['nullable', 'string', 'max:160'],
            'edition_type' => [$submission ? 'required' : 'nullable', Rule::in(['original', 'limited'])],
            'edition_number' => ['nullable', 'required_if:edition_type,limited', 'integer', 'min:1', 'lte:edition_total'],
            'edition_total' => ['nullable', 'required_if:edition_type,limited', 'integer', 'between:1,100000'],
            'category' => [$required, 'string', 'max:100'],
            'artistic_style' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:160'],
            'technique' => [$required, 'string', 'max:160'],
            'material' => [$required, 'string', 'max:160'],
            'surface' => ['nullable', 'string', 'max:120'],
            'primary_colors' => ['nullable', 'string', 'max:500'],
            'orientation' => [$required, Rule::in(['portrait', 'landscape', 'square', 'panoramic', 'other'])],
            'width' => [$required, 'numeric', 'gt:0', 'max:100000'],
            'height' => [$required, 'numeric', 'gt:0', 'max:100000'],
            'depth' => ['nullable', 'numeric', 'gte:0', 'max:100000'],
            'dimension_unit' => [$submission ? 'required' : 'nullable', Rule::in(['cm', 'in'])],
            'weight' => ['nullable', 'numeric', 'gte:0', 'max:100000'],
            'weight_unit' => ['nullable', Rule::in(['kg', 'lb'])],
            'is_framed' => ['nullable', 'boolean'],
            'frame_material' => ['nullable', 'required_if:is_framed,1', 'string', 'max:120'],
            'condition' => [$required, Rule::in(['new', 'excellent', 'good', 'fair', 'restored'])],
            'ready_to_hang' => ['nullable', 'boolean'],
            'short_description' => [$required, 'string', 'max:2000'],
            'detailed_description' => [$required, 'string', 'max:50000'],
            'artwork_story' => ['nullable', 'string', 'max:10000'],
            'inspiration_source' => ['nullable', 'string', 'max:5000'],
            'care_instructions' => ['nullable', 'string', 'max:3000'],
            'requested_price' => [$required, 'regex:/^(?:0|[1-9][0-9]*)(?:\.\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'],
            'currency' => [$submission ? 'required' : 'nullable', 'in:JOD'],
            'frame_cost' => ['nullable', 'regex:/^(?:0|[1-9][0-9]*)(?:\.\d{1,3})?$/'],
            'packaging_cost' => ['nullable', 'regex:/^(?:0|[1-9][0-9]*)(?:\.\d{1,3})?$/'],
            'availability' => [$required, Rule::in(['available', 'made_to_order', 'temporarily_unavailable'])],
            'preparation_days' => [$required, 'integer', 'between:0,365'],
            'shipping_notes' => ['nullable', 'string', 'max:3000'],
            'authenticity_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function autosave(Request $request, ArtworkProductService $artworks, ?string $productPublicId, bool $admin): JsonResponse
    {
        $request->merge(['intent' => 'save']);
        $sellerOptions = $admin ? $this->sellerOptions($productPublicId) : collect();
        if ($admin && ! $request->has('ownership_type')) {
            $request->merge(['ownership_type' => 'artist_account']);
        }
        $data = $request->validate($this->rules(false, $admin, $sellerOptions->pluck('id')->map(fn ($id): int => (int) $id)->all()));
        if ($admin && ($data['ownership_type'] ?? null) === 'artist_account' && blank($data['artist_name'] ?? null)) {
            $data['artist_name'] = (string) ($sellerOptions->firstWhere('id', (int) $data['owner_user_id'])?->name ?? '');
        }
        try {
            $record = $artworks->save(
                (int) $request->user()->id,
                (string) $request->user()->name,
                $data,
                $productPublicId,
                $admin,
            );
        } catch (DomainException $exception) {
            return response()->json(['state' => 'conflict', 'message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'state' => 'saved',
            'message' => 'Private Store draft saved.',
            'product_public_id' => $record->product_public_id,
            'lock_version' => (int) $record->lock_version,
            'autosave_url' => route($admin ? 'admin.plugins.store.artworks.autosave.update' : 'store.seller.artworks.autosave.update', $record->product_public_id),
            'edit_url' => route($admin ? 'admin.plugins.store.artworks.edit' : 'store.seller.artworks.edit', $record->product_public_id),
        ]);
    }

    private function assertSeller(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $isArtist = method_exists($user, 'hasRole') && $user->hasRole('artist');
        abort_unless($isArtist || $user->can('store.artworks.sell'), 403);
    }

    private function sellerOptions(?string $productPublicId = null)
    {
        try {
            $options = User::permission('store.artworks.sell')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        } catch (PermissionDoesNotExist) {
            // Installation and isolated test flows may run before Store permissions are seeded.
            $options = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        }
        if ($productPublicId !== null) {
            $ownerId = DB::table('store_artwork_details as artwork')
                ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
                ->where('product.public_id', $productPublicId)
                ->value('artwork.owner_user_id');
            if ($ownerId && ! $options->contains('id', (int) $ownerId)) {
                $owner = User::query()->find((int) $ownerId, ['id', 'name', 'email']);
                if ($owner) {
                    $options->push($owner);
                }
            }
        }

        return $options->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
