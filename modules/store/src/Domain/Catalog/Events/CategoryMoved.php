<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class CategoryMoved
{
    public function __construct(
        public string $categoryPublicId,
        public ?string $parentPublicId,
        public DateTimeImmutable $occurredAt,
        public string $actorType,
        public ?string $actorId,
    ) {}
}
