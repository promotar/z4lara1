<?php

namespace Modules\Store\Application\Coupons;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class CouponDefinition
{
    /**
     * @param  list<array{type: string, public_id: ?string}>  $targets
     */
    public function __construct(
        public string $name,
        public string $code,
        public string $discountType,
        public ?int $fixedAmountMinor,
        public ?int $percentageBasisPoints,
        public int $minimumSubtotalMinor,
        public ?int $usageLimit,
        public ?int $perCustomerLimit,
        public array $targets,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $active,
        public ActorContext $actor,
    ) {}
}
