<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class ProductRestored
{
    public function __construct(public string $productPublicId, public DateTimeImmutable $occurredAt) {}
}
