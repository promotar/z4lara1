<?php

namespace Modules\Store\Contracts\Pricing;

use DateTimeImmutable;

final readonly class PriceView
{
    public function __construct(
        public string $variantPublicId,
        public int $regularAmountMinor,
        public ?int $saleAmountMinor,
        public int $effectiveAmountMinor,
        public string $currency,
        public string $priceRecordPublicId,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
