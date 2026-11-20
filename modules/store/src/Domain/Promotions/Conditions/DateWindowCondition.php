<?php

namespace Modules\Store\Domain\Promotions\Conditions;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Store\Domain\Promotions\PromotionConditionContext;
use Modules\Store\Domain\Promotions\PromotionConditionHandler;

final class DateWindowCondition implements PromotionConditionHandler
{
    public function type(): string
    {
        return 'date_window';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function evaluate(array $payload, PromotionConditionContext $context): bool
    {
        try {
            $startsAt = isset($payload['starts_at']) ? new DateTimeImmutable((string) $payload['starts_at']) : null;
            $endsAt = isset($payload['ends_at']) ? new DateTimeImmutable((string) $payload['ends_at']) : null;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('date_window payload is invalid.', previous: $exception);
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('date_window end must be after its start.');
        }

        return ($startsAt === null || $startsAt <= $context->at)
            && ($endsAt === null || $context->at < $endsAt);
    }
}
