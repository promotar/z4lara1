<?php

namespace Modules\Store\Domain\Pricing\Events;

use DateTimeImmutable;

final readonly class RegularPriceSet
{
    public function __construct(
        public string $variantPublicId,
        public string $pricePublicId,
        public int $amountMinor,
        public string $currency,
        public DateTimeImmutable $occurredAt,
    ) {}
}
