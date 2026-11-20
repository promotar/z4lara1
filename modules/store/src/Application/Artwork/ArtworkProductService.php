<?php

namespace Modules\Store\Application\Artwork;

use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Store\Application\Catalog\CreateProduct\CreateProductCommand;
use Modules\Store\Application\Catalog\CreateProduct\CreateProductHandler;
use Modules\Store\Application\Catalog\ProductLifecycleService;
use Modules\Store\Application\Catalog\ProductPublicationService;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Pricing\SetRegularPrice;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\PublicIdGenerator;

final readonly class ArtworkProductService
{
    public function __construct(
        private CreateProductHandler $products,
        private SetRegularPrice $prices,
        private InventoryService $inventory,
        private ProductPublicationService $publisher,
        private ProductLifecycleService $lifecycle,
        private PublicIdGenerator $ids,
    ) {}

    public function owned(int $ownerUserId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->where('artwork.owner_user_id', $ownerUserId)
            ->orderByDesc('product.updated_at')
            ->paginate($perPage);
    }

    public function reviewQueue(int $perPage = 30): LengthAwarePaginator
    {
        return $this->query()
            ->whereIn('artwork.workflow_state', ['submitted', 'under_review', 'changes_required', 'approved', 'rejected'])
            ->orderByRaw("CASE artwork.workflow_state WHEN 'submitted' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")
            ->orderBy('artwork.submitted_at')
            ->paginate($perPage);
    }

    public function find(string $productPublicId): object
    {
        return $this->query()->where('product.public_id', $productPublicId)->first() ?? abort(404);
    }

    public function findOwned(int $ownerUserId, string $productPublicId): object
    {
        return $this->query()
            ->where('artwork.owner_user_id', $ownerUserId)
            ->where('product.public_id', $productPublicId)
            ->first() ?? abort(404);
    }

    public function save(
        int $actorUserId,
        string $actorName,
        array $data,
        ?string $productPublicId = null,
        bool $allowAnyOwner = false,
    ): object {
        return DB::transaction(function () use ($actorUserId, $actorName, $data, $productPublicId, $allowAnyOwner): object {
            $actor = new ActorContext('user', (string) $actorUserId, $actorName);
            $ownershipType = $allowAnyOwner
                ? (string) ($data['ownership_type'] ?? 'artist_account')
                : 'artist_account';
            if (! in_array($ownershipType, ['artist_account', 'platform'], true)) {
                throw new DomainException('Choose a supported artwork ownership type.');
            }
            $ownerUserId = $ownershipType === 'artist_account'
                ? ($allowAnyOwner ? (int) ($data['owner_user_id'] ?? 0) : $actorUserId)
                : null;
            if ($ownershipType === 'artist_account' && (! is_int($ownerUserId) || $ownerUserId <= 0)) {
                throw new DomainException('Choose the platform account that owns this artwork.');
            }
            if ($ownershipType === 'platform' && ! $allowAnyOwner) {
                throw new DomainException('Only the administration can create a platform-owned artwork.');
            }
            if ($ownershipType === 'platform' && blank($data['artist_name'] ?? null)) {
                throw new DomainException('Enter the public artist or attribution name for a platform-owned artwork.');
            }
            if ($productPublicId === null && filled($data['draft_key'] ?? null)) {
                $existingQuery = DB::table('store_artwork_details as artwork')
                    ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
                    ->where('artwork.draft_key', $data['draft_key']);
                if (! $allowAnyOwner) {
                    $existingQuery->where('artwork.owner_user_id', $ownerUserId);
                }
                $existing = $existingQuery->value('product.public_id');
                $productPublicId = $existing ?: null;
            }
            if ($productPublicId === null) {
                $slug = $this->uniqueSlug((string) ($data['title'] ?? 'artwork'));
                $sku = 'ART-'.Str::upper(Str::random(16));
                $created = $this->products->handle(new CreateProductCommand(
                    'artwork', (string) app()->getLocale(), (string) (($data['title'] ?? null) ?: 'Untitled artwork'), $slug,
                    ProductVisibility::Hidden, $actor, null, $sku,
                ));
                $productPublicId = $created->productPublicId;
                $productId = (int) DB::table('store_products')->where('public_id', $productPublicId)->value('id');
                DB::table('store_artwork_details')->insert([
                    'product_id' => $productId,
                    'owner_user_id' => $ownerUserId,
                    'ownership_type' => $ownershipType,
                    'workflow_state' => 'draft',
                    'draft_key' => (string) ($data['draft_key'] ?? Str::uuid()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $artwork = DB::table('store_artwork_details as artwork')
                ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
                ->where('product.public_id', $productPublicId)
                ->lockForUpdate()
                ->first(['artwork.*', 'product.id as store_product_id', 'product.type_code', 'product.default_locale']);
            if ($artwork === null || $artwork->type_code !== 'artwork') {
                throw new DomainException('The selected Store product is not an artwork.');
            }
            if (! $allowAnyOwner && (int) $artwork->owner_user_id !== $actorUserId) {
                abort(404);
            }
            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $artwork->lock_version) {
                throw new DomainException('This artwork draft changed elsewhere. Refresh before saving again.');
            }
            if (! in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true)) {
                throw new DomainException('This artwork is locked while it is under review or published.');
            }

            $colors = array_values(array_filter(array_map(
                static fn (string $color): string => trim($color),
                preg_split('/[,;\r\n]+/u', (string) ($data['primary_colors'] ?? '')) ?: [],
            )));
            $money = filled($data['requested_price'] ?? null)
                ? Money::fromDecimal((string) $data['requested_price'], (string) ($data['currency'] ?? 'JOD'))
                : null;
            $frameCost = filled($data['frame_cost'] ?? null)
                ? Money::fromDecimal((string) $data['frame_cost'], (string) ($data['currency'] ?? 'JOD'))
                : null;
            $packagingCost = filled($data['packaging_cost'] ?? null)
                ? Money::fromDecimal((string) $data['packaging_cost'], (string) ($data['currency'] ?? 'JOD'))
                : null;

            DB::table('store_products')->where('id', $artwork->store_product_id)->update([
                'artist_name' => trim((string) ($data['artist_name'] ?? $actorName)),
                'updated_by' => $actorUserId,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('store_product_translations')
                ->where('product_id', $artwork->store_product_id)
                ->where('locale', $artwork->default_locale)
                ->update([
                    'name' => trim((string) (($data['title'] ?? null) ?: 'Untitled artwork')),
                    'short_description' => $this->nullable($data['short_description'] ?? null),
                    'description' => $this->nullable($data['detailed_description'] ?? null),
                    'meta_description' => $this->nullable($data['short_description'] ?? null),
                    'updated_at' => now(),
                ]);
            DB::table('store_artwork_details')->where('id', $artwork->id)->update([
                'owner_user_id' => $allowAnyOwner ? $ownerUserId : $artwork->owner_user_id,
                'ownership_type' => $allowAnyOwner ? $ownershipType : 'artist_account',
                'completion_year' => $data['completion_year'] ?? null,
                'work_type' => $this->nullable($data['work_type'] ?? null),
                'collection_name' => $this->nullable($data['collection_name'] ?? null),
                'edition_type' => $data['edition_type'] ?? 'original',
                'edition_number' => $data['edition_number'] ?? null,
                'edition_total' => $data['edition_total'] ?? null,
                'category' => $this->nullable($data['category'] ?? null),
                'artistic_style' => $this->nullable($data['artistic_style'] ?? null),
                'subject' => $this->nullable($data['subject'] ?? null),
                'technique' => $this->nullable($data['technique'] ?? null),
                'material' => $this->nullable($data['material'] ?? null),
                'surface' => $this->nullable($data['surface'] ?? null),
                'primary_colors' => $colors === [] ? null : json_encode($colors, JSON_THROW_ON_ERROR),
                'orientation' => $data['orientation'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'depth' => $data['depth'] ?? null,
                'dimension_unit' => $data['dimension_unit'] ?? 'cm',
                'weight' => $data['weight'] ?? null,
                'weight_unit' => $data['weight_unit'] ?? 'kg',
                'is_framed' => (bool) ($data['is_framed'] ?? false),
                'frame_material' => $this->nullable($data['frame_material'] ?? null),
                'condition' => $data['condition'] ?? null,
                'ready_to_hang' => (bool) ($data['ready_to_hang'] ?? false),
                'artwork_story' => $this->nullable($data['artwork_story'] ?? null),
                'inspiration_source' => $this->nullable($data['inspiration_source'] ?? null),
                'care_instructions' => $this->nullable($data['care_instructions'] ?? null),
                'requested_price_minor' => $money?->amountMinor,
                'frame_cost_minor' => $frameCost?->amountMinor,
                'packaging_cost_minor' => $packagingCost?->amountMinor,
                'currency' => $money?->currency ?? 'JOD',
                'availability' => $data['availability'] ?? null,
                'preparation_days' => $data['preparation_days'] ?? null,
                'shipping_notes' => $this->nullable($data['shipping_notes'] ?? null),
                'ownership_confirmed' => (bool) ($data['ownership_confirmed'] ?? false),
                'sale_rights_confirmed' => (bool) ($data['sale_rights_confirmed'] ?? false),
                'authenticity_notes' => $this->nullable($data['authenticity_notes'] ?? null),
                'workflow_state' => in_array($artwork->workflow_state, ['changes_required', 'rejected'], true) ? 'draft' : $artwork->workflow_state,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

            return $this->find($productPublicId);
        }, 3);
    }

    public function submit(int $ownerUserId, string $productPublicId): object
    {
        return DB::transaction(function () use ($ownerUserId, $productPublicId): object {
            $artwork = $this->findOwned($ownerUserId, $productPublicId);
            if (! in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true)) {
                throw new DomainException('Only an editable artwork can be submitted.');
            }
            $this->assertComplete($artwork);
            $hasPrimary = DB::table('store_artwork_media')
                ->where('artwork_detail_id', $artwork->artwork_detail_id)
                ->where('role', 'front')
                ->where('is_primary', true)
                ->where('state', 'ready')
                ->exists();
            if (! $hasPrimary) {
                throw new DomainException('Add and select a front artwork image before submitting for review.');
            }
            $this->assertAuthenticityReady($artwork);
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => 'submitted',
                'submitted_at' => now(),
                'rejected_at' => null,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

            return $this->find($productPublicId);
        }, 3);
    }

    public function decide(int $reviewerUserId, string $reviewerName, string $productPublicId, string $decision, ?string $notes): object
    {
        if (! in_array($decision, ['approve', 'changes_required', 'reject'], true)) {
            throw new DomainException('Unsupported artwork review decision.');
        }

        return DB::transaction(function () use ($reviewerUserId, $reviewerName, $productPublicId, $decision, $notes): object {
            $artwork = $this->find($productPublicId);
            if (! in_array($artwork->workflow_state, ['submitted', 'under_review'], true)) {
                throw new DomainException('Only a submitted artwork can receive a review decision.');
            }
            $target = match ($decision) {
                'approve' => 'approved',
                'changes_required' => 'changes_required',
                default => 'rejected',
            };
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => $target,
                'approved_at' => $target === 'approved' ? now() : null,
                'approved_by' => $target === 'approved' ? $reviewerUserId : null,
                'rejected_at' => $target === 'rejected' ? now() : null,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('store_artwork_reviews')->insert([
                'public_id' => (string) $this->ids->generate(),
                'artwork_detail_id' => $artwork->artwork_detail_id,
                'reviewer_user_id' => $reviewerUserId,
                'decision' => $decision,
                'notes' => $this->nullable($notes),
                'snapshot' => json_encode((array) $artwork, JSON_THROW_ON_ERROR),
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($target !== 'approved') {
                DB::table('store_artwork_authenticity')
                    ->where('artwork_detail_id', $artwork->artwork_detail_id)
                    ->update([
                        'state' => $target === 'changes_required' ? 'additional_evidence_required' : 'rejected',
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            if ($target === 'approved') {
                $this->acceptAuthenticity($artwork, $reviewerUserId, 'documentation_accepted', 'Authenticity documentation accepted with artwork approval.');
                $this->publishApprovedArtwork($artwork, $reviewerUserId, $reviewerName, 'artwork-approval:'.$productPublicId);
            }

            return $this->find($productPublicId);
        }, 3);
    }

    public function publishDirectlyByAdmin(int $adminUserId, string $adminName, string $productPublicId): object
    {
        return DB::transaction(function () use ($adminUserId, $adminName, $productPublicId): object {
            $artwork = $this->query()->where('product.public_id', $productPublicId)->lockForUpdate()->first()
                ?? throw new DomainException('Artwork product does not exist.');
            if (! in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true)) {
                throw new DomainException('Only an editable artwork can be published directly.');
            }
            $this->assertComplete($artwork);
            if (! DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('role', 'front')->where('is_primary', true)->where('state', 'ready')->exists()) {
                throw new DomainException('A primary front artwork image is required before publication.');
            }
            $this->assertAuthenticityReady($artwork);
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => 'approved',
                'submitted_at' => $artwork->submitted_at ?: now(),
                'approved_at' => now(),
                'approved_by' => $adminUserId,
                'rejected_at' => null,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->recordLifecycle($artwork, $adminUserId, 'admin_direct_publish', 'Directly verified and published by '.$adminName.'.');
            $this->acceptAuthenticity($artwork, $adminUserId, 'admin_verified', 'Administration verified the supplied authenticity evidence before direct publication.');
            $this->publishApprovedArtwork($artwork, $adminUserId, $adminName, 'artwork-admin-publish:'.$productPublicId);

            return $this->find($productPublicId);
        }, 3);
    }

    public function duplicateOwned(int $ownerUserId, string $actorName, string $productPublicId): object
    {
        return DB::transaction(function () use ($ownerUserId, $actorName, $productPublicId): object {
            $source = $this->findOwned($ownerUserId, $productPublicId);
            $copy = $this->save($ownerUserId, $actorName, [
                'title' => trim((string) $source->title).' Copy',
                'artist_name' => $source->artist_name,
                'completion_year' => $source->completion_year,
                'work_type' => $source->work_type,
                'collection_name' => $source->collection_name,
                'edition_type' => $source->edition_type,
                'edition_number' => $source->edition_number,
                'edition_total' => $source->edition_total,
                'category' => $source->category,
                'artistic_style' => $source->artistic_style,
                'subject' => $source->subject,
                'technique' => $source->technique,
                'material' => $source->material,
                'surface' => $source->surface,
                'primary_colors' => implode(', ', json_decode((string) ($source->primary_colors ?? '[]'), true) ?: []),
                'orientation' => $source->orientation,
                'width' => $source->width,
                'height' => $source->height,
                'depth' => $source->depth,
                'dimension_unit' => $source->dimension_unit,
                'weight' => $source->weight,
                'weight_unit' => $source->weight_unit,
                'is_framed' => $source->is_framed,
                'frame_material' => $source->frame_material,
                'condition' => $source->condition,
                'ready_to_hang' => $source->ready_to_hang,
                'short_description' => $source->short_description,
                'detailed_description' => $source->detailed_description,
                'artwork_story' => $source->artwork_story,
                'inspiration_source' => $source->inspiration_source,
                'care_instructions' => $source->care_instructions,
                'requested_price' => $source->requested_price_minor === null
                    ? null
                    : (new Money((int) $source->requested_price_minor, (string) $source->currency))->decimal(),
                'frame_cost' => $source->frame_cost_minor === null
                    ? null
                    : (new Money((int) $source->frame_cost_minor, (string) $source->currency))->decimal(),
                'packaging_cost' => $source->packaging_cost_minor === null
                    ? null
                    : (new Money((int) $source->packaging_cost_minor, (string) $source->currency))->decimal(),
                'currency' => $source->currency,
                'availability' => $source->availability,
                'preparation_days' => $source->preparation_days,
                'shipping_notes' => $source->shipping_notes,
                'ownership_confirmed' => false,
                'sale_rights_confirmed' => false,
                'authenticity_notes' => $source->authenticity_notes,
            ]);

            return $this->find($copy->product_public_id);
        }, 3);
    }

    public function unpublishForEdit(int $ownerUserId, string $actorName, string $productPublicId): object
    {
        return $this->withdrawForEdit($ownerUserId, $actorName, $productPublicId, false);
    }

    public function unpublishForEditByAdmin(int $adminUserId, string $adminName, string $productPublicId): object
    {
        return $this->withdrawForEdit($adminUserId, $adminName, $productPublicId, true);
    }

    private function withdrawForEdit(int $actorUserId, string $actorName, string $productPublicId, bool $allowAnyOwner): object
    {
        return DB::transaction(function () use ($actorUserId, $actorName, $productPublicId, $allowAnyOwner): object {
            $artwork = $this->lockArtwork($actorUserId, $productPublicId, $allowAnyOwner);
            if ($artwork->workflow_state !== 'approved' || $artwork->product_status !== 'published') {
                throw new DomainException('Only a published artwork can be withdrawn for editing.');
            }
            $this->assertNoCommerceActivity($artwork);
            DB::table('store_products')->where('id', $artwork->store_product_id)->update([
                'status' => 'draft',
                'visibility' => 'hidden',
                'updated_by' => $actorUserId,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => 'changes_required',
                'approved_at' => null,
                'approved_by' => null,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->recordLifecycle($artwork, $actorUserId, 'unpublish_for_edit', 'Withdrawn by '.$actorName.' for editing.');

            return $this->find($productPublicId);
        }, 3);
    }

    public function archiveOwned(int $ownerUserId, string $actorName, string $productPublicId): object
    {
        return $this->archiveArtwork($ownerUserId, $actorName, $productPublicId, false);
    }

    public function archiveByAdmin(int $adminUserId, string $adminName, string $productPublicId): object
    {
        return $this->archiveArtwork($adminUserId, $adminName, $productPublicId, true);
    }

    public function restoreAsDraftByAdmin(int $adminUserId, string $adminName, string $productPublicId): object
    {
        return DB::transaction(function () use ($adminUserId, $adminName, $productPublicId): object {
            $artwork = $this->lockArtwork($adminUserId, $productPublicId, true);
            if ($artwork->workflow_state !== 'archived' || $artwork->product_status !== 'archived') {
                throw new DomainException('Only an archived artwork can be restored.');
            }
            $this->assertNoCommerceActivity($artwork);
            DB::table('store_products')->where('id', $artwork->store_product_id)->update([
                'status' => 'draft',
                'visibility' => 'hidden',
                'archived_at' => null,
                'updated_by' => $adminUserId,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => 'draft',
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->recordLifecycle($artwork, $adminUserId, 'restore_as_draft', 'Restored as a private draft by '.$adminName.'.');

            return $this->find($productPublicId);
        }, 3);
    }

    private function archiveArtwork(int $actorUserId, string $actorName, string $productPublicId, bool $allowAnyOwner): object
    {
        return DB::transaction(function () use ($actorUserId, $actorName, $productPublicId, $allowAnyOwner): object {
            $artwork = $this->lockArtwork($actorUserId, $productPublicId, $allowAnyOwner);
            if ($artwork->workflow_state === 'archived') {
                return $this->find($productPublicId);
            }
            $this->assertNoCommerceActivity($artwork);
            $actor = new ActorContext('user', (string) $actorUserId, $actorName);
            $this->lifecycle->archive($productPublicId, (int) $artwork->product_version, $actor);
            DB::table('store_products')->where('id', $artwork->store_product_id)->update([
                'visibility' => 'hidden',
                'updated_at' => now(),
            ]);
            DB::table('store_artwork_details')->where('id', $artwork->artwork_detail_id)->update([
                'workflow_state' => 'archived',
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->recordLifecycle($artwork, $actorUserId, 'archive', 'Archived by '.$actorName.'.');

            return $this->find($productPublicId);
        }, 3);
    }

    private function query()
    {
        return DB::table('store_artwork_details as artwork')
            ->join('store_products as product', 'product.id', '=', 'artwork.product_id')
            ->join('store_product_translations as translation', function ($join): void {
                $join->on('translation.product_id', '=', 'product.id')
                    ->whereColumn('translation.locale', 'product.default_locale');
            })
            ->leftJoin('store_product_media as media', function ($join): void {
                $join->on('media.product_id', '=', 'product.id')
                    ->where('media.is_primary', true)
                    ->where('media.visibility', 'public');
            })
            ->leftJoin('store_media_assets as asset', 'asset.id', '=', 'media.media_asset_id')
            ->where('product.type_code', 'artwork')
            ->select([
                'artwork.id as artwork_detail_id', 'artwork.*',
                'product.id as store_product_id', 'product.public_id as product_public_id', 'product.status as product_status',
                'product.visibility as product_visibility', 'product.version as product_version', 'product.artist_name',
                'translation.name as title', 'translation.slug', 'translation.short_description', 'translation.description as detailed_description',
                'asset.path as primary_image_path',
            ]);
    }

    private function assertComplete(object $artwork): void
    {
        foreach ([
            'title', 'completion_year', 'work_type', 'edition_type', 'category', 'technique', 'material',
            'orientation', 'width', 'height', 'condition', 'short_description', 'detailed_description',
            'requested_price_minor', 'currency', 'availability', 'preparation_days',
        ] as $field) {
            if (! filled($artwork->{$field} ?? null)) {
                throw new DomainException("Artwork field [{$field}] is required before submission.");
            }
        }
        if ($artwork->edition_type === 'limited' && (! $artwork->edition_number || ! $artwork->edition_total)) {
            throw new DomainException('Limited editions require the edition number and total edition count.');
        }
    }

    private function assertAuthenticityReady(object $artwork): void
    {
        $declaration = DB::table('store_artwork_authenticity')
            ->where('artwork_detail_id', $artwork->artwork_detail_id)
            ->first();
        if ($declaration === null
            || ! in_array($declaration->ownership_basis, ['owner', 'authorized'], true)
            || ! $declaration->may_display_and_sell
            || blank($declaration->inspiration_disclosure)
            || blank($declaration->digital_tools_disclosure)
            || blank($declaration->current_physical_location)) {
            throw new DomainException('Complete the ownership and authenticity declaration before continuing.');
        }
        if (! DB::table('store_artwork_evidence')
            ->where('artwork_detail_id', $artwork->artwork_detail_id)
            ->where('state', 'ready')
            ->exists()) {
            throw new DomainException('At least one applicable private authenticity evidence file is required.');
        }
    }

    private function acceptAuthenticity(object $artwork, int $reviewerUserId, string $decision, string $reason): void
    {
        $declaration = DB::table('store_artwork_authenticity')
            ->where('artwork_detail_id', $artwork->artwork_detail_id)
            ->lockForUpdate()
            ->first() ?? throw new DomainException('Artwork authenticity declaration is missing.');
        $references = DB::table('store_artwork_evidence')
            ->where('artwork_detail_id', $artwork->artwork_detail_id)
            ->where('state', 'ready')
            ->pluck('public_id')
            ->all();
        DB::table('store_artwork_authenticity_reviews')->insert([
            'public_id' => (string) $this->ids->generate(),
            'artwork_detail_id' => $artwork->artwork_detail_id,
            'reviewer_user_id' => $reviewerUserId,
            'previous_state' => $declaration->state,
            'decision' => $decision,
            'reason' => $reason,
            'evidence_references' => json_encode($references, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('store_artwork_authenticity')->where('id', $declaration->id)->update([
            'state' => 'documentation_accepted',
            'lock_version' => DB::raw('lock_version + 1'),
            'updated_at' => now(),
        ]);
    }

    private function publishApprovedArtwork(object $artwork, int $actorUserId, string $actorName, string $idempotencyKey): void
    {
        $variant = DB::table('store_product_variants')
            ->where('product_id', $artwork->store_product_id)
            ->where('is_default', true)
            ->first() ?? throw new DomainException('Artwork sellable variant is missing.');
        $actor = new ActorContext('user', (string) $actorUserId, $actorName);
        $this->prices->execute(
            (string) $variant->public_id,
            new Money((int) $artwork->requested_price_minor, (string) $artwork->currency),
            $actor,
        );
        if (! DB::table('store_inventory_items')->where('variant_id', $variant->id)->exists()) {
            $this->inventory->createItem(
                (string) $variant->public_id,
                InventoryTrackingMode::Tracked,
                1,
                false,
                0,
                $actor,
                $idempotencyKey,
            );
        }
        $productVersion = (int) DB::table('store_products')->where('id', $artwork->store_product_id)->value('version');
        $this->publisher->publish($artwork->product_public_id, $productVersion, $actor);
        DB::table('store_products')->where('id', $artwork->store_product_id)->update([
            'visibility' => 'public',
            'updated_at' => now(),
        ]);
    }

    private function lockOwned(int $ownerUserId, string $productPublicId): object
    {
        return $this->lockArtwork($ownerUserId, $productPublicId, false);
    }

    private function lockArtwork(int $actorUserId, string $productPublicId, bool $allowAnyOwner): object
    {
        $query = $this->query()->where('product.public_id', $productPublicId);
        if (! $allowAnyOwner) {
            $query->where('artwork.owner_user_id', $actorUserId);
        }

        return $query->lockForUpdate()->first() ?? abort(404);
    }

    private function assertNoCommerceActivity(object $artwork): void
    {
        $variantIds = DB::table('store_product_variants')->where('product_id', $artwork->store_product_id)->pluck('id');
        if ($variantIds->isNotEmpty() && DB::table('store_inventory_reservations')
            ->join('store_inventory_items', 'store_inventory_items.id', '=', 'store_inventory_reservations.inventory_item_id')
            ->whereIn('store_inventory_items.variant_id', $variantIds)
            ->whereIn('store_inventory_reservations.status', ['active', 'committed'])
            ->exists()) {
            throw new DomainException('This artwork has an active inventory reservation and cannot be changed.');
        }
        if (DB::table('store_order_items as item')
            ->join('store_orders as order', 'order.id', '=', 'item.order_id')
            ->where('item.product_public_id', $artwork->product_public_id)
            ->whereNotIn('order.status', ['cancelled', 'returned'])
            ->exists()) {
            throw new DomainException('This artwork belongs to an active or completed order and cannot be changed.');
        }
    }

    private function recordLifecycle(object $artwork, int $actorUserId, string $decision, string $notes): void
    {
        DB::table('store_artwork_reviews')->insert([
            'public_id' => (string) $this->ids->generate(),
            'artwork_detail_id' => $artwork->artwork_detail_id,
            'reviewer_user_id' => $actorUserId,
            'decision' => $decision,
            'notes' => $notes,
            'snapshot' => json_encode((array) $artwork, JSON_THROW_ON_ERROR),
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'artwork';

        return Str::limit($base, 220, '').'-'.Str::lower(Str::random(8));
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
