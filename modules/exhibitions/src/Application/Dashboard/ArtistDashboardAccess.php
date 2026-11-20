<?php

namespace Modules\Exhibition\Application\Dashboard;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;

final class ArtistDashboardAccess
{
    public function canAccess(int $coreUserId): bool
    {
        try {
            $this->context($coreUserId);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function context(int $coreUserId): object
    {
        $context = DB::table('exhibition_artist_identities as identities')
            ->join('exhibition_artist_applications as applications', 'applications.core_user_id', '=', 'identities.core_user_id')
            ->where('identities.core_user_id', $coreUserId)
            ->select('identities.id as artist_identity_id', 'identities.public_id as artist_public_id', 'identities.state as account_state', 'applications.*')
            ->first();

        $state = $context ? ArtistAccountState::tryFrom((string) $context->account_state) : null;
        $approvedApplication = $context && in_array($context->state, ['approved', 'contract_pending', 'active'], true);
        $availableAccount = $context && in_array($state, [ArtistAccountState::Active, ArtistAccountState::Restricted], true);
        if (! $approvedApplication || ! $availableAccount) {
            throw new AuthorizationException('The private artist dashboard is available after the artist application is approved.');
        }

        return $context;
    }
}
