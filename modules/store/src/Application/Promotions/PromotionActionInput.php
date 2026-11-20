<?php

namespace Modules\Store\Application\Promotions;

use Modules\Store\Domain\Promotions\PromotionActionType;

final readonly class PromotionActionInput
{
    public function __construct(
        public PromotionActionType $type,
        public ?int $amountMinor = null,
        public ?int $percentageBasisPoints = null,
        public ?string $currency = null,
        public ?int $maximumDiscountMinor = null,
    ) {}
}
