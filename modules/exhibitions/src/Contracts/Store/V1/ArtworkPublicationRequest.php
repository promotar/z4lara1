<?php

namespace Modules\Exhibition\Contracts\Store\V1;

final readonly class ArtworkPublicationRequest
{
    /**
     * @param list<string> $approvedImageUrls
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $artworkPublicId,
        public int $approvedRevision,
        public string $title,
        public string $description,
        public array $approvedImageUrls,
        public int $priceMinor,
        public string $currency,
        public int $availableQuantity,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}
}
