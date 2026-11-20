<?php

namespace Modules\Store\Domain\Promotions\Conditions;

use InvalidArgumentException;
use Modules\Store\Domain\Promotions\PromotionConditionContext;
use Modules\Store\Domain\Promotions\PromotionConditionHandler;

final class MinimumSubtotalCondition implements PromotionConditionHandler
{
    public function type(): string
    {
        return 'minimum_subtotal';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function evaluate(array $payload, PromotionConditionContext $context): bool
    {
        $minimum = $payload['amount_minor'] ?? null;
        if (! is_int($minimum) || $minimum < 0) {
            throw new InvalidArgumentException('minimum_subtotal payload is invalid.');
        }

        return $context->subtotalMinor >= $minimum;
    }
}
