<?php

namespace Modules\Store\Domain\Promotions;

use DateTimeImmutable;

final readonly class PromotionConditionContext
{
    public function __construct(
        public int $quantity,
        public int $subtotalMinor,
        public bool $customerAuthenticated,
        public DateTimeImmutable $at,
    ) {}
}
