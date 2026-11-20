<?php

namespace Modules\Store\Domain\Promotions;

use DateTimeImmutable;
use LogicException;
use Modules\Store\Domain\Promotions\Conditions\CustomerAuthenticatedCondition;
use Modules\Store\Domain\Promotions\Conditions\DateWindowCondition;
use Modules\Store\Domain\Promotions\Conditions\MinimumQuantityCondition;
use Modules\Store\Domain\Promotions\Conditions\MinimumSubtotalCondition;
use Modules\Store\Domain\Shared\Exceptions\PromotionConflict;

final class DefaultPromotionConditionRegistry implements PromotionConditionRegistry
{
    /** @var array<string, PromotionConditionHandler> */
    private array $handlers = [];

    public function __construct()
    {
        $this->register(new MinimumQuantityCondition);
        $this->register(new MinimumSubtotalCondition);
        $this->register(new CustomerAuthenticatedCondition);
        $this->register(new DateWindowCondition);
    }

    public function register(PromotionConditionHandler $handler): void
    {
        if (isset($this->handlers[$handler->type()])) {
            throw new LogicException("Promotion condition [{$handler->type()}] is already registered.");
        }
        $this->handlers[$handler->type()] = $handler;
    }

    public function evaluate(string $type, int $schemaVersion, array $payload, PromotionConditionContext $context): bool
    {
        $handler = $this->handlers[$type] ?? throw new PromotionConflict("Unknown promotion condition [{$type}].");
        if ($handler->schemaVersion() !== $schemaVersion) {
            throw new PromotionConflict("Unsupported schema version for promotion condition [{$type}].");
        }

        return $handler->evaluate($payload, $context);
    }

    public function supports(string $type, int $schemaVersion): bool
    {
        return isset($this->handlers[$type]) && $this->handlers[$type]->schemaVersion() === $schemaVersion;
    }

    public function validate(string $type, int $schemaVersion, array $payload): void
    {
        $this->evaluate(
            $type,
            $schemaVersion,
            $payload,
            new PromotionConditionContext(1, 0, false, new DateTimeImmutable('@0')),
        );
    }
}
