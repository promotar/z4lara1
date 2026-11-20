<?php

namespace Modules\Store\Domain\Promotions\Conditions;

use InvalidArgumentException;
use Modules\Store\Domain\Promotions\PromotionConditionContext;
use Modules\Store\Domain\Promotions\PromotionConditionHandler;

final class CustomerAuthenticatedCondition implements PromotionConditionHandler
{
    public function type(): string
    {
        return 'customer_authenticated';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function evaluate(array $payload, PromotionConditionContext $context): bool
    {
        $required = $payload['required'] ?? true;
        if (! is_bool($required)) {
            throw new InvalidArgumentException('customer_authenticated payload is invalid.');
        }

        return $context->customerAuthenticated === $required;
    }
}
