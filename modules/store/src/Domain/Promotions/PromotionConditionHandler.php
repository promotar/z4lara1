<?php

namespace Modules\Store\Domain\Promotions;

interface PromotionConditionHandler
{
    public function type(): string;

    public function schemaVersion(): int;

    /** @param array<string, mixed> $payload */
    public function evaluate(array $payload, PromotionConditionContext $context): bool;
}
