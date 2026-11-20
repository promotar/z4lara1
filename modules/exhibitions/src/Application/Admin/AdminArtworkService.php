<?php

namespace Modules\Exhibition\Application\Admin;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkPermalink;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;

final readonly class AdminArtworkService
{
    private const EDITABLE_STATES = ['draft', 'submitted', 'changes_required', 'rejected'];

    public function __construct(
        private ArtworkService $artworks,
        private ArtworkPermalink $permalinks,
        private AdminArtistService $artists,
        private AuditRecorder $audit,
        private CoreUserGateway $users,
    ) {}

    public function activeOwners(): object
    {
        $records = DB::table('exhibition_artist_identities as identity')
            ->leftJoin('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('identity.state', 'active')
            ->select(
                'identity.core_user_id',
                'application.artistic_name',
                'application.legal_name',
                'application.contact_email',
            )
            ->get();
        $users = $this->users->findMany($records->pluck('core_user_id')->map(static fn ($id): int => (int) $id)->all());

        return $records->map(function (object $record) use ($users): object {
            $user = $users[(int) $record->core_user_id] ?? null;
            $record->email = (string) ($record->contact_email ?: ($user?->email ?? ''));
            $record->display_name = (string) ($record->artistic_name ?: ($record->legal_name ?: ($user?->name ?: $record->email)));

            return $record;
        })->sortBy(fn (object $record): string => mb_strtolower($record->display_name))->values();
    }

    /** @param array<string, mixed> $payload */
    public function save(int $actorUserId, array $payload, ?string $publicId = null, ?string $newPublicId = null): object
    {
        $ownerUserId = filled($payload['artist_user_id'] ?? null)
            ? (int) $payload['artist_user_id']
            : $actorUserId;
        $identity = $this->activeIdentity($ownerUserId, $actorUserId);

        return DB::transaction(function () use ($actorUserId, $payload, $publicId, $newPublicId, $identity): object {
            $existing = $publicId === null
                ? null
                : DB::table('exhibition_artworks')->where('public_id', $publicId)->lockForUpdate()->first();
            if ($publicId !== null && $existing === null) {
                abort(404);
            }
            if ($existing !== null && ! in_array((string) $existing->state, self::EDITABLE_STATES, true)) {
                throw new DomainException('Published, approved, reserved, sold, or archived artwork must use its operational workflow and cannot be edited here.');
            }

            $values = $this->artworks->values($payload);
            $now = now();
            if ($existing === null) {
                $id = DB::table('exhibition_artworks')->insertGetId($values + [
                    'public_id' => $newPublicId ?: (string) Str::uuid(),
                    'artist_identity_id' => $identity->id,
                    'draft_key' => (string) Str::uuid(),
                    'state' => 'draft',
                    'lock_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $saved = DB::table('exhibition_artworks')->where('id', $id)->first();
                $this->recordState($saved, $actorUserId, null, 'draft', 'Artwork draft created by an administrator.');
                $action = 'exhibitions.artwork.created_by_admin';
            } else {
                DB::table('exhibition_artworks')->where('id', $existing->id)->update($values + [
                    'artist_identity_id' => $identity->id,
                    'state' => 'draft',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => $now,
                ]);
                $saved = DB::table('exhibition_artworks')->where('id', $existing->id)->first();
                $action = 'exhibitions.artwork.updated_by_admin';
            }

            $saved = $this->permalinks->ensure($saved);
            $this->recordRevision($saved, $actorUserId, $existing === null ? 'admin_create' : 'admin_update');
            $this->audit->record(new AuditEntryData(
                action: $action,
                targetType: 'artwork',
                targetId: (string) $saved->public_id,
                previousValues: $existing === null ? null : ['artist_identity_id' => (int) $existing->artist_identity_id, 'state' => (string) $existing->state],
                newValues: ['artist_identity_id' => (int) $identity->id, 'state' => 'draft', 'title' => (string) $saved->title],
                reason: $existing === null ? 'Administrator created an artwork draft.' : 'Administrator updated an artwork draft.',
                actorUserId: $actorUserId,
            ));

            return $saved;
        }, 3);
    }

    private function activeIdentity(int $ownerUserId, int $actorUserId): object
    {
        $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $ownerUserId)->first();
        if ($identity === null && $ownerUserId === $actorUserId) {
            $identity = $this->artists->addExistingUser($ownerUserId, $actorUserId);
        }
        if ($identity === null || $identity->state !== 'active') {
            throw new DomainException('Select an active artist, or leave the artist empty to keep the work under the administrator who creates it.');
        }

        return $identity;
    }

    private function recordRevision(object $artwork, int $actorUserId, string $reason): void
    {
        $number = ((int) DB::table('exhibition_artwork_revisions')->where('artwork_id', $artwork->id)->max('revision_number')) + 1;
        DB::table('exhibition_artwork_revisions')->insert([
            'public_id' => (string) Str::uuid(),
            'artwork_id' => $artwork->id,
            'revision_number' => $number,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'snapshot' => json_encode((array) $artwork, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function recordState(object $artwork, int $actorUserId, ?string $from, string $to, string $reason): void
    {
        DB::table('exhibition_artwork_state_history')->insert([
            'public_id' => (string) Str::uuid(),
            'artwork_id' => $artwork->id,
            'actor_user_id' => $actorUserId,
            'from_state' => $from,
            'to_state' => $to,
            'reason' => $reason,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
