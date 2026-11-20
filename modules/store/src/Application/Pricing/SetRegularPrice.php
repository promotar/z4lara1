<?php

namespace Modules\Store\Application\Pricing;

use InvalidArgumentException;
use Modules\Store\Domain\Pricing\Events\RegularPriceSet;
use Modules\Store\Domain\Pricing\PriceType;
use Modules\Store\Domain\Pricing\PricingRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class SetRegularPrice
{
    public function __construct(
        private PricingRepository $prices,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function execute(string $variantPublicId, Money $money, ActorContext $actor, int $priority = 0): string
    {
        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        $this->transactions->run(function () use ($variantPublicId, $money, $actor, $priority, $publicId, $now): void {
            $variantId = $this->prices->variantIdByPublicId($variantPublicId, true)
                ?? throw new InvalidArgumentException('Variant does not exist.');
            $this->prices->archiveCurrentRegular($variantId, $money->currency, $now);
            $this->prices->insert($publicId, $variantId, PriceType::Regular, $money, $now, null, $priority, $actor, $now);
            $this->events->afterCommit(new RegularPriceSet(
                $variantPublicId,
                $publicId,
                $money->amountMinor,
                $money->currency,
                $now,
            ));
        });

        return $publicId;
    }
}
