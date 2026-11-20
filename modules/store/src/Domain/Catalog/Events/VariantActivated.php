<?php

namespace Modules\Store\Domain\Catalog\Events;

use DateTimeImmutable;

final readonly class VariantActivated
{
    public function __construct(public string $variantPublicId, public DateTimeImmutable $occurredAt) {}
}
