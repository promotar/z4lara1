<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class ProductArchived
{
    public function __construct(public string $productPublicId, public DateTimeImmutable $occurredAt) {}
}
