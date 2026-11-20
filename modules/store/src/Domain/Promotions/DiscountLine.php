<?php

namespace Modules\Store\Domain\Promotions;

use Modules\Store\Domain\Shared\Money;

final readonly class DiscountLine
{
    public function __construct(
        public string $promotionPublicId,
        public Money $discount,
        public int $priority,
        public PromotionStackingPolicy $stackingPolicy,
    ) {}
}
