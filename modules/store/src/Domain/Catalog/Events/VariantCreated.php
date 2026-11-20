<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class VariantCreated
{
    public function __construct(
        public string $productPublicId,
        public string $variantPublicId,
        public DateTimeImmutable $occurredAt,
        public string $actorType,
        public ?string $actorId,
    ) {}
}
