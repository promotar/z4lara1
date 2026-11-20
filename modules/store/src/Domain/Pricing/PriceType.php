<?php

namespace Modules\Store\Domain\Pricing;

enum PriceType: string
{
    case Regular = 'regular';
    case Sale = 'sale';
}
