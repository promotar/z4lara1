<?php

namespace Modules\Store\Contracts\Integrations\V1;

final readonly class ExternalSellableRequest
{
    /**
     * @param list<string> $imageUrls
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $ownerCode,
        public string $externalId,
        public int $sourceRevision,
        public string $locale,
        public string $title,
        public string $slug,
        public ?string $shortDescription,
        public ?string $description,
        public int $priceMinor,
        public string $currency,
        public int $quantity,
        public array $imageUrls,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}
}
