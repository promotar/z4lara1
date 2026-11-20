<?php

namespace Modules\Store\Application\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Store\Domain\Pricing\Events\SalePriceScheduled;
use Modules\Store\Domain\Pricing\PriceResolver;
use Modules\Store\Domain\Pricing\PriceType;
use Modules\Store\Domain\Pricing\PricingRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\PriceConflict;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ScheduleSalePrice
{
    public function __construct(
        private PricingRepository $prices,
        private PriceResolver $resolver,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function execute(
        string $variantPublicId,
        Money $money,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        ActorContext $actor,
        int $priority = 0,
        ?string $replacePricePublicId = null,
    ): string {
        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Sale price end must be after its start.');
        }

        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        $this->transactions->run(function () use (
            $variantPublicId,
            $money,
            $startsAt,
            $endsAt,
            $actor,
            $priority,
            $publicId,
            $now,
            $replacePricePublicId,
        ): void {
            $variantId = $this->prices->variantIdByPublicId($variantPublicId, true)
                ?? throw new InvalidArgumentException('Variant does not exist.');

            if ($this->prices->hasOverlappingSale($variantId, $money->currency, $startsAt, $endsAt, $priority, $replacePricePublicId)) {
                throw new PriceConflict('Sale schedule overlaps another sale at the same priority.');
            }

            $regular = $this->resolver->resolve($this->prices->recordsForVariant($variantId, $money->currency), $money->currency, $now);
            if ($money->amountMinor > $regular->regular->amountMinor) {
                throw new PriceConflict('Sale price cannot exceed the current regular price.');
            }

            if ($replacePricePublicId !== null && ! $this->prices->archiveSale($replacePricePublicId, $variantId, $money->currency, $now)) {
                throw new InvalidArgumentException('The sale price being edited is no longer active.');
            }

            $this->prices->insert($publicId, $variantId, PriceType::Sale, $money, $startsAt, $endsAt, $priority, $actor, $now);
            $this->events->afterCommit(new SalePriceScheduled($variantPublicId, $publicId, $startsAt, $endsAt, $now));
        });

        return $publicId;
    }
}
