<?php

namespace Modules\Store\Domain\Pricing;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\Money;

final readonly class ResolvedPrice
{
    public function __construct(
        public Money $regular,
        public ?Money $sale,
        public Money $effective,
        public string $priceRecordPublicId,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
