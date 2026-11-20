<?php

namespace Modules\Exhibition\Application\Artists;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final readonly class ArtistArtworkSuspensionService
{
    public function __construct(
        private ArtworkPublicationService $publication,
        private AuditRecorder $audit,
    ) {}

    public function moveUnsoldWorksToDraft(int $coreUserId, int $actorUserId, string $reason): int
    {
        $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->first();
        if ($identity === null) {
            throw new DomainException('Artist identity not found.');
        }
        if (! Schema::hasTable('exhibition_artworks')) {
            return 0;
        }

        $hasPublicationTable = Schema::hasTable('exhibition_artwork_publications');

        $artworks = DB::table('exhibition_artworks')
            ->where('artist_identity_id', $identity->id)
            ->whereNotIn('state', ['reserved', 'sold'])
            ->get(['id', 'public_id', 'state']);

        foreach ($artworks as $artwork) {
            if (! $hasPublicationTable) {
                continue;
            }
            $publication = DB::table('exhibition_artwork_publications')
                ->where('artwork_id', $artwork->id)
                ->first();
            if ($publication === null || $publication->state === 'withdrawn') {
                continue;
            }
            if (filled($publication->store_product_public_id)) {
                $result = $this->publication->withdraw(
                    $actorUserId,
                    (string) $artwork->public_id,
                    'Artist account disabled: '.$reason,
                );
                if ((string) $result->state !== 'withdrawn') {
                    throw new DomainException('A published Store listing could not be withdrawn. The artist account was not changed.');
                }
            }
        }

        return DB::transaction(function () use ($identity, $actorUserId, $reason, $hasPublicationTable): int {
            $artworks = DB::table('exhibition_artworks')
                ->where('artist_identity_id', $identity->id)
                ->whereNotIn('state', ['reserved', 'sold'])
                ->lockForUpdate()
                ->get(['id', 'public_id', 'state']);
            $changed = 0;

            foreach ($artworks as $artwork) {
                if ($artwork->state === 'draft') {
                    continue;
                }
                DB::table('exhibition_artworks')->where('id', $artwork->id)->update([
                    'state' => 'draft',
                    'submitted_at' => null,
                    'approved_at' => null,
                    'approved_by' => null,
                    'approval_review_round_id' => null,
                    'published_at' => null,
                    'archived_at' => null,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_artwork_state_history')->insert([
                    'public_id' => (string) Str::uuid(),
                    'artwork_id' => $artwork->id,
                    'actor_user_id' => $actorUserId,
                    'from_state' => $artwork->state,
                    'to_state' => 'draft',
                    'reason' => 'Artist account disabled: '.$reason,
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($hasPublicationTable) {
                    DB::table('exhibition_artwork_publications')
                        ->where('artwork_id', $artwork->id)
                        ->whereNull('store_product_public_id')
                        ->update(['state' => 'withdrawn', 'updated_at' => now()]);
                }
                $changed++;
            }

            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.artist.artworks_moved_to_draft',
                targetType: 'artist_identity',
                targetId: (string) $identity->public_id,
                newValues: ['drafted_artworks' => $changed],
                reason: $reason,
                actorUserId: $actorUserId,
            ));

            return $changed;
        }, 3);
    }
}
