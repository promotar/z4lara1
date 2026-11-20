<?php

namespace Modules\Exhibition\Application\Applications;

use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Application\Verification\ActivationEligibility;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;
use Modules\Exhibition\Domain\Applications\ApplicationState;

final readonly class ArtistAccessGate
{
    public function __construct(private ActivationEligibility $eligibility) {}

    public function canCreateArtwork(int $coreUserId): bool
    {
        return DB::table('exhibition_artist_applications as applications')
            ->join('exhibition_artist_identities as identities', 'identities.core_user_id', '=', 'applications.core_user_id')
            ->where('applications.core_user_id', $coreUserId)
            ->whereIn('applications.state', [
                ApplicationState::Approved->value,
                ApplicationState::ContractPending->value,
                ApplicationState::Active->value,
            ])
            ->where('identities.state', ArtistAccountState::Active->value)
            ->exists();
    }

    public function canSubmitArtworkForSale(int $coreUserId): bool
    {
        if (! $this->canCreateArtwork($coreUserId)) {
            return false;
        }

        $applicationId = DB::table('exhibition_artist_applications')->where('core_user_id', $coreUserId)->value('id');
        if ($applicationId === null) {
            return false;
        }

        return $this->eligibility->readyToSell((int) $applicationId);
    }
}
