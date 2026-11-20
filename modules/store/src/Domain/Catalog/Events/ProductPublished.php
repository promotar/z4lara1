<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class ProductPublished
{
    public function __construct(
        public string $productPublicId,
        public DateTimeImmutable $occurredAt,
        public string $actorType,
        public ?string $actorId,
    ) {}
}
