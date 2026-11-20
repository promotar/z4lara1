<?php

namespace Modules\Store\Application\Promotions;

final readonly class PromotionConditionInput
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $type, public array $payload, public int $schemaVersion = 1) {}
}
