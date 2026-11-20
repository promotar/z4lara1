<?php

namespace Modules\Exhibition\Contracts\Artists;

final readonly class ArtistIdentityData
{
    public function __construct(
        public string $publicId,
        public int $coreUserId,
        public string $state,
    ) {}
}
