<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class ProductCreated
{
    public function __construct(
        public string $productPublicId,
        public string $typeCode,
        public DateTimeImmutable $occurredAt,
        public string $actorType,
        public ?string $actorId,
    ) {}
}
