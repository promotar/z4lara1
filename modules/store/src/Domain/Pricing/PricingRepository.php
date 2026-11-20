<?php

namespace Modules\Store\Domain\Pricing;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Money;

interface PricingRepository
{
    public function variantIdByPublicId(string $variantPublicId, bool $forUpdate = false): ?int;

    public function archiveCurrentRegular(int $variantId, string $currency, DateTimeImmutable $at): void;

    public function insert(
        string $publicId,
        int $variantId,
        PriceType $type,
        Money $money,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        int $priority,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): void;

    public function hasOverlappingSale(
        int $variantId,
        string $currency,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        int $priority,
        ?string $exceptPublicId = null,
    ): bool;

    public function archiveSale(string $publicId, int $variantId, string $currency, DateTimeImmutable $at): bool;

    /** @return list<PriceRecord> */
    public function recordsForVariant(int $variantId, string $currency): array;
}
