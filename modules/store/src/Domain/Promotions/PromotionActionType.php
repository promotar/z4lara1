<?php

namespace Modules\Store\Domain\Promotions;

enum PromotionActionType: string
{
    case PercentageDiscount = 'percentage_discount';
    case FixedAmountDiscount = 'fixed_amount_discount';
}
