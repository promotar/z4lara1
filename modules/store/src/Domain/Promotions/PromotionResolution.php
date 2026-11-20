<?php

namespace Modules\Store\Domain\Promotions;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\Money;

final readonly class PromotionResolution
{
    /** @param list<DiscountLine> $lines */
    public function __construct(
        public Money $base,
        public array $lines,
        public Money $totalDiscount,
        public Money $effective,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
