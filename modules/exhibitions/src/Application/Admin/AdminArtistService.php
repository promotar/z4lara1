<?php

namespace Modules\Exhibition\Application\Admin;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;

final readonly class AdminArtistService
{
    public function __construct(
        private CoreUserGateway $users,
        private AuditRecorder $audit,
    ) {}

    public function candidates(string $search = '', int $perPage = 25): object
    {
        $linked = DB::table('exhibition_artist_identities')->pluck('core_user_id')->map(static fn ($id): int => (int) $id)->all();

        return $this->users->paginateExcept($linked, trim($search), $perPage);
    }

    public function addExistingUser(int $coreUserId, int $actorUserId, ?string $artisticName = null): object
    {
        $user = $this->users->find($coreUserId);
        if ($user === null) {
            throw new DomainException('The selected platform user no longer exists.');
        }

        return DB::transaction(function () use ($user, $actorUserId, $artisticName): object {
            if (DB::table('exhibition_artist_identities')->where('core_user_id', $user->id)->lockForUpdate()->first() !== null) {
                throw new DomainException('This user already has an Exhibitions artist identity.');
            }

            $now = now();
            $identityPublicId = (string) Str::uuid();
            $identityId = DB::table('exhibition_artist_identities')->insertGetId([
                'public_id' => $identityPublicId,
                'core_user_id' => $user->id,
                'state' => ArtistAccountState::Active->value,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $applicationPublicId = (string) Str::uuid();
            $applicationId = DB::table('exhibition_artist_applications')->insertGetId([
                'public_id' => $applicationPublicId,
                'core_user_id' => $user->id,
                'state' => 'active',
                'legal_name' => trim((string) $user->name),
                'artistic_name' => trim((string) ($artisticName ?: $user->name)),
                'contact_email' => mb_strtolower(trim((string) $user->email)),
                'current_reason' => 'Added as an artist by an authorized administrator.',
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('exhibition_application_status_history')->insert([
                'public_id' => (string) Str::uuid(),
                'application_id' => $applicationId,
                'actor_user_id' => $actorUserId,
                'from_state' => null,
                'to_state' => 'active',
                'reason' => 'Existing platform user added as an artist by an authorized administrator.',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('exhibition_artist_account_status_history')->insert([
                'public_id' => (string) Str::uuid(),
                'artist_identity_id' => $identityId,
                'actor_user_id' => $actorUserId,
                'from_state' => 'candidate',
                'to_state' => ArtistAccountState::Active->value,
                'reason' => 'Existing platform user added as an artist by an authorized administrator.',
                'capabilities' => json_encode(ArtistAccountState::Active->capabilities(), JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->users->ensureRole((int) $user->id, 'artist');
            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.artist.added_by_admin',
                targetType: 'artist_identity',
                targetId: $identityPublicId,
                newValues: ['core_user_id' => (int) $user->id, 'state' => 'active'],
                reason: 'Existing platform user added as an artist.',
                actorUserId: $actorUserId,
                context: ['application_public_id' => $applicationPublicId],
            ));

            return DB::table('exhibition_artist_identities')->where('id', $identityId)->first();
        }, 3);
    }
}
