<?php

namespace Modules\Store\Domain\Pricing;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\Money;

final readonly class PriceRecord
{
    public function __construct(
        public string $publicId,
        public PriceType $type,
        public Money $money,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public int $priority,
        public string $status = 'active',
    ) {}

    public function appliesAt(DateTimeImmutable $at): bool
    {
        return $this->status === 'active'
            && ($this->startsAt === null || $this->startsAt <= $at)
            && ($this->endsAt === null || $at < $this->endsAt);
    }
}
