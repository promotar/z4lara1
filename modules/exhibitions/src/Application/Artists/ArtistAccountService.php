<?php

namespace Modules\Exhibition\Application\Artists;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;

final readonly class ArtistAccountService
{
    public function __construct(
        private AuditRecorder $audit,
        private ArtistArtworkSuspensionService $artworks,
        private CoreUserGateway $users,
    ) {}

    public function transition(int $coreUserId, ArtistAccountState $to, int $reviewerId, string $reason): object
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('An authorized reason is required for every artist account state change.');
        }

        // Validate before withdrawing listings or changing artwork state. The
        // transition is validated again under a row lock in the transaction.
        $this->assertTransitionAllowed($coreUserId, $to);

        if (in_array($to, [ArtistAccountState::Suspended, ArtistAccountState::Terminated], true)) {
            $this->artworks->moveUnsoldWorksToDraft($coreUserId, $reviewerId, $reason);
        }

        return DB::transaction(function () use ($coreUserId, $to, $reviewerId, $reason): object {
            $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->lockForUpdate()->first();
            if ($identity === null) {
                throw new DomainException('Artist identity not found.');
            }
            $fromValue = (string) $identity->state;
            $from = ArtistAccountState::tryFrom($fromValue);
            $activatingApprovedCandidate = $fromValue === 'candidate' && $to === ArtistAccountState::Active
                && DB::table('exhibition_artist_applications')
                    ->where('core_user_id', $coreUserId)
                    ->whereIn('state', ['approved', 'contract_pending', 'active'])
                    ->exists();
            if ($from === null && ! $activatingApprovedCandidate) {
                throw new DomainException('Only an activated artist account can be restricted, suspended, terminated, or reactivated.');
            }
            if ($from === $to) {
                throw new DomainException('The artist account is already in that state.');
            }
            $timestamps = [
                'activated_at' => $to === ArtistAccountState::Active ? now() : $identity->activated_at,
                'restricted_at' => $to === ArtistAccountState::Restricted ? now() : null,
                'suspended_at' => $to === ArtistAccountState::Suspended ? now() : null,
                'terminated_at' => $to === ArtistAccountState::Terminated ? now() : null,
            ];
            DB::table('exhibition_artist_identities')->where('id', $identity->id)->update($timestamps + ['state' => $to->value, 'updated_at' => now()]);
            DB::table('exhibition_artist_account_status_history')->insert([
                'public_id' => (string) Str::uuid(), 'artist_identity_id' => $identity->id, 'actor_user_id' => $reviewerId,
                'from_state' => $fromValue, 'to_state' => $to->value, 'reason' => $reason,
                'capabilities' => json_encode($to->capabilities(), JSON_THROW_ON_ERROR), 'occurred_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.artist_account.state_changed', targetType: 'artist_identity', targetId: (string) $identity->public_id,
                previousValues: ['state' => $fromValue], newValues: ['state' => $to->value, 'capabilities' => $to->capabilities()],
                reason: $reason, actorUserId: $reviewerId,
            ));

            if ($to === ArtistAccountState::Terminated) {
                $this->users->removeRole($coreUserId, 'artist');
            } elseif ($to === ArtistAccountState::Active) {
                $this->users->ensureRole($coreUserId, 'artist');
            }

            return DB::table('exhibition_artist_identities')->where('id', $identity->id)->first();
        });
    }

    /** @return array<string, bool> */
    public function capabilities(int $coreUserId): array
    {
        $state = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->value('state');

        return ArtistAccountState::tryFrom((string) $state)?->capabilities()
            ?? ['dashboard' => false, 'create_artwork' => false, 'existing_artwork' => false, 'fulfillment' => false, 'settlements' => false];
    }

    private function assertTransitionAllowed(int $coreUserId, ArtistAccountState $to): void
    {
        $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->first();
        if ($identity === null) {
            throw new DomainException('Artist identity not found.');
        }

        $fromValue = (string) $identity->state;
        $from = ArtistAccountState::tryFrom($fromValue);
        $activatingApprovedCandidate = $fromValue === 'candidate' && $to === ArtistAccountState::Active
            && DB::table('exhibition_artist_applications')
                ->where('core_user_id', $coreUserId)
                ->whereIn('state', ['approved', 'contract_pending', 'active'])
                ->exists();
        if ($from === null && ! $activatingApprovedCandidate) {
            throw new DomainException('Only an activated artist account can be restricted, suspended, terminated, or reactivated.');
        }
        if ($from === $to) {
            throw new DomainException('The artist account is already in that state.');
        }
    }
}
