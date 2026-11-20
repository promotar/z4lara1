<?php

namespace Modules\Exhibition\Events\V1;

final readonly class VirtualPlacementStateChanged
{
    public function __construct(
        public string $placementPublicId,
        public ?string $previousStatus,
        public string $status,
        public ?int $actorUserId,
    ) {}
}
