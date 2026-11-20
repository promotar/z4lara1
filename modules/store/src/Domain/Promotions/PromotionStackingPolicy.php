<?php

namespace Modules\Store\Domain\Promotions;

enum PromotionStackingPolicy: string
{
    case Exclusive = 'exclusive';
    case Stackable = 'stackable';
    case BestPrice = 'best_price';
}
