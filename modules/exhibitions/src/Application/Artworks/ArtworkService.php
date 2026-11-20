<?php

namespace Modules\Exhibition\Application\Artworks;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Applications\ArtistAccessGate;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Domain\Artworks\ArtworkState;
use Throwable;

final readonly class ArtworkService
{
    public function __construct(private ArtistDashboardAccess $access, private ArtistAccessGate $artistAccess, private AuditRecorder $audit, private ExhibitionNotificationService $notifications) {}

    public function owned(int $coreUserId, string $publicId): object
    {
        $context = $this->access->context($coreUserId);

        return DB::table('exhibition_artworks')
            ->where('artist_identity_id', $context->artist_identity_id)
            ->where('public_id', $publicId)->first() ?? abort(404);
    }

    public function duplicate(int $coreUserId, string $publicId): object
    {
        abort_unless($this->artistAccess->canCreateArtwork($coreUserId), 403);
        $source = $this->owned($coreUserId, $publicId);
        $copiedPaths = [];

        try {
            return DB::transaction(function () use ($coreUserId, $source, &$copiedPaths): object {
                $source = DB::table('exhibition_artworks')->where('id', $source->id)->lockForUpdate()->first() ?? abort(404);
                $newPublicId = (string) Str::uuid();
                $now = now();
                $values = $this->duplicateValues($source);
                $values['title'] = $this->copyTitle((string) ($source->title ?? 'Untitled artwork'));

                $artworkId = DB::table('exhibition_artworks')->insertGetId($values + [
                    'public_id' => $newPublicId,
                    'artist_identity_id' => $source->artist_identity_id,
                    'draft_key' => (string) Str::uuid(),
                    'state' => ArtworkState::Draft->value,
                    'lock_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $media = DB::table('exhibition_artwork_media')
                    ->where('artwork_id', $source->id)
                    ->where('state', 'ready')
                    ->orderBy('sort_order')
                    ->get();

                foreach ($media as $item) {
                    $this->duplicateMedia($item, $artworkId, $newPublicId, $coreUserId, $copiedPaths);
                }

                $copy = DB::table('exhibition_artworks')->where('id', $artworkId)->first();
                $this->stateHistory($artworkId, $coreUserId, null, ArtworkState::Draft->value, 'Artwork duplicated as an independent private draft.');
                $this->revision($copy, $coreUserId, 'artist_duplicate');
                $this->audit->record(new AuditEntryData(
                    'exhibitions.artwork.duplicated',
                    'artwork',
                    $copy->public_id,
                    ['source_public_id' => $source->public_id],
                    ['state' => ArtworkState::Draft->value, 'media_count' => $media->count()],
                    'Artist duplicated an artwork as an independent private draft.',
                    ['phase' => 5]
                ));

                return $copy;
            });
        } catch (Throwable $exception) {
            foreach (array_reverse($copiedPaths) as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The artwork could not be duplicated. Please retry.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $payload */
    public function save(int $coreUserId, array $payload, string $draftKey, ?string $publicId = null, ?int $expectedLockVersion = null): object
    {
        $context = $this->access->context($coreUserId);

        return DB::transaction(function () use ($context, $coreUserId, $payload, $draftKey, $publicId, $expectedLockVersion): object {
            $query = DB::table('exhibition_artworks')->where('artist_identity_id', $context->artist_identity_id);
            $artwork = $publicId
                ? (clone $query)->where('public_id', $publicId)->lockForUpdate()->first()
                : (clone $query)->where('draft_key', $draftKey)->lockForUpdate()->first();
            abort_if($publicId !== null && $artwork === null, 404);
            abort_if($artwork === null && ! $this->artistAccess->canCreateArtwork($coreUserId), 403);

            if ($artwork && ! ArtworkState::from($artwork->state)->artistEditable()) {
                throw new DomainException('This artwork is locked in its current operational state.');
            }
            if ($artwork && $expectedLockVersion !== null && (int) $artwork->lock_version !== $expectedLockVersion) {
                throw new DomainException('This draft changed in another tab or session. Refresh before saving again.');
            }

            $values = $this->values($payload);
            $now = now();
            if ($artwork === null) {
                $id = DB::table('exhibition_artworks')->insertGetId($values + [
                    'public_id' => (string) Str::uuid(), 'artist_identity_id' => $context->artist_identity_id,
                    'draft_key' => $draftKey, 'state' => ArtworkState::Draft->value, 'lock_version' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $artwork = DB::table('exhibition_artworks')->where('id', $id)->first();
                $this->stateHistory($id, $coreUserId, null, ArtworkState::Draft->value, 'Artwork draft created.');
            } else {
                DB::table('exhibition_artworks')->where('id', $artwork->id)->update($values + [
                    'lock_version' => ((int) $artwork->lock_version) + 1, 'updated_at' => $now,
                ]);
                $artwork = DB::table('exhibition_artworks')->where('id', $artwork->id)->first();
            }

            $this->revision($artwork, $coreUserId, 'artist_save');
            $this->audit->record(new AuditEntryData('exhibitions.artwork.saved', 'artwork', $artwork->public_id, null, ['state' => $artwork->state, 'lock_version' => $artwork->lock_version], 'Artist saved an artwork draft.', ['phase' => 5]));

            return $artwork;
        });
    }

    public function submit(int $coreUserId, string $publicId): object
    {
        return DB::transaction(function () use ($coreUserId, $publicId): object {
            $owned = $this->owned($coreUserId, $publicId);
            $artwork = DB::table('exhibition_artworks')->where('id', $owned->id)->lockForUpdate()->first();
            if ($artwork->state === ArtworkState::Submitted->value) {
                return $artwork;
            }
            $from = ArtworkState::from($artwork->state);
            if (! in_array($from, [ArtworkState::Draft, ArtworkState::ChangesRequired, ArtworkState::Rejected], true)) {
                throw new DomainException('Only an editable artwork can be submitted for review.');
            }
            DB::table('exhibition_artworks')->where('id', $artwork->id)->update([
                'state' => ArtworkState::Submitted->value, 'submitted_at' => now(),
                'lock_version' => ((int) $artwork->lock_version) + 1, 'updated_at' => now(),
            ]);
            $this->stateHistory($artwork->id, $coreUserId, $from->value, ArtworkState::Submitted->value, 'Artist submitted artwork for manual review.');
            $updated = DB::table('exhibition_artworks')->where('id', $artwork->id)->first();
            $this->revision($updated, $coreUserId, 'artist_submit');
            $this->audit->record(new AuditEntryData('exhibitions.artwork.submitted', 'artwork', $updated->public_id, ['state' => $from->value], ['state' => ArtworkState::Submitted->value], 'Artist submitted artwork for manual review.', ['phase' => 5]));
            $this->notifications->dispatchCommitted('artwork_submitted', [$coreUserId], ['message' => 'Your artwork was submitted for manual review.', 'title' => $updated->title, 'link' => '/exhibitions/dashboard/artworks/'.$updated->public_id], 'artwork-submitted:'.$updated->public_id.':'.$updated->lock_version);

            return $updated;
        });
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function values(array $payload): array
    {
        $columns = ['title', 'completion_year', 'work_type', 'collection_name', 'edition_type', 'edition_number', 'edition_total', 'category', 'artistic_style', 'subject', 'technique', 'material', 'surface', 'orientation', 'width', 'height', 'depth', 'dimension_unit', 'weight', 'weight_unit', 'frame_material', 'condition', 'short_description', 'detailed_description', 'artwork_story', 'inspiration_source', 'care_instructions', 'image_alt_text', 'seo_title', 'meta_description', 'requested_price', 'currency', 'frame_cost', 'packaging_cost', 'availability', 'sold_buyer_name', 'preparation_days', 'shipping_notes'];
        $values = array_intersect_key($payload, array_flip($columns));
        $values['primary_colors'] = json_encode($this->listValue($payload['primary_colors'] ?? ''), JSON_THROW_ON_ERROR);
        $values['keywords'] = json_encode($this->listValue($payload['keywords'] ?? ''), JSON_THROW_ON_ERROR);
        $values['is_framed'] = (bool) ($payload['is_framed'] ?? false);
        $values['ready_to_hang'] = (bool) ($payload['ready_to_hang'] ?? false);
        if (! $values['is_framed']) {
            $values['frame_material'] = null;
            $values['frame_cost'] = null;
        }
        if (($values['edition_type'] ?? 'original') === 'original') {
            $values['edition_number'] = null;
            $values['edition_total'] = null;
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function duplicateValues(object $source): array
    {
        $columns = [
            'completion_year', 'work_type', 'collection_name', 'edition_type', 'edition_number', 'edition_total',
            'category', 'artistic_style', 'subject', 'technique', 'material', 'surface', 'primary_colors',
            'orientation', 'width', 'height', 'depth', 'dimension_unit', 'weight', 'weight_unit', 'is_framed',
            'frame_material', 'condition', 'ready_to_hang', 'short_description', 'detailed_description',
            'artwork_story', 'inspiration_source', 'care_instructions', 'keywords', 'image_alt_text', 'seo_title',
            'meta_description', 'requested_price', 'currency', 'frame_cost', 'packaging_cost', 'availability', 'sold_buyer_name',
            'preparation_days', 'shipping_notes',
        ];

        return array_intersect_key((array) $source, array_flip($columns));
    }

    /** @param list<array{0:string,1:string}> $copiedPaths */
    private function duplicateMedia(object $source, int $artworkId, string $artworkPublicId, int $coreUserId, array &$copiedPaths): void
    {
        $mediaPublicId = (string) Str::uuid();
        $diskName = (string) ($source->disk ?? 'local');
        $paths = [];
        foreach ([
            'original_path' => 'originals',
            'display_path' => 'display',
            'thumbnail_path' => 'thumbnails',
            've03_room_path' => 've03/room',
            've03_near_path' => 've03/near',
            've03_popup_path' => 've03/popup',
        ] as $column => $directory) {
            if (! property_exists($source, $column) || blank($source->{$column})) {
                continue;
            }
            $extension = pathinfo((string) $source->{$column}, PATHINFO_EXTENSION);
            $destination = 'exhibitions/artworks/'.$artworkPublicId.'/'.$directory.'/'.$mediaPublicId.($extension !== '' ? '.'.$extension : '');
            $disk = Storage::disk($diskName);
            if (! $disk->exists((string) $source->{$column}) || ! $disk->copy((string) $source->{$column}, $destination)) {
                throw new DomainException('One or more artwork images could not be copied. The original artwork was not changed.');
            }
            $paths[$column] = $destination;
            $copiedPaths[] = [$diskName, $destination];
        }

        $values = [
            'public_id' => $mediaPublicId,
            'artwork_id' => $artworkId,
            'replaces_media_id' => null,
            'role' => $source->role,
            'state' => 'ready',
            'is_primary' => (bool) $source->is_primary,
            'sort_order' => (int) $source->sort_order,
            'disk' => $diskName,
            'original_path' => $paths['original_path'] ?? null,
            'display_path' => $paths['display_path'] ?? null,
            'thumbnail_path' => $paths['thumbnail_path'] ?? null,
            'original_name' => $source->original_name,
            'mime_type' => $source->mime_type,
            'size_bytes' => $source->size_bytes,
            'width' => $source->width,
            'height' => $source->height,
            'checksum_sha256' => $source->checksum_sha256,
            'failure_reason' => null,
            'uploaded_by' => $coreUserId,
            'ready_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach (['ve03_room_path', 've03_near_path', 've03_popup_path', 've03_derivative_profile', 've03_derivative_source_sha256', 've03_derivatives_ready_at'] as $column) {
            if (property_exists($source, $column)) {
                $values[$column] = $paths[$column] ?? $source->{$column};
            }
        }

        DB::table('exhibition_artwork_media')->insert($values);
        $copy = DB::table('exhibition_artwork_media')->where('public_id', $mediaPublicId)->first();
        DB::table('exhibition_artwork_media_history')->insert([
            'public_id' => (string) Str::uuid(),
            'artwork_id' => $artworkId,
            'media_public_id' => $mediaPublicId,
            'actor_user_id' => $coreUserId,
            'action' => 'duplicated',
            'snapshot' => json_encode((array) $copy, JSON_THROW_ON_ERROR),
            'reason' => 'Independent image copy created with duplicated artwork.',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copyTitle(string $title): string
    {
        return Str::limit($title, 173, '').' (Copy)';
    }

    /** @return list<string> */
    private function listValue(string|array $value): array
    {
        $items = is_array($value) ? $value : explode(',', $value);

        return array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string) $item), $items))));
    }

    private function revision(object $artwork, int $actorUserId, string $reason): void
    {
        $number = ((int) DB::table('exhibition_artwork_revisions')->where('artwork_id', $artwork->id)->max('revision_number')) + 1;
        DB::table('exhibition_artwork_revisions')->insert([
            'public_id' => (string) Str::uuid(), 'artwork_id' => $artwork->id, 'revision_number' => $number,
            'actor_user_id' => $actorUserId, 'reason' => $reason,
            'snapshot' => json_encode((array) $artwork, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function stateHistory(int $artworkId, int $actorUserId, ?string $from, string $to, string $reason): void
    {
        DB::table('exhibition_artwork_state_history')->insert([
            'public_id' => (string) Str::uuid(), 'artwork_id' => $artworkId, 'actor_user_id' => $actorUserId,
            'from_state' => $from, 'to_state' => $to, 'reason' => $reason, 'occurred_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
