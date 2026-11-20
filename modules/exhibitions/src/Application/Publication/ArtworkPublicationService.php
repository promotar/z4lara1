<?php

namespace Modules\Exhibition\Application\Publication;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;
use Modules\Exhibition\Application\Artworks\ArtworkPermalink;
use Modules\Exhibition\Application\Commission\CommissionResolver;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationGateway;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationRequest;

final readonly class ArtworkPublicationService
{
    public function __construct(
        private ArtworkPublicationGateway $gateway,
        private AuditRecorder $audit,
        private ArtworkInventoryService $inventory,
        private CommissionResolver $commissions,
        private ExhibitionSettingsService $settings,
        private ArtworkPermalink $permalinks,
    ) {}

    public function publish(int $actor, string $artworkPublicId, string $reason, ?string $idempotencyKey = null): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Publication requires an authorized reason.');
        }
        if (! (bool) $this->settings->value('exhibitions.store.enabled', true)) {
            throw new DomainException('Exhibitions Store integration is disabled.');
        }
        $artwork = $this->permalinks->ensure($this->eligibleArtwork($artworkPublicId));
        $revision = max(1, (int) DB::table('exhibition_artwork_revisions')->where('artwork_id', $artwork->id)->max('revision_number'));
        $media = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('state', 'ready')->orderByDesc('is_primary')->orderBy('sort_order')->get();
        $price = DB::table('exhibition_artwork_pricing')->where('artwork_id', $artwork->id)->where('state', 'approved')->first();
        $key = $idempotencyKey ?: 'publish:'.$artwork->public_id.':'.$revision;
        $publication = $this->publication($artwork);
        if (DB::table('exhibition_artwork_publication_events')->where('idempotency_key', $key)->exists()) {
            return DB::table('exhibition_artwork_publications')->where('id', $publication->id)->first();
        }
        $inventory = $this->inventory->prepareForPublication($actor, $artwork->public_id, 'Prepare exact approved inventory before Store publication: '.$reason);
        $availableQuantity = count(array_filter($inventory, static fn (object $item): bool => $item->state === 'available'));
        $images = $media->map(fn (object $item): string => '/exhibitions/artworks/'.$artwork->public_id.'/media/'.$item->public_id)->all();
        $request = new ArtworkPublicationRequest(
            $artwork->public_id,
            $revision,
            (string) $artwork->title,
            (string) ($artwork->detailed_description ?: $artwork->short_description),
            $images,
            $this->minor((string) $price->approved_amount_snapshot),
            (string) $price->currency,
            $artwork->edition_type === 'original' ? min(1, $availableQuantity) : $availableQuantity,
            $key,
            $this->metadata($artwork, (string) ($images[0] ?? '')),
        );
        $result = $this->gateway->publish($request);
        $state = $result->succeeded ? 'published' : 'sync_failed';
        DB::transaction(function () use ($publication, $artwork, $revision, $request, $result, $state, $key, $actor, $reason): void {
            DB::table('exhibition_artwork_publications')->where('id', $publication->id)->update([
                'state' => $state, 'store_product_public_id' => $result->storeProductPublicId, 'store_variant_public_id' => $result->storeVariantPublicId,
                'store_inventory_public_id' => $result->storeInventoryPublicId, 'last_synced_revision' => $result->succeeded ? $revision : $publication->last_synced_revision,
                'payload_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)), 'attempt_count' => DB::raw('attempt_count + 1'),
                'failure_code' => $result->failureCode, 'failure_message' => $result->message, 'lock_version' => DB::raw('lock_version + 1'),
                'last_attempt_at' => now(), 'last_success_at' => $result->succeeded ? now() : $publication->last_success_at, 'updated_at' => now(),
            ]);
            DB::table('exhibition_artwork_publication_events')->insert(['public_id' => (string) Str::uuid(), 'publication_id' => $publication->id, 'actor_user_id' => $actor, 'action' => 'publish', 'state' => $state, 'idempotency_key' => $key, 'request_payload' => json_encode($request, JSON_THROW_ON_ERROR), 'response_payload' => json_encode($result, JSON_THROW_ON_ERROR), 'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            if ($result->succeeded) {
                DB::table('exhibition_artworks')->where('id', $artwork->id)->update(['state' => 'published', 'published_at' => now(), 'updated_at' => now()]);
            }
        });
        $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.publication_attempted', targetType: 'artwork', targetId: $artworkPublicId, newValues: ['state' => $state, 'store_product' => $result->storeProductPublicId], reason: $reason, actorUserId: $actor));

        return DB::table('exhibition_artwork_publications')->where('id', $publication->id)->first();
    }

    public function reconcile(int $actor, string $artworkPublicId, string $reason): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Reconciliation requires a reason.');
        }
        $art = DB::table('exhibition_artworks')->where('public_id', $artworkPublicId)->first() ?? abort(404);
        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $art->id)->first() ?? throw new DomainException('Artwork has not been published.');
        $result = $this->gateway->reconcile($artworkPublicId);
        $key = 'reconcile:'.$artworkPublicId.':'.Str::uuid();
        $state = $result->succeeded ? (string) $publication->state : 'sync_failed';
        DB::table('exhibition_artwork_publications')->where('id', $publication->id)->update(['state' => $state, 'failure_code' => $result->failureCode, 'failure_message' => $result->message, 'last_attempt_at' => now(), 'last_success_at' => $result->succeeded ? now() : $publication->last_success_at, 'attempt_count' => DB::raw('attempt_count + 1'), 'updated_at' => now()]);
        DB::table('exhibition_artwork_publication_events')->insert(['public_id' => (string) Str::uuid(), 'publication_id' => $publication->id, 'actor_user_id' => $actor, 'action' => 'reconcile', 'state' => $state, 'idempotency_key' => $key, 'response_payload' => json_encode($result, JSON_THROW_ON_ERROR), 'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('exhibition_artwork_publications')->where('id', $publication->id)->first();
    }

    public function withdraw(int $actor, string $artworkPublicId, string $reason): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Withdrawal requires a reason.');
        }$art = DB::table('exhibition_artworks')->where('public_id', $artworkPublicId)->first() ?? abort(404);
        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $art->id)->first() ?? throw new DomainException('Artwork has not been published.');
        $key = 'withdraw:'.$artworkPublicId.':'.$publication->lock_version;
        $result = $this->gateway->withdraw($artworkPublicId, $reason, $key);
        $state = $result->succeeded ? 'withdrawn' : 'sync_failed';
        DB::transaction(function () use ($publication, $art, $result, $state, $key, $actor, $reason): void {
            DB::table('exhibition_artwork_publications')->where('id', $publication->id)->update(['state' => $state, 'failure_code' => $result->failureCode, 'failure_message' => $result->message, 'last_attempt_at' => now(), 'last_success_at' => $result->succeeded ? now() : $publication->last_success_at, 'attempt_count' => DB::raw('attempt_count + 1'), 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]);
            DB::table('exhibition_artwork_publication_events')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'publication_id' => $publication->id, 'actor_user_id' => $actor, 'action' => 'withdraw', 'state' => $state, 'idempotency_key' => $key, 'response_payload' => json_encode($result, JSON_THROW_ON_ERROR), 'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            if ($result->succeeded) {
                DB::table('exhibition_artworks')->where('id', $art->id)->update(['state' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);
            }
        });

        return DB::table('exhibition_artwork_publications')->where('id', $publication->id)->first();
    }

    private function eligibleArtwork(string $publicId): object
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $publicId)->first() ?? abort(404);
        if (! in_array($art->state, ['approved', 'published'], true)) {
            throw new DomainException('Only an approved artwork can publish.');
        }
        $identity = DB::table('exhibition_artist_identities')->where('id', $art->artist_identity_id)->first();
        if (! $identity || $identity->state !== 'active') {
            throw new DomainException('Artist account must be active.');
        }
        if (DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->orderByDesc('round_number')->value('state') !== 'approved') {
            throw new DomainException('Latest artwork review must be approved.');
        }
        if (! DB::table('exhibition_artwork_authenticity')->where('artwork_id', $art->id)->where('state', 'documentation_accepted')->exists()) {
            throw new DomainException('Accepted authenticity documentation is required.');
        }
        if (! DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->where('state', 'approved')->whereNotNull('approved_amount_snapshot')->exists()) {
            throw new DomainException('Approved pricing snapshot is required.');
        }
        if (! DB::table('exhibition_artwork_media')->where('artwork_id', $art->id)->where('state', 'ready')->exists()) {
            throw new DomainException('At least one approved image is required.');
        }

        return $art;
    }

    private function publication(object $art): object
    {
        DB::table('exhibition_artwork_publications')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'artwork_id' => $art->id, 'state' => 'pending', 'attempt_count' => 0, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('exhibition_artwork_publications')->where('artwork_id', $art->id)->first();
    }

    private function minor(string $amount): int
    {
        [$whole,$fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $fraction;
    }

    /** @return array<string, mixed> */
    private function metadata(object $artwork, string $mainImagePath): array
    {
        $artist = DB::table('exhibition_artist_identities as identity')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->leftJoin('exhibition_verification_cases as verification', 'verification.application_id', '=', 'application.id')
            ->leftJoin('exhibition_artist_agreements as agreement', 'agreement.verification_case_id', '=', 'verification.id')
            ->where('identity.id', $artwork->artist_identity_id)
            ->orderByDesc('application.updated_at')
            ->first([
                'identity.public_id as artist_public_id', 'application.artistic_name', 'application.legal_name',
                'agreement.accepted_version',
            ]);
        $commission = $this->commissions->forArtwork($artwork);
        $configuredRule = trim((string) ($this->setting('external_sellable_commission_rule') ?? ''));
        $commissionRule = $configuredRule !== ''
            ? $configuredRule
            : $commission->source;

        return [
            'source_module' => 'exhibitions',
            'artwork_public_id' => (string) $artwork->public_id,
            'artist_public_id' => (string) ($artist?->artist_public_id ?? ''),
            'artist_display_name' => (string) ($artist?->artistic_name ?: $artist?->legal_name ?: 'Artist'),
            'artwork_title' => (string) $artwork->title,
            'artwork_type' => (string) $artwork->work_type,
            'edition_type' => (string) $artwork->edition_type,
            'edition_number' => $artwork->edition_number === null ? null : (int) $artwork->edition_number,
            'dimensions' => [
                'width' => $artwork->width === null ? null : (string) $artwork->width,
                'height' => $artwork->height === null ? null : (string) $artwork->height,
                'depth' => $artwork->depth === null ? null : (string) $artwork->depth,
                'unit' => (string) ($artwork->dimension_unit ?: 'cm'),
            ],
            'frame_status' => (bool) $artwork->is_framed ? 'framed' : 'unframed',
            'preparation_lead_time' => $artwork->preparation_days === null ? null : (int) $artwork->preparation_days,
            'certificate_status' => 'verified',
            'main_image_display_path' => $mainImagePath,
            'commission_rule' => $commissionRule,
            'commission_rate' => $commission->basisPoints,
            'commission_rate_snapshot' => $commission->rate,
            'commission_source' => $commission->source,
            'frame_cost_included' => true,
            'packaging_cost_included' => true,
            'shipping_calculated_at_checkout' => true,
            'tax_calculated_by_store' => true,
        ];
    }

    private function setting(string $key): mixed
    {
        if (! Schema::hasTable('exhibition_settings')) {
            return null;
        }
        $value = DB::table('exhibition_settings')->where('key', $key)->value('value');
        if ($value === null) {
            return null;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
}
