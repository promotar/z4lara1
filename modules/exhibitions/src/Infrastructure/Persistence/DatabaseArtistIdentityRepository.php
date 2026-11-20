<?php

namespace Modules\Exhibition\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Artists\ArtistIdentityData;
use Modules\Exhibition\Contracts\Artists\ArtistIdentityRepository;

final class DatabaseArtistIdentityRepository implements ArtistIdentityRepository
{
    public function findByCoreUserId(int $coreUserId): ?ArtistIdentityData
    {
        $row = DB::table('exhibition_artist_identities')
            ->where('core_user_id', $coreUserId)
            ->first(['public_id', 'core_user_id', 'state']);

        return $row === null ? null : $this->map($row);
    }

    public function createCandidateForCoreUser(int $coreUserId): ArtistIdentityData
    {
        $existing = $this->findByCoreUserId($coreUserId);

        if ($existing !== null) {
            return $existing;
        }

        $publicId = (string) Str::uuid();

        DB::table('exhibition_artist_identities')->insertOrIgnore([
            'public_id' => $publicId,
            'core_user_id' => $coreUserId,
            'state' => 'candidate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findByCoreUserId($coreUserId)
            ?? throw new \RuntimeException('Unable to create the Exhibitions artist identity.');
    }

    private function map(object $row): ArtistIdentityData
    {
        return new ArtistIdentityData(
            publicId: (string) $row->public_id,
            coreUserId: (int) $row->core_user_id,
            state: (string) $row->state,
        );
    }
}
