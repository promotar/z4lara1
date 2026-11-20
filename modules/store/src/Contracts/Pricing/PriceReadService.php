<?php

namespace Modules\Store\Contracts\Pricing;

use DateTimeImmutable;

interface PriceReadService
{
    public function currentForVariant(string $variantPublicId, string $currency, DateTimeImmutable $at): ?PriceView;
}
