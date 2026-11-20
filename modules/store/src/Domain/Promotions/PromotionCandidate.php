<?php

namespace Modules\Store\Domain\Promotions;

use InvalidArgumentException;
use Modules\Store\Domain\Shared\Money;

final readonly class PromotionCandidate
{
    public function __construct(
        public string $publicId,
        public int $priority,
        public PromotionStackingPolicy $stackingPolicy,
        public PromotionActionType $actionType,
        public ?Money $fixedAmount = null,
        public ?int $percentageBasisPoints = null,
        public ?Money $maximumDiscount = null,
        public bool $eligible = true,
    ) {
        if ($actionType === PromotionActionType::FixedAmountDiscount && $fixedAmount === null) {
            throw new InvalidArgumentException('Fixed promotion requires a Money amount.');
        }

        if ($actionType === PromotionActionType::PercentageDiscount
            && ($percentageBasisPoints === null || $percentageBasisPoints < 0 || $percentageBasisPoints > 10_000)) {
            throw new InvalidArgumentException('Percentage promotion requires basis points from 0 to 10000.');
        }
    }

    public function discount(Money $base): Money
    {
        $discount = match ($this->actionType) {
            PromotionActionType::FixedAmountDiscount => $this->fixedAmount,
            PromotionActionType::PercentageDiscount => $base->percentage((int) $this->percentageBasisPoints),
        };

        if ($discount->currency !== $base->currency) {
            throw new InvalidArgumentException('Promotion discount currency differs from base price.');
        }

        $minor = min($discount->amountMinor, $base->amountMinor);
        if ($this->maximumDiscount !== null) {
            if ($this->maximumDiscount->currency !== $base->currency) {
                throw new InvalidArgumentException('Promotion maximum currency differs from base price.');
            }
            $minor = min($minor, $this->maximumDiscount->amountMinor);
        }

        return new Money($minor, $base->currency);
    }
}
