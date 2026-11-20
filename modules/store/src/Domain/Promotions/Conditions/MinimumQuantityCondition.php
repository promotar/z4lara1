<?php

namespace Modules\Store\Domain\Promotions\Conditions;

use InvalidArgumentException;
use Modules\Store\Domain\Promotions\PromotionConditionContext;
use Modules\Store\Domain\Promotions\PromotionConditionHandler;

final class MinimumQuantityCondition implements PromotionConditionHandler
{
    public function type(): string
    {
        return 'minimum_quantity';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function evaluate(array $payload, PromotionConditionContext $context): bool
    {
        $minimum = $payload['minimum'] ?? null;
        if (! is_int($minimum) || $minimum < 1) {
            throw new InvalidArgumentException('minimum_quantity payload is invalid.');
        }

        return $context->quantity >= $minimum;
    }
}
