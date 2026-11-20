<?php

namespace Modules\Exhibition\Events\V1;

final readonly class ArtworkPublicationRequested
{
    public function __construct(
        public string $artworkPublicId,
        public string $idempotencyKey,
        public string $occurredAt,
    ) {}
}
