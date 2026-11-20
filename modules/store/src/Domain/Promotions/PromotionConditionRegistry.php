<?php

namespace Modules\Store\Domain\Promotions;

interface PromotionConditionRegistry
{
    public function register(PromotionConditionHandler $handler): void;

    public function supports(string $type, int $schemaVersion): bool;

    /** @param array<string, mixed> $payload */
    public function validate(string $type, int $schemaVersion, array $payload): void;

    /** @param array<string, mixed> $payload */
    public function evaluate(string $type, int $schemaVersion, array $payload, PromotionConditionContext $context): bool;
}
