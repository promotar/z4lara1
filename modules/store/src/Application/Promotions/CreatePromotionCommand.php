<?php

namespace Modules\Store\Application\Promotions;

use DateTimeImmutable;
use Modules\Store\Domain\Promotions\PromotionStackingPolicy;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class CreatePromotionCommand
{
    /** @param list<PromotionTargetInput> $targets @param list<PromotionConditionInput> $conditions */
    public function __construct(
        public string $name,
        public string $mode,
        public PromotionStackingPolicy $stackingPolicy,
        public array $targets,
        public array $conditions,
        public PromotionActionInput $action,
        public ActorContext $actor,
        public ?string $code = null,
        public int $priority = 0,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
    ) {}
}
