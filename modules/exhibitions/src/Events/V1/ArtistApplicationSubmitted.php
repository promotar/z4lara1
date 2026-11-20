<?php

namespace Modules\Exhibition\Events\V1;

final readonly class ArtistApplicationSubmitted
{
    public function __construct(
        public string $applicationPublicId,
        public int $coreUserId,
    ) {}
}
