<?php

namespace Modules\Exhibition\Contracts\Artists;

interface ArtistIdentityRepository
{
    public function findByCoreUserId(int $coreUserId): ?ArtistIdentityData;

    public function createCandidateForCoreUser(int $coreUserId): ArtistIdentityData;
}
