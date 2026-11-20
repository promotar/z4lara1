<?php

namespace Modules\Store\Domain\Pricing\Events;

use DateTimeImmutable;

final readonly class SalePriceScheduled
{
    public function __construct(
        public string $variantPublicId,
        public string $pricePublicId,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public DateTimeImmutable $occurredAt,
    ) {}
}
