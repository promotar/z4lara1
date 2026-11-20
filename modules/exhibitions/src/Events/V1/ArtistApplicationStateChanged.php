<?php

namespace Modules\Exhibition\Events\V1;

final readonly class ArtistApplicationStateChanged
{
    public function __construct(
        public string $applicationPublicId,
        public int $coreUserId,
        public string $fromState,
        public string $toState,
    ) {}
}
